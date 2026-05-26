<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PropiedadController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));

        $propiedades = Propiedad::with('cliente')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('alias', 'like', "%{$search}%")
                        ->orWhere('domicilio', 'like', "%{$search}%")
                        ->orWhere('calle', 'like', "%{$search}%")
                        ->orWhere('numero_exterior', 'like', "%{$search}%")
                        ->orWhere('numero_interior', 'like', "%{$search}%")
                        ->orWhere('colonia', 'like', "%{$search}%")
                        ->orWhere('codigo_postal', 'like', "%{$search}%")
                        ->orWhere('municipio', 'like', "%{$search}%")
                        ->orWhere('estado', 'like', "%{$search}%")
                        ->orWhere('siapa', 'like', "%{$search}%")
                        ->orWhere('cfe', 'like', "%{$search}%")
                        ->orWhere('predial', 'like', "%{$search}%")
                        ->orWhereHas('cliente', function ($clienteQuery) use ($search) {
                            $clienteQuery->where('nombre', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('alias')
            ->paginate(20)
            ->withQueryString();

        return view('propiedades.index', compact('propiedades', 'search'));
    }

    public function create(Request $request)
    {
        Gate::authorize('manage-records');

        $clientes = Cliente::orderBy('nombre')->get();
        $clientePreseleccionado = $request->get('cliente_id');

        return view('propiedades.create', compact('clientes', 'clientePreseleccionado'));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-records');

        $request->validate([
            'fk_cliente' => 'required|exists:clientes,pk_cliente',
            'alias' => ['required','string','max:255','unique:propiedades,alias'],
            'domicilio' => 'nullable|string|max:255',
            'siapa' => 'nullable|string|max:255',
            'cfe' => 'nullable|string|max:255',
            'predial' => 'nullable|string|max:255',
            'mantenimiento_banco' => 'nullable|string|max:255',
            'mantenimiento_cuenta' => 'nullable|string|max:255',
            'mantenimiento_monto' => 'nullable|numeric',
            'mantenimiento_fecha_pago' => 'nullable|date',
            'latitud' => 'nullable|string|max:255',
            'longitud' => 'nullable|string|max:255',
            'estatus_informacion' => 'required|string',
            'calle' => 'nullable|string',
            'numero_exterior' => 'nullable|string',
            'numero_interior' => 'nullable|string',
            'colonia' => 'nullable|string',
            'codigo_postal' => 'nullable|string',
            'municipio' => 'nullable|string',
            'estado' => 'nullable|string',
        ]);

        $propiedad = Propiedad::create($request->all());

        if ($propiedad->estatus_informacion !== 'completo') {
            Task::create([
                'title' => 'Completar información de propiedad: ' . $propiedad->alias,
                'description' => 'Revisar y completar información crítica de la propiedad.',
                'due_date' => now()->addDays(3)->toDateString(),
                'status' => 'pending',
                'priority' => $propiedad->estatus_informacion === 'pendiente_critico' ? 'high' : 'medium',
                'source_type' => Propiedad::class,
                'source_id' => $propiedad->pk_propiedad,
                'created_by' => auth()->id(),
            ]);
        }

        if ($propiedad->mantenimiento_fecha_pago) {
            Task::create([
                'title' => 'Pagar mantenimiento: ' . $propiedad->alias,
                'description' => 'Pago de mantenimiento programado.',
                'due_date' => $propiedad->mantenimiento_fecha_pago,
                'status' => 'pending',
                'priority' => 'medium',
                'recurrence' => 'monthly',
                'next_run_date' => $propiedad->mantenimiento_fecha_pago,
                'source_type' => Propiedad::class,
                'source_id' => $propiedad->pk_propiedad,
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->route('clientes.show', $propiedad->fk_cliente)->with('success', 'Propiedad creada correctamente.');
    }

    public function show(Propiedad $propiedad)
    {
        $propiedad->load(['cliente','documentos','contratos.inquilino','tickets.creator','tickets.assignee']);
        return view('propiedades.show', compact('propiedad'));
    }

    public function edit(Propiedad $propiedad)
    {
        Gate::authorize('manage-records');
        $clientes = Cliente::orderBy('nombre')->get();
        return view('propiedades.edit', compact('propiedad', 'clientes'));
    }

    public function update(Request $request, Propiedad $propiedad)
    {
        Gate::authorize('manage-records');

        $request->validate([
            'fk_cliente' => 'required|exists:clientes,pk_cliente',
            'alias' => ['required','string','max:255',Rule::unique('propiedades','alias')->ignore($propiedad->pk_propiedad,'pk_propiedad')],
            'domicilio' => 'nullable|string|max:255',
            'siapa' => 'nullable|string|max:255',
            'cfe' => 'nullable|string|max:255',
            'predial' => 'nullable|string|max:255',
            'mantenimiento_banco' => 'nullable|string|max:255',
            'mantenimiento_cuenta' => 'nullable|string|max:255',
            'mantenimiento_monto' => 'nullable|numeric',
            'mantenimiento_fecha_pago' => 'nullable|date',
            'latitud' => 'nullable|string|max:255',
            'longitud' => 'nullable|string|max:255',
            'estatus_informacion' => 'required|string',
            'calle' => 'nullable|string',
            'numero_exterior' => 'nullable|string',
            'numero_interior' => 'nullable|string',
            'colonia' => 'nullable|string',
            'codigo_postal' => 'nullable|string',
            'municipio' => 'nullable|string',
            'estado' => 'nullable|string',
        ]);

        $propiedad->update($request->all());

        return redirect()->route('propiedades.index')->with('success', 'Propiedad actualizada correctamente.');
    }

    public function destroy(Propiedad $propiedad)
    {
        Gate::authorize('delete-anything');
        $propiedad->delete();
        return redirect()->route('propiedades.index')->with('success', 'Propiedad eliminada correctamente.');
    }

    public function mapa()
    {
        $propiedades = Propiedad::select('alias', 'latitud', 'longitud', 'domicilio')->get();
        return view('propiedades.mapa', compact('propiedades'));
    }
}
