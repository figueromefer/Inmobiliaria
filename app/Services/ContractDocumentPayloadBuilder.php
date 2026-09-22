<?php

namespace App\Services;

use App\Models\ContractDraftVersion;
use DateTimeImmutable;

/**
 * Traduce un snapshot contract_data_v1 a una representación documental pura.
 * No hace I/O, no conoce credenciales y no modifica el borrador.
 */
class ContractDocumentPayloadBuilder
{
    /** @return array<string, mixed> */
    public function build(ContractDraftVersion $version): array
    {
        $payload = $version->canonical_payload;
        $guarantorType = (string) data_get($payload, 'guarantor.type', 'none');
        $templateKey = $guarantorType === 'none'
            ? 'lease_without_guarantor'
            : 'lease_with_guarantor';
        $inventory = $this->templateMarkerInventory($templateKey);
        $uses = data_get($payload, 'leased_property.property_use_codes', []);
        $missing = $this->missingDocumentData($payload, $guarantorType, $inventory);
        $usageNeedsReview = count($uses) !== 1 || in_array($uses[0] ?? null, ['other'], true);
        $placeholders = $this->placeholders($payload, $templateKey);

        return [
            'status' => $missing !== [] ? 'blocked' : ($usageNeedsReview ? 'requires_review' : 'ready'),
            'reasons' => array_values(array_filter([
                $missing !== [] ? 'Faltan datos documentales críticos: '.implode(', ', $missing).'.' : null,
                $usageNeedsReview ? 'La combinación de usos del inmueble requiere revisión jurídica antes de generar una cláusula.' : null,
            ])),
            'missing_fields' => $missing,
            'template_key' => $templateKey,
            'template_marker_inventory' => $inventory,
            'template_id' => config("services.google_contracts.templates.{$templateKey}"),
            'destination_folder_id' => config('services.google_contracts.destination_folder_id'),
            'snapshot_hash' => $version->payload_hash,
            'placeholders' => $placeholders,
            'document_operations' => $this->operations($payload, $templateKey, $guarantorType, $uses, $placeholders),
            'folder_name' => $this->folderName($payload),
            'document_name' => $this->documentName($payload),
        ];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function placeholders(array $payload, string $templateKey): array
    {
        $placeholders = [
            '{{arrendador}}' => $this->partyName($payload, 'lessor'),
            '{{arrendador_rfc}}' => $this->stringAt($payload, 'lessor.person.rfc'),
            '{{arrendatario}}' => $this->partyName($payload, 'lessee'),
            '{{arrendatario_rfc}}' => $this->stringAt($payload, 'lessee.person.rfc'),
            '{{inmueble_arrendado}}' => $this->stringAt($payload, 'leased_property.address'),
            '{{tipo}}' => $this->propertyUseLabel(data_get($payload, 'leased_property.property_use_codes', [])),
            '{{dia_pago}}' => $this->stringAt($payload, 'term.rent_due_rule.raw_text'),
            '{{fecha_inicial}}' => $this->spanishDate($this->stringAt($payload, 'term.start_date')),
            '{{fecha_final}}' => $this->spanishDate($this->stringAt($payload, 'term.end_date')),
            '{{fecha_firma}}' => $this->spanishDate($this->stringAt($payload, 'metadata.contract_date')),
            '{{vigencia}}' => $this->stringAt($payload, 'term.duration_label'),
            '{{parcialidades}}' => $this->stringAt($payload, 'term.duration_label'),
            '{{monto}}' => $this->money($payload, 'amounts.total_rent'),
            '{{monto_letra}}' => $this->moneyWords(data_get($payload, 'amounts.total_rent')),
            '{{monto_mensualidad}}' => $this->money($payload, 'amounts.monthly_rent'),
            '{{monto_mensualidad_letra}}' => $this->moneyWords(data_get($payload, 'amounts.monthly_rent')),
            '{{forma_pago}}' => $this->paymentText($this->stringAt($payload, 'payment.method')),
            '{{banco}}' => $this->bankValue($payload, 'bank_name'),
            '{{beneficiario}}' => $this->bankValue($payload, 'beneficiary'),
            '{{clabe}}' => $this->bankValue($payload, 'clabe'),
        ];

        foreach (['lessor' => 'arrendador', 'lessee' => 'arrendatario'] as $path => $label) {
            $this->addPartyPlaceholders($placeholders, $payload, $path, $label);
        }
        if ($templateKey === 'lease_with_guarantor') {
            $placeholders['{{fiador}}'] = $this->partyName($payload, 'guarantor');
            $placeholders['{{fiador_rfc}}'] = $this->stringAt($payload, 'guarantor.person.rfc');
            $this->addPartyPlaceholders($placeholders, $payload, 'guarantor', 'fiador');
        }

        return $placeholders;
    }

    /** @param array<string, string> $placeholders
     * @param array<string, mixed> $payload
     */
    private function addPartyPlaceholders(array &$placeholders, array $payload, string $path, string $label): void
    {
        $fields = ['nationality' => 'nacionalidad', 'birth_place' => 'lugar_nacimiento', 'birth_date' => 'fecha_nacimiento', 'marital_status' => 'estado_civil', 'occupation' => 'ocupacion', 'address' => 'domicilio', 'identification_type' => 'identificacion', 'phone' => 'telefono', 'email' => 'correo', 'incorporation_deed' => 'acta_constitutiva'];
        foreach ($fields as $field => $legacy) {
            $value = $this->stringAt($payload, "{$path}.person.{$field}");
            $placeholders['{{'.$label.'_'.$legacy.'}}'] = $field === 'birth_date' ? $this->spanishDate($value) : $value;
        }

        $representative = "representante_{$label}";
        $placeholders['{{'.$representative.'}}'] = $this->stringAt($payload, "{$path}.representative.full_name");
        $placeholders['{{'.$representative.'_nacionalidad}}'] = $this->stringAt($payload, "{$path}.representative.nationality");
        $placeholders['{{'.$representative.'_ocupacion}}'] = $this->stringAt($payload, "{$path}.representative.occupation");
        $placeholders['{{'.$representative.'_identificacion}}'] = $this->stringAt($payload, "{$path}.representative.identification_type");
        $placeholders['{{'.$representative.'_acta_facultades}}'] = $this->stringAt($payload, "{$path}.representative.authority_deed");
        $placeholders['{{'.$representative.'_lugar_fecha_nacimiento}}'] = trim(implode(' ', array_filter([$this->stringAt($payload, "{$path}.representative.birth_place"), $this->spanishDate($this->stringAt($payload, "{$path}.representative.birth_date"))])));

        if ($this->stringAt($payload, "{$path}.person.person_type") === 'moral') {
            $signature = $label.'_representante';
            $placeholders['{{'.$signature.'}}'] = $this->stringAt($payload, "{$path}.representative.full_name");
        }
    }

    /** @param array<string, mixed> $payload
     * @param list<string> $uses
     * @param array<string, string> $placeholders
     * @return list<array<string, mixed>>
     */
    private function operations(array $payload, string $templateKey, string $guarantorType, array $uses, array $placeholders): array
    {
        $payment = $this->stringAt($payload, 'payment.method');
        $maintenance = $this->stringAt($payload, 'maintenance.exists');
        $renewal = $this->stringAt($payload, 'renewal.is_renewal');
        $guarantee = $this->stringAt($payload, 'guarantee_property.exists');

        $operations = [
            ['operation' => 'replace_placeholders', 'target_type' => 'text_placeholders', 'identifier' => 'document_body', 'action' => 'replace', 'content' => $placeholders],
        ];
        $operations = [...$operations, ...$this->partyOperations('lessor', $this->stringAt($payload, 'lessor.person.person_type'), ['__T1__', '__T2__', '__T3__'], ['__I1__', '__I2__'], 'Que es una persona física, mayor de edad, quien manifiesta que tiene facultades para dar en arrendamiento el inmueble identificado en el capítulo de GENERALES del presente instrumento', '{{arrendador_representante}}')];
        $operations = [...$operations, ...$this->partyOperations('lessee', $this->stringAt($payload, 'lessee.person.person_type'), ['__T4__', '__T5__', '__T6__'], ['__I3__', '__I4__'], 'Que es una persona física, mayor de edad y al corriente del pago de todas sus obligaciones fiscales, que cuenta con capacidad económica suficiente y de origen lícito, para contratar y obligarse en los términos y condiciones del presente contrato', '{{arrendatario_representante}}')];
        if ($templateKey === 'lease_with_guarantor') {
            $operations = [...$operations, ...$this->guarantorOperations($guarantorType)];
        }
        $operations[] = ['operation' => 'replace_marker', 'target_type' => 'clause', 'marker' => '{{clausula_deposito}}', 'action' => 'replace_and_remove_marker', 'content' => $renewal === 'yes' ? $this->renewalDepositClause($payload) : $this->normalDepositClause($payload)];
        if ($templateKey === 'lease_with_guarantor') {
            $operations[] = ['operation' => $guarantee === 'yes' ? 'replace_marker' : 'remove_marker', 'target_type' => 'clause', 'marker' => '{{si_inmueble}}', 'action' => $guarantee === 'yes' ? 'insert_and_remove_marker' : 'remove_marker', 'content' => $guarantee === 'yes' ? $this->guaranteeClause($payload) : ''];
        }
        $operations[] = ['operation' => $payment === 'bank_transfer' ? 'preserve_table' : 'remove_table', 'target_type' => 'table', 'identifier' => 'INSTITUCIÓN BANCARIA', 'action' => $payment === 'bank_transfer' ? 'preserve' : 'remove'];
        $operations[] = ['operation' => 'replace_marker', 'target_type' => 'text_placeholder', 'marker' => '{{forma_pago}}', 'action' => 'replace', 'content' => $this->paymentText($payment)];
        $operations[] = ['operation' => $maintenance === 'yes' ? 'replace_marker' : 'remove_marker', 'target_type' => 'clause', 'marker' => '{{mantenimiento_quien}}', 'action' => $maintenance === 'yes' ? 'insert_and_remove_marker' : 'remove_marker', 'content' => $maintenance === 'yes' ? $this->maintenanceClause($this->stringAt($payload, 'maintenance.payer')) : ''];
        $requiresUseReview = count($uses) !== 1 || ($uses[0] ?? null) === 'other';
        $operations[] = ['operation' => $uses === ['residential'] ? 'remove_marker' : ($requiresUseReview ? 'requires_review' : 'replace_marker'), 'target_type' => 'clause', 'marker' => '{{uso_inmueble}}', 'action' => $uses === ['residential'] ? 'remove_marker' : ($requiresUseReview ? 'no_action' : 'insert_and_remove_marker'), 'content' => !$requiresUseReview && count($uses) === 1 && $uses !== ['residential'] ? $this->nonResidentialClauses() : '', 'codes' => $uses];

        return $operations;
    }

    /** @param array<string, mixed> $payload
     * @return list<string>
     */
    private function missingDocumentData(array $payload, string $guarantorType, array $inventory): array
    {
        $required = ['domicilio del inmueble' => $this->stringAt($payload, 'leased_property.address'), 'uso del inmueble' => data_get($payload, 'leased_property.property_use_codes', []), 'inicio de vigencia' => $this->stringAt($payload, 'term.start_date'), 'fin de vigencia' => $this->stringAt($payload, 'term.end_date'), 'vigencia' => $this->stringAt($payload, 'term.duration_label'), 'días de pago' => $this->stringAt($payload, 'term.rent_due_rule.raw_text'), 'renta total' => data_get($payload, 'amounts.total_rent'), 'renta mensual' => data_get($payload, 'amounts.monthly_rent'), 'depósito en garantía' => data_get($payload, 'amounts.security_deposit'), 'fecha de firma' => $this->stringAt($payload, 'metadata.contract_date')];
        $missing = array_keys(array_filter($required, static fn (mixed $value): bool => $value === null || $value === '' || $value === []));
        $missing = [...$missing, ...$this->partyMissing($payload, 'lessor', 'arrendador') , ...$this->partyMissing($payload, 'lessee', 'arrendatario')];
        if ($guarantorType !== 'none') $missing = [...$missing, ...$this->partyMissing($payload, 'guarantor', 'tercero/fiador')];
        if (in_array('{{si_inmueble}}', $inventory['required_markers'], true) && $this->stringAt($payload, 'guarantee_property.exists') === 'yes') foreach (['address' => 'domicilio', 'title_deed' => 'título'] as $field => $label) if ($this->stringAt($payload, "guarantee_property.{$field}") === '') $missing[] = "garantía: {$label}";
        if ($this->stringAt($payload, 'payment.method') === 'bank_transfer') foreach (['bank_name' => 'banco', 'beneficiary' => 'beneficiario', 'clabe' => 'CLABE'] as $field => $label) if ($this->stringAt($payload, "payment.{$field}") === '') $missing[] = "transferencia: {$label}";
        if ($this->stringAt($payload, 'maintenance.exists') === 'yes' && $this->stringAt($payload, 'maintenance.payer') === '') $missing[] = 'mantenimiento: pagador';

        return array_values(array_unique($missing));
    }

    /** @param array<string, mixed> $payload
     * @return list<string>
     */
    private function partyMissing(array $payload, string $path, string $label): array
    {
        $type = $path === 'guarantor' ? $this->stringAt($payload, 'guarantor.type') : $this->stringAt($payload, "{$path}.person.person_type");
        $fields = $type === 'moral'
            ? ['legal_name' => 'razón social', 'rfc' => 'RFC', 'address' => 'domicilio', 'phone' => 'teléfono', 'email' => 'correo', 'incorporation_deed' => 'acta constitutiva']
            : ['full_name' => 'nombre', 'rfc' => 'RFC', 'nationality' => 'nacionalidad', 'birth_place' => 'lugar de nacimiento', 'birth_date' => 'fecha de nacimiento', 'marital_status' => 'estado civil', 'occupation' => 'ocupación', 'address' => 'domicilio', 'identification_type' => 'identificación', 'phone' => 'teléfono', 'email' => 'correo'];
        $missing = [];
        foreach ($fields as $field => $fieldLabel) if ($this->stringAt($payload, "{$path}.person.{$field}") === '') $missing[] = "{$label}: {$fieldLabel}";
        if ($type === 'moral') foreach (['full_name' => 'nombre', 'nationality' => 'nacionalidad', 'birth_place' => 'lugar de nacimiento', 'birth_date' => 'fecha de nacimiento', 'occupation' => 'ocupación', 'address' => 'domicilio', 'identification_type' => 'identificación', 'authority_deed' => 'acta de facultades'] as $field => $fieldLabel) if ($this->stringAt($payload, "{$path}.representative.{$field}") === '') $missing[] = "representante {$label}: {$fieldLabel}";

        return $missing;
    }

    /** @param list<string> $tableMarkers
     * @param list<string> $paragraphMarkers
     * @return list<array<string, mixed>>
     */
    private function partyOperations(string $party, string $type, array $tableMarkers, array $paragraphMarkers, string $physicalDeclaration, string $legacySignatureMarker): array
    {
        [$physical, $moral, $representative] = $tableMarkers;
        $operations = $type === 'moral'
            ? [['operation' => 'remove_table', 'target_type' => 'table', 'marker' => $physical, 'action' => 'remove'], ['operation' => 'remove_marker', 'target_type' => 'table_marker', 'marker' => $moral, 'action' => 'remove_marker'], ['operation' => 'remove_marker', 'target_type' => 'table_marker', 'marker' => $representative, 'action' => 'remove_marker']]
            : [['operation' => 'remove_table', 'target_type' => 'table', 'marker' => $moral, 'action' => 'remove'], ['operation' => 'remove_table_with_preceding_title', 'target_type' => 'table', 'marker' => $representative, 'action' => 'remove'], ['operation' => 'remove_marker', 'target_type' => 'table_marker', 'marker' => $physical, 'action' => 'remove_marker']];
        if ($type === 'moral') {
            $operations[] = ['operation' => 'remove_paragraph_containing', 'target_type' => 'paragraph', 'marker' => $physicalDeclaration, 'action' => 'remove'];
            foreach ($paragraphMarkers as $marker) $operations[] = ['operation' => 'remove_marker', 'target_type' => 'paragraph_marker', 'marker' => $marker, 'action' => 'remove_marker'];
        } else {
            foreach ($paragraphMarkers as $marker) $operations[] = ['operation' => 'remove_paragraph_containing', 'target_type' => 'paragraph', 'marker' => $marker, 'action' => 'remove'];
        }
        if ($type === 'fisica') {
            $operations[] = ['operation' => 'remove_text', 'target_type' => 'signature', 'marker' => 'POR CONDUCTO DE SU REPRESENTANTE LEGAL '.$legacySignatureMarker, 'action' => 'remove'];
            $operations[] = ['operation' => 'remove_text', 'target_type' => 'signature', 'marker' => 'POR CONDUCTO DE SU REPRESENTANTE LEGAL', 'action' => 'remove'];
            $operations[] = ['operation' => 'remove_marker', 'target_type' => 'signature_placeholder', 'marker' => $legacySignatureMarker, 'action' => 'remove_marker'];
        }

        return $operations;
    }

    /** @return list<array<string, mixed>> */
    private function guarantorOperations(string $type): array
    {
        if ($type === 'none') return [];

        return $this->partyOperations('guarantor', $type, ['__T7__', '__T8__', '__T9__'], ['__I5__', '__I6__'], 'Que es una persona física, mayor de edad y al corriente del pago de todas sus obligaciones fiscales, que cuenta con capacidad económica suficiente y de origen lícito, para garantizar el cumplimiento de las obligaciones del presente contrato', '{{fiador_representante}}');
    }

    /**
     * Inventario físico confirmado en el gate de plantillas. No contiene
     * placeholders legacy que ya no existen en las plantillas activas.
     *
     * @return array{template_key:string,required_markers:list<string>,optional_markers:list<string>,not_applicable_markers:list<string>}
     */
    public function templateMarkerInventory(string $templateKey): array
    {
        $common = [
            '__T1__', '__T2__', '__T3__', '__T4__', '__T5__', '__T6__',
            '__I1__', '__I2__', '__I3__', '__I4__',
            '{{arrendador}}', '{{arrendador_rfc}}', '{{arrendador_nacionalidad}}', '{{arrendador_lugar_nacimiento}}', '{{arrendador_fecha_nacimiento}}', '{{arrendador_ocupacion}}', '{{arrendador_estado_civil}}', '{{arrendador_domicilio}}', '{{arrendador_telefono}}', '{{arrendador_correo}}', '{{arrendador_identificacion}}', '{{arrendador_acta_constitutiva}}',
            '{{arrendatario}}', '{{arrendatario_rfc}}', '{{arrendatario_nacionalidad}}', '{{arrendatario_lugar_nacimiento}}', '{{arrendatario_fecha_nacimiento}}', '{{arrendatario_ocupacion}}', '{{arrendatario_estado_civil}}', '{{arrendatario_domicilio}}', '{{arrendatario_telefono}}', '{{arrendatario_correo}}', '{{arrendatario_identificacion}}', '{{arrendatario_acta_constitutiva}}',
            '{{representante_arrendador}}', '{{representante_arrendador_nacionalidad}}', '{{representante_arrendador_ocupacion}}', '{{representante_arrendador_lugar_fecha_nacimiento}}', '{{representante_arrendador_acta_facultades}}', '{{representante_arrendador_identificacion}}',
            '{{representante_arrendatario}}', '{{representante_arrendatario_nacionalidad}}', '{{representante_arrendatario_ocupacion}}', '{{representante_arrendatario_lugar_fecha_nacimiento}}', '{{representante_arrendatario_acta_facultades}}', '{{representante_arrendatario_identificacion}}',
            '{{arrendador_representante}}', '{{arrendatario_representante}}', '{{inmueble_arrendado}}', '{{tipo}}', '{{dia_pago}}', '{{fecha_inicial}}', '{{fecha_final}}', '{{fecha_firma}}', '{{vigencia}}', '{{parcialidades}}', '{{monto}}', '{{monto_letra}}', '{{monto_mensualidad}}', '{{monto_mensualidad_letra}}', '{{forma_pago}}', '{{banco}}', '{{beneficiario}}', '{{clabe}}', '{{clausula_deposito}}', '{{mantenimiento_quien}}', '{{uso_inmueble}}', 'INSTITUCIÓN BANCARIA',
        ];
        $guarantor = [
            '__T7__', '__T8__', '__T9__', '__I5__', '__I6__', '{{fiador}}', '{{fiador_rfc}}', '{{fiador_nacionalidad}}', '{{fiador_lugar_nacimiento}}', '{{fiador_fecha_nacimiento}}', '{{fiador_ocupacion}}', '{{fiador_estado_civil}}', '{{fiador_domicilio}}', '{{fiador_telefono}}', '{{fiador_correo}}', '{{fiador_identificacion}}', '{{fiador_acta_constitutiva}}', '{{representante_fiador}}', '{{representante_fiador_nacionalidad}}', '{{representante_fiador_ocupacion}}', '{{representante_fiador_lugar_fecha_nacimiento}}', '{{representante_fiador_acta_facultades}}', '{{representante_fiador_identificacion}}', '{{fiador_representante}}', '{{si_inmueble}}',
        ];
        $legacyAbsent = ['{{garantia}}', '{{garantia_monto}}'];

        if ($templateKey === 'lease_with_guarantor') {
            return ['template_key' => $templateKey, 'required_markers' => [...$common, ...$guarantor], 'optional_markers' => [], 'not_applicable_markers' => $legacyAbsent];
        }

        return ['template_key' => 'lease_without_guarantor', 'required_markers' => $common, 'optional_markers' => [], 'not_applicable_markers' => [...$guarantor, ...$legacyAbsent]];
    }

    /** @param array<string, mixed> $payload */
    private function normalDepositClause(array $payload): string
    {
        $amount = $this->documentAmount($payload, 'amounts.security_deposit');
        $words = $this->moneyWords(data_get($payload, 'amounts.security_deposit'));
        return "2.1\tEL ARRENDATARIO entrega y EL ARRENDADOR recibe a su entera satisfacción, precisamente a la firma del presente contrato, la cantidad de {$amount} ({$words}), por concepto de depósito en garantía, cantidad que al término de vigencia del presente contrato, contra devolución de la posesión del inmueble arrendado, y comprobar que no existen adeudos por RENTA o de sus elementos constitutivos no pagadas, y el pago de servicios o desperfectos que en su caso se hubieren causado, le será devuelta a EL ARRENDATARIO, sin intereses. La firma de este contrato hace de veces de recibo por parte de EL ARRENDADOR, respecto de la cantidad establecida como depósito.";
    }

    /** @param array<string, mixed> $payload */
    private function renewalDepositClause(array $payload): string
    {
        $amount = $this->documentAmount($payload, 'amounts.security_deposit');
        $words = $this->moneyWords(data_get($payload, 'amounts.security_deposit'));
        return "2.1\tLAS PARTES acuerdan que subsistirá el depósito en garantía que fue entregado por EL ARRENDATARIO mediante el primer convenio de arrendamiento por la cantidad de {$amount} ({$words}), cantidad que al término de vigencia del presente convenio, contra devolución de la posesión del inmueble arrendado, y comprobar que no existen adeudos por RENTA o de sus elementos constitutivos no pagadas, y el pago de servicios o desperfectos que en su caso se hubieren causado, le será devuelta a EL ARRENDATARIO en un término no mayor a 30 días, sin intereses.";
    }

    /** @param array<string, mixed> $payload */
    private function guaranteeClause(array $payload): string
    {
        return implode("\n\n", [
            '13.3\tEL FIADOR acredita su solvencia económica manifestando bajo protesta de decir verdad, ser propietario del siguiente inmueble: '.$this->stringAt($payload, 'guarantee_property.title_deed'),
            $this->stringAt($payload, 'guarantee_property.address'),
            'Por lo anterior, EL FIADOR, manifiesta en este acto que el inmueble antes mencionado se encuentra a su nombre y libre de todo gravamen o limitación alguna, por lo que se obliga que mientras tenga vigencia el presente convenio, o se encuentre en posesión EL ARRENDATARIO de EL INMUEBLE dado en arrendamiento o exista algún adeudo pendiente de pago derivado del ARRENDAMIENTO y a cargo de EL ARRENDATARIO, no gravara, cederá, enajenará o trasmitirá de alguna forma el bien antes descrito, ya que es la garantía que otorga para el cumplimiento de las obligaciones contraídas por EL ARRENDATARIO.',
        ]);
    }

    private function maintenanceClause(string $payer): string
    {
        return $payer === 'lessor'
            ? '1.4BIS\tPor lo que respecta a la cuota de mantenimiento serán cubiertas por EL ARRENDADOR. En este sentido, las partes establecen que en caso de que el monto de la cuota condominal incremente a lo largo de la vigencia del convenio, LA RENTA incrementará por el monto que incremente la cuota condominal.'
            : '1.4BIS\tPor lo que respecta a la cuota de mantenimiento serán cubiertas por EL ARRENDATARIO.';
    }

    private function nonResidentialClauses(): string
    {
        return implode("\n\n", [
            '4.7\tEn virtud de la responsabilidad a que refiere la cláusula inmediata anterior, EL ARRENDATARIO tendrá la obligación de contratar el o los seguros para cubrir los siniestros que pueda llegar a sufrir el inmueble, así como para los daños y perjuicios que se pudiesen causar, en el entendido de que el beneficiario de dichas pólizas de seguro, será EL ARRENDADOR o quien éste designe.',
            '4.8\tEL ARRENDATARIO, se obliga a obtener por su cuenta y costo todos los permisos y licencias que sean necesarios para la operación del negocio, así como asumir cualquier responsabilidad derivada de su operación, por tal motivo EL ARRENDADOR autoriza a EL ARRENDATARIO para que tramite ante las Autoridades Administrativas (Federales, Estatales y Municipales), los permisos necesarios para la operación correspondiente al giro aquí convenido. EL ARRENDATARIO se obliga a entregar a EL ARRENDADOR las constancias de BAJA TOTAL de las licencias y/o permisos que se hayan obtenido para el giro y uso de EL INMUEBLE.',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function partyName(array $payload, string $path): string { return $this->stringAt($payload, "{$path}.person.full_name") ?: $this->stringAt($payload, "{$path}.person.legal_name"); }
    /** @param array<string, mixed> $payload */
    private function stringAt(array $payload, string $path): string { $value = data_get($payload, $path); return is_scalar($value) ? (string) $value : ''; }
    /** @param array<string, mixed> $payload */
    private function money(array $payload, string $path): string { return $this->documentAmount($payload, $path); }
    /** El DTO V1 hoy no conserva display_value: se conserva el decimal canónico. */
    private function documentAmount(array $payload, string $path): string { $value = data_get($payload, $path); return is_scalar($value) ? (string) $value : ''; }
    private function spanishDate(string $value): string { if ($value === '') return ''; try { $date = new DateTimeImmutable($value); } catch (\Exception) { return ''; } $months = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE']; return $date->format('d').' DE '.$months[(int) $date->format('n') - 1].' DEL '.$date->format('Y'); }
    private function paymentText(string $method): string { return match ($method) { 'cash' => 'en efectivo en el domicilio de EL ARRENDADOR', 'bank_transfer' => 'mediante depósito bancario/ transferencia en la siguiente cuenta:', default => 'en efectivo en el domicilio de EL ARRENDADOR o mediante depósito bancario/ transferencia en la cuenta que señale EL ARRENDADOR.' }; }
    /** @param list<string> $uses */
    private function propertyUseLabel(array $uses): string { $labels = ['residential' => 'Casa Habitación', 'industrial' => 'Industrial', 'commercial' => 'Comercial', 'other' => 'Otro']; return implode(', ', array_map(static fn (string $use): string => $labels[$use] ?? $use, $uses)); }
    /** @param array<string, mixed> $payload */
    private function bankValue(array $payload, string $field): string { return $this->stringAt($payload, 'payment.method') === 'bank_transfer' ? $this->stringAt($payload, "payment.{$field}") : ''; }
    private function moneyWords(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $normalized = str_replace(['$', ','], '', (string) $value);
        if (!is_numeric($normalized)) return '';
        $parts = explode('.', number_format((float) $normalized, 2, '.', ''));

        return $this->numberToWords((int) $parts[0]).' pesos '.$parts[1].'/100 MN';
    }

    /** Reimplementación local, determinista, del formato legado hasta millones. */
    private function numberToWords(int $number): string
    {
        if ($number === 0) return 'Cero';
        if ($number === 100) return 'Cien';
        $parts = [];
        if ($number >= 1000000) { $millions = intdiv($number, 1000000); $parts[] = $millions === 1 ? 'un millón' : $this->numberToWords($millions).' millones'; $number %= 1000000; }
        if ($number >= 1000) { $thousands = intdiv($number, 1000); $parts[] = $thousands === 1 ? 'mil' : $this->numberToWords($thousands).' mil'; $number %= 1000; }
        if ($number > 0) $parts[] = $this->wordGroup($number);

        return mb_convert_case(trim(implode(' ', $parts)), MB_CASE_TITLE, 'UTF-8');
    }

    private function wordGroup(int $number): string
    {
        $units = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $teens = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $twenties = ['veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
        $tens = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $hundreds = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
        $words = [];
        if ($number >= 100) { $words[] = $hundreds[intdiv($number, 100)]; $number %= 100; }
        if ($number >= 30) { $words[] = $tens[intdiv($number, 10)].($number % 10 ? ' y '.$units[$number % 10] : ''); }
        elseif ($number >= 20) $words[] = $twenties[$number - 20];
        elseif ($number >= 10) $words[] = $teens[$number - 10];
        elseif ($number > 0) $words[] = $units[$number];

        return trim(implode(' ', $words));
    }
    /** @param array<string, mixed> $payload */
    private function folderName(array $payload): string { return trim('Contrato '.($this->partyName($payload, 'lessor') ?: 'sin arrendador')); }
    /** @param array<string, mixed> $payload */
    private function documentName(array $payload): string { return trim('Contrato - '.($this->stringAt($payload, 'leased_property.alias') ?: 'sin alias')); }
}
