<?php

namespace Tests\Feature;

use App\Models\ContractDraftVersion;
use App\Services\ContractDocumentPayloadBuilder;
use App\Services\ContractDraftPayload;
use Tests\TestCase;

class ContractDocumentPayloadBuilderTest extends TestCase
{
    public function test_template_selection_uses_only_guarantor_type(): void
    {
        $this->assertSame('lease_without_guarantor', $this->build($this->completePayload())['template_key']);
        foreach (['fisica', 'moral'] as $type) {
            $payload = $this->completePayload();
            $payload['guarantor'] = ['type' => $type, ...$this->party($type, 'Fiador')];
            $this->assertSame('lease_with_guarantor', $this->build($payload)['template_key']);
        }
    }

    public function test_operations_use_literal_legacy_markers_and_deposit_clauses(): void
    {
        $normal = $this->build($this->completePayload())['document_operations'];
        $this->assertTrue($this->hasOperation($normal, '{{clausula_deposito}}', 'replace_marker'));
        $this->assertStringContainsString('recibe a su entera satisfacción', $this->operationContent($normal, '{{clausula_deposito}}'));
        $this->assertTrue($this->hasOperation($normal, '__T1__', 'remove_marker'));
        $this->assertTrue($this->hasOperation($normal, 'INSTITUCIÓN BANCARIA', 'remove_table'));
        $this->assertTrue($this->hasOperation($normal, '{{mantenimiento_quien}}', 'remove_marker'));
        $this->assertTrue($this->hasOperation($normal, '{{uso_inmueble}}', 'remove_marker'));
        $this->assertFalse($this->hasMarker($normal, '{{si_inmueble}}'));

        $renewal = $this->completePayload();
        $renewal['renewal']['is_renewal'] = 'yes';
        $operations = $this->build($renewal)['document_operations'];
        $this->assertStringContainsString('subsistirá el depósito', $this->operationContent($operations, '{{clausula_deposito}}'));
    }

    public function test_builder_blocks_each_incomplete_document_branch(): void
    {
        $cases = [
            ['lessee.person.address', null, 'arrendatario: domicilio'],
            ['guarantee_property.address', null, 'garantía: domicilio'],
            ['payment.bank_name', null, 'transferencia: banco'],
            ['maintenance.payer', null, 'mantenimiento: pagador'],
            ['amounts.total_rent', null, 'renta total'],
            ['amounts.security_deposit', null, 'depósito en garantía'],
            ['term.duration_label', null, 'vigencia'],
            ['term.rent_due_rule.raw_text', null, 'días de pago'],
        ];
        foreach ($cases as [$path, $value, $missing]) {
            $payload = $this->completePayload();
            if (str_starts_with($path, 'guarantee_property')) {
                $payload['guarantor'] = ['type' => 'fisica', ...$this->party('fisica', 'Fiador')];
                $payload['guarantee_property'] = ['exists' => 'yes', 'address' => 'D', 'title_deed' => 'T'];
            }
            if (str_starts_with($path, 'payment')) $payload['payment'] = ['method' => 'bank_transfer', 'bank_name' => 'Banco', 'beneficiary' => 'B', 'clabe' => '123'];
            if (str_starts_with($path, 'maintenance')) $payload['maintenance'] = ['exists' => 'yes', 'payer' => 'lessor'];
            data_set($payload, $path, $value);
            $document = $this->build($payload);
            $this->assertSame('blocked', $document['status']);
            $this->assertContains($missing, $document['missing_fields']);
        }
    }

    public function test_ready_document_formats_like_legacy_and_preserves_raw_amount(): void
    {
        $payload = $this->completePayload();
        $payload['amounts']['monthly_rent'] = '12,500.50';
        $document = $this->build($payload);

        $this->assertSame('ready', $document['status']);
        $this->assertSame('15 DE SEPTIEMBRE DEL 2026', $document['placeholders']['{{fecha_firma}}']);
        $this->assertSame('12,500.50', $document['placeholders']['{{monto_mensualidad}}']);
        $this->assertSame('Doce Mil Quinientos pesos 50/100 MN', $document['placeholders']['{{monto_mensualidad_letra}}']);
        $this->assertSame('05 a 10', $document['placeholders']['{{dia_pago}}']);
    }

