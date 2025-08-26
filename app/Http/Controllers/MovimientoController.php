<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Movimiento;
use App\Models\Cliente;
use App\Models\Propiedad;

class MovimientoController extends Controller
{
    // Listado simple con paginación/filtros básicos
    public function index(Request $request)
    {
        $q       = trim((string)$request->query('q', ''));
        $perPage = (int)$request->query('perPage', 15);
        if ($perPage < 5 || $perPage > 100) $perPage = 15;

        $query = Movimiento::query()->with(['cliente','propiedad']);

        if ($q !== '') {
            $query->where(function($w) use ($q){
                $w->whereHas('cliente', fn($c)=>$c->where('nombre','like',"%{$q}%"))
                  ->orWhereHas('propiedad', fn($p)=>$p->where('alias','like',"%{$q}%"))
                  ->orWhere('concepto','like',"%{$q}%")
                  ->orWhere('forma_pago','like',"%{$q}%");
            });
        }

        $query->orderByDesc('fecha')->orderByDesc('id');

        $movimientos = $query->paginate($perPage)->withQueryString();

        return view('movimientos.index', compact('movimientos','q','perPage'));
    }

    // Formulario de captura
    public function create(Request $request)
    {
        $hoy = now()->toDateString();

        // CAMBIO: clientes con contrato ACTIVO hoy, uniendo por FK (sin hacks de collation)
        $clientesActivos = Cliente::query()
            ->select('clientes.pk_cliente as id', 'clientes.nombre')
            ->join('contratos', function ($join) {
                $join->on('contratos.fk_cliente', '=', 'clientes.pk_cliente'); // CAMBIO
            })
            ->whereDate('contratos.fecha_inicio', '<=', $hoy)
            ->where(function($w) use ($hoy) {
                $w->whereNull('contratos.fecha_fin')
                  ->orWhereDate('contratos.fecha_fin', '>=', $hoy);
            })
            ->distinct()
            ->orderBy('clientes.nombre')
            ->get();

        // Si se quiere precargar propiedades de un cliente específico (opcional)
        $clienteId = (int) $request->query('cliente_id', 0);
        $propiedades = collect();
        if ($clienteId > 0) {
            $propiedades = Propiedad::where('fk_cliente', $clienteId)
                ->orderBy('alias')
                ->get(['pk_propiedad as id','alias']); // CAMBIO: usa pk_propiedad como id
        }

        return view('movimientos.create', compact('clientesActivos','propiedades','clienteId'));
    }

    // Guardar
    public function store(Request $request)
    {
        $rules = [
            'cliente_id'   => ['required','exists:clientes,pk_cliente'],
            'concepto'     => ['required','in:deposito,renta,gasto,gasto_cliente'],
            'fecha'        => ['required','date'],
            'importe'      => ['required','numeric','min:0'],
            'forma_pago'   => ['nullable','in:efectivo,transferencia'], // se fuerza efectivo para gastos
            'notas'        => ['nullable','string'],
        ];

        $concepto = $request->input('concepto');

        if ($concepto === 'gasto_cliente') {
            $rules['propiedad_id'] = ['nullable'];
        } else {
            // deposito, renta, gasto (de la propiedad) requieren propiedad
            $rules['propiedad_id'] = ['required','exists:propiedades,pk_propiedad'];
        }

        $data = $request->validate($rules);

        // Reglas de forma de pago: forzar EFECTIVO en gasto/gasto_cliente
        if ($concepto === 'gasto' || $concepto === 'gasto_cliente') {
            $data['forma_pago'] = 'efectivo';
            if ($concepto === 'gasto_cliente') {
                $data['propiedad_id'] = $data['propiedad_id'] ?? null;
            }
        } else {
            // Para depósito/renta exigir forma de pago
            if (!$request->filled('forma_pago')) {
                return back()->withInput()->withErrors(['forma_pago' => 'Selecciona la forma de pago.']);
            }
        }

        // CAMBIO: Validar pertenencia de propiedad al cliente cuando haya propiedad
        if (!empty($data['propiedad_id'])) {
            $propiedad = Propiedad::select('pk_propiedad','fk_cliente')
                ->where('pk_propiedad', $data['propiedad_id'])
                ->first();

            if (!$propiedad || (int)$propiedad->fk_cliente !== (int)$data['cliente_id']) {
                return back()->withInput()->withErrors([
                    'propiedad_id' => 'La propiedad no corresponde al cliente seleccionado.'
                ]);
            }

            // (Re)afirmar la relación cliente-propiedad para evitar inconsistencias
            // CAMBIO: Si quieres blindar, sobreescribe el cliente con el de la propiedad
            // $data['cliente_id'] = (int)$propiedad->fk_cliente;
        }

        Movimiento::create($data);

        return redirect()->route('movimientos.index')->with('ok','Movimiento registrado.');
    }

    // AJAX: propiedades por cliente
    public function propiedadesPorCliente($clienteId)
    {
        $props = Propiedad::where('fk_cliente', (int)$clienteId)
            ->orderBy('alias')
            ->get(['pk_propiedad as id', 'alias']); // ya correcto

        return response()->json($props);
    }
}
