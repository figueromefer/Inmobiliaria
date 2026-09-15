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
}
