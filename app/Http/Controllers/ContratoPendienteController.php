<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratoPendienteController extends Controller
{
    public function index()
    {
        $pendientes = ContratoPendiente::with([
            'cliente',
            'propiedad',
            'inquilino',
            'contrato',
        ])
        ->latest()
        ->paginate(20);

        return view('contratos.pendientes.index', compact('pendientes'));
    }

    public function show(ContratoPendiente $pendiente)
    {
        $clientes = Cliente::orderBy('nombre')->get(['pk_cliente', 'nombre', 'correo', 'rfc']);
        $propiedades = Propiedad::orderBy('alias')->orderBy('domicilio')->get(['pk_propiedad', 'fk_cliente', 'alias', 'domicilio']);
        $inquilinos = Inquilino::orderBy('nombre')->get(['id', 'nombre', 'correo', 'telefono']);

        return view('contratos.pendientes.show', compact('pendiente', 'clientes', 'propiedades', 'inquilinos'));
    }

    public function resolver(Request $request, ContratoPendiente $pendiente)
    {
        if ($pendiente->estado !== 'pendiente_match') {
            return redirect()
                ->route('contratos.pendientes.show', $pendiente)
                ->with('error', 'Este contrato pendiente ya no está disponible para resolver.');
        }

        $validated = $request->validate([
            'cliente_action' => ['required', 'in:existing,new'],
            'fk_cliente' => ['nullable', 'integer', 'exists:clientes,pk_cliente', 'required_if:cliente_action,existing'],
            'propiedad_action' => ['required', 'in:existing,new'],
            'fk_propiedad' => ['nullable', 'integer', 'exists:propiedades,pk_propiedad', 'required_if:propiedad_action,existing'],
            'inquilino_action' => ['required', 'in:existing,new'],
            'inquilino_id' => ['nullable', 'integer', 'exists:inquilinos,id', 'required_if:inquilino_action,existing'],
        ]);

        $mapped = $pendiente->mapped_payload ?? [];

        $contrato = DB::transaction(function () use ($validated, $mapped, $pendiente) {
            if ($validated['cliente_action'] === 'existing') {
                $cliente = Cliente::findOrFail($validated['fk_cliente']);
            } else {
                $cliente = Cliente::create([
                    'nombre' => $mapped['nombre_solicitante'] ?? 'Cliente sin nombre',
                    'rfc' => $mapped['rfc_solicitante'] ?? null,
                    'correo' => $mapped['correo_solicitante'] ?? null,
                    'domicilio' => $mapped['domicilio_solicitante'] ?? null,
                    'notas' => 'Cliente creado desde contrato pendiente #'.$pendiente->id.'. Información pendiente de completar.',
                ]);

                $this->crearTareaCompletarInformacion('cliente', $cliente->pk_cliente, 'Completar información del cliente: '.$cliente->nombre, $pendiente->id);
            }

            if ($validated['propiedad_action'] === 'existing') {
                $propiedad = Propiedad::where('pk_propiedad', $validated['fk_propiedad'])
                    ->where('fk_cliente', $cliente->pk_cliente)
                    ->firstOrFail();
            } else {
                $propiedad = Propiedad::create([
                    'fk_cliente' => $cliente->pk_cliente,
                    'alias' => $mapped['domicilio_inmueble_arrendamiento'] ?? 'Propiedad pendiente',
                    'domicilio' => $mapped['domicilio_inmueble_arrendamiento'] ?? null,
                    'estatus_informacion' => 'pendiente_completar',
                ]);

                $this->crearTareaCompletarInformacion('propiedad', $propiedad->pk_propiedad, 'Completar información de la propiedad: '.($propiedad->alias ?: 'Propiedad #'.$propiedad->pk_propiedad), $pendiente->id);
            }

            if ($validated['inquilino_action'] === 'existing') {
                $inquilino = Inquilino::findOrFail($validated['inquilino_id']);
            } else {
                $inquilino = null;
                if (!empty($mapped['nombre_complementaria'])) {
                    $inquilino = Inquilino::create([
                        'nombre' => $mapped['nombre_complementaria'],
                        'nacionalidad' => $mapped['nacionalidad_complementaria'] ?? null,
                        'domicilio' => $mapped['domicilio_complementaria'] ?? null,
                        'telefono' => $mapped['telefono_complementaria'] ?? null,
                        'correo' => $mapped['correo_complementaria'] ?? null,
                    ]);
                }
            }

            $contrato = Contrato::create([
                'fk_cliente' => $cliente->pk_cliente,
                'fk_propiedad' => $propiedad->pk_propiedad,
                'tipo_solicitante' => $mapped['tipo_solicitante'] ?? null,
                'tipo_complementaria' => $mapped['tipo_complementaria'] ?? null,
                'fecha' => now(),
                'inquilino_id' => $inquilino?->id,
                'domicilio_inmueble' => $mapped['domicilio_inmueble_arrendamiento'] ?? null,
                'fecha_inicio' => $mapped['fecha_inicio_contrato'] ?? null,
                'fecha_fin' => $mapped['fecha_terminacion_contrato'] ?? null,
                'dias_pago' => $mapped['dias_pago'] ?? null,
                'monto_total' => $mapped['monto_total'] ?? null,
                'monto_mensual' => $mapped['monto_mensual'] ?? null,
                'monto_deposito' => $mapped['monto_deposito'] ?? null,
                'origen' => $pendiente->origen,
                'expediente_justicia_alternativa' => $pendiente->origen === 'justicia_alternativa' ? $pendiente->expediente : null,
                'imported_at' => now(),
                'raw_justicia_alternativa' => $pendiente->raw_payload,
            ]);

            $pendiente->update([
                'estado' => 'importado',
                'matched_cliente_id' => $cliente->pk_cliente,
                'matched_propiedad_id' => $propiedad->pk_propiedad,
                'matched_inquilino_id' => $inquilino?->id,
                'contrato_id' => $contrato->id,
                'processed_at' => now(),
            ]);

            return $contrato;
        });

        return redirect()
            ->route('contratos.index')
            ->with('success', 'Contrato pendiente resuelto correctamente. Contrato #'.$contrato->id.'.');
    }

    private function crearTareaCompletarInformacion(string $sourceType, int $sourceId, string $title, int $pendienteId): void
    {
        Task::create([
            'title' => $title,
            'description' => 'Registro creado desde contrato pendiente #'.$pendienteId.'. Completar y validar la información faltante.',
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
            'priority' => 'medium',
            'task_type' => 'complete_information',
            'period_key' => 'contrato-pendiente-'.$pendienteId.'-'.$sourceType.'-'.$sourceId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => auth()->id(),
        ]);
    }
}
