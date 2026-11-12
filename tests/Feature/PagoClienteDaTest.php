<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\ReporteFinancieroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PagoClienteDaTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_forces_true_for_cash_and_transfer(): void
    {
        $client = $this->client(); $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach (['efectivo', 'transferencia'] as $payment) $this->actingAs($admin)->post(route('movimientos.store'), $this->payload($client, $payment))->assertRedirect();
        $this->assertSame(2, Movimiento::where('concepto', 'pago_cliente_da')->where('afecta_saldo_cliente', true)->count());
        $this->actingAs($admin)->get(route('movimientos.create'))->assertOk()->assertSee('Pago cliente a DA');
    }

    public function test_payment_to_da_compensates_commission_and_has_its_own_report_section(): void
    {
        $client = $this->client();
        $this->movement($client, 'renta', 20000, 'transferencia', Movimiento::PAYMENT_LIQUIDATED, false);
        $this->movement($client, 'iguala', 2000, 'efectivo');
        $payment = $this->movement($client, 'pago_cliente_da', 2000, 'transferencia');
        $report = $this->report($client);
        $this->assertSame(20000.0, $report['periodo']['rentas']);
        $this->assertSame(2000.0, $report['periodo']['pagos_cliente_da']);
        $this->assertSame(0.0, $report['saldo_final']);
        $this->assertSame(0.0, $report['saldo_disponible_para_pago']);
        $this->actingAs(User::factory()->create())->get(route('reportes.mensual', ['cliente_id' => $client->pk_cliente, 'mes' => '2026-11']))->assertOk()->assertSee('Pagos cliente a DA')->assertSee('PAGOS CLIENTE A DA')->assertSee('$2,000.00');
        $this->actingAs(User::factory()->create())->get(route('reportes.mensual.pdf', ['cliente_id' => $client->pk_cliente, 'mes' => '2026-11']))->assertOk();
    }

    public function test_partial_pending_and_existing_client_payment_keep_their_signs(): void
    {
        $client = $this->client(); $this->movement($client, 'iguala', 2000, 'efectivo'); $this->movement($client, 'pago_cliente_da', 1000, 'efectivo', Movimiento::PAYMENT_PENDING);
        $report = $this->report($client);
        $this->assertSame(-1000.0, $report['periodo']['saldo_periodo_contable']);
        $this->assertSame(-2000.0, $report['saldo_disponible_para_pago']);
        Movimiento::where('concepto', 'pago_cliente_da')->update(['estado_pago' => Movimiento::PAYMENT_LIQUIDATED, 'fecha_liquidacion' => '2026-11-10']);
        $this->assertSame(-1000.0, $this->report($client)['saldo_disponible_para_pago']);
        $this->movement($client, 'pago_cliente', 500, 'efectivo');
        $this->assertSame(-1500.0, $this->report($client)['saldo_final']);
    }

    public function test_payment_is_not_duplicated_in_pagos_extras(): void
    {
        $client = $this->client(); $payment = $this->movement($client, 'pago_cliente_da', 2000, 'efectivo'); $data = null;
        View::composer('reportes.mensual', function ($view) use (&$data) { $data = $view->getData(); });
        $this->actingAs(User::factory()->create())->get(route('reportes.mensual', ['cliente_id' => $client->pk_cliente, 'mes' => '2026-11']))->assertOk();
        $this->assertTrue($data['pagosClienteDa']->contains('id', $payment->id));
        $this->assertFalse($data['pagosExtras']->contains('id', $payment->id));
    }

    public function test_client_profile_filter_exposes_the_new_concept(): void
    {
        $client = $this->client();
        $this->actingAs(User::factory()->create())->get(route('clientes.show', $client))->assertOk()->assertSee('Pago cliente a DA');
    }

    private function client(): Cliente { return Cliente::create(['nombre' => 'Cliente DA', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']); }
    private function payload(Cliente $c, string $payment): array { return ['asignado_a_tipo' => 'cliente', 'cliente_id' => $c->pk_cliente, 'concepto' => 'pago_cliente_da', 'fecha' => '2026-11-10', 'importe' => '2000', 'forma_pago' => $payment, 'estado_pago' => 'liquidado', 'fecha_liquidacion' => '2026-11-10', 'afecta_saldo_cliente' => '0']; }
    private function movement(Cliente $c, string $concept, float $amount, string $payment, string $state = Movimiento::PAYMENT_LIQUIDATED, bool $affects = true): Movimiento { return Movimiento::create(['cliente_id' => $c->pk_cliente, 'asignado_a_tipo' => 'cliente', 'concepto' => $concept, 'fecha' => '2026-11-10', 'importe' => $amount, 'forma_pago' => $payment, 'approval_status' => Movimiento::STATUS_APPROVED, 'estado_pago' => $state, 'fecha_liquidacion' => $state === Movimiento::PAYMENT_LIQUIDATED ? '2026-11-10' : null, 'afecta_saldo_cliente' => $affects]); }
    private function report(Cliente $c): array { return app(ReporteFinancieroService::class)->generarPorCliente($c->pk_cliente, '2026-11-01', '2026-11-30'); }
}
