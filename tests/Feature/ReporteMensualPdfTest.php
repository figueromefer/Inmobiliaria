<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReporteMensualPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_view_uses_the_local_logo_and_identifies_the_cliente(): void
    {
        $html = view('reportes.mensual_pdf', $this->reportData())->render();

        $this->assertStringContainsString(public_path('images/logo.png'), $html);
        $this->assertStringNotContainsString('imgages/logo.png', $html);
        $this->assertStringContainsString('Reporte mensual — Cliente PDF', $html);
    }

    public function test_report_views_label_period_and_liquidation_dates(): void
    {
        $data = $this->reportData();
        $pdfHtml = view('reportes.mensual_pdf', $data)->render();
        $webHtml = $this->renderWebReport($data);

        $this->assertStringContainsString('Periodo / fecha a la que corresponde', $pdfHtml);
        $this->assertStringContainsString('Fecha de liquidación', $pdfHtml);
        $this->assertStringContainsString('15/09/2026', $pdfHtml);
        $this->assertStringContainsString('Periodo / fecha a la que corresponde', $webHtml);
        $this->assertStringContainsString('Fecha de liquidación', $webHtml);
        $this->assertStringContainsString('2026-09-15', $webHtml);
    }

    public function test_liquidation_date_column_is_omitted_when_no_renta_has_one(): void
    {
        $data = $this->reportData();
        $data['rentasRecabadas']->each(function (Movimiento $movimiento) {
            $movimiento->fecha_liquidacion = null;
        });

        $this->assertStringNotContainsString('Fecha de liquidación', view('reportes.mensual_pdf', $data)->render());
        $this->assertStringNotContainsString('Fecha de liquidación', $this->renderWebReport($data));
    }

    public function test_summary_hides_numeric_zeroes_but_keeps_positive_and_negative_values(): void
    {
        $data = $this->reportData([
            'ingresos_efectivo' => 0,
            'total_depositos' => 1234.5,
            'gastos_efectivo' => -50,
            'total_despues_gastos' => '0.00',
        ]);

        foreach (['reportes.mensual', 'reportes.mensual_pdf'] as $view) {
            $html = $view === 'reportes.mensual'
                ? $this->renderWebReport($data)
                : view($view, $data)->render();

            $this->assertStringNotContainsString('INGRESOS DEL PERIODO', $html);
            $this->assertStringNotContainsString('TOTAL DESPUÉS DE GASTOS', $html);
            $this->assertStringContainsString('TOTAL DEPOSITOS', $html);
            $this->assertStringContainsString('$1,234.50', $html);
            $this->assertStringContainsString('EGRESOS DEL PERIODO', $html);
            $this->assertStringContainsString('$-50.00', $html);
        }
    }

    public function test_short_and_long_reports_render_as_pdfs_with_the_closing_block_kept_together(): void
    {
        $short = Pdf::loadView('reportes.mensual_pdf', $this->reportData());
        $shortOutput = $short->output();
        $long = Pdf::loadView('reportes.mensual_pdf', $this->reportData([], 80));
        $longOutput = $long->output();
        $html = view('reportes.mensual_pdf', $this->reportData([], 80))->render();

        $this->assertStringStartsWith('%PDF-', $shortOutput);
        $this->assertStringStartsWith('%PDF-', $longOutput);
        $this->assertGreaterThan(1, $long->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringContainsString('class="closing-block"', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringContainsString('break-inside: avoid', $html);
    }

    private function reportData(array $summaryOverrides = [], int $rentas = 1): array
    {
        $cliente = new Cliente(['nombre' => 'Cliente PDF']);
        $cliente->pk_cliente = 1;
        $propiedad = new Propiedad(['alias' => 'Propiedad PDF']);
        $propiedad->pk_propiedad = 1;

        $movimientos = Collection::times($rentas, function (int $index) use ($cliente, $propiedad) {
            $movimiento = new Movimiento([
                'cliente_id' => $cliente->pk_cliente,
                'propiedad_id' => $propiedad->pk_propiedad,
                'concepto' => 'renta',
                'fecha' => '2026-09-01',
                'fecha_liquidacion' => '2026-09-15',
                'importe' => '1000.00',
                'forma_pago' => 'transferencia',
                'notas' => 'Renta de prueba '.$index,
            ]);
            $movimiento->setRelation('propiedad', $propiedad);

            return $movimiento;
        });

        $summary = array_merge([
            'ingresos_efectivo' => 1000,
            'total_depositos' => 0,
            'gastos_efectivo' => 0,
            'total_despues_gastos' => 1000,
            'iguala' => 0,
            'pagos_cliente_mes' => 0,
            'saldo_anterior' => 0,
            'saldo_anterior_contable' => 0,
            'saldo_anterior_liquidado' => 0,
            'total_mes' => 1000,
            'total_incluye_saldos' => 1000,
            'saldo_periodo_contable' => 1000,
            'saldo_periodo_liquidado' => 1000,
            'saldo_contable' => 1000,
            'saldo_liquidado' => 1000,
            'saldo_disponible_para_pago' => 1000,
            'pendiente_por_cobrar' => 0,
            'pendiente_por_pagar_o_liquidar' => 0,
        ], $summaryOverrides);

        return [
            'clientes' => collect([$cliente]),
            'cliente' => $cliente,
            'clienteId' => $cliente->pk_cliente,
            'mes' => '2026-09',
            'reporteFinanciero' => null,
            'rentasRecabadas' => $movimientos,
            'rentasAdelantadas' => collect(),
            'pagosExtras' => collect(),
            'desocupadas' => collect(),
            'gastosCliente' => collect(),
            'gastosPropiedad' => collect(),
            'igualas' => collect(),
            'pagosCliente' => collect(),
            'resumen' => $summary,
        ];
    }

    private function renderWebReport(array $data): string
    {
        $this->actingAs(User::factory()->create());

        return view('reportes.mensual', $data)->render();
    }
}
