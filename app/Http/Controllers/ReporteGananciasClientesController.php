<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Movimiento;
use Carbon\Carbon;

class ReporteGananciasClientesController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        $clientes = Cliente::orderBy('nombre')->get(['pk_cliente', 'nombre']);

        $series = [];
        foreach ($clientes as $cliente) {
            $totalComisiones = 0.0;

            for ($m = 1; $m <= 12; $m++) {
                $mes = sprintf('%04d-%02d', $year, $m);
                $start = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
                $end = Carbon::createFromFormat('Y-m', $mes)->endOfMonth();

                $rentas = Movimiento::where('concepto', 'renta')
                    ->where('approval_status', Movimiento::STATUS_APPROVED)
                    ->where('forma_pago', 'efectivo')
                    ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
                    ->whereHas('propiedad', function ($q) use ($cliente) {
                        $q->where('fk_cliente', $cliente->pk_cliente);
                    })
                    ->get();

                foreach ($rentas as $renta) {
                    $contrato = Contrato::where('fk_cliente', $cliente->pk_cliente)
                        ->whereDate('fecha_inicio', '<=', $renta->fecha)
                        ->where(function ($w) use ($renta) {
                            $w->whereNull('fecha_fin')
                              ->orWhereDate('fecha_fin', '>=', $renta->fecha);
                        })
                        ->orderBy('fecha_inicio', 'desc')
                        ->first();

                    if ($contrato) {
                        $totalComisiones += $renta->importe * (float) $contrato->comision_mensual_fraction;

                        $inicioContrato = Carbon::parse($contrato->fecha_inicio);
                        $fechaRenta = Carbon::parse($renta->fecha);
                        if ($inicioContrato->isSameMonth($fechaRenta) && $inicioContrato->isSameYear($fechaRenta)) {
                            $totalComisiones += (float) ($contrato->comision_renta ?? 0.0);
                        }
                    }
                }
            }

            $series[] = [
                'cliente' => $cliente->nombre,
                'total' => round($totalComisiones, 2),
            ];
        }

        return view('reportes.ganancias-clientes', [
            'year' => $year,
            'series' => $series,
        ]);
    }
}
