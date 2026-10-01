<?php

namespace App\Services;

use App\Exceptions\ContractFinalizationException;
use App\Models\ContractDocumentVersion;
use App\Models\ContractDraft;
use App\Models\ContractDraftVersion;
use App\Models\Contrato;

/** Maps only a frozen contract_data_v1 snapshot to the legacy contratos projection. */
class ContractPublicationMapper
{
    /** @return array<string, mixed> */
    public function attributes(ContractDraft $draft, ContractDraftVersion $version, ContractDocumentVersion $document): array
    {
        if (! $draft->cliente_id || ! $draft->propiedad_id) {
            throw new ContractFinalizationException('El borrador debe tener cliente y propiedad conciliados antes de publicarse.');
        }

        if ($document->status !== ContractDocumentVersion::STATUS_GENERATED) {
            throw new ContractFinalizationException('El documento contractual no está generado.');
        }

        $payload = $version->canonical_payload;
        $previousContractId = data_get($payload, 'renewal.previous_contract_id');
        if ($previousContractId !== null && (! is_int($previousContractId) || $previousContractId < 1)) {
            throw new ContractFinalizationException('La referencia del contrato previo no es válida.');
        }

        return [
            // References are operational only. Contractual data below comes only from the snapshot.
            'fk_cliente' => $draft->cliente_id,
            'fk_propiedad' => $draft->propiedad_id,
            'inquilino_id' => $draft->inquilino_id,
            'tipo_solicitante' => data_get($payload, 'lessor.person.person_type'),
            'tipo_complementaria' => data_get($payload, 'lessee.person.person_type'),
            'tipo_tercero' => data_get($payload, 'guarantor.type'),
            'fecha' => now(),
            'domicilio_inmueble' => data_get($payload, 'leased_property.address'),
            'fecha_inicio' => data_get($payload, 'term.start_date'),
            'fecha_fin' => data_get($payload, 'term.end_date'),
            // dias_pago is an unsigned integer legacy field. A textual range remains in the snapshot.
            'dias_pago' => $this->legacyPaymentDay($payload),
            'monto_total' => data_get($payload, 'amounts.total_rent'),
            'monto_mensual' => data_get($payload, 'amounts.monthly_rent'),
            'monto_deposito' => data_get($payload, 'amounts.security_deposit'),
            'comision_renta' => data_get($payload, 'amounts.rental_commission'),
            'comision_mensual' => data_get($payload, 'amounts.monthly_commission_value'),
            'urldoc' => $document->url,
            'origen' => 'privado',
            'contract_draft_version_id' => $version->id,
            'contract_document_version_id' => $document->id,
            'previous_contract_id' => $previousContractId,
        ];
    }

    public function create(ContractDraft $draft, ContractDraftVersion $version, ContractDocumentVersion $document): Contrato
    {
        return Contrato::create($this->attributes($draft, $version, $document));
    }

    /** @param array<string, mixed> $payload */
    private function legacyPaymentDay(array $payload): ?int
    {
        $day = data_get($payload, 'term.rent_due_rule.day_of_month');

        return is_int($day) && $day >= 1 && $day <= 31 ? $day : null;
    }
}
