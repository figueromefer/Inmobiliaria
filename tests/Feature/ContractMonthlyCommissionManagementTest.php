<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Models\User;
use App\Services\ReporteFinancieroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractMonthlyCommissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_marks_only_contracts_without_a_usable_monthly_commission(): void
    {
        $actor = $this->agent();
        [$client, $property] = $this->fixture();
        $missing = $this->contract($client, $property, null);
        $validTen = $this->contract($client, Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => 'Diez']), 10);
        $validFraction = $this->contract($client, Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => 'Fracción']), .10);

        $response = $this->actingAs($actor)->get(route('contratos.index'));

        $response->assertOk()
            ->assertSee('Falta comisión mensual')
            ->assertSee($validTen->id)
            ->assertSee($validFraction->id);
        $this->assertFalse($missing->hasValidMonthlyCommission());
        $this->assertFalse(Contrato::monthlyCommissionFractionFor('-') !== null);
        $this->assertFalse(Contrato::monthlyCommissionFractionFor(0) !== null);
        $this->assertTrue($validTen->hasValidMonthlyCommission());
        $this->assertTrue($validFraction->hasValidMonthlyCommission());
    }

    public function test_manage_records_can_edit_valid_monthly_commission_and_viewer_cannot(): void
    {
        [$client, $property] = $this->fixture();
        $contract = $this->contract($client, $property, null);

        $this->actingAs($this->agent())->put(route('contratos.comision-mensual.update', $contract), ['comision_mensual' => '0.10'])
            ->assertRedirect(route('contratos.show', $contract));
        $this->assertSame(.10, (float) $contract->fresh()->comision_mensual);

        $this->actingAs($this->agent())->put(route('contratos.comision-mensual.update', $contract), [
            'comision_mensual' => '1000', 'comision_mensual_unidad' => 'fixed',
        ])->assertRedirect(route('contratos.show', $contract));
        $this->assertSame(1000.0, (float) $contract->fresh()->comision_mensual);
        $this->assertSame('fixed', $contract->fresh()->comision_mensual_unidad);
        $this->actingAs($this->agent())->get(route('contratos.show', $contract))
            ->assertSee('$1,000.00 fijo')
            ->assertSee('Guardar comisión')
            ->assertSee('bg-gray-800 hover:bg-gray-700', false)
            ->assertDontSee('bg-slate-700', false);

        $this->actingAs($this->agent())->put(route('contratos.comision-mensual.update', $contract), ['comision_mensual' => '0'])
            ->assertSessionHasErrors('comision_mensual');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->put(route('contratos.comision-mensual.update', $contract), ['comision_mensual' => '10'])
            ->assertForbidden();
    }

    public function test_commission_units_have_explicit_display_validation_and_calculation_rules(): void
    {
        [$client, $property] = $this->fixture();
        $percent = $this->contract($client, $property, 10, 'percent');
        $fraction = $this->contract($client, Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => 'Fracción explícita']), .10, 'fraction');
        $fixed = $this->contract($client, Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => 'Fijo']), 1000, 'fixed');
        $legacyPercent = $this->contract($client, Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => 'Legado porcentaje']), 10);
        $legacyFraction = $this->contract($client, Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => 'Legado fracción']), .10);

        $this->assertSame('10.00%', $percent->monthlyCommissionDisplay());
        $this->assertSame('10.00%', $fraction->monthlyCommissionDisplay());
        $this->assertSame('$1,000.00 fijo', $fixed->monthlyCommissionDisplay());
        $this->assertSame('10.00%', $legacyPercent->monthlyCommissionDisplay());
        $this->assertSame('10.00%', $legacyFraction->monthlyCommissionDisplay());
        $this->assertSame(1046.0, $percent->calculateMonthlyCommission(10460));
        $this->assertSame(1046.0, $fraction->calculateMonthlyCommission(10460));
        $this->assertSame(1000.0, $fixed->calculateMonthlyCommission(10460));
        $this->assertFalse(Contrato::isValidMonthlyCommission(2, 'fraction'));
        $this->assertFalse(Contrato::isValidMonthlyCommission(1000, 'unknown'));
        $this->assertFalse(Contrato::isValidMonthlyCommission(0, 'fixed'));
    }

    public function test_explicit_preview_and_generation_are_safe_and_idempotent(): void
    {
        $actor = $this->agent();
        [$client, $property] = $this->fixture();
        $contract = $this->contract($client, $property, 10);
        $candidate = $this->rent($client, $property, 10460, '2026-10-19');
        $manualConflict = $this->rent($client, $property, 5000, '2026-10-20');
        Movimiento::create([
            'cliente_id' => $client->pk_cliente, 'propiedad_id' => $property->pk_propiedad,
            'asignado_a_tipo' => 'propiedad', 'concepto' => 'iguala', 'fecha' => '2026-10-01',
            'importe' => 100, 'forma_pago' => 'efectivo', 'approval_status' => Movimiento::STATUS_APPROVED,
            'estado_pago' => Movimiento::PAYMENT_LIQUIDATED, 'auto_generated' => false,
        ]);
        $cancelled = $this->rent($client, $property, 2000, '2026-10-21', Movimiento::PAYMENT_CANCELED);
        $outside = $this->rent($client, $property, 3000, '2027-01-01');

        $preview = $this->actingAs($actor)->get(route('contratos.igualas.preview', $contract));
        $preview->assertOk()
            ->assertSee('1,046.00')
            ->assertSee('Existen igualas manuales en este periodo')
            ->assertSee('value="'.$candidate->id.'"', false)
            ->assertSee('value="'.$manualConflict->id.'"', false)
            ->assertDontSee('value="'.$cancelled->id.'"', false)
            ->assertDontSee('value="'.$outside->id.'"', false);
        $this->assertStringNotContainsString('value="'.$manualConflict->id.'" checked', $preview->getContent());

        $this->actingAs($actor)->post(route('contratos.igualas.recalculate', $contract), ['rentas' => [$candidate->id]])
            ->assertRedirect(route('contratos.show', $contract));
        $this->assertDatabaseHas('movimientos', [
            'source_movimiento_id' => $candidate->id, 'contrato_id' => $contract->id,
            'automation_type' => 'monthly_commission', 'auto_generated' => 1, 'importe' => 1046,
        ]);
        $this->assertDatabaseHas('movimientos', ['id' => Movimiento::where('auto_generated', false)->where('concepto', 'iguala')->sole()->id, 'importe' => 100]);

        $this->actingAs($actor)->post(route('contratos.igualas.recalculate', $contract), ['rentas' => [$candidate->id]]);
        $this->assertSame(1, Movimiento::where('source_movimiento_id', $candidate->id)->count());
        $report = app(ReporteFinancieroService::class)->generarPorCliente($client->pk_cliente, '2026-10-01', '2026-10-31');
        $this->assertSame(1146.0, $report['periodo']['igualas']);
    }

    public function test_fixed_monthly_commission_preview_and_recalculation_use_the_fixed_amount(): void
    {
        $actor = $this->agent();
        [$client, $property] = $this->fixture();
        $contract = $this->contract($client, $property, 1000, 'fixed');
        $rent = $this->rent($client, $property, 10460, '2026-10-19');

        $this->actingAs($actor)->get(route('contratos.igualas.preview', $contract))
            ->assertOk()
            ->assertSee('Comisión: $1,000.00 fijo')
            ->assertSee('$1,000.00');
        $this->actingAs($actor)->post(route('contratos.igualas.recalculate', $contract), ['rentas' => [$rent->id]])
            ->assertRedirect(route('contratos.show', $contract));
        $this->assertDatabaseHas('movimientos', ['source_movimiento_id' => $rent->id, 'contrato_id' => $contract->id, 'importe' => 1000]);
        $this->actingAs($actor)->post(route('contratos.igualas.recalculate', $contract), ['rentas' => [$rent->id]]);
        $this->assertSame(1, Movimiento::where('source_movimiento_id', $rent->id)->count());
    }

    public function test_recalculation_requires_valid_commission_and_manage_records_permission(): void
    {
        [$client, $property] = $this->fixture();
        $missing = $this->contract($client, $property, null);
        $valid = $this->contract($client, Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => 'Otra']), 10);

        $this->actingAs($this->agent())->get(route('contratos.igualas.preview', $missing))->assertStatus(422);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->get(route('contratos.igualas.preview', $valid))->assertForbidden();
    }

    private function fixture(): array
    {
        $client = Cliente::create(['nombre' => 'Cliente comisión', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $property = Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => 'Propiedad comisión']);

        return [$client, $property];
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => User::ROLE_AGENT]);
    }

    private function contract(Cliente $client, Propiedad $property, ?float $commission, ?string $unit = null): Contrato
    {
        return Contrato::create([
            'fk_cliente' => $client->pk_cliente, 'fk_propiedad' => $property->pk_propiedad,
            'fecha' => '2026-01-01', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31',
            'comision_mensual' => $commission,
            'comision_mensual_unidad' => $unit,
        ]);
    }

    private function rent(Cliente $client, Propiedad $property, float $amount, string $date, string $payment = Movimiento::PAYMENT_LIQUIDATED): Movimiento
    {
        return Movimiento::create([
            'cliente_id' => $client->pk_cliente, 'propiedad_id' => $property->pk_propiedad,
            'asignado_a_tipo' => 'propiedad', 'concepto' => 'renta', 'fecha' => $date, 'importe' => $amount,
            'forma_pago' => 'efectivo', 'approval_status' => Movimiento::STATUS_APPROVED,
            'estado_pago' => $payment, 'fecha_liquidacion' => $date, 'afecta_saldo_cliente' => true,
        ]);
    }
}
