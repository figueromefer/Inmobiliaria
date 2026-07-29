<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\Task;
use App\Services\GeocodingService;
use App\Services\JusticiaAlternativaImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContratoPendienteController extends Controller
{
    private const MAX_DOMICILIO_LENGTH = 2000;

    public function index()
    {
        $pendientes = ContratoPendiente::pendientes()
            ->with([
                'cliente',
                'propiedad',
                'inquilino',
                'contrato',
            ])
            ->latest()
            ->paginate(20);

        return view('contratos.pendientes.index', compact('pendientes'));
    }

    public function show(ContratoPendiente $pendiente, JusticiaAlternativaImportService $service)
    {
        return $this->showResolveForm($pendiente, $service);
    }

    public function showResolveForm(ContratoPendiente $pendiente, JusticiaAlternativaImportService $service)
    {
        $mapped = $this->previewJusticiaAlternativaMapping($pendiente, $service);

        $clientes = Cliente::whereNull('deleted_at')->orderBy('nombre')->get(['pk_cliente', 'nombre', 'correo', 'rfc']);
        $propiedades = Propiedad::orderBy('alias')->orderBy('domicilio')->get(['pk_propiedad', 'fk_cliente', 'alias', 'domicilio']);
        $inquilinos = Inquilino::orderBy('nombre')->get(['id', 'nombre', 'correo', 'telefono']);
        $suggestions = $this->buildSuggestions($mapped);

        return view('contratos.pendientes.show', compact('pendiente', 'mapped', 'clientes', 'propiedades', 'inquilinos', 'suggestions'));
    }

    public function destroy(ContratoPendiente $pendiente)
    {
        if ($pendiente->estado !== 'pendiente_match') {
            return redirect()
                ->route('contratos.pendientes.index')
                ->with('error', 'Solo se pueden eliminar pendientes activos.');
        }

        $pendiente->delete();

        return redirect()
            ->route('contratos.pendientes.index')
            ->with('success', 'Contrato pendiente eliminado correctamente.');
    }

    public function resolver(Request $request, ContratoPendiente $pendiente, JusticiaAlternativaImportService $service, GeocodingService $geocodingService)
    {
        if ($pendiente->estado !== 'pendiente_match') {
            return redirect()
                ->route('contratos.pendientes.index')
                ->with('error', 'Este contrato pendiente ya fue resuelto o ya no está disponible.');
        }

        $validated = $request->validate([
            'cliente_action' => ['required', 'in:existing,new'],
            'fk_cliente' => [
                'nullable',
                'integer',
                Rule::exists('clientes', 'pk_cliente')->whereNull('deleted_at'),
                'required_if:cliente_action,existing',
            ],
            'propiedad_action' => ['required', 'in:existing,new'],
            'fk_propiedad' => [
                'nullable',
                'integer',
                Rule::exists('propiedades', 'pk_propiedad')->whereNull('deleted_at'),
                'required_if:propiedad_action,existing',
            ],
            'propiedad_alias' => ['nullable', 'string', 'max:255', Rule::unique('propiedades', 'alias'), 'required_if:propiedad_action,new'],
            'inquilino_action' => ['required', 'in:existing,new'],
            'inquilino_id' => ['nullable', 'integer', 'exists:inquilinos,id', 'required_if:inquilino_action,existing'],
        ]);

        $mappingError = $this->refreshJusticiaAlternativaMapping($pendiente, $service);

        if ($mappingError) {
            return back()->with('error', $mappingError);
        }

        $mapped = $pendiente->mapped_payload ?? [];

        if ($this->domicilioImportadoExceedsLimit($mapped)) {
            return back()
                ->withInput()
                ->withErrors([
                    'domicilio_inmueble_arrendamiento' => 'El domicilio del inmueble no puede exceder '.self::MAX_DOMICILIO_LENGTH.' caracteres.',
                ]);
        }

        $geocodedCoordinates = null;

        if ($validated['propiedad_action'] === 'new' && $pendiente->origen === 'justicia_alternativa') {
            $geocodedCoordinates = $geocodingService->geocode($mapped['domicilio_inmueble_arrendamiento'] ?? null);
        }

        [$contrato, $inquilinoWarning] = DB::transaction(function () use ($validated, $mapped, $pendiente, $geocodedCoordinates) {
            if ($validated['cliente_action'] === 'existing') {
                $cliente = Cliente::whereNull('deleted_at')->findOrFail($validated['fk_cliente']);
            } else {
                $cliente = Cliente::create([
                    'nombre' => $mapped['nombre_solicitante'] ?? 'Cliente sin nombre',
                    'rfc' => $mapped['rfc_solicitante'] ?? '',
                    'correo' => $mapped['correo_solicitante'] ?? '',
                    'telefono' => $mapped['telefono_solicitante'] ?? '',
                    'domicilio' => $mapped['domicilio_solicitante'] ?? '',
                    'notas' => 'Cliente creado desde contrato pendiente #'.$pendiente->id.'. Información pendiente de completar.',
                ]);

                $this->crearTareaCompletarInformacion('cliente', $cliente->pk_cliente, 'Completar información del cliente: '.$cliente->nombre, $pendiente->id);
            }

            if ($validated['propiedad_action'] === 'existing') {
                $propiedad = Propiedad::where('pk_propiedad', $validated['fk_propiedad'])
                    ->where('fk_cliente', $cliente->pk_cliente)
                    ->firstOrFail();
            } else {
                $domicilioPropiedad = $mapped['propiedad_domicilio'] ?? $mapped['domicilio_inmueble_arrendamiento'] ?? '';
                $propiedadData = [
                    'fk_cliente' => $cliente->pk_cliente,
                    'alias' => $validated['propiedad_alias'],
                    'domicilio' => $domicilioPropiedad,
                    'estatus_informacion' => 'pendiente_completar',
                ];

                if ($geocodedCoordinates) {
                    $propiedadData['latitud'] = $geocodedCoordinates['latitud'];
                    $propiedadData['longitud'] = $geocodedCoordinates['longitud'];
                }

                $propiedad = Propiedad::create($propiedadData);

                $this->crearTareaCompletarInformacion(
                    'propiedad',
                    $propiedad->pk_propiedad,
                    'Propiedad: '.($propiedad->alias ?: 'Propiedad #'.$propiedad->pk_propiedad).'. Domicilio: '.($domicilioPropiedad ?: 'sin domicilio'),
                    $pendiente->id
                );
            }

            $inquilinoWarning = null;

            if ($validated['inquilino_action'] === 'existing') {
                $inquilino = Inquilino::findOrFail($validated['inquilino_id']);
                $this->updateInquilinoMissingData($inquilino, $mapped);
            } else {
                [$inquilino, $inquilinoWarning] = $this->resolveImportedInquilino($mapped);

                if ($inquilino) {
                    $this->updateInquilinoMissingData($inquilino, $mapped);
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

            return [$contrato, $inquilinoWarning];
        });

        $redirect = redirect()
            ->route('contratos.index')
            ->with('success', 'Contrato pendiente resuelto correctamente. Contrato #'.$contrato->id.'.');

        if (!empty($inquilinoWarning)) {
            $redirect->with('warning', $inquilinoWarning);
        }

        return $redirect;
    }

    private function domicilioImportadoExceedsLimit(array $mapped): bool
    {
        $domicilios = [
            $mapped['domicilio_inmueble_arrendamiento'] ?? null,
            $mapped['propiedad_domicilio'] ?? null,
        ];

        foreach ($domicilios as $domicilio) {
            if ($domicilio !== null && mb_strlen((string) $domicilio) > self::MAX_DOMICILIO_LENGTH) {
                return true;
            }
        }

        return false;
    }

    private function resolveImportedInquilino(array $mapped): array
    {
        if (empty($mapped['nombre_complementaria']) && empty($mapped['correo_complementaria']) && empty($mapped['telefono_complementaria'])) {
            return [null, 'El contrato se importó sin inquilino porque Justicia Alternativa no envió datos de la Parte Complementaria.'];
        }

        $existing = $this->findReusableInquilino($mapped);

        if ($existing) {
            return [$existing, null];
        }

        if (empty($mapped['nombre_complementaria'])) {
            return [null, 'El contrato se importó sin crear inquilino porque la Parte Complementaria no tiene nombre.'];
        }

        return [Inquilino::create([
            'nombre' => $mapped['nombre_complementaria'],
            'nacionalidad' => $mapped['nacionalidad_complementaria'] ?? null,
            'domicilio' => $mapped['domicilio_complementaria'] ?? null,
            'telefono' => $mapped['telefono_complementaria'] ?? null,
            'correo' => $mapped['correo_complementaria'] ?? null,
        ]), null];
    }

    private function findReusableInquilino(array $mapped): ?Inquilino
    {
        $correo = $this->normalizeEmail($mapped['correo_complementaria'] ?? null);

        if ($correo !== '') {
            $inquilino = Inquilino::all()->first(fn ($inquilino) => $this->normalizeEmail($inquilino->correo) === $correo);

            if ($inquilino) {
                return $inquilino;
            }
        }

        $telefono = $this->normalizePhone($mapped['telefono_complementaria'] ?? null);

        if ($telefono !== '') {
            $inquilino = Inquilino::all()->first(fn ($inquilino) => $this->normalizePhone($inquilino->telefono) === $telefono);

            if ($inquilino) {
                return $inquilino;
            }
        }

        $nombre = $this->normalizeForMatch($mapped['nombre_complementaria'] ?? null);

        if ($nombre !== '') {
            return Inquilino::all()->first(fn ($inquilino) => $this->normalizeForMatch($inquilino->nombre) === $nombre);
        }

        return null;
    }

    private function updateInquilinoMissingData(Inquilino $inquilino, array $mapped): void
    {
        $updates = [];
        $fieldMap = [
            'nacionalidad' => 'nacionalidad_complementaria',
            'domicilio' => 'domicilio_complementaria',
            'telefono' => 'telefono_complementaria',
            'correo' => 'correo_complementaria',
        ];

        foreach ($fieldMap as $field => $mappedKey) {
            if (blank($inquilino->{$field}) && !blank($mapped[$mappedKey] ?? null)) {
                $updates[$field] = $mapped[$mappedKey];
            }
        }

        if ($updates) {
            $inquilino->update($updates);
        }
    }

    private function refreshJusticiaAlternativaMapping(ContratoPendiente $pendiente, JusticiaAlternativaImportService $service): ?string
    {
        if ($pendiente->origen !== 'justicia_alternativa' || !is_array($pendiente->raw_payload)) {
            return null;
        }

        $mapped = $service->mapPayload($pendiente->raw_payload);

        if ($service->hasComplementariaMappingMismatch($pendiente->raw_payload, $mapped)) {
            return 'El mapeo de Justicia Alternativa asignó la Parte Solicitante como Parte Complementaria. Revisa los encabezados del Google Sheet antes de importar.';
        }

        if (($pendiente->mapped_payload ?? []) !== $mapped) {
            $pendiente->update(['mapped_payload' => $mapped]);
            $pendiente->refresh();
        }

        return null;
    }

    private function previewJusticiaAlternativaMapping(ContratoPendiente $pendiente, JusticiaAlternativaImportService $service): array
    {
        if ($pendiente->origen !== 'justicia_alternativa' || !is_array($pendiente->raw_payload)) {
            return $pendiente->mapped_payload ?? [];
        }

        $mapped = $service->mapPayload($pendiente->raw_payload);

        if ($service->hasComplementariaMappingMismatch($pendiente->raw_payload, $mapped)) {
            return $pendiente->mapped_payload ?? [];
        }

        return $mapped;
    }

    private function buildSuggestions(array $mapped): array
    {
        $cliente = $this->suggestCliente($mapped);
        $propiedad = $this->suggestPropiedad($mapped, $cliente['model'] ?? null);
        $inquilino = $this->suggestInquilino($mapped);

        return compact('cliente', 'propiedad', 'inquilino');
    }

    private function suggestCliente(array $mapped): array
    {
        if (!empty($mapped['rfc_solicitante'])) {
            $rfc = $this->normalizeCode($mapped['rfc_solicitante']);
            $cliente = Cliente::whereNull('deleted_at')->get()->first(fn ($cliente) => $rfc !== '' && $this->normalizeCode($cliente->rfc) === $rfc);
            if ($cliente) return $this->suggestion($cliente, 'alta', 'RFC exacto', 100);
        }

        $correo = $this->normalizeEmail($mapped['correo_solicitante'] ?? null);
        if ($correo !== '') {
            $cliente = Cliente::whereNull('deleted_at')->get()->first(fn ($cliente) => $this->normalizeEmail($cliente->correo) === $correo);
            if ($cliente) return $this->suggestion($cliente, 'alta', 'Correo exacto', 100);
        }

        $candidate = $this->bestTextMatch(
            Cliente::whereNull('deleted_at')->get(),
            $mapped['nombre_solicitante'] ?? null,
            fn ($cliente) => [$cliente->nombre]
        );

        if ($candidate && $candidate['score'] >= 92) {
            return $this->suggestion($candidate['model'], 'alta', 'Nombre casi exacto', $candidate['score']);
        }

        if ($candidate && $candidate['score'] >= 78) {
            return $this->suggestion($candidate['model'], 'media', 'Nombre parecido', $candidate['score']);
        }

        return $this->suggestion(null, 'ninguna', 'Sin coincidencia', 0);
    }

    private function suggestPropiedad(array $mapped, ?Cliente $cliente): array
    {
        if (!$cliente) {
            return $this->suggestion(null, 'ninguna', 'Sin cliente sugerido para comparar propiedades', 0);
        }

        $props = Propiedad::where('fk_cliente', $cliente->pk_cliente)->get();

        if (!empty($mapped['propiedad_alias'])) {
            $alias = $this->normalizeForMatch($mapped['propiedad_alias']);
            $propiedad = $props->first(fn ($propiedad) => $alias !== '' && $this->normalizeForMatch($propiedad->alias) === $alias);
            if ($propiedad) return $this->suggestion($propiedad, 'alta', 'Alias exacto dentro del cliente', 100);
        }

        if (!empty($mapped['domicilio_inmueble_arrendamiento'])) {
            $dom = $this->normalizeForMatch($mapped['domicilio_inmueble_arrendamiento']);
            $propiedad = $props->first(fn ($propiedad) => $dom !== '' && $this->normalizeForMatch($propiedad->domicilio) === $dom);
            if ($propiedad) return $this->suggestion($propiedad, 'alta', 'Domicilio exacto dentro del cliente', 100);
        }

        $candidate = $this->bestTextMatch(
            $props,
            $mapped['domicilio_inmueble_arrendamiento'] ?? $mapped['propiedad_alias'] ?? null,
            fn ($propiedad) => [$propiedad->alias, $propiedad->domicilio]
        );

        if ($candidate && $candidate['score'] >= 90) {
            return $this->suggestion($candidate['model'], 'alta', 'Propiedad casi exacta dentro del cliente', $candidate['score']);
        }

        if ($candidate && $candidate['score'] >= 76) {
            return $this->suggestion($candidate['model'], 'media', 'Domicilio/alias parecido dentro del cliente', $candidate['score']);
        }

        return $this->suggestion(null, 'ninguna', 'Sin coincidencia de propiedad para el cliente sugerido', 0);
    }

    private function suggestInquilino(array $mapped): array
    {
        $correo = $this->normalizeEmail($mapped['correo_complementaria'] ?? null);
        if ($correo !== '') {
            $inquilino = Inquilino::all()->first(fn ($inquilino) => $this->normalizeEmail($inquilino->correo) === $correo);
            if ($inquilino) return $this->suggestion($inquilino, 'alta', 'Correo exacto', 100);
        }

        if (!empty($mapped['telefono_complementaria'])) {
            $telefono = $this->normalizePhone($mapped['telefono_complementaria']);
            $inquilino = Inquilino::all()->first(fn ($inquilino) => $telefono !== '' && $this->normalizePhone($inquilino->telefono) === $telefono);
            if ($inquilino) return $this->suggestion($inquilino, 'alta', 'Teléfono exacto', 100);
        }

        $candidate = $this->bestTextMatch(
            Inquilino::all(),
            $mapped['nombre_complementaria'] ?? null,
            fn ($inquilino) => [$inquilino->nombre]
        );

        if ($candidate && $candidate['score'] >= 92) {
            return $this->suggestion($candidate['model'], 'alta', 'Nombre casi exacto', $candidate['score']);
        }

        if ($candidate && $candidate['score'] >= 78) {
            return $this->suggestion($candidate['model'], 'media', 'Nombre parecido', $candidate['score']);
        }

        return $this->suggestion(null, 'ninguna', 'Sin coincidencia', 0);
    }

    private function bestTextMatch($collection, $needle, callable $fields): ?array
    {
        $needle = $this->normalizeForMatch($needle);
        if ($needle === '') return null;

        $best = null;

        foreach ($collection as $model) {
            foreach ($fields($model) as $field) {
                $haystack = $this->normalizeForMatch($field);
                if ($haystack === '') continue;

                similar_text($needle, $haystack, $percent);

                if (Str::contains($haystack, $needle) || Str::contains($needle, $haystack)) {
                    $percent = max($percent, min(strlen($needle), strlen($haystack)) / max(strlen($needle), strlen($haystack)) * 100);
                }

                if (!$best || $percent > $best['score']) {
                    $best = [
                        'model' => $model,
                        'score' => round($percent, 2),
                    ];
                }
            }
        }

        return $best;
    }

    private function suggestion($model, string $confidence, string $reason, float $score): array
    {
        return [
            'model' => $model,
            'confidence' => $confidence,
            'reason' => $score > 0 ? $reason.' · '.$score.'%' : $reason,
            'score' => $score,
        ];
    }

    private function normalizeForMatch($value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function normalizeEmail($value): string
    {
        $email = strtolower(trim((string) $value));

        if (in_array($email, ['', '-', '--', '---', 'n/a', 'na', 'no aplica', 'sin correo', 'sin email'], true)) {
            return '';
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function normalizeCode($value): string
    {
        return Str::of((string) $value)
            ->upper()
            ->ascii()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->toString();
    }

    private function normalizePhone($value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    private function crearTareaCompletarInformacion(string $sourceType, int $sourceId, string $title, int $pendienteId): void
    {
        $label = match ($sourceType) {
            'cliente' => 'cliente',
            'propiedad' => 'propiedad',
            default => 'registro',
        };
        $taskTitle = Str::limit('Revisar '.$label.' de contrato pendiente #'.$pendienteId, 255, '');

        Task::create([
            'title' => $taskTitle,
            'description' => 'Registro creado desde contrato pendiente #'.$pendienteId.'. Completar y validar la información faltante. '.$title,
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
