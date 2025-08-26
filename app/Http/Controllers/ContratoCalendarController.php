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
        $start = Carbon::parse($request->query('start', now()->startOfMonth()));
        $end   = Carbon::parse($request->query('end', now()->endOfMonth()));

        $contratos = Contrato::query()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('fecha_inicio', [$start->toDateString(), $end->toDateString()])
                  ->orWhereBetween('fecha_fin', [$start->toDateString(), $end->toDateString()]);
            })
            ->orWhere(function ($q) use ($start, $end) {
                $q->whereNotNull('fecha_fin')
                  ->whereBetween(DB::raw("DATE_SUB(fecha_fin, INTERVAL 30 DAY)"), [$start->toDateString(), $end->toDateString()]);
            })
            ->with('inquilino')
            ->get();

        $events = [];
        foreach ($contratos as $c) {
            if ($c->fecha_inicio) {
                $events[] = [
                    'id'    => "inicio-{$c->id}",
                    'title' => "Inicio: " . ($c->solicitante ?? '—'),
                    'start' => $c->fecha_inicio->toDateString(),
                    'allDay'=> true,
                    'color' => '#16a34a',
                    'extendedProps' => [
                        'contrato_id' => $c->id,
                        'tipo'        => 'inicio',
                        'inquilino'   => optional($c->inquilino)->nombre,
                        'domicilio'   => $c->domicilio_inmueble,
                    ],
                ];
            }
            if ($c->fecha_fin) {
                $events[] = [
                    'id'    => "fin-{$c->id}",
                    'title' => "Fin: " . ($c->solicitante ?? '—'),
                    'start' => $c->fecha_fin->toDateString(),
                    'allDay'=> true,
                    'color' => '#dc2626',
                    'extendedProps' => [
                        'contrato_id' => $c->id,
                        'tipo'        => 'fin',
                        'inquilino'   => optional($c->inquilino)->nombre,
                        'domicilio'   => $c->domicilio_inmueble,
                    ],
                ];
                $reminder = $c->fecha_fin->copy()->subDays(30);
                $events[] = [
                    'id'    => "rem-{$c->id}",
                    'title' => "⚠ Renovar: " . ($c->solicitante ?? '—'),
                    'start' => $reminder->toDateString(),
                    'allDay'=> true,
                    'color' => '#f59e0b',
                    'extendedProps' => [
                        'contrato_id' => $c->id,
                        'tipo'        => 'recordatorio',
                        'fecha_fin'   => $c->fecha_fin->toDateString(),
                    ],
                ];
            }
        }

        return response()->json($events);
    }
}
