<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Movimiento;
use App\Models\Propiedad;
use Carbon\Carbon;

class ReporteGananciasClientesController extends Controller
{
    /**
     * Muestra la gráfica de ganancias (comisiones) por cliente.
     */
    public function index(Request $request)
    {
        // opcionalmente se puede filtrar por año con ?year=2024
        $year = (int) $request->query('year', now()->year);

        // obtengo lista de todos los clientes con su ID y nombre
        $clientes = Cliente::orderBy('nombre')
            ->get(['pk_cliente', 'nombre']);

        $series = [];
        foreach ($clientes as $cliente) {
            $totalComisiones = 0.0;

            // recorro los 12 meses del año seleccionado
            for ($m = 1; $m <= 12; $m++) {
                $mes = sprintf('%04d-%02d', $year, $m);
                $start = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
                $end   = Carbon::createFromFormat('Y-m', $mes)->endOfMonth();

                // obtengo rentas en efectivo del cliente para ese mes
                $rentas = Movimiento::where('concepto', 'renta')
                    ->where('forma_pago', 'efectivo')
                    ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
                    ->whereHas('propiedad', function ($q) use ($cliente) {
                        $q->where('fk_cliente', $cliente->pk_cliente);
                    })
                    ->get();

                foreach ($rentas as $renta) {
                    // busco el contrato activo para la renta (misma lógica que en el reporte mensual:contentReference[oaicite:3]{index=3})
                    $contrato = Contrato::where('fk_cliente', $cliente->pk_cliente)
                        ->whereDate('fecha_inicio', '<=', $renta->fecha)
                        ->where(function ($w) use ($renta) {
                            $w->whereNull('fecha_fin')
                              ->orWhereDate('fecha_fin', '>=', $renta->fecha);
                        })
                        ->orderBy('fecha_inicio', 'desc')
                        ->first();

                    if ($contrato) {
                        // comisión mensual (porcentaje sobre el importe de la renta)
                        $totalComisiones += $renta->importe * (float) $contrato->comision_mensual_fraction;

                        // si es el primer mes del contrato, sumar comisión fija por renta
                        $inicioContrato = Carbon::parse($contrato->fecha_inicio);
                        $fechaRenta     = Carbon::parse($renta->fecha);
                        if ($inicioContrato->isSameMonth($fechaRenta) && $inicioContrato->isSameYear($fechaRenta)) {
                            $totalComisiones += (float) ($contrato->comision_renta ?? 0.0);
                        }
                    }
                }
            }

            $series[] = [
                'cliente' => $cliente->nombre,
                'total'   => round($totalComisiones, 2),
            ];
        }

        return view('reportes.ganancias-clientes', [
            'year'   => $year,
            'series' => $series,
        ]);
    }
}
