<?php

namespace Tests\Feature;

use App\Models\ContractDocumentVersion;
use App\Models\ContractDraftVersion;
use App\Services\ContractDocumentVersioningService;
use App\Services\ContractDraftVersioningService;
use App\Services\ContractPayloadCanonicalizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use DomainException;
use Tests\TestCase;

class ContractDraftVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_draft_with_its_first_immutable_version(): void
    {
        $draft = $this->versioning()->createDraft($this->payload());

        $this->assertSame(1, $draft->currentVersion->draft_version);
        $this->assertSame('contract_data_v1', $draft->currentVersion->schema_version);
        $canonicalPayload = app(ContractPayloadCanonicalizer::class)->canonicalize($this->payload());
        $this->assertEquals($canonicalPayload, $draft->currentVersion->canonical_payload);
        $this->assertSame(
            app(ContractPayloadCanonicalizer::class)->hashCanonical($canonicalPayload),
            $draft->currentVersion->payload_hash
        );
        $this->assertNull($draft->currentVersion->raw_legacy_payload);
        $this->assertDatabaseHas('contract_drafts', [
            'id' => $draft->id,
            'current_version_id' => $draft->currentVersion->id,
        ]);
    }

    public function test_it_appends_a_second_version_and_updates_the_current_version(): void
    {
        $service = $this->versioning();
        $draft = $service->createDraft($this->payload());
        $versionOne = $draft->currentVersion;
        $payload = $this->payload();
        $payload['amounts']['monthly_rent'] = '12500.00';

        $versionTwo = $service->appendVersion($draft, $payload, ['legacy' => true]);

        $draft->refresh();
        $this->assertSame(1, $versionOne->draft_version);
        $this->assertSame(2, $versionTwo->draft_version);
        $this->assertSame($versionTwo->id, $draft->current_version_id);
        $this->assertSame(['legacy' => true], $versionTwo->raw_legacy_payload);
        $this->assertNotSame($versionOne->payload_hash, $versionTwo->payload_hash);
    }

    public function test_each_draft_version_records_the_actor_that_created_that_version(): void
    {
        $creator = \App\Models\User::factory()->create();
        $editor = \App\Models\User::factory()->create();
        $service = $this->versioning();
        $draft = $service->createDraft($this->payload(), ['created_by' => $creator->id]);

        $secondVersion = $service->appendVersion($draft, $this->payload(), null, 'contract_data_v1', 'saved', $editor->id);

        $this->assertSame($creator->id, $draft->currentVersion->created_by);
        $this->assertSame($editor->id, $secondVersion->created_by);
    }

    public function test_current_version_must_belong_to_the_same_draft(): void
    {
        $service = $this->versioning();
        $firstDraft = $service->createDraft($this->payload());
        $secondDraft = $service->createDraft($this->payload());

        try {
            $service->setCurrentVersion($firstDraft, $secondDraft->currentVersion);
            $this->fail('Se esperaba una excepción de dominio.');
        } catch (DomainException) {
            $this->assertSame($firstDraft->currentVersion->id, $firstDraft->fresh()->current_version_id);
        }
    }

    public function test_current_version_id_is_not_mass_assignable(): void
    {
        $draft = $this->versioning()->createDraft($this->payload());
        $otherDraft = $this->versioning()->createDraft($this->payload());

        $draft->fill(['current_version_id' => $otherDraft->currentVersion->id]);

        $this->assertSame($draft->currentVersion->id, $draft->current_version_id);
    }

    public function test_source_and_external_id_are_unique_but_null_external_ids_are_independent(): void
    {
        $service = $this->versioning();
        $service->createDraft($this->payload(), ['source' => 'google_form', 'external_id' => 'response-1']);

        try {
            $service->createDraft($this->payload(), ['source' => 'google_form', 'external_id' => 'response-1']);
            $this->fail('Se esperaba una violación de unicidad.');
        } catch (QueryException) {
            $this->assertDatabaseCount('contract_drafts', 1);
        }

        $service->createDraft($this->payload(), ['source' => 'laravel']);
        $service->createDraft($this->payload(), ['source' => 'laravel']);
        $this->assertDatabaseCount('contract_drafts', 3);
    }

    public function test_draft_versions_are_unique(): void
    {
        $draft = $this->versioning()->createDraft($this->payload());

        $this->expectException(QueryException::class);
        ContractDraftVersion::create([
            'contract_draft_id' => $draft->id,
            'draft_version' => 1,
            'schema_version' => 'contract_data_v1',
            'canonical_payload' => [],
            'payload_hash' => str_repeat('a', 64),
            'action' => 'saved',
        ]);
    }

    public function test_draft_version_snapshots_cannot_be_silently_updated(): void
    {
        $version = $this->versioning()->createDraft($this->payload())->currentVersion;

        $this->expectException(LogicException::class);
        $version->update(['action' => 'changed']);
    }

    public function test_logically_identical_payloads_have_the_same_hash_while_array_order_is_preserved(): void
    {
        $canonicalizer = new ContractPayloadCanonicalizer();
        $first = [
            'term' => ['dias_pago' => ['raw_text' => '05 a 10']],
            'leased_property' => ['property_use_codes' => ['commercial', 'residential']],
            'metadata' => ['source' => 'laravel'],
        ];
        $sameLogicalPayload = [
            'metadata' => ['source' => 'laravel'],
            'leased_property' => ['property_use_codes' => ['commercial', 'residential']],
            'term' => ['dias_pago' => ['raw_text' => '05 a 10']],
        ];
        $differentArrayOrder = [
            'metadata' => ['source' => 'laravel'],
            'leased_property' => ['property_use_codes' => ['residential', 'commercial']],
            'term' => ['dias_pago' => ['raw_text' => '05 a 10']],
        ];

        $this->assertSame($canonicalizer->hash($first), $canonicalizer->hash($sameLogicalPayload));
        $this->assertNotSame($canonicalizer->hash($first), $canonicalizer->hash($differentArrayOrder));
        $this->assertSame('05 a 10', $canonicalizer->canonicalize($first)['term']['dias_pago']['raw_text']);
    }

    public function test_canonicalizer_preserves_scalar_semantics_and_rejects_ambiguous_or_unsupported_values(): void
    {
        $canonicalizer = new ContractPayloadCanonicalizer();
        $payload = [
            'null' => null,
            'boolean' => true,
            'integer' => 1,
            'float' => 1.0,
            'leading_zero' => '05',
            'range' => '05 a 10',
        ];

        $canonical = $canonicalizer->canonicalize($payload);
        $this->assertEquals($payload, $canonical);
        $this->assertNotSame($canonicalizer->hash(['value' => 1]), $canonicalizer->hash(['value' => 1.0]));

        $invalidValues = [
            ['value' => new \DateTimeImmutable()],
            ['value' => new class implements \JsonSerializable {
                public function jsonSerialize(): mixed { return ['value' => 'x']; }
            }],
            ['value' => static fn (): string => 'x'],
            ['value' => NAN],
            ['value' => INF],
            ['value' => -INF],
            ['value' => "\xB1\x31"],
            ['value' => [1 => 'no-densa']],
            ['value' => [0 => 'lista', 'clave' => 'mixta']],
        ];

        $resource = fopen('php://memory', 'r');
        $invalidValues[] = ['value' => $resource];

        try {
            foreach ($invalidValues as $invalidValue) {
                try {
                    $canonicalizer->canonicalize($invalidValue);
                    $this->fail('Se esperaba rechazo del valor no admitido.');
                } catch (\InvalidArgumentException) {
                    $this->addToAssertionCount(1);
                }
            }
        } finally {
            fclose($resource);
        }
    }

    public function test_a_real_payload_change_produces_a_different_hash(): void
    {
        $canonicalizer = new ContractPayloadCanonicalizer();
        $first = $this->payload();
        $second = $this->payload();
        $second['amounts']['monthly_rent'] = '12501.00';

        $this->assertNotSame($canonicalizer->hash($first), $canonicalizer->hash($second));
    }

    public function test_document_retries_reuse_the_existing_version_and_regeneration_creates_a_new_one(): void
    {
        $draft = $this->versioning()->createDraft($this->payload());
        $service = app(ContractDocumentVersioningService::class);

        $first = $service->createVersion(
            $draft->currentVersion,
            'lease_with_guarantor',
            null,
            'dde8d2bc-ea8d-4d70-9de4-8fe9b985b7e2'
        );
        $retry = $service->createVersion(
            $draft->currentVersion,
            'lease_with_guarantor',
            null,
            'dde8d2bc-ea8d-4d70-9de4-8fe9b985b7e2'
        );
        $second = $service->createVersion(
            $draft->currentVersion,
            'lease_with_guarantor',
            null,
            'f3d6da6a-d129-4245-93f9-2d21c58bdba3'
        );

        $this->assertSame(1, $first->document_version);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(2, $second->document_version);
        $this->assertSame($draft->currentVersion->payload_hash, $first->snapshot_hash);
        $this->assertSame(ContractDocumentVersion::STATUS_NOT_REQUESTED, $first->status);
        $this->assertDatabaseCount('contract_document_versions', 2);

        try {
            $service->createVersion($draft->currentVersion, 'different_template', null, $first->idempotency_key);
            $this->fail('Se esperaba un conflicto de idempotencia.');
        } catch (DomainException) {
            $this->assertDatabaseCount('contract_document_versions', 2);
        }
    }

    public function test_document_identity_is_protected_and_operational_fields_use_the_service(): void
    {
        $draft = $this->versioning()->createDraft($this->payload());
        $service = app(ContractDocumentVersioningService::class);
        $documentVersion = $service->createVersion($draft->currentVersion, 'lease_with_guarantor');

        $this->expectException(LogicException::class);
        $documentVersion->forceFill(['snapshot_hash' => str_repeat('b', 64)])->save();
    }

    public function test_operational_document_updates_are_allowed_only_through_the_controlled_service(): void
    {
        $draft = $this->versioning()->createDraft($this->payload());
        $service = app(ContractDocumentVersioningService::class);
        $documentVersion = $service->createVersion($draft->currentVersion, 'lease_with_guarantor');

        $updated = $service->updateOperationalState($documentVersion, [
            'status' => ContractDocumentVersion::STATUS_FAILED,
            'attempts' => 1,
            'last_error' => 'Error técnico controlado.',
        ]);

        $this->assertSame(ContractDocumentVersion::STATUS_FAILED, $updated->status);
        $this->assertSame(1, $updated->attempts);
    }

    public function test_draft_payloads_are_hidden_from_automatic_serialization(): void
    {
        $version = $this->versioning()->createDraft($this->payload(), [], ['legacy' => 'sensible'])->currentVersion;

        $this->assertArrayNotHasKey('canonical_payload', $version->toArray());
        $this->assertArrayNotHasKey('raw_legacy_payload', $version->toArray());
    }

    public function test_creating_a_draft_rolls_back_when_the_payload_cannot_be_serialized(): void
    {
        $payload = $this->payload();
        $payload['metadata']['invalid_utf8'] = "\xB1\x31";

        try {
            $this->versioning()->createDraft($payload);
            $this->fail('Se esperaba una excepción por UTF-8 inválido.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('contract_drafts', 0);
            $this->assertDatabaseCount('contract_draft_versions', 0);
        }
    }

    private function versioning(): ContractDraftVersioningService
    {
        return app(ContractDraftVersioningService::class);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'schema_version' => 'contract_data_v1',
            'metadata' => ['source' => 'laravel'],
            'term' => ['dias_pago' => ['raw_text' => '05 a 10']],
            'amounts' => ['monthly_rent' => '12000.00'],
            'leased_property' => ['property_use_codes' => ['residential']],
        ];
    }
}
