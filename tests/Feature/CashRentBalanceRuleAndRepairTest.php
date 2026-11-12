<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\CashRentBalanceRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRentBalanceRuleAndRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_rent_store_and_update_enforce_payment_balance_rule(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post(route('movimientos.store'), $this->payload(['forma_pago' => 'efectivo', 'afecta_saldo_cliente' => '0']))->assertRedirect();
        $cash = Movimiento::where('forma_pago', 'efectivo')->sole();
        $this->assertTrue($cash->afecta_saldo_cliente);

        $this->actingAs($admin)->put(route('movimientos.update', $cash), $this->payload(['forma_pago' => 'transferencia', 'afecta_saldo_cliente' => '1']))->assertRedirect();
        $this->assertFalse($cash->fresh()->afecta_saldo_cliente);
        $this->actingAs($admin)->put(route('movimientos.update', $cash), $this->payload(['forma_pago' => 'efectivo', 'afecta_saldo_cliente' => '0']))->assertRedirect();
        $this->assertTrue($cash->fresh()->afecta_saldo_cliente);
    }

    public function test_repair_is_admin_only_scoped_audited_and_idempotent(): void
    {
        $candidate = $this->movement(['folio' => 'MOV-000568', 'afecta_saldo_cliente' => false, 'importe' => 17000]);
        $transfer = $this->movement(['forma_pago' => 'transferencia', 'afecta_saldo_cliente' => false]);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $this->actingAs($viewer)->get(route('admin.mantenimiento.reparar-rentas-efectivo.preview'))->assertForbidden();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('admin.mantenimiento.reparar-rentas-efectivo.preview'))->assertOk()->assertSee('MOV-000568')->assertSee('$17,000.00');
        $this->actingAs($admin)->post(route('admin.mantenimiento.reparar-rentas-efectivo.apply'), ['confirmacion' => '1'])->assertRedirect();
        $this->assertTrue($candidate->fresh()->afecta_saldo_cliente);
        $this->assertFalse($transfer->fresh()->afecta_saldo_cliente);
        $this->assertSame(1, ActivityLog::where('action', 'movement_cash_rent_balance_repair')->count());
        $this->actingAs($admin)->post(route('admin.mantenimiento.reparar-rentas-efectivo.apply'), ['confirmacion' => '1'])->assertSessionHas('ok', 'Corrección aplicada: 0 movimiento(s) actualizado(s).');
    }

    public function test_approvals_reaffirm_the_rule_for_pending_rents(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cash = $this->movement(['approval_status' => Movimiento::STATUS_PENDING, 'afecta_saldo_cliente' => false]);
        $transfer = $this->movement(['approval_status' => Movimiento::STATUS_PENDING, 'forma_pago' => 'transferencia', 'afecta_saldo_cliente' => true]);

        $this->actingAs($admin)->patch(route('movimientos.approve', $cash))->assertRedirect();
        $this->actingAs($admin)->patch(route('movimientos.approve-bulk'), ['movimientos' => [$transfer->id]])->assertRedirect();

        $this->assertTrue($cash->fresh()->afecta_saldo_cliente);
        $this->assertFalse($transfer->fresh()->afecta_saldo_cliente);
    }

    public function test_other_concepts_keep_their_manual_balance_value(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $other = $this->movement(['concepto' => 'pago_cliente', 'afecta_saldo_cliente' => false]);
        $this->actingAs($admin)->put(route('movimientos.update', $other), $this->payload(['concepto' => 'pago_cliente', 'afecta_saldo_cliente' => '0']))->assertRedirect();
        $this->assertFalse($other->fresh()->afecta_saldo_cliente);
    }

    private function payload(array $overrides = []): array
    {
        $client = Cliente::query()->first() ?: Cliente::create(['nombre' => 'ADRIANA SAINZ ALBAÑEZ', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        return array_replace(['asignado_a_tipo' => 'cliente', 'cliente_id' => $client->pk_cliente, 'concepto' => 'renta', 'fecha' => '2026-11-09', 'importe' => '17000.00', 'forma_pago' => 'efectivo', 'estado_pago' => 'liquidado', 'fecha_liquidacion' => '2026-11-09', 'afecta_saldo_cliente' => '1'], $overrides);
    }

    private function movement(array $overrides = []): Movimiento
    {
        $payload = $this->payload();
        return Movimiento::create(array_replace($payload, ['approval_status' => Movimiento::STATUS_APPROVED, 'afecta_saldo_cliente' => true], $overrides));
    }
}
