<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractDraftPublishedException;
use App\Exceptions\ContractDraftVersionConflictException;
use App\Models\ContractDraft;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use App\Services\ContractDocumentPayloadBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated adapter for the shared contract-capture renderer.
 *
 * Public requests deliberately keep their own controller, token checks and
 * anti-abuse controls.  This adapter only supplies authenticated routes for
 * an existing internal draft; both adapters persist the same canonical DTO.
 */
class PrivateContractWizardController extends Controller
{
    private const STEPS = [
        'generales' => 'Datos generales', 'arrendador' => 'Arrendador', 'arrendatario' => 'Arrendatario',
        'tercero' => 'Tercero / fiador', 'garantia' => 'Garantía', 'vigencia' => 'Vigencia e importes',
        'uso' => 'Uso del inmueble', 'pago' => 'Forma de pago', 'mantenimiento' => 'Mantenimiento',
        'renovacion' => 'Renovación', 'resumen' => 'Resumen',
    ];

    public function show(ContractDraft $draft, string $step, ContractDocumentPayloadBuilder $documents)
    {
        if ($draft->status === ContractDraft::STATUS_PUBLISHED && $draft->contrato_id) {
            return redirect()->route('contratos.show', $draft->contrato_id);
        }

        $this->assertEditableInternalDraft($draft);
        $this->assertStep($step);
        $draft->load('currentVersion');

        $plan = $documents->build($draft->currentVersion);
        $missing = $plan['missing_fields'];
        if (! $draft->cliente_id) {
            $missing[] = 'arrendador: conciliación pendiente';
        }
        if (! $draft->propiedad_id) {
            $missing[] = 'propiedad: conciliación pendiente';
        }

        return view('contrato.public_wizard', [
            'draft' => $draft,
            'step' => $step,
            'steps' => self::STEPS,
            'payload' => $draft->currentVersion->canonical_payload,
            'wizardContext' => 'internal',
            'generation' => [
                'is_revision' => $draft->purpose === 'revision',
                'is_renewal' => $draft->purpose === 'renewal',
                'ready' => $plan['status'] === 'ready' && $missing === [],
                'requires_review' => $plan['status'] === 'requires_review',
                'missing_fields' => $missing,
                'reasons' => $plan['reasons'],
                'capture_step' => $this->captureStepFor($missing),
            ],
        ]);
    }

    public function save(
        Request $request,
        ContractDraft $draft,
        string $step,
        ContractDraftPayload $payloads,
        ContractDraftVersioningService $versioning,
    ): RedirectResponse {
        $this->assertEditableInternalDraft($draft);
        $this->assertStep($step);
        abort_if($step === 'resumen', 405);

        $data = $request->validate([
            'payload' => ['required', 'array'],
            'expected_version_id' => ['required', 'integer', 'min:1'],
            'next' => ['nullable', 'boolean'],
            'exit' => ['nullable', 'boolean'],
        ]);

        try {
            $versioning->appendVersionFromExpected(
                $draft,
                (int) $data['expected_version_id'],
                fn (array $current): array => $payloads->validateAndNormalize(
                    $payloads->mergeForEditing($current, $data['payload']),
                ),
                null,
                ContractDraftPayload::SCHEMA_VERSION,
                'private_wizard_saved:'.$step,
                $request->user()->id,
            );
        } catch (ContractDraftVersionConflictException|ContractDraftPublishedException) {
            throw ValidationException::withMessages([
                'expected_version_id' => 'El borrador fue actualizado por otro usuario o ya fue publicado. Recarga este paso antes de guardar.',
            ]);
        }

        if ($request->boolean('exit')) {
            return redirect()->route('contratos.borradores.show', $draft)
                ->with('success', 'Paso guardado como una nueva versión. El borrador permanece sin publicar.');
        }

        $target = $request->boolean('next') ? $this->nextStep($step) : $step;

        return redirect()->route('contratos.privados.wizard.show', [$draft, $target])
            ->with('success', 'Paso guardado como una nueva versión.');
    }

    private function assertEditableInternalDraft(ContractDraft $draft): void
    {
        abort_unless(in_array($draft->source, ['internal', 'revision'], true), 404);

        if ($draft->status === ContractDraft::STATUS_PUBLISHED) {
            abort(409, 'Este borrador ya fue publicado y no se puede editar.');
        }
    }

    private function assertStep(string $step): void
    {
        abort_unless(array_key_exists($step, self::STEPS), 404);
    }

    private function nextStep(string $step): string
    {
        $keys = array_keys(self::STEPS);
        $index = array_search($step, $keys, true);

        return $keys[min($index + 1, count($keys) - 1)];
    }

    /** @param list<string> $missing */
    private function captureStepFor(array $missing): string
    {
        $first = mb_strtolower($missing[0] ?? '');

        return match (true) {
            str_contains($first, 'arrendador') => 'arrendador',
            str_contains($first, 'arrendatario') => 'arrendatario',
            str_contains($first, 'fiador'), str_contains($first, 'tercero'), str_contains($first, 'garantía') => 'tercero',
            str_contains($first, 'transferencia') => 'pago',
            str_contains($first, 'mantenimiento') => 'mantenimiento',
            str_contains($first, 'uso') => 'uso',
            str_contains($first, 'domicilio del inmueble'), str_contains($first, 'propiedad') => 'generales',
            default => 'vigencia',
        };
    }
}
