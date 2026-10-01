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

/** Publishes a correction into the same logical Contract without erasing its prior versions. */
class ContractRevisionService
{
    public function __construct(
        private readonly ContractDocumentPayloadBuilder $documents,
        private readonly ContractDocumentVersioningService $documentVersions,
        private readonly GoogleContractDocumentRenderer $renderer,
        private readonly ContractPublicationMapper $mapper,
    ) {}

    public function publish(ContractDraft $draft, int $expectedVersionId, ?int $actorId = null): Contrato
    {
        $state = DB::transaction(function () use ($draft, $expectedVersionId): array {
            $locked = ContractDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $contract = Contrato::query()->lockForUpdate()->findOrFail($locked->editing_contract_id);

            if ($locked->purpose !== 'revision') {
                throw new ContractFinalizationException('El borrador no corresponde a una revisión contractual.');
            }
            if ($locked->status === ContractDraft::STATUS_PUBLISHED) {
                return ['contrato' => $contract];
            }
            if ((int) $locked->current_version_id !== $expectedVersionId) {
                throw new ContractDraftVersionConflictException('El borrador cambió antes de generar la nueva versión.');
            }
            if ($locked->finalization_draft_version_id !== null && (int) $locked->finalization_draft_version_id !== $expectedVersionId) {
                throw new ContractFinalizationException('La revisión ya está vinculada a otro snapshot.');
            }
            $version = ContractDraftVersion::query()->lockForUpdate()->findOrFail($expectedVersionId);
            if ((int) $version->contract_draft_id !== (int) $locked->id) {
                throw new ContractFinalizationException('El snapshot indicado no pertenece a esta revisión.');
            }
            $plan = $this->documents->build($version);
            if ($plan['status'] !== 'ready') {
                throw new ContractFinalizationException('La revisión no está lista para generar documento.');
            }
            $locked->forceFill([
                'finalization_key' => $locked->finalization_key ?: (string) Str::uuid(),
                'finalization_draft_version_id' => $version->id,
            ])->save();

            return ['draft' => $locked, 'version' => $version, 'plan' => $plan, 'contract' => $contract];
        });

        if (isset($state['contrato']) && ! isset($state['draft'])) {
            return $state['contrato'];
        }

        $document = $this->documentVersions->createVersionForExpectedCurrentDraft(
            $state['draft'], $state['version']->id, $state['plan']['template_key'], $state['plan']['template_id'], $state['draft']->finalization_key, $actorId,
        );
        // Revisions use a new file in the same contractual folder when one exists.
        if (! $document->drive_folder_id && $state['contract']->documentVersion?->drive_folder_id) {
            $document = $this->documentVersions->updateOperationalState($document, [
                'drive_folder_id' => $state['contract']->documentVersion->drive_folder_id,
            ]);
        }
        $document = $this->renderer->generate($document);
        if ($document->status !== ContractDocumentVersion::STATUS_GENERATED) {
            throw new ContractFinalizationException('No fue posible generar la nueva versión documental.');
        }

        return DB::transaction(function () use ($draft, $expectedVersionId, $actorId, $document): Contrato {
            $locked = ContractDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $contract = Contrato::query()->lockForUpdate()->findOrFail($locked->editing_contract_id);
            if ($locked->status === ContractDraft::STATUS_PUBLISHED) {
                return $contract;
            }
            if ((int) $locked->current_version_id !== $expectedVersionId || (int) $locked->finalization_draft_version_id !== $expectedVersionId) {
                throw new ContractDraftVersionConflictException('La revisión cambió durante la generación documental.');
            }
            $version = ContractDraftVersion::query()->lockForUpdate()->findOrFail($expectedVersionId);
            $lockedDocument = ContractDocumentVersion::query()->lockForUpdate()->findOrFail($document->id);
            if ($lockedDocument->status !== ContractDocumentVersion::STATUS_GENERATED || (int) $lockedDocument->contract_draft_version_id !== (int) $version->id || $lockedDocument->idempotency_key !== $locked->finalization_key) {
                throw new ContractFinalizationException('El documento no corresponde a esta revisión.');
            }

            $contract->fill($this->mapper->attributes($locked, $version, $lockedDocument));
            $contract->save();
            $locked->forceFill(['status' => ContractDraft::STATUS_PUBLISHED, 'published_by' => $actorId])->save();

            return $contract;
        });
    }
}
