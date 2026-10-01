<?php

namespace App\Services;

use App\Exceptions\ContractDraftPublishedException;
use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\Inquilino;
use App\Models\Propiedad;

class ContractDraftPreparationService
{
    public function __construct(
        private readonly ContractDraftPayload $payloads,
        private readonly ContractDraftVersioningService $versioning,
    ) {}

    public function saveGeneral(ContractDraft $draft, int $expectedVersionId, array $data, ?int $actorId): void
    {
        $this->assertEditable($draft);

        $this->versioning->appendVersionFromExpected(
            $draft,
            $expectedVersionId,
            function (array $payload) use ($data): array {
                data_set($payload, 'metadata.contract_date', $this->nullable($data['contract_date'] ?? null));
                data_set($payload, 'metadata.contract_reference', $this->nullable($data['contract_reference'] ?? null));
                data_set($payload, 'leased_property.alias', $this->nullable($data['property_alias'] ?? null));
                data_set($payload, 'leased_property.address', $this->nullable($data['property_address'] ?? null));

                return $this->payloads->validateAndNormalize($payload);
            },
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'preparation_saved:general',
            $actorId,
        );
    }

    public function reconcile(ContractDraft $draft, int $expectedVersionId, string $entity, int $entityId, ?int $actorId): void
    {
        $this->assertEditable($draft);

        match ($entity) {
            'cliente' => $this->reconcileCliente($draft, $expectedVersionId, Cliente::query()->findOrFail($entityId), $actorId),
            'propiedad' => $this->reconcilePropiedad($draft, $expectedVersionId, Propiedad::query()->findOrFail($entityId), $actorId),
            'inquilino' => $this->reconcileInquilino($draft, $expectedVersionId, Inquilino::query()->findOrFail($entityId), $actorId),
            default => abort(404),
        };
    }

    public function setGuarantorMode(ContractDraft $draft, int $expectedVersionId, string $mode, ?int $actorId): void
    {
        $this->assertEditable($draft);

        $this->versioning->appendVersionFromExpected(
            $draft,
            $expectedVersionId,
            function (array $payload) use ($mode): array {
                if ($mode === 'none') {
                    $payload['guarantor'] = ['type' => 'none', 'person' => null, 'representative' => null];
                } elseif (data_get($payload, 'guarantor.type') === 'none') {
                    $empty = $this->payloads->empty();
                    $payload['guarantor'] = [
                        'type' => 'fisica',
                        'person' => $empty['lessor']['person'],
                        'representative' => null,
                    ];
                }

                return $this->payloads->validateAndNormalize($payload);
            },
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'preparation_saved:guarantor',
            $actorId,
        );
    }

    private function reconcileCliente(ContractDraft $draft, int $expectedVersionId, Cliente $cliente, ?int $actorId): void
    {
        $this->versioning->appendVersionFromExpected(
            $draft,
            $expectedVersionId,
            function (array $payload, ContractDraft $lockedDraft) use ($cliente): array {
                $lockedDraft->forceFill(['cliente_id' => $cliente->pk_cliente])->save();
                data_set($payload, 'metadata.cliente_id', $cliente->pk_cliente);
                $personPath = $this->personPath($payload, 'lessor');
                $this->fillBlank($payload, "{$personPath}.full_name", $cliente->nombre);
                $this->fillBlank($payload, "{$personPath}.legal_name", $cliente->nombre);
                $this->fillBlank($payload, "{$personPath}.rfc", $cliente->rfc);
                $this->fillBlank($payload, "{$personPath}.address", $cliente->domicilio ?: $cliente->domicilio_notificaciones);
                $this->fillBlank($payload, "{$personPath}.phone", $cliente->celular ?: $cliente->fijo);
                $this->fillBlank($payload, "{$personPath}.email", $cliente->correo);

                return $this->payloads->validateAndNormalize($payload);
            },
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'preparation_reconciled:cliente',
            $actorId,
        );
    }

    private function reconcilePropiedad(ContractDraft $draft, int $expectedVersionId, Propiedad $propiedad, ?int $actorId): void
    {
        $this->versioning->appendVersionFromExpected(
            $draft,
            $expectedVersionId,
            function (array $payload, ContractDraft $lockedDraft) use ($propiedad): array {
                $lockedDraft->forceFill(['propiedad_id' => $propiedad->pk_propiedad])->save();
                data_set($payload, 'metadata.propiedad_id', $propiedad->pk_propiedad);
                data_set($payload, 'leased_property.master_property_id', $propiedad->pk_propiedad);
                $this->fillBlank($payload, 'leased_property.alias', $propiedad->alias);
                $this->fillBlank($payload, 'leased_property.address', $propiedad->domicilio);

                return $this->payloads->validateAndNormalize($payload);
            },
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'preparation_reconciled:propiedad',
            $actorId,
        );
    }

    private function reconcileInquilino(ContractDraft $draft, int $expectedVersionId, Inquilino $inquilino, ?int $actorId): void
    {
        $this->versioning->appendVersionFromExpected(
            $draft,
            $expectedVersionId,
            function (array $payload, ContractDraft $lockedDraft) use ($inquilino): array {
                $lockedDraft->forceFill(['inquilino_id' => $inquilino->id])->save();
                data_set($payload, 'metadata.inquilino_id', $inquilino->id);
                $personPath = $this->personPath($payload, 'lessee');
                $this->fillBlank($payload, "{$personPath}.full_name", $inquilino->nombre);
                $this->fillBlank($payload, "{$personPath}.legal_name", $inquilino->nombre);
                $this->fillBlank($payload, "{$personPath}.nationality", $inquilino->nacionalidad);
                $this->fillBlank($payload, "{$personPath}.address", $inquilino->domicilio);
                $this->fillBlank($payload, "{$personPath}.phone", $inquilino->telefono);
                $this->fillBlank($payload, "{$personPath}.email", $inquilino->correo);

                return $this->payloads->validateAndNormalize($payload);
            },
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'preparation_reconciled:inquilino',
            $actorId,
        );
    }

    private function personPath(array $payload, string $party): string
    {
        return data_get($payload, "{$party}.person.person_type") === 'moral'
            ? "{$party}.person"
            : "{$party}.person";
    }

    private function fillBlank(array &$payload, string $path, mixed $value): void
    {
        if ($this->isBlank(data_get($payload, $path)) && ! $this->isBlank($value)) {
            data_set($payload, $path, $value);
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function assertEditable(ContractDraft $draft): void
    {
        if ($draft->status === ContractDraft::STATUS_PUBLISHED || $draft->contrato_id !== null) {
            throw new ContractDraftPublishedException('Un borrador publicado no admite cambios de preparación.');
        }
    }
}
