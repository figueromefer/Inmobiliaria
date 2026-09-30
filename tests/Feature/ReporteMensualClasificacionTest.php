<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\ReporteFinancieroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ReporteMensualClasificacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_gasto_cliente_is_not_duplicated_as_pago_extra_in_web_or_pdf_reports(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'OSCAR DAVID RODRIGUEZ SALDIVAR',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio de prueba',
        ]);
        $gastoCliente = $this->movement($cliente, 'MOV-000092', 'gasto_cliente', 1250, 'Gasto cliente exclusivo');
        $pagoExtra = $this->movement($cliente, 'MOV-000093', 'deposito', 500, 'Depósito extra legítimo');

        $webData = null;
        $pdfData = null;
        View::composer('reportes.mensual', function ($view) use (&$webData) {
            $webData = $view->getData();
        });
        View::composer('reportes.mensual_pdf', function ($view) use (&$pdfData) {
            $pdfData = $view->getData();
        });

        $user = User::factory()->create();
        $webResponse = $this->actingAs($user)->get(route('reportes.mensual', [
            'cliente_id' => $cliente->pk_cliente,
            'mes' => '2026-09',
        ]));
        $webResponse->assertOk();
        $this->assertSame(1, substr_count($webResponse->getContent(), 'Gasto cliente exclusivo'));
        $this->assertSame(1, substr_count($webResponse->getContent(), 'Depósito extra legítimo'));

        $this->actingAs($user)->get(route('reportes.mensual.pdf', [
            'cliente_id' => $cliente->pk_cliente,
            'mes' => '2026-09',
        ]))->assertOk();

        foreach ([$webData, $pdfData] as $reportData) {
            $this->assertTrue($reportData['gastosCliente']->contains('id', $gastoCliente->id));
            $this->assertFalse($reportData['pagosExtras']->contains('id', $gastoCliente->id));
            $this->assertTrue($reportData['pagosExtras']->contains('id', $pagoExtra->id));
        }

        $financiero = app(ReporteFinancieroService::class)->generarPorCliente(
            $cliente->pk_cliente,
            '2026-09-01',
            '2026-09-30'
        );
        $this->assertSame(1250.0, $financiero['periodo']['gastos_cliente']);
        $this->assertSame(1250.0, $financiero['periodo']['egresos_total']);
        $this->assertSame(-750.0, $financiero['periodo']['saldo_periodo']);
    }

    public function test_direct_transfer_income_is_visible_and_informative_without_affecting_any_balance(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'CLIENTE TRANSFERENCIAS',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio de prueba',
        ]);

        $rentaEfectivo = $this->financialMovement($cliente, 'MOV-TR-001', 'renta', 10000, 'efectivo');
        $rentaTransferencia = $this->financialMovement($cliente, 'MOV-TR-002', 'renta', 8000, 'transferencia');
        $depositoTransferencia = $this->financialMovement($cliente, 'MOV-TR-003', 'deposito', 500, 'transferencia');
        $gasto = $this->financialMovement($cliente, 'MOV-TR-004', 'gasto', 2000, 'efectivo');
        $pagoCliente = $this->financialMovement($cliente, 'MOV-TR-005', 'pago_cliente', 1000, 'transferencia');
        $rentaPendienteTransferencia = $this->financialMovement($cliente, 'MOV-TR-006', 'renta', 300, 'transferencia', Movimiento::PAYMENT_PENDING);

        $reporte = app(ReporteFinancieroService::class)->generarPorCliente(
            $cliente->pk_cliente,
            '2026-09-01',
            '2026-09-30',
        );

        $this->assertTrue($reporte['movimientos']->contains('id', $rentaEfectivo->id));
        $this->assertTrue($reporte['movimientos']->contains('id', $rentaTransferencia->id));
        $this->assertTrue($reporte['movimientos']->contains('id', $depositoTransferencia->id));
        $this->assertTrue($reporte['movimientos']->contains('id', $rentaPendienteTransferencia->id));
        $this->assertSame(8800.0, $reporte['periodo']['transferencias_informativas']);
        $this->assertSame(8800.0, $reporte['periodo']['total_transferencias']);
        $this->assertSame(10000.0, $reporte['periodo']['ingresos_afectan_saldo']);
        $this->assertSame(10000.0, $reporte['periodo']['ingresos_total']);
        $this->assertSame(7000.0, $reporte['periodo']['saldo_periodo']);
        $this->assertSame(7000.0, $reporte['periodo']['saldo_periodo_contable']);
        $this->assertSame(7000.0, $reporte['periodo']['saldo_periodo_liquidado']);
        $this->assertSame(7000.0, $reporte['saldo_final']);
        $this->assertSame(7000.0, $reporte['saldo_contable']);
        $this->assertSame(7000.0, $reporte['saldo_liquidado']);
        $this->assertSame(7000.0, $reporte['saldo_por_pagar_cliente']);
        $this->assertSame(7000.0, $reporte['saldo_disponible_para_pago']);
        $this->assertEquals(0.0, $reporte['pendientes']['por_cobrar']);
        $this->assertSame(1000.0, $reporte['liquidados']['pagos_cliente']);
        $this->assertSame(2000.0, $reporte['liquidados']['egresos']);

        $user = User::factory()->create();
        $web = $this->actingAs($user)->get(route('reportes.mensual', [
            'cliente_id' => $cliente->pk_cliente,
            'mes' => '2026-09',
        ]));
        $web->assertOk()->assertSee('TOTAL TRANSFERENCIAS (INFORMATIVO; DEPOSITADAS DIRECTAMENTE AL CLIENTE)');
        $web->assertSee('$8,800.00');

        $pdf = $this->actingAs($user)->get(route('reportes.mensual.pdf', [
            'cliente_id' => $cliente->pk_cliente,
            'mes' => '2026-09',
        ]));
        $pdf->assertOk();
    }

    public function test_historical_direct_transfers_do_not_inflate_prior_or_current_balances(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'CLIENTE HISTORICO',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio de prueba',
        ]);

        $this->financialMovement($cliente, 'MOV-HIS-001', 'renta', 9000, 'transferencia', Movimiento::PAYMENT_LIQUIDATED, '2026-08-20');
        $this->financialMovement($cliente, 'MOV-HIS-002', 'deposito', 500, 'transferencia', Movimiento::PAYMENT_LIQUIDATED, '2026-08-21');
        $this->financialMovement($cliente, 'MOV-HIS-003', 'renta', 1000, 'efectivo', Movimiento::PAYMENT_LIQUIDATED, '2026-08-22');
        $this->financialMovement($cliente, 'MOV-HIS-004', 'renta', 2000, 'transferencia', Movimiento::PAYMENT_LIQUIDATED, '2026-09-14');
        $this->financialMovement($cliente, 'MOV-HIS-005', 'renta', 500, 'efectivo', Movimiento::PAYMENT_LIQUIDATED, '2026-09-15');

        $reporte = app(ReporteFinancieroService::class)->generarPorCliente(
            $cliente->pk_cliente,
            '2026-09-01',
            '2026-09-30',
        );

        $this->assertSame(1000.0, $reporte['saldo_anterior']);
        $this->assertSame(1000.0, $reporte['saldo_anterior_contable']);
        $this->assertSame(1000.0, $reporte['saldo_anterior_liquidado']);
        $this->assertSame(500.0, $reporte['periodo']['saldo_periodo']);
        $this->assertSame(1500.0, $reporte['saldo_final']);
        $this->assertSame(1500.0, $reporte['saldo_contable']);
        $this->assertSame(1500.0, $reporte['saldo_liquidado']);
        $this->assertSame(1500.0, $reporte['saldo_disponible_para_pago']);
        $this->assertSame(1500.0, $reporte['saldo_por_pagar_cliente']);
    }

    public function test_only_direct_income_transfers_bypass_historical_visibility_rule(): void
    {
        $cliente = Cliente::create([
            'nombre' => 'CLIENTE VISIBILIDAD',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio de prueba',
        ]);

        $transferenciaRenta = $this->financialMovement($cliente, 'MOV-VIS-001', 'renta', 500, 'transferencia', Movimiento::PAYMENT_LIQUIDATED, '2026-09-14', false, 'Transferencia visible');
        $transferenciaDeposito = $this->financialMovement($cliente, 'MOV-VIS-002', 'deposito', 200, 'transferencia', Movimiento::PAYMENT_LIQUIDATED, '2026-09-14', false, 'Depósito transferencia visible');
        $rentaEfectivo = $this->financialMovement($cliente, 'MOV-VIS-003', 'renta', 100, 'efectivo', Movimiento::PAYMENT_LIQUIDATED, '2026-09-14', false, 'No mostrar renta efectivo');
        $gasto = $this->financialMovement($cliente, 'MOV-VIS-004', 'gasto', 100, 'efectivo', Movimiento::PAYMENT_LIQUIDATED, '2026-09-14', false, 'No mostrar gasto');
        $iguala = $this->financialMovement($cliente, 'MOV-VIS-005', 'iguala', 100, 'efectivo', Movimiento::PAYMENT_LIQUIDATED, '2026-09-14', false, 'No mostrar iguala');
        $pagoCliente = $this->financialMovement($cliente, 'MOV-VIS-006', 'pago_cliente', 100, 'transferencia', Movimiento::PAYMENT_LIQUIDATED, '2026-09-14', false, 'No mostrar pago cliente');

        $reporte = app(ReporteFinancieroService::class)->generarPorCliente(
            $cliente->pk_cliente,
            '2026-09-01',
            '2026-09-30',
        );

        $this->assertTrue($reporte['movimientos']->contains('id', $transferenciaRenta->id));
        $this->assertTrue($reporte['movimientos']->contains('id', $transferenciaDeposito->id));
        $this->assertFalse($reporte['movimientos']->contains('id', $rentaEfectivo->id));
        $this->assertFalse($reporte['movimientos']->contains('id', $gasto->id));
        $this->assertFalse($reporte['movimientos']->contains('id', $iguala->id));
        $this->assertFalse($reporte['movimientos']->contains('id', $pagoCliente->id));
        $this->assertSame(700.0, $reporte['periodo']['total_transferencias']);
        $this->assertSame(0.0, $reporte['saldo_final']);

        $webData = null;
        $pdfData = null;
        View::composer('reportes.mensual', function ($view) use (&$webData) {
            $webData = $view->getData();
        });
        View::composer('reportes.mensual_pdf', function ($view) use (&$pdfData) {
            $pdfData = $view->getData();
        });

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('reportes.mensual', [
            'cliente_id' => $cliente->pk_cliente,
            'mes' => '2026-09',
        ]))->assertOk()->assertSee('Transferencia visible')->assertDontSee('No mostrar gasto');
        $this->actingAs($user)->get(route('reportes.mensual.pdf', [
            'cliente_id' => $cliente->pk_cliente,
            'mes' => '2026-09',
        ]))->assertOk();

        foreach ([$webData, $pdfData] as $reportData) {
            $this->assertTrue($reportData['rentasRecabadas']->contains('id', $transferenciaRenta->id));
            $this->assertTrue($reportData['pagosExtras']->contains('id', $transferenciaDeposito->id));
            $this->assertFalse($reportData['rentasRecabadas']->contains('id', $rentaEfectivo->id));
            $this->assertFalse($reportData['gastosPropiedad']->contains('id', $gasto->id));
            $this->assertFalse($reportData['igualas']->contains('id', $iguala->id));
            $this->assertFalse($reportData['pagosCliente']->contains('id', $pagoCliente->id));
        }
    }

    private function movement(Cliente $cliente, string $folio, string $concepto, float $importe, string $notas): Movimiento
    {
        return Movimiento::create([
            'cliente_id' => $cliente->pk_cliente,
            'asignado_a_tipo' => 'cliente',
            'folio' => $folio,
            'concepto' => $concepto,
            'fecha' => '2026-09-14',
            'importe' => $importe,
            'forma_pago' => 'efectivo',
            'notas' => $notas,
            'approval_status' => Movimiento::STATUS_APPROVED,
            'estado_pago' => Movimiento::PAYMENT_LIQUIDATED,
            'fecha_liquidacion' => '2026-09-14',
            'afecta_saldo_cliente' => true,
        ]);
    }

    private function financialMovement(
        Cliente $cliente,
        string $folio,
        string $concepto,
        float $importe,
        string $formaPago,
        string $estadoPago = Movimiento::PAYMENT_LIQUIDATED,
        string $fecha = '2026-09-14',
        bool $afectaSaldoCliente = true,
        ?string $notas = null,
    ): Movimiento {
        return Movimiento::create([
            'cliente_id' => $cliente->pk_cliente,
            'asignado_a_tipo' => 'cliente',
            'folio' => $folio,
            'concepto' => $concepto,
            'fecha' => $fecha,
            'importe' => $importe,
            'forma_pago' => $formaPago,
            'notas' => $notas,
            'approval_status' => Movimiento::STATUS_APPROVED,
            'estado_pago' => $estadoPago,
            'fecha_liquidacion' => $estadoPago === Movimiento::PAYMENT_LIQUIDATED ? $fecha : null,
            'afecta_saldo_cliente' => $afectaSaldoCliente,
        ]);
    }
}
