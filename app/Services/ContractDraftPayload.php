<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Normaliza únicamente la forma técnica del DTO contract_data_v1.
 * No impone los requisitos jurídicos pendientes de DH-05.
 */
class ContractDraftPayload
{
    public const SCHEMA_VERSION = 'contract_data_v1';

    private const PERSON_TYPES = ['fisica', 'moral'];
    private const GUARANTOR_TYPES = ['none', 'fisica', 'moral'];
    private const PAYMENT_METHODS = ['unspecified', 'cash', 'bank_transfer'];
    private const PROPERTY_USES = ['residential', 'industrial', 'commercial', 'other'];
    private const YES_NO = ['yes', 'no'];
    private const MAINTENANCE_PAYERS = ['lessor', 'lessee'];
    private const COMMISSION_UNITS = ['percent', 'fraction', 'fixed', 'unknown'];
    private const PHYSICAL_ONLY_PERSON_FIELDS = [
        'full_name', 'nationality', 'birth_place', 'birth_date', 'marital_status', 'occupation', 'identification_type',
    ];
    private const MORAL_ONLY_PERSON_FIELDS = ['legal_name', 'incorporation_deed'];

    /** @return array<string, mixed> */
    public function empty(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'metadata' => [
                'contract_kind' => 'private_lease',
                'contract_reference' => null,
                'contract_date' => null,
                'source' => 'laravel',
                'external_id' => null,
                'google_form_edit_url' => null,
                'cliente_id' => null,
                'propiedad_id' => null,
                'inquilino_id' => null,
            ],
            'lessor' => ['person' => $this->emptyPerson('fisica'), 'representative' => null],
            'lessee' => ['person' => $this->emptyPerson('fisica'), 'representative' => null],
            'guarantor' => ['type' => 'none', 'person' => null, 'representative' => null],
            'leased_property' => [
                'alias' => null,
                'address' => null,
                'property_use_codes' => [],
                'master_property_id' => null,
            ],
            'guarantee_property' => ['exists' => 'no', 'address' => null, 'title_deed' => null],
            'term' => [
                'start_date' => null,
                'end_date' => null,
                'duration_label' => null,
                'rent_due_rule' => ['raw_text' => null, 'day_from' => null, 'day_to' => null, 'day_of_month' => null],
            ],
            'amounts' => [
                'total_rent' => null,
                'monthly_rent' => null,
                'security_deposit' => null,
                'rental_commission' => null,
                'monthly_commission_value' => null,
                'monthly_commission_unit' => 'unknown',
            ],
            'payment' => ['method' => 'unspecified', 'bank_name' => null, 'beneficiary' => null, 'clabe' => null],
            'maintenance' => ['exists' => 'no', 'payer' => null],
            'renewal' => [
                'is_renewal' => 'no',
                'previous_contract_id' => null,
                'deposit_treatment' => null,
                'legacy_deposit_clause_snapshot' => null,
            ],
            'document_generation' => [],
            'audit' => [],
        ];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function validateAndNormalize(array $payload): array
    {
        $payload = $this->normalizeTransportFields($payload);
        $this->assertOnlyKeys($payload, array_keys($this->empty()), 'payload');
        $this->assertSubmittedShapes($payload);
        $data = $this->merge($this->empty(), $payload);

        if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            $this->error('payload.schema_version', 'La versión de esquema no es compatible.');
        }

        $this->validateMetadata($data['metadata']);
        $data['lessor'] = $this->normalizeParty($data['lessor'], 'payload.lessor');
        $data['lessee'] = $this->normalizeParty($data['lessee'], 'payload.lessee');
        $data['guarantor'] = $this->normalizeGuarantor($data['guarantor']);
        $data['guarantee_property'] = $this->normalizeGuaranteeProperty($data['guarantee_property']);
        $this->validateLeasedProperty($data['leased_property']);
        $this->validateTerm($data['term']);
        $this->validateAmounts($data['amounts']);
        $data['payment'] = $this->normalizePayment($data['payment']);
        $data['maintenance'] = $this->normalizeMaintenance($data['maintenance']);
        $data['renewal'] = $this->normalizeRenewal($data['renewal']);
        $this->assertReservedObject($data['document_generation'], 'payload.document_generation');
        $this->assertReservedObject($data['audit'], 'payload.audit');

