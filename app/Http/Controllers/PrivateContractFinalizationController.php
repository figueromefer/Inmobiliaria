<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractDraftVersionConflictException;
use App\Exceptions\ContractFinalizationException;
use App\Models\ContractDraft;
use App\Services\ContractDocumentPayloadBuilder;
use App\Services\ContractFinalizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PrivateContractFinalizationController extends Controller
{
    public function store(
        Request $request,
        ContractDraft $draft,
        ContractDocumentPayloadBuilder $documents,
        ContractFinalizationService $finalization,
    ): RedirectResponse {
        abort_unless($draft->source === 'internal', 404);
        $data = $request->validate(['expected_version_id' => ['required', 'integer', 'min:1']]);

        if ($draft->contrato_id !== null) {
            return redirect()->route('contratos.index')->with('success', 'Contrato generado correctamente.');
        }

        $draft->load('currentVersion');
        if ((int) $draft->current_version_id !== (int) $data['expected_version_id'] && $draft->contrato_id === null) {
            throw ValidationException::withMessages([
                'expected_version_id' => 'El borrador cambió. Recarga el resumen antes de generar el contrato.',
            ]);
        }

        $plan = $documents->build($draft->currentVersion);
        if ($plan['status'] !== 'ready') {
            return redirect()->route('contratos.privados.wizard.show', [$draft, 'resumen'])
                ->with('generation_error', 'Completa o revisa la captura antes de generar el contrato.');
        }

        try {
            $finalization->finalize($draft, (int) $data['expected_version_id'], $request->user()->id);
        } catch (ContractDraftVersionConflictException) {
            throw ValidationException::withMessages([
                'expected_version_id' => 'El borrador cambió mientras se generaba. Recarga el resumen y vuelve a intentarlo.',
            ]);
        } catch (ContractFinalizationException $exception) {
            Log::warning('No se pudo finalizar un contrato privado.', [
                'contract_draft_id' => $draft->id,
                'exception' => $exception::class,
            ]);

            return redirect()->route('contratos.privados.wizard.show', [$draft, 'resumen'])
                ->with('generation_error', 'No fue posible generar el documento del contrato. Puedes volver a intentarlo sin perder la información capturada.');
        }

        return redirect()->route('contratos.index')->with('success', 'Contrato generado correctamente.');
    }
}
