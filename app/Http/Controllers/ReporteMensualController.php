<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Movimiento;
use App\Models\Contrato;

class ReporteMensualController extends Controller
{
    public function index(Request $request)
    {
        // === 1) Parámetros ===
        $clienteId = (int) $request->query('cliente_id', 0);
        $mes       = trim((string) $request->query('mes', '')); // formato YYYY-MM (input type="month")

        // Clientes para el select
        $clientes = Cliente::orderBy('nombre')->get(['pk_cliente as id','nombre']);

        // Si faltan parámetros, solo renderiza el formulario
        if ($clienteId <= 0 || !preg_match('/^\d{4}\-\d{2}$/', $mes)) {
            return view('reportes.mensual', [
                'clientes'   => $clientes,
                'clienteId'  => $clienteId,
                'mes'        => $mes,
                // resultados vacíos
                'rentasRecabadas'   => collect(),
                'rentasAdelantadas' => collect(),
                'pagosExtras'       => collect(),
                'desocupadas'       => collect(),
                'gastosCliente'     => collect(),
                'gastosPropiedad'   => collect(),
                'resumen'           => [
                    'ingresos_efectivo'   => 0,
                    'total_depositos'     => 0,
                    'gastos_efectivo'     => 0,
                    'total_despues_gastos'=> 0,
                    'iguala'              => 0,
                ],
            ]);
        }

        // Rango del mes
        $start = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
        $end   = Carbon::createFromFormat('Y-m', $mes)->endOfMonth();

        // Datos base del cliente
        $cliente = Cliente::where('pk_cliente', $clienteId)->first();
        if (!$cliente) {
            return back()->with('error', 'Cliente no encontrado.');
        }

        // 2) Rentas recabadas (rentas del mes para el cliente)
        $rentasRecabadas = Movimiento::with('propiedad')
            ->where('concepto','renta')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->whereHas('propiedad', function($q) use ($clienteId) {
                $q->where('fk_cliente', $clienteId);
            })
            ->orderBy('fecha')
            ->get();

        // 3) Rentas adelantadas
        $rentasAdelantadas = Movimiento::with('propiedad')
            ->where('concepto','renta')
            ->whereBetween('created_at', [$start, $end])
            ->whereDate('fecha','>', $end->toDateString())
            ->whereHas('propiedad', function($q) use ($clienteId) {
                $q->where('fk_cliente', $clienteId);
            })
            ->orderBy('created_at')
            ->get();


        // === 4) Pagos extras ===
        // Definición: movimientos del mes que NO caen dentro de un contrato activo (por nombre cliente),
        // o que no tienen propiedad (propiedad_id NULL). Nos enfocamos en conceptos de ingreso/egreso relevantes.
        $movMes = Movimiento::with('propiedad')
            ->where('cliente_id', $clienteId)
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->orderBy('fecha')
            ->get();

        // Contratos del cliente (match por nombre)
        $contratosCliente = Contrato::where('fk_cliente', $cliente->pk_cliente)->get();

       $pagosExtras = $movMes->filter(function ($m) use ($contratosCliente) {
            // Incluir depósitos siempre como pagos extra
            if ($m->concepto === 'deposito') {
                return true;
            }
            // Movimientos sin propiedad asignada
            if (empty($m->propiedad_id)) {
                return true;
            }
            // Movimientos fuera de contratos activos
            foreach ($contratosCliente as $c) {
                $ini = $c->fecha_inicio;
                $fin = $c->fecha_fin;
                // Si el movimiento cae dentro de algún contrato activo, NO es pago extra
                if ($ini && $m->fecha->greaterThanOrEqualTo($ini) &&
                    (!$fin || $m->fecha->lessThanOrEqualTo($fin))) {
                    return false;
                }
            }
            return true;
        })->values();


        // === 5) Desocupadas ===
        // Propiedades del cliente sin contrato ACTIVO en el mes seleccionado.
        // Sin relación propiedad-contrato, usamos: si el cliente NO tiene ningún contrato activo en el mes -> todas sus propiedades se marcan "desocupadas".
        // Si sí tiene contrato activo, mostramos propiedades que no tuvieron renta en el mes (heurística).
        $propiedadesCliente = Propiedad::where('fk_cliente', $clienteId)
            ->orderBy('alias')->get(['pk_propiedad','alias']);

        $hayContratoActivoMes = Contrato::where('fk_cliente', $cliente->pk_cliente)
            ->whereDate('fecha_inicio', '<=', $end->toDateString())
            ->where(function($w) use ($start) {
                $w->whereNull('fecha_fin')
                  ->orWhereDate('fecha_fin', '>=', $start->toDateString());
            })
            ->exists();

        if (!$hayContratoActivoMes) {
            $desocupadas = $propiedadesCliente;
        } else {
            $propConRentaMes = Movimiento::where('cliente_id',$clienteId)
                ->where('concepto','renta')
                ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
                ->whereNotNull('propiedad_id')
                ->pluck('propiedad_id')
                ->unique()
                ->all();

            $desocupadas = $propiedadesCliente->filter(function($p) use ($propConRentaMes){
                return !in_array($p->pk_propiedad, $propConRentaMes, true);
            })->values();
        }

        // === 6) Gastos de cliente (mes) ===
        $gastosCliente = Movimiento::where('cliente_id', $clienteId)
            ->where('concepto', 'gasto_cliente')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->orderBy('fecha')
            ->get();

        // === 7) Gastos de la propiedad (mes) ===
        $gastosPropiedad = Movimiento::with('propiedad')
            ->where('cliente_id', $clienteId)
            ->where('concepto', 'gasto')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->orderBy('fecha')
            ->get();

        // === 8) Resumen ===
        $ingresosEfectivo = Movimiento::where('cliente_id', $clienteId)
            ->whereIn('concepto', ['renta','deposito'])
            ->where('forma_pago','efectivo')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->sum('importe');

        $totalDepositos = Movimiento::where('cliente_id', $clienteId)
            ->whereIn('concepto', ['renta','deposito'])
            ->where('forma_pago','transferencia')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->sum('importe');

        $gastosEfectivo = Movimiento::where('cliente_id', $clienteId)
            ->whereIn('concepto', ['gasto','gasto_cliente'])
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->sum('importe');

        // IGUALA: suma de comisiones por cada RENTA del mes
        // - comision_mensual: porcentaje (0.10 = 10%) x importe del movimiento
        // - comision_renta: se suma solo si el movimiento cae en el mes de inicio del contrato
        $iguala = 0.0;
        foreach ($rentasRecabadas as $r) {
            // Buscar contrato activo en la fecha del movimiento (por nombre del cliente)
            $contrato = Contrato::where('fk_cliente', $cliente->pk_cliente)
                ->whereDate('fecha_inicio', '<=', $r->fecha)
                ->where(function($w) use ($r) {
                    $w->whereNull('fecha_fin')
                      ->orWhereDate('fecha_fin', '>=', $r->fecha);
                })
                ->orderBy('fecha_inicio','desc') // por si hay varios, toma el más reciente que cubra
                ->first();

            if ($contrato) {
               $pct = $contrato->comision_mensual_fraction;
                $iguala += $r->importe * $pct;

                // ¿primer mes del contrato?
                $ini = Carbon::parse($contrato->fecha_inicio);
                $rm  = Carbon::parse($r->fecha);
                if ($ini->isSameMonth($rm) && $ini->isSameYear($rm)) {
                    $iguala += (float) ($contrato->comision_renta ?? 0);
                }
            }
        }

        $resumen = [
            'ingresos_efectivo'     => (float) $ingresosEfectivo,
            'total_depositos'       => (float) $totalDepositos,
            'gastos_efectivo'       => (float) $gastosEfectivo,
            'total_despues_gastos'  => (float) $ingresosEfectivo - (float) $gastosEfectivo,
            'iguala'                => (float) $iguala,
        ];

        return view('reportes.mensual', [
            'clientes'         => $clientes,
            'clienteId'        => $clienteId,
            'mes'              => $mes,
            'rentasRecabadas'  => $rentasRecabadas,
            'rentasAdelantadas'=> $rentasAdelantadas,
            'pagosExtras'      => $pagosExtras,
            'desocupadas'      => $desocupadas,
            'gastosCliente'    => $gastosCliente,
            'gastosPropiedad'  => $gastosPropiedad,
            'resumen'          => $resumen,
        ]);
    }
}