    public function test_legacy_date_format_handles_month_changes_and_leap_dates(): void
    {
        $payload = $this->completePayload();
        $payload['metadata']['contract_date'] = '2028-02-29';
        $payload['term']['start_date'] = '2026-01-01';
        $payload['term']['end_date'] = '2026-12-31';
        $document = $this->build($payload);

        $this->assertSame('29 DE FEBRERO DEL 2028', $document['placeholders']['{{fecha_firma}}']);
        $this->assertSame('01 DE ENERO DEL 2026', $document['placeholders']['{{fecha_inicial}}']);
        $this->assertSame('31 DE DICIEMBRE DEL 2026', $document['placeholders']['{{fecha_final}}']);
    }

    public function test_pm_guarantor_guarantee_transfer_maintenance_and_non_residential_are_ready(): void
    {
        $payload = $this->completePayload('moral', 'moral');
        $payload['guarantor'] = ['type' => 'moral', ...$this->party('moral', 'Fiador PM')];
        $payload['guarantee_property'] = ['exists' => 'yes', 'address' => 'Garantía', 'title_deed' => 'Escritura'];
        $payload['payment'] = ['method' => 'bank_transfer', 'bank_name' => 'Banco', 'beneficiary' => 'Beneficiario', 'clabe' => '012345678901234567'];
        $payload['maintenance'] = ['exists' => 'yes', 'payer' => 'lessee'];
        $payload['leased_property']['property_use_codes'] = ['commercial'];
        $document = $this->build($payload);

        $this->assertSame('ready', $document['status']);
        $this->assertSame('lease_with_guarantor', $document['template_key']);
        $this->assertTrue($this->hasOperation($document['document_operations'], '{{si_inmueble}}', 'replace_marker'));
        $this->assertTrue($this->hasOperation($document['document_operations'], 'INSTITUCIÓN BANCARIA', 'preserve_table'));
        $this->assertTrue($this->hasOperation($document['document_operations'], '{{mantenimiento_quien}}', 'replace_marker'));
        $this->assertTrue($this->hasOperation($document['document_operations'], '{{uso_inmueble}}', 'replace_marker'));
    }

    public function test_template_marker_inventory_and_operations_are_specific_to_the_selected_template(): void
    {
        $without = $this->build($this->completePayload());

        $this->assertSame('lease_without_guarantor', $without['template_marker_inventory']['template_key']);
        $this->assertContains('{{clausula_deposito}}', $without['template_marker_inventory']['required_markers']);
        $this->assertContains('{{garantia}}', $without['template_marker_inventory']['not_applicable_markers']);
        $this->assertContains('{{garantia_monto}}', $without['template_marker_inventory']['not_applicable_markers']);
        foreach (['__T7__', '__T8__', '__T9__', '__I5__', '__I6__', '{{fiador}}', '{{representante_fiador}}', '{{fiador_representante}}', '{{si_inmueble}}'] as $marker) {
            $this->assertContains($marker, $without['template_marker_inventory']['not_applicable_markers']);
            $this->assertFalse($this->hasMarker($without['document_operations'], $marker));
            $this->assertArrayNotHasKey($marker, $without['placeholders']);
        }
        $this->assertArrayNotHasKey('{{garantia}}', $without['placeholders']);
        $this->assertArrayNotHasKey('{{garantia_monto}}', $without['placeholders']);
        $this->assertFalse($this->hasMarker($without['document_operations'], 'lease_with_guarantor'));

        $withPayload = $this->completePayload();
        $withPayload['guarantor'] = ['type' => 'moral', ...$this->party('moral', 'Fiador PM')];
        $with = $this->build($withPayload);
        $this->assertSame('lease_with_guarantor', $with['template_marker_inventory']['template_key']);
        foreach (['__T7__', '__T8__', '__T9__', '__I5__', '__I6__', '{{fiador}}', '{{representante_fiador}}', '{{fiador_representante}}', '{{si_inmueble}}'] as $marker) {
            $this->assertContains($marker, $with['template_marker_inventory']['required_markers']);
        }
    }

