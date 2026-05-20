<?php

namespace App\Http\Controllers;

use App\Models\Inquilino;
use Illuminate\Http\Request;

class InquilinoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('perPage', 15);
        if ($perPage < 5 || $perPage > 100) $perPage = 15;

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
        if (!in_array($sort, $sortable, true)) $sort = 'nombre';

        $query->orderBy($sort, $dir);

        $inquilinos = $query->paginate($perPage)->appends([
            'q' => $q,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
        ]);

        return view('inquilinos.index', compact('inquilinos', 'q', 'perPage', 'sort', 'dir'));
    }

    public function show(Inquilino $inquilino)
    {
        $inquilino->load(['contratos.propiedad', 'documentos']);

        return view('inquilinos.show', compact('inquilino'));
    }
}
