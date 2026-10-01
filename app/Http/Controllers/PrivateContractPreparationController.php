<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractDraftPublishedException;
use App\Exceptions\ContractDraftVersionConflictException;
use App\Models\ContractDraft;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftPreparationService;
use App\Services\ContractDraftReconciliationService;
use App\Services\ContractDraftVersioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrivateContractPreparationController extends Controller
{
    private const SESSION_DRAFT_KEY = 'private_contract_preparation_draft_id';

    public function start(Request $request, ContractDraftPayload $payloads, ContractDraftVersioningService $versioning): RedirectResponse
    {
        $draft = ContractDraft::query()
            ->whereKey($request->session()->get(self::SESSION_DRAFT_KEY))
            ->where('source', 'internal')
            ->where('created_by', $request->user()->id)
            ->whereNull('contrato_id')
            ->where('status', ContractDraft::STATUS_DRAFT)
            ->first();

        if (! $draft) {
            $draft = $versioning->createDraft(
                $payloads->empty(),
                [
                    'source' => 'internal',
                    'status' => ContractDraft::STATUS_DRAFT,
                    'created_by' => $request->user()->id,
                ],
                null,
                ContractDraftPayload::SCHEMA_VERSION,
                'private_preparation_started',
            );
            $request->session()->put(self::SESSION_DRAFT_KEY, $draft->id);
        }

        return redirect()->route('contratos.privados.preparacion.show', $draft);
    }

    public function show(Request $request, ContractDraft $draft, ContractDraftReconciliationService $reconciliation)
    {
        $this->assertInternalDraft($draft);
        $draft->load(['currentVersion', 'cliente', 'propiedad.cliente', 'inquilino']);

        return view('contratos.privados.preparacion', [
            'draft' => $draft,
            'payload' => $draft->currentVersion->canonical_payload,
            'searches' => $reconciliation->searches($request),
        ]);
    }

    public function saveGeneral(Request $request, ContractDraft $draft, ContractDraftPreparationService $preparation): RedirectResponse
    {
        $this->assertInternalDraft($draft);
        $data = $request->validate([
            'expected_version_id' => ['required', 'integer', 'min:1'],
            'contract_date' => ['nullable', 'date'],
            'contract_reference' => ['nullable', 'string', 'max:255'],
            'property_alias' => ['nullable', 'string', 'max:255'],
            'property_address' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $preparation->saveGeneral($draft, (int) $data['expected_version_id'], $data, $request->user()->id);
        } catch (ContractDraftVersionConflictException|ContractDraftPublishedException $exception) {
            throw ValidationException::withMessages(['expected_version_id' => $exception->getMessage()]);
        }

        return back()->with('success', 'Datos generales guardados como una nueva versión.');
    }

    public function reconcile(Request $request, ContractDraft $draft, string $entity, ContractDraftPreparationService $preparation): RedirectResponse
    {
        $this->assertInternalDraft($draft);
        abort_unless(in_array($entity, ['cliente', 'propiedad', 'inquilino'], true), 404);
        $data = $request->validate([
            'expected_version_id' => ['required', 'integer', 'min:1'],
            'entity_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $preparation->reconcile($draft, (int) $data['expected_version_id'], $entity, (int) $data['entity_id'], $request->user()->id);
        } catch (ContractDraftVersionConflictException|ContractDraftPublishedException $exception) {
            throw ValidationException::withMessages(['expected_version_id' => $exception->getMessage()]);
        }

        return back()->with('success', 'Entidad conciliada y datos disponibles copiados al snapshot.');
    }

    public function saveGuarantor(Request $request, ContractDraft $draft, ContractDraftPreparationService $preparation): RedirectResponse
    {
        $this->assertInternalDraft($draft);
        $data = $request->validate([
            'expected_version_id' => ['required', 'integer', 'min:1'],
            'guarantor_mode' => ['required', 'in:none,manual'],
        ]);

        try {
            $preparation->setGuarantorMode($draft, (int) $data['expected_version_id'], $data['guarantor_mode'], $request->user()->id);
        } catch (ContractDraftVersionConflictException|ContractDraftPublishedException $exception) {
            throw ValidationException::withMessages(['expected_version_id' => $exception->getMessage()]);
        }

        return back()->with('success', $data['guarantor_mode'] === 'none' ? 'Configurado sin fiador.' : 'La captura manual de fiador podrá completarse en el siguiente paso.');
    }

    public function next(Request $request, ContractDraft $draft): RedirectResponse
    {
        $this->assertInternalDraft($draft);
        $request->session()->forget(self::SESSION_DRAFT_KEY);

        return redirect()->route('contratos.privados.wizard.show', [$draft, 'generales']);
    }

    private function assertInternalDraft(ContractDraft $draft): void
    {
        abort_unless($draft->source === 'internal', 404);
    }
}