    public function test_representative_data_and_signature_placeholders_remain_separate(): void
    {
        $payload = $this->completePayload('moral', 'moral');
        $payload['guarantor'] = ['type' => 'moral', ...$this->party('moral', 'Fiador PM')];
        $document = $this->build($payload);

        $this->assertSame('Representante', $document['placeholders']['{{representante_arrendador}}']);
        $this->assertSame('Representante', $document['placeholders']['{{arrendador_representante}}']);
        $this->assertSame('Representante', $document['placeholders']['{{representante_arrendatario}}']);
        $this->assertSame('Representante', $document['placeholders']['{{arrendatario_representante}}']);
        $this->assertSame('Representante', $document['placeholders']['{{representante_fiador}}']);
        $this->assertSame('Representante', $document['placeholders']['{{fiador_representante}}']);
    }

    public function test_person_declaration_operations_remove_the_incompatible_paragraph_instead_of_only_its_marker(): void
    {
        $physical = $this->build($this->completePayload());
        $this->assertTrue($this->hasOperation($physical['document_operations'], '__I1__', 'remove_paragraph_containing'));
        $this->assertTrue($this->hasOperation($physical['document_operations'], '__I2__', 'remove_paragraph_containing'));

        $moral = $this->build($this->completePayload('moral', 'moral'));
        $this->assertTrue($this->hasOperationContaining($moral['document_operations'], 'Que es una persona física, mayor de edad, quien manifiesta', 'remove_paragraph_containing'));
        $this->assertTrue($this->hasOperation($moral['document_operations'], '__I1__', 'remove_marker'));
        $this->assertTrue($this->hasOperationContaining($moral['document_operations'], 'Que es una persona física, mayor de edad y al corriente', 'remove_paragraph_containing'));
        $this->assertTrue($this->hasOperation($moral['document_operations'], '__I3__', 'remove_marker'));
    }

    public function test_multiuse_and_other_require_review_without_arbitrary_clause(): void
    {
        foreach ([['residential', 'commercial'], ['other']] as $uses) {
            $payload = $this->completePayload();
            $payload['leased_property']['property_use_codes'] = $uses;
            $document = $this->build($payload);
            $this->assertSame('requires_review', $document['status']);
            $this->assertTrue($this->hasOperation($document['document_operations'], '{{uso_inmueble}}', 'requires_review'));
        }
    }

    public function test_anonymized_golden_snapshot_matches(): void
    {
        $document = $this->build($this->completePayload());
        $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/contract-document/ready-without-guarantor.json')), true, 512, JSON_THROW_ON_ERROR);
        $projection = ['status' => $document['status'], 'template_key' => $document['template_key'], 'folder_name' => $document['folder_name'], 'document_name' => $document['document_name'], 'placeholders' => array_intersect_key($document['placeholders'], $golden['placeholders']), 'not_applicable_markers' => $document['template_marker_inventory']['not_applicable_markers']];
        $this->assertSame($golden, $projection);
        $this->assertSame($golden['not_applicable_markers'], $document['template_marker_inventory']['not_applicable_markers']);
    }

