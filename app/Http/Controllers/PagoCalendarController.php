<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\CorteMensualService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PagoCalendarController extends Controller
{
    protected $cortes;

    public function __construct(CorteMensualService $cortes)
    {
        $this->cortes = $cortes;
    }

    public function events(Request $request)
    {
        $start = \Illuminate\Support\Carbon::parse($request->query('start', now()->startOfMonth()));
        $yyyymm = $start->format('Y-m');
        $end    = $start->copy()->endOfMonth();       // <-- añadimos $end para rangos
        $ultimoDia = $end->toDateString();

        $eventos = [];
        $clientes = \App\Models\Cliente::orderBy('nombre')->get(['pk_cliente as id','nombre']);

        foreach ($clientes as $cliente) {
            $calc = $this->cortes->calcularCorteClienteMes((int)$cliente->id, $yyyymm);

            // Sólo generamos evento si hay algo pendiente de pagar
            if ($calc['total_incluye_saldos'] > 0) {

                // 1) ¿Hay saldo anterior? => rojo
                if ($calc['tieneSaldoAnterior']) {
                    $color = '#ef4444'; // rojo
                } else {
                    // 2) ¿Hay cobranza completa en el mes? (todas las propiedades con contrato ACTIVO tuvieron renta registrada)
                    //    * Tomamos contratos activos durante el mes:
                    $propIdsActivas = \App\Models\Contrato::query()
                        ->where('fk_cliente', $cliente->id)
                        ->whereDate('fecha_inicio', '<=', $end->toDateString())
                        ->where(function($q) use ($start) {
                            $q->whereNull('fecha_fin')
                            ->orWhereDate('fecha_fin', '>=', $start->toDateString());
                        })
                        ->pluck('fk_propiedad')   // ajusta si tu FK se llama distinto
                        ->filter()                // por seguridad
                        ->unique()
                        ->values();

                    $numPropsActivas = $propIdsActivas->count();

                    // Cuenta de propiedades con al menos UNA renta registrada en el mes
                    // Nota: si deseas que cuente también transferencias, elimina el where('forma_pago','efectivo')
                    $propsConRenta = \App\Models\Movimiento::query()
                        ->where('concepto','renta')
                        ->where('forma_pago','efectivo') // <- mantener para “caja” del mes
                        ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
                        ->whereIn('propiedad_id', $propIdsActivas)
                        ->distinct('propiedad_id')
                        ->count('propiedad_id');

                    $cobranzaCompleta = $numPropsActivas > 0 && $propsConRenta === $numPropsActivas;

                    // 2a) Si hay cobranza completa y aún hay total pendiente => morado; de lo contrario => naranja
                    $color = $cobranzaCompleta ? '#8b5cf6' /* morado-500 */ : '#f59e0b' /* naranja */ ;
                }

                $eventos[] = [
                    'title' => $cliente->nombre,   // etiqueta = nombre del cliente
                    'start' => $ultimoDia,
                    'allDay'=> true,
                    'color' => $color,
                    'extendedProps' => [
                        'cliente_id'     => $cliente->id,
                        'total_a_pagar'  => $calc['total_incluye_saldos'],
                        'saldo_anterior' => $calc['saldo_anterior'],
                        'total_mes'      => $calc['total_mes'],
                        // Opcional: puedes exponer si fue “cobranzaCompleta” para el tooltip
                        'cobranza_completa' => $cobranzaCompleta ?? false,
                    ],
                ];
            }
        }

        return response()->json($eventos);
    }

}
