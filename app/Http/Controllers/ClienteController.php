<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Services\PerfilMovimientosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));

        $clientes = Cliente::query()
            ->when(Schema::hasColumn('clientes', 'deleted_at'), function ($query) {
                $query->whereNull('clientes.deleted_at');
            })
            ->withCount(['contratos' => function ($query) {
                if (Schema::hasColumn('contratos', 'deleted_at')) {
                    $query->whereNull('contratos.deleted_at');
                }
            }])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('rfc', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%")
                        ->orWhere('celular', 'like', "%{$search}%")
                        ->orWhere('fijo', 'like', "%{$search}%")
                        ->orWhere('domicilio', 'like', "%{$search}%")
                        ->orWhere('domicilio_notificaciones', 'like', "%{$search}%")
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
        Gate::authorize('manage-records');

        return view('clientes.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-records');

        $request->validate([
            'nombre' => 'required|string|max:255',
            'rfc' => 'nullable|string|max:13',
            'domicilio' => 'nullable|string',
            'domicilio_notificaciones' => 'nullable|string',
            'fijo' => 'nullable|string|regex:/^\+?[0-9 ]+$/',
            'celular' => 'nullable|string|regex:/^\+?[0-9 ]+$/',
            'correo' => 'nullable|email',
            'banco' => 'nullable|string',
            'cuenta' => 'nullable|string',
            'clabe' => 'nullable|string|max:18',
            'notas' => 'nullable|string',
        ]);

        Cliente::create($request->all());

        return redirect()->route('clientes.index')->with('success', 'Cliente creado exitosamente.');
    }

    public function show($id, Request $request, PerfilMovimientosService $movimientosService)
    {
        $cliente = Cliente::with([
            'propiedades.contratos.inquilino',
            'propiedades.documentos',
            'contratos.inquilino',
            'documentos',
        ])->findOrFail($id);
        $movimientosPerfil = $movimientosService->forCliente($cliente->pk_cliente, $request);

        return view('clientes.show', compact('cliente', 'movimientosPerfil'));
    }

    public function edit($id)
    {
        Gate::authorize('manage-records');

        $cliente = Cliente::findOrFail($id);
        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, $id)
    {
        Gate::authorize('manage-records');

        $request->validate([
            'nombre' => 'required|string|max:255',
            'rfc' => 'nullable|string|max:13',
            'domicilio' => 'nullable|string',
            'domicilio_notificaciones' => 'nullable|string',
            'fijo' => 'nullable|string|regex:/^\+?[0-9 ]+$/',
            'celular' => 'nullable|string|regex:/^\+?[0-9 ]+$/',
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

    public function destroy(Request $request, $id)
    {
        Gate::authorize('delete-anything');

        if (!Schema::hasColumn('clientes', 'deleted_at') || !Schema::hasColumn('contratos', 'deleted_at')) {
            return redirect()
                ->route('clientes.index')
                ->with('error', 'Falta ejecutar migraciones para habilitar archivado lógico. Ejecuta migrate antes de archivar clientes.');
        }

        $cliente = Cliente::withCount(['contratos' => function ($query) {
            $query->whereNull('contratos.deleted_at');
        }])->findOrFail($id);

        if ($cliente->contratos_count > 0 && !$request->boolean('archive_contracts')) {
            return redirect()
                ->route('clientes.index')
                ->with('error', 'El cliente tiene contratos asociados. Confirma que deseas archivar también sus contratos.');
        }

        DB::transaction(function () use ($cliente, $request) {
            $now = now();
            $contratos = Contrato::query()
                ->where('fk_cliente', $cliente->pk_cliente)
                ->whereNull('deleted_at')
                ->get();
            $clienteOldValues = $cliente->toArray();

            DB::table('contratos')
                ->where('fk_cliente', $cliente->pk_cliente)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            DB::table('clientes')
                ->where('pk_cliente', $cliente->pk_cliente)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            foreach ($contratos as $contrato) {
                $this->logArchive($request, $contrato, $contrato->toArray(), [
                    'deleted_at' => $now->toDateTimeString(),
                ]);
            }

            $this->logArchive($request, $cliente, $clienteOldValues, [
                'deleted_at' => $now->toDateTimeString(),
            ]);
        });

        return redirect()->route('clientes.index')->with('success', 'Cliente y contratos asociados archivados correctamente.');
    }

    private function logArchive(Request $request, $model, array $oldValues, array $newValues): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'archived',
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'module' => class_basename($model),
            'old_values' => ActivityLog::sanitizeValues($oldValues),
            'new_values' => ActivityLog::sanitizeValues($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
