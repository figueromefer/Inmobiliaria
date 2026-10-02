<?php

namespace App\Http\Controllers;

use App\Models\Inquilino;
use App\Services\PerfilMovimientosService;
use App\Services\MinimumDocumentChecklistService;
use Illuminate\Http\Request;

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
        $today = now()->toDateString();

        $activeContracts = function ($contracts) use ($today) {
            $contracts->where(function ($query) use ($today) {
                $query->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today);
            })->where(function ($query) use ($today) {
                $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today);
            });
        };

        $query = Inquilino::query()->with(['contratos' => function ($contracts) use ($activeContracts) {
            $activeContracts($contracts);
            $contracts->with('propiedad');
        }]);

        if ($q !== '') {
            $query->where(function ($w) use ($q, $activeContracts) {
                $w->where('nombre', 'like', "%{$q}%")
                    ->orWhere('correo', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%")
                    ->orWhere('domicilio', 'like', "%{$q}%")
                    ->orWhere('nacionalidad', 'like', "%{$q}%")
                    ->orWhereHas('contratos', function ($contracts) use ($q, $activeContracts) {
                        $activeContracts($contracts);
                        $contracts->whereHas('propiedad', function ($propiedad) use ($q) {
                            $propiedad->where('alias', 'like', "%{$q}%")
                                ->orWhere('domicilio', 'like', "%{$q}%");
                        });
                    });
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

    public function show(Inquilino $inquilino, Request $request, PerfilMovimientosService $movimientosService, MinimumDocumentChecklistService $checklists)
    {
        $inquilino->load([
            'contratos' => fn ($query) => $query->with(['cliente', 'propiedad.cliente'])->orderByRaw('fecha_fin IS NULL DESC')->orderByDesc('fecha_inicio')->orderByDesc('id'),
            'documentos',
        ]);
        $movimientosPerfil = $movimientosService->forInquilino($inquilino->id, $request);

        $documentChecklist = $checklists->for('inquilino', $inquilino->documentos);
        return view('inquilinos.show', compact('inquilino', 'movimientosPerfil', 'documentChecklist'));
    }
}