    public function test_branch_golden_projections_match(): void
    {
        $payload = $this->completePayload('moral', 'moral');
        $payload['guarantor'] = ['type' => 'moral', ...$this->party('moral', 'Fiador PM')];
        $payload['guarantee_property'] = ['exists' => 'yes', 'address' => 'Garantía', 'title_deed' => 'Escritura'];
        $payload['payment'] = ['method' => 'bank_transfer', 'bank_name' => 'Banco', 'beneficiary' => 'Beneficiario', 'clabe' => '012345678901234567'];
        $payload['maintenance'] = ['exists' => 'yes', 'payer' => 'lessor'];
        $payload['leased_property']['property_use_codes'] = ['industrial'];
        $document = $this->build($payload);
        $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/contract-document/pm-guarantor-transfer.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($golden['status'], $document['status']);
        $this->assertSame($golden['template_key'], $document['template_key']);
        $this->assertSame($golden['placeholders'], $this->placeholderProjection($document['placeholders'], $golden['placeholders']));
        foreach ($golden['markers'] as $marker) $this->assertNotSame('', $this->operationContent($document['document_operations'], $marker));
        foreach ($golden['required_marker_subset'] as $marker) {
            $this->assertContains($marker, $document['template_marker_inventory']['required_markers']);
        }

        $multiuse = $this->completePayload();
        $multiuse['leased_property']['property_use_codes'] = ['residential', 'commercial'];
        $review = $this->build($multiuse);
        $reviewGolden = json_decode((string) file_get_contents(base_path('tests/Fixtures/contract-document/review-multiuse.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($reviewGolden['status'], $review['status']);
        $this->assertSame($reviewGolden['template_key'], $review['template_key']);
        $this->assertTrue($this->hasOperation($review['document_operations'], $reviewGolden['marker'], $reviewGolden['operation']));
    }

    /** @return array<string, mixed> */
    private function completePayload(string $lessorType = 'fisica', string $lesseeType = 'fisica'): array
    {
        $payload = app(ContractDraftPayload::class)->empty();
        $payload['metadata']['contract_date'] = '2026-09-15';
        $payload['lessor'] = $this->party($lessorType, 'Arrendador Prueba');
        $payload['lessee'] = $this->party($lesseeType, 'Arrendatario Prueba');
        $payload['leased_property'] = ['alias' => 'Casa Prueba', 'address' => 'Domicilio de prueba', 'property_use_codes' => ['residential'], 'master_property_id' => null];
        $payload['term'] = ['start_date' => '2026-02-28', 'end_date' => '2028-02-29', 'duration_label' => '12 meses', 'rent_due_rule' => ['raw_text' => '05 a 10', 'day_from' => null, 'day_to' => null, 'day_of_month' => null]];
        $payload['amounts']['total_rent'] = '120000';
        $payload['amounts']['monthly_rent'] = '10000';
        $payload['amounts']['security_deposit'] = '10000';
        return $payload;
    }

    /** @return array<string, mixed> */
    private function party(string $type, string $name): array
    {
        $person = ['person_type' => $type, 'full_name' => $type === 'fisica' ? $name : null, 'legal_name' => $type === 'moral' ? $name : null, 'rfc' => 'XAXX010101000', 'nationality' => $type === 'fisica' ? 'Mexicana' : null, 'birth_place' => $type === 'fisica' ? 'México' : null, 'birth_date' => $type === 'fisica' ? '1990-02-28' : null, 'marital_status' => $type === 'fisica' ? 'Soltero' : null, 'occupation' => $type === 'fisica' ? 'Comerciante' : null, 'address' => 'Domicilio', 'identification_type' => $type === 'fisica' ? 'INE' : null, 'phone' => '5555555555', 'email' => 'anon@example.test', 'incorporation_deed' => $type === 'moral' ? 'Acta' : null, 'contact_email_entered' => null, 'contact_phone_entered' => null];
        $representative = $type === 'moral' ? ['full_name' => 'Representante', 'nationality' => 'Mexicana', 'birth_place' => 'México', 'birth_date' => '1990-02-28', 'occupation' => 'Apoderado', 'address' => 'Domicilio', 'identification_type' => 'INE', 'authority_deed' => 'Poder'] : null;
        return ['person' => $person, 'representative' => $representative];
    }

    /** @param list<array<string, mixed>> $operations */
    private function hasOperation(array $operations, string $marker, string $operation): bool { foreach ($operations as $item) if (($item['marker'] ?? $item['identifier'] ?? null) === $marker && ($item['operation'] ?? null) === $operation) return true; return false; }
    /** @param list<array<string, mixed>> $operations */
    private function hasMarker(array $operations, string $marker): bool { foreach ($operations as $item) if (($item['marker'] ?? $item['identifier'] ?? null) === $marker) return true; return false; }
    /** @param list<array<string, mixed>> $operations */
    private function hasOperationContaining(array $operations, string $needle, string $operation): bool { foreach ($operations as $item) if (str_contains((string) ($item['marker'] ?? $item['identifier'] ?? ''), $needle) && ($item['operation'] ?? null) === $operation) return true; return false; }
    /** @param list<array<string, mixed>> $operations */
    private function operationContent(array $operations, string $marker): string { foreach ($operations as $item) if (($item['marker'] ?? null) === $marker) return (string) ($item['content'] ?? ''); return ''; }
    /** @param array<string, string> $placeholders
     * @param array<string, string> $expected
     * @return array<string, string>
     */
    private function placeholderProjection(array $placeholders, array $expected): array { $projection = []; foreach (array_keys($expected) as $marker) $projection[$marker] = $placeholders[$marker] ?? null; return $projection; }
    /** @param array<string, mixed> $payload */
    private function build(array $payload): array { return app(ContractDocumentPayloadBuilder::class)->build(new ContractDraftVersion(['canonical_payload' => $payload, 'payload_hash' => 'snapshot-hash'])); }
}
