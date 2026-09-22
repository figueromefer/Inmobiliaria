<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractDraftVersionConflictException;
use App\Http\Requests\SaveContractDraftWizardStepRequest;
use App\Models\ContractDraft;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContractDraftWizardController extends Controller
{
    private const STEPS = [
        'generales' => 'Datos generales', 'arrendador' => 'Arrendador', 'arrendatario' => 'Arrendatario',
        'tercero' => 'Tercero / fiador', 'garantia' => 'Garantía', 'vigencia' => 'Vigencia e importes',
        'uso' => 'Uso del inmueble', 'pago' => 'Forma de pago', 'mantenimiento' => 'Mantenimiento',
        'renovacion' => 'Renovación', 'resumen' => 'Resumen',
    ];

    public function show(Request $request, ContractDraft $draft, string $step)
    {
        $this->assertStep($step);
        $draft->load(['currentVersion.createdBy', 'cliente', 'propiedad', 'inquilino']);

        return view('contratos.borradores.wizard', [
            'draft' => $draft,
            'step' => $step,
            'steps' => self::STEPS,
            'payload' => $draft->currentVersion->canonical_payload,
        ]);
    }

    public function save(
        SaveContractDraftWizardStepRequest $request,
        ContractDraft $draft,
        string $step,
        ContractDraftPayload $payloads,
        ContractDraftVersioningService $versioning,
    ) {
        $this->assertStep($step);
        abort_if($step === 'resumen', 405);

        try {
            $versioning->appendVersionFromExpected(
                $draft,
                (int) $request->validated('expected_version_id'),
                fn (array $current): array => $payloads->validateAndNormalize(
                    $payloads->mergeForEditing($current, $request->validated('payload')),
                ),
                null,
                ContractDraftPayload::SCHEMA_VERSION,
                'wizard_saved:' . $step,
                $request->user()->id,
            );
        } catch (ContractDraftVersionConflictException) {
            throw ValidationException::withMessages([
                'expected_version_id' => 'El borrador fue actualizado por otro usuario. Recarga este paso antes de guardar.',
            ]);
        }

        if ($request->boolean('exit')) {
            return redirect()->route('contratos.borradores.show', $draft)
                ->with('success', 'Paso guardado como una nueva versión. El borrador no está publicado.');
        }

        $target = $request->boolean('next') ? $this->nextStep($step) : $step;

        return redirect()->route('contratos.borradores.wizard.show', [$draft, $target])
            ->with('success', 'Paso guardado como una nueva versión. El borrador no está publicado.');
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
}
