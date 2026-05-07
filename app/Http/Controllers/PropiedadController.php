<?php

namespace App\Http\Controllers;

use App\Models\Propiedad;
use App\Models\Cliente;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class PropiedadController extends Controller
{
    public function index()
    {
        $propiedades = Propiedad::with('cliente')->get();
        return view('propiedades.index', compact('propiedades'));
    }

    public function create(Request $request)
    {
        $clientes = Cliente::orderBy('nombre')->get();
        $clientePreseleccionado = $request->get('cliente_id');

        return view('propiedades.create', compact('clientes', 'clientePreseleccionado'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fk_cliente' => 'required|exists:clientes,pk_cliente',
            'alias' => 'required|string|max:255',
            'domicilio' => 'nullable|string|max:255',
            'siapa' => 'nullable|string|max:255',
            'cfe' => 'nullable|string|max:255',
            'predial' => 'nullable|string|max:255',
            'mantenimiento_banco' => 'nullable|string|max:255',
            'mantenimiento_cuenta' => 'nullable|string|max:255',
            'mantenimiento_monto' => 'nullable|numeric',
            'latitud' => 'nullable|string|max:255',
            'longitud' => 'nullable|string|max:255',
            'estatus_informacion' => 'required|string',
        ]);

        $propiedad = Propiedad::create($request->all());

        return redirect()
            ->route('clientes.show', $propiedad->fk_cliente)
            ->with('success', 'Propiedad creada correctamente.');
    }

    public function show(Propiedad $propiedad)
    {
        $propiedad->load(['cliente', 'documentos', 'contratos.inquilino']);
        return view('propiedades.show', compact('propiedad'));
    }

    public function edit(Propiedad $propiedad)
    {
        $clientes = Cliente::orderBy('nombre')->get();
        return view('propiedades.edit', compact('propiedad', 'clientes'));
    }

    public function update(Request $request, Propiedad $propiedad)
    {
        $request->validate([
            'fk_cliente' => 'required|exists:clientes,pk_cliente',
            'alias' => 'required|string|max:255',
            'domicilio' => 'nullable|string|max:255',
            'siapa' => 'nullable|string|max:255',
            'cfe' => 'nullable|string|max:255',
            'predial' => 'nullable|string|max:255',
            'mantenimiento_banco' => 'nullable|string|max:255',
            'mantenimiento_cuenta' => 'nullable|string|max:255',
            'mantenimiento_monto' => 'nullable|numeric',
            'latitud' => 'nullable|string|max:255',
            'longitud' => 'nullable|string|max:255',
            'estatus_informacion' => 'required|string',
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
