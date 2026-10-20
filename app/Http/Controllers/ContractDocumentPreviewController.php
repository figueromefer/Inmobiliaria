<?php

namespace App\Http\Controllers;

use App\Models\ContractDraft;
use App\Models\ContractDocumentVersion;
use App\Exceptions\ContractDraftVersionConflictException;
use App\Services\ContractDocumentPayloadBuilder;
use App\Services\ContractDocumentVersioningService;
use App\Services\ContractRevisionService;
use App\Services\GoogleContractDocumentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContractDocumentPreviewController extends Controller
{
    public function show(ContractDraft $draft, ContractDocumentPayloadBuilder $builder)
    {
        $draft->load('currentVersion.documentVersions');
        $document = $builder->build($draft->currentVersion);
        $idempotencyKey = (string) Str::uuid();
        $documentVersions = $draft->currentVersion->documentVersions()->latest('document_version')->get();

        return view('contratos.borradores.document_preview', compact('draft', 'document', 'idempotencyKey', 'documentVersions'));
    }

    public function request(
        ContractDraft $draft,
        ContractDocumentPayloadBuilder $builder,
        ContractDocumentVersioningService $versioning,
    ): RedirectResponse {
        $data = request()->validate([
            'expected_draft_version_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $previewedVersion = $draft->versions()->findOrFail($data['expected_draft_version_id']);
        $document = $builder->build($previewedVersion);
        if ($document['status'] !== 'ready') {
            throw ValidationException::withMessages([
                'document' => 'La solicitud documental requiere revisión o datos adicionales antes de prepararse.',
            ]);
        }

        try {
            $versioning->createVersionForExpectedCurrentDraft(
            $draft,
            (int) $data['expected_draft_version_id'],
            $document['template_key'],
            $document['template_id'],
            $data['idempotency_key'],
            request()->user()->id,
        );
        } catch (ContractDraftVersionConflictException $exception) {
            abort(409, $exception->getMessage());
        }

        return redirect()->route('contratos.borradores.document-preview', $draft)
            ->with('success', 'Solicitud documental preparada. No se generó ningún documento ni se contactó a Google.');
    }

    public function generate(
        ContractDraft $draft,
        ContractDocumentPayloadBuilder $builder,
        ContractDocumentVersioningService $versioning,
        GoogleContractDocumentRenderer $renderer,
        ContractRevisionService $revisions,
    ): RedirectResponse {
        $data = request()->validate([
            'expected_draft_version_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $previewedVersion = $draft->versions()->findOrFail($data['expected_draft_version_id']);
        $document = $builder->build($previewedVersion);
        if ($document['status'] !== 'ready') {
            throw ValidationException::withMessages(['document' => 'La generación requiere una previsualización documental lista.']);
        }

        // This is the route used by the draft preview UI. A revision must not
        // stop at Drive generation: publication is what applies its frozen
        // snapshot (including propiedad_id) to the existing contract.
        if ($draft->purpose === 'revision') {
            try {
                $contrato = $revisions->publish($draft, (int) $data['expected_draft_version_id'], request()->user()->id);
            } catch (ContractDraftVersionConflictException $exception) {
                abort(409, $exception->getMessage());
            } catch (\App\Exceptions\ContractFinalizationException) {
                return redirect()->route('contratos.borradores.document-preview', $draft)
                    ->with('error', 'No fue posible generar el documento y publicar los cambios. Puedes volver a intentarlo sin perder la información capturada.');
            }

            return redirect()->route('contratos.show', $contrato)
                ->with('success', 'Documento generado y cambios de la revisión publicados correctamente.');
        }

        try {
            $documentVersion = $versioning->createVersionForExpectedCurrentDraft(
                $draft,
                (int) $data['expected_draft_version_id'],
                $document['template_key'],
                $document['template_id'],
                $data['idempotency_key'],
                request()->user()->id,
            );
            $documentVersion = $renderer->generate($documentVersion);
        } catch (ContractDraftVersionConflictException $exception) {
            abort(409, $exception->getMessage());
        }

        return redirect()->route('contratos.borradores.document-preview', $draft)
            ->with($documentVersion->status === ContractDocumentVersion::STATUS_GENERATED ? 'success' : 'error', $documentVersion->status === ContractDocumentVersion::STATUS_GENERATED ? 'Documento generado correctamente.' : 'La generación falló. Revise el estado e intente nuevamente.');
    }

    public function retry(ContractDraft $draft, ContractDocumentVersion $documentVersion, GoogleContractDocumentRenderer $renderer, ContractRevisionService $revisions): RedirectResponse
    {
        abort_unless((int) $documentVersion->draftVersion->contract_draft_id === (int) $draft->id, 404);
        abort_unless($documentVersion->status === ContractDocumentVersion::STATUS_FAILED, 422);

        if ($draft->purpose === 'revision') {
            try {
                $contrato = $revisions->publish($draft, (int) $draft->current_version_id, request()->user()->id);
            } catch (ContractDraftVersionConflictException $exception) {
                abort(409, $exception->getMessage());
            } catch (\App\Exceptions\ContractFinalizationException) {
                return redirect()->route('contratos.borradores.document-preview', $draft)
                    ->with('error', 'No fue posible generar el documento y publicar los cambios. Puedes volver a intentarlo sin perder la información capturada.');
            }

            return redirect()->route('contratos.show', $contrato)
                ->with('success', 'Documento generado y cambios de la revisión publicados correctamente.');
        }

        $documentVersion = $renderer->generate($documentVersion);

        return redirect()->route('contratos.borradores.document-preview', $draft)
            ->with($documentVersion->status === ContractDocumentVersion::STATUS_GENERATED ? 'success' : 'error', $documentVersion->status === ContractDocumentVersion::STATUS_GENERATED ? 'Documento generado correctamente.' : 'El reintento falló. Revise el estado.');
    }
}
