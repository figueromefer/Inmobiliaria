<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contrato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContratoCalendarController extends Controller
{
    public function index()
    {
        // Renderiza resources/views/calendario/index.blade.php
        return view('calendario.index');
    }

    public function events(Request $request)
    {
        // Rango solicitado por FullCalendar (string o fecha)
        $start = Carbon::parse($request->query('start', now()->startOfMonth()));
        $end   = Carbon::parse($request->query('end',   now()->endOfMonth()));

        // Cargamos relaciones para no pegarle a la BD por cada fila
        // - cliente (para mostrar nombre)
        // - propiedad (para alias/domicilio)
        // - inquilino (para mostrar en props)
        $contratos = Contrato::query()
            ->with(['cliente', 'propiedad', 'inquilino'])
            ->where(function ($q) use ($start, $end) {
                // Mostrar evento si INICIO cae dentro del rango
                $q->whereBetween('fecha_inicio', [$start->toDateString(), $end->toDateString()])
                  // o si FIN cae dentro del rango
                  ->orWhereBetween('fecha_fin',   [$start->toDateString(), $end->toDateString()])
                  // o si el RECORDATORIO (30 días antes del fin) cae dentro del rango
                  ->orWhere(function ($qq) use ($start, $end) {
                      $qq->whereNotNull('fecha_fin')
                         ->whereRaw(
                             'DATE_SUB(fecha_fin, INTERVAL 30 DAY) BETWEEN ? AND ?',
                             [$start->toDateString(), $end->toDateString()]
                         );
                  });
            })
            ->get();

        $events = [];
        foreach ($contratos as $c) {
            // Datos “bonitos” para el título
            $clienteNombre   = optional($c->cliente)->nombre ?: '—';
            $propAlias       = optional($c->propiedad)->alias;
            $propDomicilio   = optional($c->propiedad)->domicilio ?: $c->domicilio_inmueble;
            $propMostrar     = $propAlias ?: $propDomicilio;

            // Helper para no empujar eventos fuera del rango (por seguridad)
            $inRange = function (?Carbon $d) use ($start, $end) {
                return $d && $d->betweenIncluded($start->copy()->startOfDay(), $end->copy()->endOfDay());
            };

            // INICIO
            if ($c->fecha_inicio && $inRange($c->fecha_inicio)) {
                $events[] = [
                    'id'    => "inicio-{$c->id}",
                    'title' => "Inicio: {$clienteNombre}" . ($propMostrar ? " — {$propMostrar}" : ''),
                    'start' => $c->fecha_inicio->toDateString(),
                    'allDay'=> true,
                    'color' => '#16a34a', // verde
                    'extendedProps' => [
                        'contrato_id' => $c->id,
                        'tipo'        => 'inicio',
                        'cliente'     => $clienteNombre,
                        'inquilino'   => optional($c->inquilino)->nombre,
                        'propiedad'   => $propAlias,
                        'domicilio'   => $propDomicilio,
                        'fecha_fin'   => $c->fecha_fin ? $c->fecha_fin->toDateString() : null,
                    ],
                ];
            }

            // FIN
            if ($c->fecha_fin && $inRange($c->fecha_fin)) {
                $events[] = [
                    'id'    => "fin-{$c->id}",
                    'title' => "Fin: {$clienteNombre}" . ($propMostrar ? " — {$propMostrar}" : ''),
                    'start' => $c->fecha_fin->toDateString(),
                    'allDay'=> true,
                    'color' => '#dc2626', // rojo
                    'extendedProps' => [
                        'contrato_id' => $c->id,
                        'tipo'        => 'fin',
                        'cliente'     => $clienteNombre,
                        'inquilino'   => optional($c->inquilino)->nombre,
                        'propiedad'   => $propAlias,
                        'domicilio'   => $propDomicilio,
                        'fecha_inicio'=> $c->fecha_inicio ? $c->fecha_inicio->toDateString() : null,
                    ],
                ];
            }

            // RECORDATORIO (30 días antes del FIN)
            if ($c->fecha_fin) {
                $reminder = $c->fecha_fin->copy()->subDays(30);
                if ($inRange($reminder)) {
                    $events[] = [
                        'id'    => "rem-{$c->id}",
                        'title' => "⚠ Renovar: {$clienteNombre}" . ($propMostrar ? " — {$propMostrar}" : ''),
                        'start' => $reminder->toDateString(),
                        'allDay'=> true,
                        'color' => '#f59e0b', // ámbar
                        'extendedProps' => [
                            'contrato_id' => $c->id,
                            'tipo'        => 'recordatorio',
                            'cliente'     => $clienteNombre,
                            'propiedad'   => $propAlias,
                            'domicilio'   => $propDomicilio,
                            'fecha_fin'   => $c->fecha_fin->toDateString(),
                        ],
                    ];
                }
            }
        }

        return response()->json($events);
    }
}
