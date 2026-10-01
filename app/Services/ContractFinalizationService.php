<?php

namespace App\Services;

use App\Exceptions\ContractDraftVersionConflictException;
use App\Exceptions\ContractFinalizationException;
use App\Models\ContractDocumentVersion;
use App\Models\ContractDraft;
use App\Models\ContractDraftVersion;
use App\Models\Contrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Coordinates the external Drive operation and the final DB publication.
 * Google is intentionally outside SQL transactions; persisted finalization
 * identity makes retries resume the same document request and publication.
 */
class ContractFinalizationService
{
    public function __construct(
        private readonly ContractDocumentPayloadBuilder $documents,
        private readonly ContractDocumentVersioningService $documentVersions,
        private readonly GoogleContractDocumentRenderer $renderer,
        private readonly ContractPublicationMapper $mapper,
    ) {}

    public function finalize(ContractDraft $draft, int $expectedDraftVersionId, ?int $actorId = null): Contrato
    {
        $state = DB::transaction(function () use ($draft, $expectedDraftVersionId): array {
            $lockedDraft = ContractDraft::query()->lockForUpdate()->findOrFail($draft->id);

            if ($lockedDraft->contrato_id !== null) {
                $contrato = Contrato::query()->find($lockedDraft->contrato_id);
                if (! $contrato) {
                    throw $this->inconsistency($lockedDraft, 'missing_linked_contract');
                }

                return ['contrato' => $contrato];
            }

            if ($lockedDraft->status === ContractDraft::STATUS_PUBLISHED) {
                throw $this->inconsistency($lockedDraft, 'published_without_contract');
            }

            if ((int) $lockedDraft->current_version_id !== $expectedDraftVersionId) {
                throw new ContractDraftVersionConflictException('El borrador cambió antes de finalizarse. Recarga y revisa la información.');
            }

            if ($lockedDraft->finalization_draft_version_id !== null
                && (int) $lockedDraft->finalization_draft_version_id !== $expectedDraftVersionId) {
                throw new ContractFinalizationException('La finalización ya quedó vinculada a otro snapshot del borrador.');
            }

            $version = ContractDraftVersion::query()->lockForUpdate()->findOrFail($expectedDraftVersionId);
            if ((int) $version->contract_draft_id !== (int) $lockedDraft->id) {
                throw new ContractFinalizationException('El snapshot indicado no pertenece al borrador.');
            }

            $plan = $this->documents->build($version);
            if ($plan['status'] !== 'ready') {
                throw new ContractFinalizationException('El borrador no está listo para generar contrato.');
            }

            $lockedDraft->forceFill([
                'finalization_key' => $lockedDraft->finalization_key ?: (string) Str::uuid(),
                'finalization_draft_version_id' => $version->id,
            ])->save();

            return ['draft' => $lockedDraft, 'version' => $version, 'plan' => $plan];
        });

        if (isset($state['contrato'])) {
            return $state['contrato'];
        }

        /** @var ContractDraft $lockedDraft */
        $lockedDraft = $state['draft'];
        /** @var ContractDraftVersion $version */
        $version = $state['version'];
        /** @var array<string, mixed> $plan */
        $plan = $state['plan'];

        $document = $this->documentVersions->createVersionForExpectedCurrentDraft(
            $lockedDraft,
            $version->id,
            $plan['template_key'],
            $plan['template_id'],
            $lockedDraft->finalization_key,
            $actorId,
        );
        $document = $this->renderer->generate($document);

        if ($document->status !== ContractDocumentVersion::STATUS_GENERATED) {
            throw new ContractFinalizationException('No fue posible generar el documento contractual. El borrador puede reintentarse.');
        }

        return DB::transaction(function () use ($draft, $expectedDraftVersionId, $actorId, $document): Contrato {
            $lockedDraft = ContractDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ($lockedDraft->contrato_id !== null) {
                $contrato = Contrato::query()->find($lockedDraft->contrato_id);
                if (! $contrato) {
                    throw $this->inconsistency($lockedDraft, 'missing_linked_contract');
                }

                return $contrato;
            }

            if ((int) $lockedDraft->current_version_id !== $expectedDraftVersionId
                || (int) $lockedDraft->finalization_draft_version_id !== $expectedDraftVersionId) {
                throw new ContractDraftVersionConflictException('El borrador cambió durante la generación documental.');
            }

            $lockedVersion = ContractDraftVersion::query()->lockForUpdate()->findOrFail($expectedDraftVersionId);
            $lockedDocument = ContractDocumentVersion::query()->lockForUpdate()->findOrFail($document->id);
            if ((int) $lockedVersion->contract_draft_id !== (int) $lockedDraft->id
                || $lockedDocument->status !== ContractDocumentVersion::STATUS_GENERATED
                || $lockedDocument->idempotency_key !== $lockedDraft->finalization_key
                || (int) $lockedDocument->contract_draft_version_id !== (int) $lockedVersion->id) {
                throw new ContractFinalizationException('La versión documental no corresponde a la finalización del borrador.');
            }

            $contrato = $this->mapper->create($lockedDraft, $lockedVersion, $lockedDocument);
            $lockedDraft->forceFill([
                'contrato_id' => $contrato->id,
                'status' => ContractDraft::STATUS_PUBLISHED,
                'published_by' => $actorId,
            ])->save();

            return $contrato;
        });
    }

    private function inconsistency(ContractDraft $draft, string $reason): ContractFinalizationException
    {
        Log::warning('Inconsistencia de publicación de contrato.', [
            'contract_draft_id' => $draft->id,
            'reason' => $reason,
        ]);

        return new ContractFinalizationException('El borrador publicado tiene una referencia contractual inconsistente.');
    }
}
