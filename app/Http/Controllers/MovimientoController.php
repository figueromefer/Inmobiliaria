<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Movimiento;
use App\Models\Cliente;
use App\Models\Propiedad;
use Barryvdh\DomPDF\Facade\Pdf;

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
            'concepto'     => ['required','in:deposito,renta,gasto,gasto_cliente,pago_cliente'],
            'fecha'        => ['required','date'],
            'importe'      => ['required','numeric','min:0'],
            'forma_pago'   => ['nullable','in:efectivo,transferencia'], // se forza efectivo para gastos
            'notas'        => ['nullable','string'],
            'comprobante' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:2048'],
        ];

        $concepto = $request->input('concepto');

        if (in_array($concepto, ['gasto_cliente','pago_cliente'])) {
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

         // Cargar el archivo si se proporcionó
        if ($request->hasFile('comprobante')) {
            $path = $request->file('comprobante')
                            ->store('comprobantes', 'public'); // se guarda en storage/app/public/comprobantes
            $data['comprobante'] = $path;
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

    /**
     * Genera un recibo en PDF para un movimiento.
     * Sólo se permiten movimientos con concepto 'deposito' o 'renta'.
     */
    public function recibo(Movimiento $movimiento)
    {
        if (!in_array($movimiento->concepto, ['deposito', 'renta'], true)) {
            return redirect()->back()
                ->with('error', 'El recibo sólo está disponible para depósitos o rentas.');
        }

        $pdf = Pdf::loadView('movimientos.recibo_pdf', [
            'movimiento' => $movimiento,
        ]);

        $fileName = 'recibo_' . $movimiento->id . '.pdf';
        return $pdf->download($fileName);
    }
}
