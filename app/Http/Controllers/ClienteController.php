<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));

        $clientes = Cliente::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('rfc', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%")
                        ->orWhere('celular', 'like', "%{$search}%")
                        ->orWhere('fijo', 'like', "%{$search}%")
                        ->orWhere('domicilio', 'like', "%{$search}%")
                        ->orWhere('notas', 'like', "%{$search}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('clientes.index', compact('clientes', 'search'));
    }

    public function create()
    {
        return view('clientes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'rfc' => 'nullable|string|max:13',
            'domicilio' => 'nullable|string',
            'fijo' => 'nullable|string',
            'celular' => 'nullable|string',
            'correo' => 'nullable|email',
            'banco' => 'nullable|string',
            'cuenta' => 'nullable|string',
            'clabe' => 'nullable|string|max:18',
            'notas' => 'nullable|string',
        ]);

        Cliente::create($request->all());

        return redirect()->route('clientes.index')->with('success', 'Cliente creado exitosamente.');
    }

    public function show($id)
{
    $cliente = Cliente::with([
        'propiedades.contratos.inquilino',
        'propiedades.documentos',
        'contratos.inquilino',
        'documentos',
    ])->findOrFail($id);

    return view('clientes.show', compact('cliente'));
}

    public function edit($id)
    {
        $cliente = Cliente::findOrFail($id);
        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'rfc' => 'nullable|string|max:13',
            'domicilio' => 'nullable|string',
            'fijo' => 'nullable|string',
            'celular' => 'nullable|string',
            'correo' => 'nullable|email',
            'banco' => 'nullable|string',
            'cuenta' => 'nullable|string',
            'clabe' => 'nullable|string|max:18',
            'notas' => 'nullable|string',
        ]);

        $cliente = Cliente::findOrFail($id);
        $cliente->update($request->all());

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy($id)
    {
        Gate::authorize('delete-anything');

        $cliente = Cliente::findOrFail($id);
        $cliente->delete();

        return redirect()->route('clientes.index')->with('success', 'Cliente eliminado exitosamente.');
    }
}
