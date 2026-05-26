<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Movimiento;
use App\Models\Cliente;
use App\Models\Propiedad;
use Barryvdh\DomPDF\Facade\Pdf;

class MovimientoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $perPage = (int)$request->query('perPage', 15);
        if ($perPage < 5 || $perPage > 100) $perPage = 15;

        $query = Movimiento::query()->with(['cliente','propiedad','approver']);

        if ($q !== '') {
            $query->where(function($w) use ($q){
                $w->whereHas('cliente', fn($c)=>$c->where('nombre','like',"%{$q}%"))
                  ->orWhereHas('propiedad', fn($p)=>$p->where('alias','like',"%{$q}%"))
                  ->orWhere('concepto','like',"%{$q}%")
                  ->orWhere('forma_pago','like',"%{$q}%")
                  ->orWhere('approval_status','like',"%{$q}%");
            });
        }

        $query->orderByDesc('fecha')->orderByDesc('id');

        $movimientos = $query->paginate($perPage)->withQueryString();

        return view('movimientos.index', compact('movimientos','q','perPage'));
    }

    public function create(Request $request)
    {
        $hoy = now()->toDateString();

        $clientesActivos = Cliente::query()
            ->select('clientes.pk_cliente as id', 'clientes.nombre')
            ->join('contratos', function ($join) {
                $join->on('contratos.fk_cliente', '=', 'clientes.pk_cliente');
            })
            ->whereDate('contratos.fecha_inicio', '<=', $hoy)
            ->where(function($w) use ($hoy) {
                $w->whereNull('contratos.fecha_fin')
                  ->orWhereDate('contratos.fecha_fin', '>=', $hoy);
            })
            ->distinct()
            ->orderBy('clientes.nombre')
            ->get();

        $clienteId = (int) $request->query('cliente_id', 0);
        $propiedades = collect();
        if ($clienteId > 0) {
            $propiedades = Propiedad::where('fk_cliente', $clienteId)
                ->orderBy('alias')
                ->get(['pk_propiedad as id','alias']);
        }

        return view('movimientos.create', compact('clientesActivos','propiedades','clienteId'));
    }

    public function store(Request $request)
    {
        $rules = [
            'cliente_id' => ['required','exists:clientes,pk_cliente'],
            'concepto' => ['required','in:deposito,renta,gasto,gasto_cliente,pago_cliente'],
            'fecha' => ['required','date'],
            'importe' => ['required','numeric','min:0'],
            'forma_pago' => ['nullable','in:efectivo,transferencia'],
            'notas' => ['nullable','string'],
            'comprobante' => ['nullable','file','mimes:jpg,jpeg,png,pdf','max:2048'],
        ];

        $concepto = $request->input('concepto');

        if (in_array($concepto, ['gasto_cliente','pago_cliente'], true)) {
            $rules['propiedad_id'] = ['nullable'];
        } else {
            $rules['propiedad_id'] = ['required','exists:propiedades,pk_propiedad'];
        }

        $data = $request->validate($rules);

        if ($concepto === 'gasto' || $concepto === 'gasto_cliente') {
            $data['forma_pago'] = 'efectivo';
            if ($concepto === 'gasto_cliente') {
                $data['propiedad_id'] = $data['propiedad_id'] ?? null;
            }
        } else {
            if (!$request->filled('forma_pago')) {
                return back()->withInput()->withErrors(['forma_pago' => 'Selecciona la forma de pago.']);
            }
        }

        if (!empty($data['propiedad_id'])) {
            $propiedad = Propiedad::select('pk_propiedad','fk_cliente')
                ->where('pk_propiedad', $data['propiedad_id'])
                ->first();

            if (!$propiedad || (int)$propiedad->fk_cliente !== (int)$data['cliente_id']) {
                return back()->withInput()->withErrors([
                    'propiedad_id' => 'La propiedad no corresponde al cliente seleccionado.'
                ]);
            }
        }

        if ($request->hasFile('comprobante')) {
            $data['comprobante'] = $request->file('comprobante')->store('comprobantes', 'public');
        }

        if ($request->user()?->role === 'admin') {
            $data['approval_status'] = Movimiento::STATUS_APPROVED;
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
            $message = 'Movimiento registrado y aprobado.';
        } else {
            $data['approval_status'] = Movimiento::STATUS_PENDING;
            $data['approved_by'] = null;
            $data['approved_at'] = null;
            $message = 'Movimiento registrado y pendiente de aprobación.';
        }

        Movimiento::create($data);

        return redirect()->route('movimientos.index')->with('ok', $message);
    }

    public function approve(Request $request, Movimiento $movimiento)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        if (! $movimiento->isPendingApproval()) {
            return redirect()->route('movimientos.index')->with('ok', 'El movimiento ya no está pendiente.');
        }

        $movimiento->approveBy($request->user());

        return redirect()->route('movimientos.index')->with('ok', 'Movimiento aprobado correctamente.');
    }

    public function propiedadesPorCliente($clienteId)
    {
        $props = Propiedad::where('fk_cliente', (int)$clienteId)
            ->orderBy('alias')
            ->get(['pk_propiedad as id', 'alias']);

        return response()->json($props);
    }

    public function recibo(Movimiento $movimiento)
    {
        if (!in_array($movimiento->concepto, ['deposito', 'renta'], true)) {
            return redirect()->back()->with('error', 'El recibo sólo está disponible para depósitos o rentas.');
        }

        if ($movimiento->approval_status !== Movimiento::STATUS_APPROVED) {
            return redirect()->back()->with('error', 'El recibo sólo está disponible para movimientos aprobados.');
        }

        $pdf = Pdf::loadView('movimientos.recibo_pdf', [
            'movimiento' => $movimiento,
        ]);

        return $pdf->stream('recibo.pdf', ['Attachment' => false]);
    }
}
