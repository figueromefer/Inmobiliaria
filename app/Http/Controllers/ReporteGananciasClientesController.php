<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
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

                $totalComisiones += (float) Movimiento::where('concepto', 'iguala')
                    ->where('approval_status', Movimiento::STATUS_APPROVED)
                    ->where('afecta_saldo_cliente', true)
                    ->where(function ($query) {
                        $query->whereNull('estado_pago')
                            ->orWhere('estado_pago', '!=', Movimiento::PAYMENT_CANCELED);
                    })
                    ->where('cliente_id', $cliente->pk_cliente)
                    ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
                    ->sum('importe');
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
