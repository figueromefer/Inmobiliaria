<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArchivadoController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->archiveColumnsExist()) {
            return view('archivados.index', [
                'clientesArchivados' => collect(),
                'contratosArchivados' => collect(),
                'q' => trim((string) $request->query('q', '')),
                'missingArchiveColumns' => true,
            ]);
        }

        $q = trim((string) $request->query('q', ''));

        $clientesArchivados = Cliente::query()
            ->whereNotNull('deleted_at')
            ->withCount(['contratos as contratos_archivados_count' => function ($query) {
                $query->whereNotNull('deleted_at');
            }])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nombre', 'like', "%{$q}%")
                        ->orWhere('correo', 'like', "%{$q}%")
                        ->orWhere('celular', 'like', "%{$q}%")
                        ->orWhere('fijo', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate(10, ['*'], 'clientes_page')
            ->withQueryString();

        $contratosArchivados = Contrato::query()
            ->with(['cliente', 'propiedad', 'inquilino'])
            ->whereNotNull('deleted_at')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->whereHas('cliente', fn ($cliente) => $cliente->where('nombre', 'like', "%{$q}%"))
                        ->orWhereHas('propiedad', fn ($propiedad) => $propiedad
                            ->where('alias', 'like', "%{$q}%")
                            ->orWhere('domicilio', 'like', "%{$q}%"))
                        ->orWhereHas('inquilino', fn ($inquilino) => $inquilino->where('nombre', 'like', "%{$q}%"))
                        ->orWhere('domicilio_inmueble', 'like', "%{$q}%")
                        ->orWhere('expediente_justicia_alternativa', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate(10, ['*'], 'contratos_page')
            ->withQueryString();

        return view('archivados.index', compact('clientesArchivados', 'contratosArchivados', 'q') + [
            'missingArchiveColumns' => false,
        ]);
    }

    public function restoreCliente(Request $request, Cliente $cliente)
    {
        if (! Schema::hasColumn('clientes', 'deleted_at')) {
            return back()->with('error', 'La tabla clientes no tiene columna deleted_at.');
        }

        if (blank($cliente->deleted_at)) {
            return back()->with('info', 'El cliente ya estaba activo.');
        }

        $oldValues = $cliente->toArray();

        DB::table('clientes')
            ->where('pk_cliente', $cliente->pk_cliente)
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        $this->logRestored($request, $cliente, $oldValues, ['deleted_at' => null]);

        return redirect()
            ->route('archivados.index')
            ->with('success', 'Cliente desarchivado correctamente. Sus contratos archivados permanecen archivados hasta restaurarlos de forma individual.');
    }

    public function restoreContrato(Request $request, Contrato $contrato)
    {
        if (! $this->archiveColumnsExist()) {
            return back()->with('error', 'Faltan columnas deleted_at para restaurar contratos.');
        }

        if (blank($contrato->deleted_at)) {
            return back()->with('info', 'El contrato ya estaba activo.');
        }

        $clienteArchivado = $contrato->cliente && ! blank($contrato->cliente->deleted_at);

        if ($clienteArchivado) {
            return back()->with('error', 'No se puede desarchivar el contrato porque su cliente sigue archivado. Desarchiva primero el cliente.');
        }

        $oldValues = $contrato->toArray();

        DB::table('contratos')
            ->where('id', $contrato->id)
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        $this->logRestored($request, $contrato, $oldValues, ['deleted_at' => null]);

        return redirect()
            ->route('archivados.index')
            ->with('success', 'Contrato desarchivado correctamente.');
    }

    private function archiveColumnsExist(): bool
    {
        return Schema::hasColumn('clientes', 'deleted_at')
            && Schema::hasColumn('contratos', 'deleted_at');
    }

    private function logRestored(Request $request, $model, array $oldValues, array $newValues): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'restored',
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
