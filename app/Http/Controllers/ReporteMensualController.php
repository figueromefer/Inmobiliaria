<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Services\ReporteFinancieroService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReporteMensualController extends Controller
{
    public function index(Request $request, ReporteFinancieroService $reportes)
    {
        $clienteId = (int) $request->query('cliente_id', 0);
        $mes = trim((string) $request->query('mes', ''));
        $clientes = $this->clientesParaFiltro();

        if (! $this->parametrosValidos($clienteId, $mes)) {
            return view('reportes.mensual', $this->emptyViewData($clientes, $clienteId, $mes));
        }

        $data = $this->buildReportData($reportes, $clientes, $clienteId, $mes);

        if (! $data) {
            return back()->with('error', 'Cliente no encontrado.');
        }

        return view('reportes.mensual', $data);
    }

    public function pdf(Request $request, ReporteFinancieroService $reportes)
    {
        $clienteId = (int) $request->query('cliente_id', 0);
        $mes = trim((string) $request->query('mes', ''));
        $clientes = $this->clientesParaFiltro();

        if (! $this->parametrosValidos($clienteId, $mes)) {
            return view('reportes.mensual', $this->emptyViewData($clientes, $clienteId, $mes));
        }

        $data = $this->buildReportData($reportes, $clientes, $clienteId, $mes);

        if (! $data) {
            return back()->with('error', 'Cliente no encontrado.');
        }

        $clienteNombre = Str::slug((string) $data['cliente']->nombre, '_');
        $nombreArchivo = sprintf(
            'reporte_mensual_%s_%s.pdf',
            $clienteNombre !== '' ? $clienteNombre : 'cliente',
            $mes
        );

        return Pdf::loadView('reportes.mensual_pdf', $data)->stream($nombreArchivo);
    }

    private function buildReportData(ReporteFinancieroService $reportes, Collection $clientes, int $clienteId, string $mes): ?array
    {
        $cliente = Cliente::where('pk_cliente', $clienteId)->first();

        if (! $cliente) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $mes)->endOfMonth();
        $reporteFinanciero = $reportes->generarPorCliente($clienteId, $start, $end);
        $movimientos = $reporteFinanciero['movimientos'];
        $rentasRecabadas = $movimientos->where('concepto', 'renta')->values();
        $rentasAdelantadas = $this->rentasAdelantadas($clienteId, $start, $end);
        $pagosExtras = $this->pagosExtras($movimientos, $cliente);
        $desocupadas = $this->propiedadesDesocupadas($clienteId, $cliente, $start, $end, $rentasRecabadas);
        $gastosCliente = $movimientos->where('concepto', 'gasto_cliente')->values();
        $gastosPropiedad = $movimientos->where('concepto', 'gasto')->values();
        $igualas = $movimientos->where('concepto', 'iguala')->values();
        $pagosCliente = $movimientos->where('concepto', 'pago_cliente')->values();
        $periodo = $reporteFinanciero['periodo'];

        return [
            'clientes' => $clientes,
            'cliente' => $cliente,
            'clienteId' => $clienteId,
            'mes' => $mes,
            'reporteFinanciero' => $reporteFinanciero,
            'rentasRecabadas' => $rentasRecabadas,
            'rentasAdelantadas' => $rentasAdelantadas,
            'pagosExtras' => $pagosExtras,
            'desocupadas' => $desocupadas,
            'gastosCliente' => $gastosCliente,
            'gastosPropiedad' => $gastosPropiedad,
            'igualas' => $igualas,
            'pagosCliente' => $pagosCliente,
            'resumen' => [
                'ingresos_efectivo' => (float) $periodo['ingresos_total'],
                'total_depositos' => (float) $periodo['depositos'],
                'gastos_efectivo' => (float) $periodo['egresos_total'],
                'total_despues_gastos' => (float) $periodo['ingresos_total'] - (float) $periodo['egresos_total'],
                'iguala' => (float) ($periodo['igualas'] ?? 0),
                'pagos_cliente_mes' => (float) $periodo['pagos_cliente'],
                'saldo_anterior' => (float) $reporteFinanciero['saldo_anterior'],
                'saldo_anterior_contable' => (float) $reporteFinanciero['saldo_anterior_contable'],
                'saldo_anterior_liquidado' => (float) $reporteFinanciero['saldo_anterior_liquidado'],
                'total_mes' => (float) $periodo['saldo_periodo'],
                'total_incluye_saldos' => (float) $reporteFinanciero['saldo_final'],
                'saldo_periodo_contable' => (float) $periodo['saldo_periodo_contable'],
                'saldo_periodo_liquidado' => (float) $periodo['saldo_periodo_liquidado'],
                'saldo_contable' => (float) $reporteFinanciero['saldo_contable'],
                'saldo_liquidado' => (float) $reporteFinanciero['saldo_liquidado'],
                'saldo_disponible_para_pago' => (float) $reporteFinanciero['saldo_disponible_para_pago'],
                'pendiente_por_cobrar' => (float) $reporteFinanciero['pendientes']['por_cobrar'],
                'pendiente_por_pagar_o_liquidar' => (float) $reporteFinanciero['pendientes']['por_pagar_o_liquidar'],
            ],
        ];
    }

    private function rentasAdelantadas(int $clienteId, Carbon $start, Carbon $end): Collection
    {
        return Movimiento::query()
            ->with('propiedad')
            ->where('cliente_id', $clienteId)
            ->where('approval_status', Movimiento::STATUS_APPROVED)
            ->where('afecta_saldo_cliente', true)
            ->where(function ($query) {
                $query->whereNull('estado_pago')
                    ->orWhere('estado_pago', '!=', Movimiento::PAYMENT_CANCELED);
            })
            ->where('concepto', 'renta')
            ->whereBetween('created_at', [$start, $end])
            ->whereDate('fecha', '>', $end->toDateString())
            ->orderBy('created_at')
            ->get();
    }

    private function pagosExtras(Collection $movimientos, Cliente $cliente): Collection
    {
        $contratosCliente = Contrato::where('fk_cliente', $cliente->pk_cliente)->get();

        return $movimientos->filter(function (Movimiento $movimiento) use ($contratosCliente) {
            if ($movimiento->concepto === 'iguala') {
                return false;
            }

            if ($movimiento->concepto === 'deposito' || empty($movimiento->propiedad_id)) {
                return true;
            }

            foreach ($contratosCliente as $contrato) {
                $inicio = $contrato->fecha_inicio;
                $fin = $contrato->fecha_fin;

                if ($inicio && $movimiento->fecha->greaterThanOrEqualTo($inicio) && (! $fin || $movimiento->fecha->lessThanOrEqualTo($fin))) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function propiedadesDesocupadas(int $clienteId, Cliente $cliente, Carbon $start, Carbon $end, Collection $rentasRecabadas): Collection
    {
        $propiedadesCliente = Propiedad::where('fk_cliente', $clienteId)
            ->orderBy('alias')
            ->get(['pk_propiedad', 'alias']);

        $hayContratoActivoMes = Contrato::where('fk_cliente', $cliente->pk_cliente)
            ->whereDate('fecha_inicio', '<=', $end->toDateString())
            ->where(function ($query) use ($start) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $start->toDateString());
            })
            ->exists();

        if (! $hayContratoActivoMes) {
            return $propiedadesCliente;
        }

        $propiedadesConRenta = $rentasRecabadas
            ->pluck('propiedad_id')
            ->filter()
            ->unique()
            ->all();

        return $propiedadesCliente
            ->filter(fn ($propiedad) => ! in_array($propiedad->pk_propiedad, $propiedadesConRenta, true))
            ->values();
    }

    private function emptyViewData(Collection $clientes, int $clienteId, string $mes): array
    {
        return [
            'clientes' => $clientes,
            'clienteId' => $clienteId,
            'mes' => $mes,
            'reporteFinanciero' => null,
            'rentasRecabadas' => collect(),
            'rentasAdelantadas' => collect(),
            'pagosExtras' => collect(),
            'desocupadas' => collect(),
            'gastosCliente' => collect(),
            'gastosPropiedad' => collect(),
            'igualas' => collect(),
            'pagosCliente' => collect(),
            'resumen' => [
                'ingresos_efectivo' => 0,
                'total_depositos' => 0,
                'gastos_efectivo' => 0,
                'total_despues_gastos' => 0,
                'iguala' => 0,
                'pagos_cliente_mes' => 0,
                'saldo_anterior' => 0,
                'saldo_anterior_contable' => 0,
                'saldo_anterior_liquidado' => 0,
                'total_mes' => 0,
                'total_incluye_saldos' => 0,
                'saldo_periodo_contable' => 0,
                'saldo_periodo_liquidado' => 0,
                'saldo_contable' => 0,
                'saldo_liquidado' => 0,
                'saldo_disponible_para_pago' => 0,
                'pendiente_por_cobrar' => 0,
                'pendiente_por_pagar_o_liquidar' => 0,
            ],
        ];
    }

    private function clientesParaFiltro(): Collection
    {
        return Cliente::orderBy('nombre')->get(['pk_cliente as id', 'nombre']);
    }

    private function parametrosValidos(int $clienteId, string $mes): bool
    {
        return $clienteId > 0 && preg_match('/^\d{4}\-\d{2}$/', $mes);
    }
}
