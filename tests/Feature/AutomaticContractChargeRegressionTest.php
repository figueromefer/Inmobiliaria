<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Models\User;
use App\Services\AutomaticContractChargeService;
use App\Services\ReporteFinancieroService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutomaticContractChargeRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_and_legacy_contracts_generate_the_expected_monthly_commission(): void
    {
        [$cliente, $property] = $this->clientAndProperty('Laravel');
        $this->contract($cliente, $property, 10);
        $rent = $this->rent($cliente, $property, 10460);
        $service = app(AutomaticContractChargeService::class);

        $this->assertNull($service->reconcileMonthlyCommission($rent));
        $this->assertDatabaseHas('movimientos', ['source_movimiento_id' => $rent->id, 'automation_type' => 'monthly_commission', 'auto_generated' => 1, 'importe' => 1046]);

        [$legacyClient, $legacyProperty] = $this->clientAndProperty('Legacy');
        $this->contract($legacyClient, $legacyProperty, 10, null, '2026-12-31', 'justicia_alternativa');
        $legacyRent = $this->rent($legacyClient, $legacyProperty, 10460);
        $this->assertNull($service->reconcileMonthlyCommission($legacyRent));
        $this->assertDatabaseHas('movimientos', ['source_movimiento_id' => $legacyRent->id, 'importe' => 1046]);
    }

    public function test_resolving_a_google_forms_pending_contract_preserves_its_monthly_commission(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        [$cliente, $property] = $this->clientAndProperty('Google Forms');
        $pending = ContratoPendiente::create([
            'origen' => 'privado', 'external_id' => 'google-commission-regression', 'estado' => 'pendiente_match',
            'raw_payload' => [],
            'mapped_payload' => [
                'nombre_solicitante' => $cliente->nombre,
                'domicilio_inmueble_arrendamiento' => 'Domicilio Forms',
                'fecha_inicio_contrato' => '2026-01-01', 'fecha_terminacion_contrato' => '2026-12-31',
                'comision_mensual' => 10,
            ],
        ]);

        $this->actingAs($actor)->post(route('contratos.pendientes.resolver', $pending), [
            'cliente_action' => 'existing', 'fk_cliente' => $cliente->pk_cliente,
            'propiedad_action' => 'existing', 'fk_propiedad' => $property->pk_propiedad,
            'inquilino_action' => 'new',
        ])->assertRedirect(route('contratos.index'));

        $contract = $pending->fresh()->contrato;
        $this->assertSame(10.0, (float) $contract->comision_mensual);
        $rent = $this->rent($cliente, $property, 10460);
        app(AutomaticContractChargeService::class)->reconcileMonthlyCommission($rent);
        $this->assertDatabaseHas('movimientos', ['source_movimiento_id' => $rent->id, 'contrato_id' => $contract->id, 'importe' => 1046]);
    }

    public function test_direct_google_forms_normalizes_a_percent_commission_before_persisting_it(): void
    {
        config(['services.forms_contratos.mode' => 'direct']);
        [$client, $property] = $this->clientAndProperty('Google Direct');

        $this->postJson('/api/forms/contratos', [
            'fk_cliente' => $client->pk_cliente, 'fk_propiedad' => $property->pk_propiedad,
            'fecha_inicio_contrato' => '2026-01-01', 'fecha_terminacion_contrato' => '2026-12-31',
            'comision_mensual' => '10%',
        ])->assertCreated();

        $this->assertSame(10.0, (float) Contrato::query()->sole()->comision_mensual);
    }

    public function test_missing_link_commission_and_ambiguous_contracts_warn_without_creating_a_charge(): void
    {
        $service = app(AutomaticContractChargeService::class);
        [$cliente, $property] = $this->clientAndProperty('Warnings');
        $rent = $this->rent($cliente, $property, 1000);
        $this->assertStringContainsString('no se encontró un contrato vigente', $service->reconcileMonthlyCommission($rent));

        $this->contract($cliente, $property, null);
        $this->assertStringContainsString('no tiene porcentaje', $service->reconcileMonthlyCommission($rent));
        $this->assertDatabaseMissing('movimientos', ['source_movimiento_id' => $rent->id]);

        $this->contract($cliente, $property, 10);
        $this->assertStringContainsString('más de un contrato vigente', $service->reconcileMonthlyCommission($rent));
        $this->assertDatabaseMissing('movimientos', ['source_movimiento_id' => $rent->id]);

        // The current schema prevents this, but imported databases from before
        // FK hardening can contain it; keep the warning path covered.
        Schema::table('contratos', function (Blueprint $table): void {
            $table->unsignedBigInteger('fk_propiedad')->nullable()->change();
        });
        $unlinked = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => null, 'fecha' => now(), 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'comision_mensual' => 10]);
        $other = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Sin FK contractual']);
        $otherRent = $this->rent($cliente, $other, 1000);
        $this->assertStringContainsString('sin propiedad vinculada', $service->reconcileMonthlyCommission($otherRent));
        $this->assertDatabaseMissing('movimientos', ['source_movimiento_id' => $otherRent->id]);
        $this->assertNotNull($unlinked);
    }

    public function test_reconciliation_after_contract_correction_is_idempotent_updates_amount_and_is_visible_in_reports(): void
    {
        [$cliente, $property] = $this->clientAndProperty('Recovery');
        $contract = $this->contract($cliente, $property, null);
        $rent = $this->rent($cliente, $property, 10000);
        $service = app(AutomaticContractChargeService::class);
        $service->reconcileMonthlyCommission($rent);

        $contract->update(['comision_mensual' => 10]);
        $this->assertNull($service->reconcileMonthlyCommission($rent));
        $this->assertNull($service->reconcileMonthlyCommission($rent));
        $this->assertSame(1, Movimiento::query()->where('source_movimiento_id', $rent->id)->count());

        $rent->update(['importe' => 10460]);
        $service->reconcileMonthlyCommission($rent->fresh());
        $this->assertDatabaseHas('movimientos', ['source_movimiento_id' => $rent->id, 'importe' => 1046]);

        $other = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Propiedad corregida']);
        $otherContract = $this->contract($cliente, $other, 5);
        $rent->update(['propiedad_id' => $other->pk_propiedad]);
        $this->assertNull($service->reconcileMonthlyCommission($rent->fresh()));
        $this->assertDatabaseHas('movimientos', ['source_movimiento_id' => $rent->id, 'contrato_id' => $otherContract->id, 'importe' => 523]);
        $report = app(ReporteFinancieroService::class)->generarPorCliente($cliente->pk_cliente, '2026-10-01', '2026-10-31');
        $this->assertSame(523.0, $report['periodo']['igualas']);
    }

    public function test_movement_store_shows_a_non_blocking_warning_when_no_contract_can_be_charged(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $property] = $this->clientAndProperty('Visible warning');

        $this->actingAs($admin)->post(route('movimientos.store'), [
            'asignado_a_tipo' => 'propiedad', 'propiedad_id' => $property->pk_propiedad,
            'concepto' => 'renta', 'fecha' => '2026-10-19', 'importe' => '10460.00',
            'forma_pago' => 'efectivo', 'estado_pago' => 'liquidado', 'fecha_liquidacion' => '2026-10-19', 'afecta_saldo_cliente' => '1',
        ])->assertRedirect(route('movimientos.index'))
            ->assertSessionHas('warning', fn (string $warning) => str_contains($warning, 'no se encontró un contrato vigente'));
    }

    public function test_commissions_match_the_contract_valid_on_the_exact_rent_date_and_preview_uses_the_same_rule(): void
    {
        [$client, $property] = $this->clientAndProperty('Exact date');
        $first = $this->contract($client, $property, 10, '2025-10-01', '2026-10-05');
        $second = $this->contract($client, $property, 5, '2026-10-06', '2027-10-05');
        $earlyRent = $this->rent($client, $property, 10460, '2026-10-03');
        $lateRent = $this->rent($client, $property, 10460, '2026-10-19');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->get(route('contratos.igualas.preview', $second))
            ->assertOk()
            ->assertSee('value="'.$lateRent->id.'"', false)
            ->assertDontSee('value="'.$earlyRent->id.'"', false);

        $service = app(AutomaticContractChargeService::class);
        $this->assertNull($service->reconcileMonthlyCommission($lateRent));
        $this->assertDatabaseHas('movimientos', [
            'source_movimiento_id' => $lateRent->id,
            'contrato_id' => $second->id,
            'importe' => 523,
        ]);

        $this->assertNull($service->reconcileMonthlyCommission($earlyRent));
        $this->assertDatabaseHas('movimientos', [
            'source_movimiento_id' => $earlyRent->id,
            'contrato_id' => $first->id,
            'importe' => 1046,
        ]);
    }

    private function clientAndProperty(string $suffix): array
    {
        $client = Cliente::create(['nombre' => "Cliente {$suffix}", 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $property = Propiedad::create(['fk_cliente' => $client->pk_cliente, 'alias' => "Propiedad {$suffix}"]);

        return [$client, $property];
    }

    private function contract(Cliente $client, Propiedad $property, ?float $commission, ?string $start = '2026-01-01', ?string $end = '2026-12-31', string $origin = 'privado'): Contrato
    {
        return Contrato::create(['fk_cliente' => $client->pk_cliente, 'fk_propiedad' => $property->pk_propiedad, 'fecha' => '2026-01-01', 'fecha_inicio' => $start, 'fecha_fin' => $end, 'comision_mensual' => $commission, 'origen' => $origin]);
    }

    private function rent(Cliente $client, Propiedad $property, float $amount, string $date = '2026-10-19'): Movimiento
    {
        return Movimiento::create(['cliente_id' => $client->pk_cliente, 'propiedad_id' => $property->pk_propiedad, 'asignado_a_tipo' => 'propiedad', 'concepto' => 'renta', 'fecha' => $date, 'importe' => $amount, 'forma_pago' => 'efectivo', 'approval_status' => Movimiento::STATUS_APPROVED, 'estado_pago' => Movimiento::PAYMENT_LIQUIDATED, 'fecha_liquidacion' => $date, 'afecta_saldo_cliente' => true]);
    }
}
