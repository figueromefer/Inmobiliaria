<?php

namespace App\Http\Controllers;

use App\Models\Inquilino;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InquilinoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('perPage', 15);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 15;
        }

        $sort = $request->query('sort', 'nombre');
        $dir = strtolower($request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Inquilino::query();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                    ->orWhere('correo', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%")
                    ->orWhere('domicilio', 'like', "%{$q}%")
                    ->orWhere('nacionalidad', 'like', "%{$q}%");
            });
        }

        $sortable = ['id', 'nombre', 'correo', 'created_at'];
        if (!in_array($sort, $sortable, true)) {
            $sort = 'nombre';
        }

        $query->orderBy($sort, $dir);

        $inquilinos = $query->paginate($perPage)->appends([
            'q' => $q,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
        ]);

        return view('inquilinos.index', compact('inquilinos', 'q', 'perPage', 'sort', 'dir'));
    }

    public function create()
    {
        Gate::authorize('manage-records');

        return view('inquilinos.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-records');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'nacionalidad' => 'nullable|string|max:255',
            'domicilio' => 'nullable|string',
            'telefono' => 'nullable|string|max:100',
            'correo' => 'nullable|email|max:255',
        ]);

        $inquilino = Inquilino::create($data);

        return redirect()->route('inquilinos.show', $inquilino)->with('success', 'Inquilino creado correctamente.');
    }

    public function show(Inquilino $inquilino)
    {
        $inquilino->load(['contratos.propiedad', 'documentos']);

        return view('inquilinos.show', compact('inquilino'));
    }

    public function edit(Inquilino $inquilino)
    {
        Gate::authorize('manage-records');

        return view('inquilinos.edit', compact('inquilino'));
    }

    public function update(Request $request, Inquilino $inquilino)
    {
        Gate::authorize('manage-records');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'nacionalidad' => 'nullable|string|max:255',
            'domicilio' => 'nullable|string',
            'telefono' => 'nullable|string|max:100',
            'correo' => 'nullable|email|max:255',
        ]);

        $inquilino->update($data);

        return redirect()->route('inquilinos.show', $inquilino)->with('success', 'Inquilino actualizado correctamente.');
    }

    public function destroy(Inquilino $inquilino)
    {
        Gate::authorize('delete-anything');

        $inquilino->delete();

        return redirect()->route('inquilinos.index')->with('success', 'Inquilino eliminado correctamente.');
    }
}
