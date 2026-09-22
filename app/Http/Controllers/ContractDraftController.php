<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractDraftRequest;
use App\Http\Requests\UpdateContractDraftRequest;
use App\Http\Requests\LinkContractDraftEntityRequest;
use App\Exceptions\ContractDraftVersionConflictException;
use App\Models\ContractDraft;
use App\Models\ContractDraftVersion;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftReconciliationService;
use App\Services\ContractDraftVersioningService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContractDraftController extends Controller
{
    public function index()
    {
        $drafts = ContractDraft::query()
            ->with(['currentVersion', 'createdBy'])
            ->latest()
            ->paginate(20);

        return view('contratos.borradores.index', compact('drafts'));
    }

    public function create(ContractDraftPayload $payloads)
    {
        return view('contratos.borradores.create', ['payload' => $payloads->empty()]);
    }

    public function store(
        StoreContractDraftRequest $request,
        ContractDraftPayload $payloads,
        ContractDraftVersioningService $versioning,
    ) {
        $payload = $payloads->validateAndNormalize($request->validated('payload'));
        $draft = $versioning->createDraft($payload, [
            'source' => 'laravel',
            'status' => ContractDraft::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ], null, ContractDraftPayload::SCHEMA_VERSION, 'created');

        return redirect()->route('contratos.borradores.show', $draft)
            ->with('success', 'Borrador creado como versión 1.');
    }

    public function show(Request $request, ContractDraft $draft, ContractDraftReconciliationService $reconciliation)
    {
        $draft->load(['currentVersion.createdBy', 'createdBy', 'cliente', 'propiedad', 'inquilino']);

        return view('contratos.borradores.show', [
            'draft' => $draft,
            'searches' => $reconciliation->searches($request),
            'comparisons' => $reconciliation->comparisons($draft),
        ]);
    }

    public function edit(ContractDraft $draft)
    {
        $draft->load('currentVersion');

        return view('contratos.borradores.edit', [
            'draft' => $draft,
            'payload' => $draft->currentVersion->canonical_payload,
        ]);
    }

    public function update(
        UpdateContractDraftRequest $request,
        ContractDraft $draft,
        ContractDraftPayload $payloads,
        ContractDraftVersioningService $versioning,
    ) {
        try {
            $version = $versioning->appendVersionFromExpected(
                $draft,
                (int) $request->validated('expected_version_id'),
                fn (array $current): array => $payloads->validateAndNormalize(
                    $payloads->mergeForEditing($current, $request->validated('payload')),
                ),
                null,
                ContractDraftPayload::SCHEMA_VERSION,
                'saved',
                $request->user()->id,
            );
        } catch (ContractDraftVersionConflictException $exception) {
            throw ValidationException::withMessages([
                'expected_version_id' => 'El borrador fue actualizado por otro usuario. Recarga la página antes de guardar.',
            ]);
        }

        return redirect()->route('contratos.borradores.show', $draft)
            ->with('success', "Borrador guardado como versión {$version->draft_version}.");
    }

    public function versions(ContractDraft $draft)
    {
        $versions = $draft->versions()->with('createdBy')->latest('draft_version')->get();

        return view('contratos.borradores.versions', compact('draft', 'versions'));
    }

    public function version(ContractDraft $draft, ContractDraftVersion $version)
    {
        abort_unless((int) $version->contract_draft_id === (int) $draft->id, 404);
        $version->load('createdBy');

        return view('contratos.borradores.version', compact('draft', 'version'));
    }

    public function linkEntity(
        LinkContractDraftEntityRequest $request,
        ContractDraft $draft,
        string $entity,
        ContractDraftReconciliationService $reconciliation,
    ) {
        $reconciliation->link(
            $draft,
            $entity,
            (int) $request->validated('entity_id'),
            $request->user(),
            $request,
        );

        return redirect()->route('contratos.borradores.show', $draft)
            ->with('success', 'Vínculo de conciliación actualizado. El snapshot contractual no fue modificado.');
    }

    public function unlinkEntity(
        Request $request,
        ContractDraft $draft,
        string $entity,
        ContractDraftReconciliationService $reconciliation,
    ) {
        $reconciliation->unlink($draft, $entity, $request->user(), $request);

        return redirect()->route('contratos.borradores.show', $draft)
            ->with('success', 'Vínculo de conciliación eliminado. El snapshot contractual no fue modificado.');
    }
}
