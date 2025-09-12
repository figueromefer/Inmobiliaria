<?php

namespace App\Http\Controllers;
use App\Services\CorteMensualService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarioController extends Controller
{
    public function eventosDeAdeudos(Request $request, CorteMensualService $cortes)
    {
        $mes = $request->query('month'); // 'YYYY-MM'
        if (!preg_match('/^\d{4}\-\d{2}$/', (string)$mes)) {
            return response()->json([]);
        }

        $start = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
        $end   = Carbon::createFromFormat('Y-m', $mes)->endOfMonth();
        $ultimoDia = $end->toDateString();

        $eventos = [];
        $clientes = Cliente::orderBy('nombre')
            ->get(['pk_cliente as id','nombre']);

        foreach ($clientes as $c) {
            $calc = $cortes->calcularCorteClienteMes((int)$c->id, $mes);

            if ($calc['total_incluye_saldos'] > 0) {
                $color = $calc['tieneSaldoAnterior'] ? '#ef4444' : '#f59e0b'; // rojo / naranja

                $eventos[] = [
                    'title' => $c->nombre,
                    'start' => $ultimoDia,        // FullCalendar admite 'YYYY-MM-DD'
                    // 'allDay' => true,          // si usas allDay
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    // Puedes adjuntar info extra:
                    'extendedProps' => [
                        'total_a_pagar' => $calc['total_incluye_saldos'],
                        'saldo_anterior'=> $calc['saldo_anterior'],
                        'total_mes'     => $calc['total_mes'],
                    ],
                ];
            }
        }

        return response()->json($eventos);
    }
}
