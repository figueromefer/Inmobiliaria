<?php

namespace App\Services;

use App\Models\ContractDocumentVersion;
use App\Models\ContractDraft;
use App\Models\ContractDraftVersion;
use App\Exceptions\ContractDraftVersionConflictException;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContractDocumentVersioningService
{
    public function createVersion(
        ContractDraftVersion $draftVersion,
        string $templateKey,
        ?string $templateId = null,
        ?string $idempotencyKey = null,
        ?int $createdBy = null
    ): ContractDocumentVersion {
        $idempotencyKey ??= (string) Str::uuid();

        return DB::transaction(function () use ($draftVersion, $templateKey, $templateId, $idempotencyKey, $createdBy): ContractDocumentVersion {
            $lockedVersion = ContractDraftVersion::query()->lockForUpdate()->findOrFail($draftVersion->getKey());
            return $this->createForLockedVersion($lockedVersion, $templateKey, $templateId, $idempotencyKey, $createdBy);
        });
    }

    /**
     * Crea o reutiliza una solicitud sólo si la versión que el usuario vio
     * sigue siendo la actual del borrador al tomar el lock.
     */
    public function createVersionForExpectedCurrentDraft(
        ContractDraft $draft,
        int $expectedDraftVersionId,
        string $templateKey,
        ?string $templateId,
        string $idempotencyKey,
        ?int $createdBy = null,
    ): ContractDocumentVersion {
        return DB::transaction(function () use ($draft, $expectedDraftVersionId, $templateKey, $templateId, $idempotencyKey, $createdBy): ContractDocumentVersion {
            $lockedDraft = ContractDraft::query()->lockForUpdate()->findOrFail($draft->getKey());
            if ((int) $lockedDraft->current_version_id !== $expectedDraftVersionId) {
                throw new ContractDraftVersionConflictException('El borrador cambió desde la previsualización documental. Recárgala antes de preparar la solicitud.');
            }
            $lockedVersion = ContractDraftVersion::query()->lockForUpdate()->findOrFail($expectedDraftVersionId);
            if ((int) $lockedVersion->contract_draft_id !== (int) $lockedDraft->id) {
                throw new ContractDraftVersionConflictException('La versión previsualizada no pertenece al borrador indicado.');
            }

            return $this->createForLockedVersion($lockedVersion, $templateKey, $templateId, $idempotencyKey, $createdBy);
        });
    }

    private function createForLockedVersion(ContractDraftVersion $lockedVersion, string $templateKey, ?string $templateId, string $idempotencyKey, ?int $createdBy): ContractDocumentVersion
    {
        $existing = ContractDocumentVersion::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
        if ($existing) {
            $this->assertCompatibleRetry($existing, $lockedVersion, $templateKey, $templateId);
            return $existing;
        }
        $nextVersion = ((int) $lockedVersion->documentVersions()->max('document_version')) + 1;
        $documentVersion = new ContractDocumentVersion();
        $documentVersion->forceFill([
            'document_version' => $nextVersion, 'template_key' => $templateKey, 'template_id' => $templateId,
            'snapshot_hash' => $lockedVersion->payload_hash, 'idempotency_key' => $idempotencyKey,
            'status' => ContractDocumentVersion::STATUS_NOT_REQUESTED, 'attempts' => 0, 'created_by' => $createdBy,
        ]);
        $lockedVersion->documentVersions()->save($documentVersion);

        return $documentVersion;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateOperationalState(ContractDocumentVersion $documentVersion, array $attributes): ContractDocumentVersion
    {
        return DB::transaction(function () use ($documentVersion, $attributes): ContractDocumentVersion {
            $lockedDocumentVersion = ContractDocumentVersion::query()
                ->lockForUpdate()
                ->findOrFail($documentVersion->getKey());

            $lockedDocumentVersion->fill(Arr::only($attributes, [
                'status',
                'attempts',
                'last_error',
                'drive_file_id',
                'drive_folder_id',
                'url',
            ]));
            $lockedDocumentVersion->save();

            return $lockedDocumentVersion;
        });
    }

    private function assertCompatibleRetry(
        ContractDocumentVersion $existing,
        ContractDraftVersion $draftVersion,
        string $templateKey,
        ?string $templateId
    ): void {
        if (
            (int) $existing->contract_draft_version_id !== (int) $draftVersion->id
            || $existing->snapshot_hash !== $draftVersion->payload_hash
            || $existing->template_key !== $templateKey
            || $existing->template_id !== $templateId
        ) {
            throw new DomainException('La clave de idempotencia ya corresponde a otra solicitud documental.');
        }
    }
}