        if ($data['guarantor']['type'] === 'none') {
            $data['guarantee_property'] = ['exists' => 'no', 'address' => null, 'title_deed' => null];
        }

        return $data;
    }

    /**
     * Conserva campos no expuestos todavía por la UI mínima de Fase 1B.
     * La validación posterior sigue siendo la única autoridad sobre el DTO.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $submitted
     * @return array<string, mixed>
     */
    public function mergeForEditing(array $current, array $submitted): array
    {
        $submitted = $this->normalizeTransportFields($submitted);
        $this->assertOnlyKeys($submitted, array_keys($this->empty()), 'payload');
        $this->assertSubmittedShapes($submitted);

        return $this->merge($current, $submitted);
    }

    /** @return array<string, mixed> */
    private function emptyPerson(string $type): array
    {
        return [
            'person_type' => $type,
            'full_name' => null,
            'legal_name' => null,
            'rfc' => null,
            'nationality' => null,
            'birth_place' => null,
            'birth_date' => null,
            'marital_status' => null,
            'occupation' => null,
            'address' => null,
            'identification_type' => null,
            'phone' => null,
            'email' => null,
            'incorporation_deed' => null,
            'contact_email_entered' => null,
            'contact_phone_entered' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyRepresentative(): array
    {
        return [
            'full_name' => null,
            'nationality' => null,
            'birth_place' => null,
            'birth_date' => null,
            'occupation' => null,
            'address' => null,
            'identification_type' => null,
            'authority_deed' => null,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function validateMetadata(array $metadata): void
    {
        $this->assertOnlyKeys($metadata, array_keys($this->empty()['metadata']), 'payload.metadata');
        if ($metadata['contract_kind'] !== 'private_lease') {
            $this->error('payload.metadata.contract_kind', 'El tipo de contrato no es compatible.');
        }
        if (!in_array($metadata['source'], ['laravel', 'public_form'], true)) {
            $this->error('payload.metadata.source', 'El origen del borrador no es compatible.');
        }
        if ($metadata['external_id'] !== null) {
            $this->error('payload.metadata.external_id', 'La captura interna no acepta un external_id.');
        }
        foreach (['contract_reference', 'google_form_edit_url'] as $key) {
            $this->stringOrNull($metadata[$key], "payload.metadata.{$key}");
        }
        $this->dateOrNull($metadata['contract_date'], 'payload.metadata.contract_date', true);
        foreach (['cliente_id', 'propiedad_id', 'inquilino_id'] as $key) {
            $this->integerOrNull($metadata[$key], "payload.metadata.{$key}");
        }
    }

    /** @param array<string, mixed> $party
     * @return array<string, mixed>
     */
    private function normalizeParty(array $party, string $path): array
    {
        $this->assertOnlyKeys($party, ['person', 'representative'], $path);
        if (!is_array($party['person'])) {
            $this->error("{$path}.person", 'La persona debe ser un objeto.');
        }
        $person = $this->merge($this->emptyPerson('fisica'), $party['person']);
        $this->assertOnlyKeys($person, array_keys($this->emptyPerson('fisica')), "{$path}.person");
        $this->enum($person['person_type'], self::PERSON_TYPES, "{$path}.person.person_type");
        foreach ($person as $key => $value) {
            if ($key !== 'person_type') {
                $this->dateOrNull($value, "{$path}.person.{$key}", $key === 'birth_date');
            }
        }

        foreach ($person['person_type'] === 'fisica' ? self::MORAL_ONLY_PERSON_FIELDS : self::PHYSICAL_ONLY_PERSON_FIELDS as $key) {
            $person[$key] = null;
        }

        if ($person['person_type'] === 'fisica') {
            return ['person' => $person, 'representative' => null];
        }

        $representative = $party['representative'];
        if ($representative === null) {
            return ['person' => $person, 'representative' => null];
        }
        if (!is_array($representative)) {
            $this->error("{$path}.representative", 'El representante debe ser un objeto o null.');
        }
        $representative = $this->merge($this->emptyRepresentative(), $representative);
        $this->assertOnlyKeys($representative, array_keys($this->emptyRepresentative()), "{$path}.representative");
        foreach ($representative as $key => $value) {
            $this->dateOrNull($value, "{$path}.representative.{$key}", $key === 'birth_date');
        }

        return ['person' => $person, 'representative' => $representative];
    }

    /** @param array<string, mixed> $guarantor
     * @param array<string, mixed> $guaranteeProperty
     * @return array<string, mixed>
     */
    private function normalizeGuarantor(array $guarantor): array
    {
        $this->assertOnlyKeys($guarantor, ['type', 'person', 'representative'], 'payload.guarantor');
        $this->enum($guarantor['type'], self::GUARANTOR_TYPES, 'payload.guarantor.type');
        if ($guarantor['type'] === 'none') {
            return ['type' => 'none', 'person' => null, 'representative' => null];
        }
        if (!is_array($guarantor['person'])) {
            $this->error('payload.guarantor.person', 'El tercero requiere una persona estructurada.');
        }
        $party = $this->normalizeParty([
            'person' => $guarantor['person'],
            'representative' => $guarantor['representative'],
        ], 'payload.guarantor');
        if ($party['person']['person_type'] !== $guarantor['type']) {
            $this->error('payload.guarantor.person.person_type', 'El tipo de persona debe coincidir con el tipo de tercero.');
        }
        if ($guarantor['type'] === 'fisica') {
            $party['representative'] = null;
        }

        return ['type' => $guarantor['type'], ...$party];
    }

    /** @param array<string, mixed> $property
     * @return array<string, mixed>
     */
    private function normalizeGuaranteeProperty(array $property): array
    {
        $this->assertOnlyKeys($property, ['exists', 'address', 'title_deed'], 'payload.guarantee_property');
        $this->enum($property['exists'], self::YES_NO, 'payload.guarantee_property.exists');
        foreach (['address', 'title_deed'] as $key) {
            $this->stringOrNull($property[$key], "payload.guarantee_property.{$key}");
        }
        if ($property['exists'] === 'no') {
            return ['exists' => 'no', 'address' => null, 'title_deed' => null];
        }

        return $property;
    }

    /** @param array<string, mixed> $property */
    private function validateLeasedProperty(array $property): void
    {
        $this->assertOnlyKeys($property, array_keys($this->empty()['leased_property']), 'payload.leased_property');
        $this->stringOrNull($property['alias'], 'payload.leased_property.alias');
        $this->stringOrNull($property['address'], 'payload.leased_property.address');
        $this->integerOrNull($property['master_property_id'], 'payload.leased_property.master_property_id');
        if (!is_array($property['property_use_codes']) || !array_is_list($property['property_use_codes'])) {
            $this->error('payload.leased_property.property_use_codes', 'Los usos deben ser una lista ordenada.');
        }
        foreach ($property['property_use_codes'] as $index => $code) {
            $this->enum($code, self::PROPERTY_USES, "payload.leased_property.property_use_codes.{$index}");
        }
    }

    /** @param array<string, mixed> $term */
    private function validateTerm(array $term): void
    {
        $this->assertOnlyKeys($term, array_keys($this->empty()['term']), 'payload.term');
        foreach (['start_date', 'end_date'] as $key) {
            $this->dateOrNull($term[$key], "payload.term.{$key}", true);
        }
        $this->stringOrNull($term['duration_label'], 'payload.term.duration_label');
        if (!is_array($term['rent_due_rule'])) {
            $this->error('payload.term.rent_due_rule', 'La regla de pago debe ser un objeto.');
        }
        $this->assertOnlyKeys($term['rent_due_rule'], array_keys($this->empty()['term']['rent_due_rule']), 'payload.term.rent_due_rule');
        $this->stringOrNull($term['rent_due_rule']['raw_text'], 'payload.term.rent_due_rule.raw_text');
        foreach (['day_from', 'day_to', 'day_of_month'] as $key) {
            $this->integerOrNull($term['rent_due_rule'][$key], "payload.term.rent_due_rule.{$key}");
        }
    }

    /** @param array<string, mixed> $amounts */
    private function validateAmounts(array $amounts): void
    {
        $this->assertOnlyKeys($amounts, array_keys($this->empty()['amounts']), 'payload.amounts');
        foreach (['total_rent', 'monthly_rent', 'security_deposit', 'rental_commission', 'monthly_commission_value'] as $key) {
            $this->numericOrNull($amounts[$key], "payload.amounts.{$key}");
        }
        $this->enum($amounts['monthly_commission_unit'], self::COMMISSION_UNITS, 'payload.amounts.monthly_commission_unit');
    }

    /** @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    private function normalizePayment(array $payment): array
    {
        $this->assertOnlyKeys($payment, array_keys($this->empty()['payment']), 'payload.payment');
        $this->enum($payment['method'], self::PAYMENT_METHODS, 'payload.payment.method');
        foreach (['bank_name', 'beneficiary', 'clabe'] as $key) {
            $this->stringOrNull($payment[$key], "payload.payment.{$key}");
        }
        if ($payment['method'] !== 'bank_transfer') {
            return ['method' => $payment['method'], 'bank_name' => null, 'beneficiary' => null, 'clabe' => null];
        }

        return $payment;
    }

    /** @param array<string, mixed> $maintenance
     * @return array<string, mixed>
     */
    private function normalizeMaintenance(array $maintenance): array
    {
        $this->assertOnlyKeys($maintenance, array_keys($this->empty()['maintenance']), 'payload.maintenance');
        $this->enum($maintenance['exists'], self::YES_NO, 'payload.maintenance.exists');
        if ($maintenance['exists'] === 'no') {
            return ['exists' => 'no', 'payer' => null];
        }
        $this->enum($maintenance['payer'], self::MAINTENANCE_PAYERS, 'payload.maintenance.payer', true);

        return $maintenance;
    }

    /** @param array<string, mixed> $renewal
     * @return array<string, mixed>
     */
    private function normalizeRenewal(array $renewal): array
    {
        $this->assertOnlyKeys($renewal, array_keys($this->empty()['renewal']), 'payload.renewal');
        $this->enum($renewal['is_renewal'], self::YES_NO, 'payload.renewal.is_renewal');
        $this->integerOrNull($renewal['previous_contract_id'], 'payload.renewal.previous_contract_id');
        $this->stringOrNull($renewal['legacy_deposit_clause_snapshot'], 'payload.renewal.legacy_deposit_clause_snapshot');
        if ($renewal['deposit_treatment'] !== null && !is_array($renewal['deposit_treatment'])) {
            $this->error('payload.renewal.deposit_treatment', 'El tratamiento de depósito debe ser un objeto o null.');
        }
        if ($renewal['is_renewal'] === 'no') {
            return [
                'is_renewal' => 'no',
                'previous_contract_id' => null,
                'deposit_treatment' => null,
                'legacy_deposit_clause_snapshot' => null,
            ];
        }

        return $renewal;
    }

    /** @param array<string, mixed> $base
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function merge(array $base, array $values): array
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $base) && is_array($base[$key]) && is_array($value) && !array_is_list($base[$key])) {
                $base[$key] = $this->merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeTransportFields(array $payload): array
    {
        if (isset($payload['amounts']) && is_array($payload['amounts'])) {
            foreach (['total_rent', 'monthly_rent', 'security_deposit', 'rental_commission', 'monthly_commission_value'] as $key) {
                if (array_key_exists($key, $payload['amounts'])) {
                    $payload['amounts'][$key] = $this->normalizeMoneyTransportValue($payload['amounts'][$key]);
                }
            }
        }

        if (!isset($payload['leased_property']) || !is_array($payload['leased_property'])) {
            return $payload;
        }

        $property = $payload['leased_property'];
        if (!array_key_exists('_property_use_codes_submitted', $property)) {
            return $payload;
        }

        if ((string) $property['_property_use_codes_submitted'] !== '1') {
            $this->error('payload.leased_property._property_use_codes_submitted', 'El marcador de usos del inmueble no es válido.');
        }

        unset($property['_property_use_codes_submitted']);
        if (!array_key_exists('property_use_codes', $property)) {
            $property['property_use_codes'] = [];
        }
        $payload['leased_property'] = $property;

        return $payload;
    }

    private function normalizeMoneyTransportValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        // La UI puede presentar MXN como "$23,000.00". Conservamos el
        // importe como cadena decimal para no perder precisión al convertirlo
        // a float; los formatos ambiguos siguen fallando en numericOrNull().
        if (preg_match('/^\\$?(?:\\d{1,3}(?:,\\d{3})+|\\d+)(?:\\.\\d+)?$/', $value)) {
            return str_replace([',', '$'], '', $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function assertSubmittedShapes(array $payload): void
    {
        foreach ([
            'metadata', 'lessor', 'lessee', 'guarantor', 'leased_property', 'guarantee_property',
            'term', 'amounts', 'payment', 'maintenance', 'renewal', 'document_generation', 'audit',
        ] as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            if (!is_array($payload[$key])) {
                $this->error("payload.{$key}", 'El nodo debe ser un objeto.');
            }

            // document_generation y audit están reservados y, por ahora, sólo
            // aceptan el arreglo vacío que representa su objeto vacío V1.
            if (!in_array($key, ['document_generation', 'audit'], true) && array_is_list($payload[$key])) {
                $this->error("payload.{$key}", 'El nodo debe ser un objeto asociativo, no una lista.');
            }
        }

        foreach (['lessor', 'lessee'] as $partyKey) {
            if (!isset($payload[$partyKey])) {
                continue;
            }
            $party = $payload[$partyKey];
            if (array_key_exists('person', $party) && !is_array($party['person'])) {
                $this->error("payload.{$partyKey}.person", 'La persona debe ser un objeto.');
            }
            if (array_key_exists('representative', $party) && $party['representative'] !== null && !is_array($party['representative'])) {
                $this->error("payload.{$partyKey}.representative", 'El representante debe ser un objeto o null.');
            }
        }

        if (isset($payload['guarantor'])) {
            $guarantor = $payload['guarantor'];
            if (array_key_exists('person', $guarantor) && $guarantor['person'] !== null && !is_array($guarantor['person'])) {
                $this->error('payload.guarantor.person', 'La persona debe ser un objeto o null.');
            }
            if (array_key_exists('representative', $guarantor) && $guarantor['representative'] !== null && !is_array($guarantor['representative'])) {
                $this->error('payload.guarantor.representative', 'El representante debe ser un objeto o null.');
            }
        }

        if (isset($payload['term']) && array_key_exists('rent_due_rule', $payload['term']) && !is_array($payload['term']['rent_due_rule'])) {
            $this->error('payload.term.rent_due_rule', 'La regla de pago debe ser un objeto.');
        }
    }

    /** @param array<string, mixed> $value */
    private function assertReservedObject(array $value, string $path): void
    {
        if ($value !== []) {
            $this->error($path, 'Este nodo está reservado para una fase posterior.');
        }
    }

    /** @param list<string> $allowed */
    private function assertOnlyKeys(array $value, array $allowed, string $path): void
    {
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                $this->error($path, 'La estructura del borrador contiene una clave no compatible.');
            }
        }
    }

    private function enum(mixed $value, array $allowed, string $path, bool $nullable = false): void
    {
        if ($nullable && $value === null) {
            return;
        }
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            $this->error($path, 'El valor no pertenece al catálogo técnico permitido.');
        }
    }

    private function stringOrNull(mixed $value, string $path): void
    {
        if ($value !== null && !is_string($value)) {
            $this->error($path, 'El valor debe ser texto o null.');
        }
    }

    private function dateOrNull(mixed $value, string $path, bool $isDate = false): void
    {
        if ($value === '') {
            return;
        }
        $this->stringOrNull($value, $path);
        if ($isDate && $value !== null) {
            if (!preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $value, $matches)
                || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                $this->error($path, 'La fecha debe ser una fecha calendario válida en formato YYYY-MM-DD.');
            }
        }
    }

    private function integerOrNull(mixed $value, string $path): void
    {
        if ($value === '' || $value === null) {
            return;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->error($path, 'El valor debe ser entero o null.');
        }
    }

    private function numericOrNull(mixed $value, string $path): void
    {
        if ($value === '' || $value === null) {
            return;
        }
        if (!is_numeric($value) || !is_finite((float) $value)) {
            $this->error($path, 'El importe debe ser numérico o null.');
        }
    }

    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
