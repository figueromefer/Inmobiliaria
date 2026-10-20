<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractDraftVersionConflictException;
use App\Exceptions\ContractFinalizationException;
use App\Models\ContractDraft;
use App\Models\Contrato;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use App\Services\LegacyContractRevisionPayloadService;
use App\Services\ContractRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContractRevisionController extends Controller
{
    public function start(Request $request, Contrato $contrato, ContractDraftPayload $payloads, ContractDraftVersioningService $versions, LegacyContractRevisionPayloadService $legacyPayloads): RedirectResponse
    {
        abort_if($contrato->origen === 'justicia_alternativa', 404);
        $draft = ContractDraft::query()->where('editing_contract_id', $contrato->id)->where('purpose', 'revision')
            ->where('created_by', $request->user()->id)->where('status', ContractDraft::STATUS_DRAFT)->first();
        if (! $draft) {
            $contrato->load(['draftVersion', 'cliente', 'propiedad', 'inquilino']);
            $snapshot = $contrato->draftVersion?->canonical_payload;
            $draft = $versions->createDraft(
                $snapshot ?: $legacyPayloads->build($contrato, $payloads),
                [
                    'source' => 'revision', 'purpose' => 'revision', 'editing_contract_id' => $contrato->id,
                    'cliente_id' => $contrato->fk_cliente, 'propiedad_id' => $contrato->fk_propiedad,
                    'inquilino_id' => $contrato->inquilino_id, 'created_by' => $request->user()->id,
                ],
                $snapshot ? null : ['revision_origin' => 'legacy_fallback'],
                ContractDraftPayload::SCHEMA_VERSION,
                $snapshot ? 'revision_started_from_snapshot' : 'revision_started_from_legacy_fallback',
            );
        }

        return redirect()->route('contratos.privados.wizard.show', [$draft, 'generales']);
    }

    public function publish(Request $request, ContractDraft $draft, ContractRevisionService $revisions): RedirectResponse
    {
        abort_unless($draft->purpose === 'revision' && $draft->editing_contract_id, 404);
        $data = $request->validate(['expected_version_id' => ['required', 'integer', 'min:1']]);
        try {
            $contrato = $revisions->publish($draft, (int) $data['expected_version_id'], $request->user()->id);
        } catch (ContractDraftVersionConflictException) {
            throw ValidationException::withMessages(['expected_version_id' => 'La revisión cambió. Recarga el resumen antes de generar.']);
        } catch (ContractFinalizationException $exception) {
            Log::warning('No se pudo publicar una revisión de contrato.', ['contract_draft_id' => $draft->id, 'exception' => $exception::class]);
            return redirect()->route('contratos.privados.wizard.show', [$draft, 'resumen'])
                ->with('generation_error', 'No fue posible generar la nueva versión. Puedes volver a intentarlo sin perder la información capturada.');
        }

        return redirect()->route('contratos.show', $contrato)->with('success', 'Nueva versión del contrato generada correctamente.');
    }

}
