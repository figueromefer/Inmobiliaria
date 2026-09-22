<?php

namespace App\Services;

use App\Exceptions\ContractDraftVersionConflictException;
use App\Models\ContractDraft;
use App\Models\ContractDraftVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use DomainException;

class ContractDraftVersioningService
{
    public function __construct(private readonly ContractPayloadCanonicalizer $canonicalizer)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $draftAttributes
     * @param array<string, mixed>|null $rawLegacyPayload
     */
    public function createDraft(
        array $payload,
        array $draftAttributes = [],
        ?array $rawLegacyPayload = null,
        string $schemaVersion = 'contract_data_v1',
        string $action = 'created'
    ): ContractDraft {
        return DB::transaction(function () use ($payload, $draftAttributes, $rawLegacyPayload, $schemaVersion, $action): ContractDraft {
            $draft = ContractDraft::create(array_merge([
                'source' => 'laravel',
                'status' => ContractDraft::STATUS_DRAFT,
            ], Arr::only($draftAttributes, [
                'source',
                'external_id',
                'status',
                'contrato_id',
                'cliente_id',
                'propiedad_id',
                'inquilino_id',
                'created_by',
                'published_by',
            ])));

            $this->appendVersion(
                $draft,
                $payload,
                $rawLegacyPayload,
                $schemaVersion,
                $action,
                $draft->created_by
            );

            return $draft->refresh(['currentVersion']);
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $rawLegacyPayload
     */
    public function appendVersion(
        ContractDraft $draft,
        array $payload,
        ?array $rawLegacyPayload = null,
        string $schemaVersion = 'contract_data_v1',
        string $action = 'saved',
        ?int $createdBy = null
    ): ContractDraftVersion {
        return DB::transaction(function () use ($draft, $payload, $rawLegacyPayload, $schemaVersion, $action, $createdBy): ContractDraftVersion {
            $lockedDraft = ContractDraft::query()->lockForUpdate()->findOrFail($draft->getKey());
            $nextVersion = ((int) $lockedDraft->versions()->max('draft_version')) + 1;
            $canonicalPayload = $this->canonicalizer->canonicalize($payload);
            $payloadHash = $this->canonicalizer->hashCanonical($canonicalPayload);

            $version = $lockedDraft->versions()->create([
                'draft_version' => $nextVersion,
                'schema_version' => $schemaVersion,
                'canonical_payload' => $canonicalPayload,
                'payload_hash' => $payloadHash,
                'raw_legacy_payload' => $rawLegacyPayload,
                'source' => $lockedDraft->source,
                'action' => $action,
                'created_by' => $createdBy,
            ]);

            $this->setCurrentVersion($lockedDraft, $version);

            return $version;
        });
    }

    /**
     * Construye una nueva versión sólo si el snapshot que el editor vio sigue
     * siendo el actual. El resolver se ejecuta después del lock para que un
     * merge parcial nunca parta de una versión obsoleta.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $payloadResolver
     * @param array<string, mixed>|null $rawLegacyPayload
     */
    public function appendVersionFromExpected(
        ContractDraft $draft,
        int $expectedVersionId,
        callable $payloadResolver,
        ?array $rawLegacyPayload = null,
        string $schemaVersion = 'contract_data_v1',
        string $action = 'saved',
        ?int $createdBy = null,
    ): ContractDraftVersion {
        return DB::transaction(function () use ($draft, $expectedVersionId, $payloadResolver, $rawLegacyPayload, $schemaVersion, $action, $createdBy): ContractDraftVersion {
            $lockedDraft = ContractDraft::query()->lockForUpdate()->findOrFail($draft->getKey());

            if ((int) $lockedDraft->current_version_id !== $expectedVersionId) {
                throw new ContractDraftVersionConflictException('El borrador fue actualizado por otro usuario. Recarga la página antes de guardar.');
            }

            $currentVersion = ContractDraftVersion::query()
                ->lockForUpdate()
                ->findOrFail($expectedVersionId);

            if ((int) $currentVersion->contract_draft_id !== (int) $lockedDraft->id) {
                throw new ContractDraftVersionConflictException('La versión esperada no pertenece al borrador indicado.');
            }

            $payload = $payloadResolver($currentVersion->canonical_payload);
            $nextVersion = ((int) $lockedDraft->versions()->max('draft_version')) + 1;
            $canonicalPayload = $this->canonicalizer->canonicalize($payload);
            $payloadHash = $this->canonicalizer->hashCanonical($canonicalPayload);

            $version = $lockedDraft->versions()->create([
                'draft_version' => $nextVersion,
                'schema_version' => $schemaVersion,
                'canonical_payload' => $canonicalPayload,
                'payload_hash' => $payloadHash,
                'raw_legacy_payload' => $rawLegacyPayload,
                'source' => $lockedDraft->source,
                'action' => $action,
                'created_by' => $createdBy,
            ]);

            $this->setCurrentVersion($lockedDraft, $version);

            return $version;
        });
    }

    public function setCurrentVersion(ContractDraft $draft, ContractDraftVersion $version): ContractDraft
    {
        return DB::transaction(function () use ($draft, $version): ContractDraft {
            $lockedDraft = ContractDraft::query()->lockForUpdate()->findOrFail($draft->getKey());
            $lockedVersion = ContractDraftVersion::query()->lockForUpdate()->findOrFail($version->getKey());

            if ((int) $lockedVersion->contract_draft_id !== (int) $lockedDraft->id) {
                throw new DomainException('La versión no pertenece al borrador indicado.');
            }

            $lockedDraft->forceFill(['current_version_id' => $lockedVersion->id])->save();

            return $lockedDraft;
        });
    }
}
