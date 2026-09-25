<?php

namespace Tests\Feature;

use App\Contracts\GoogleContractDocumentClient;
use App\Models\ContractDocumentVersion;
use App\Models\User;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractDocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_preview_and_prepare_a_ready_document_request_without_google_io(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = $this->draft($actor);

        $this->actingAs($actor)
            ->get(route('contratos.borradores.document-preview', $draft))
            ->assertOk()
            ->assertSee('Previsualización documental')
            ->assertSee('lease_without_guarantor')
            ->assertSee('document-generate-button')
            ->assertSee('Generar documento');

        $intent = ['expected_draft_version_id' => $draft->current_version_id, 'idempotency_key' => '15f0a3ca-20d7-4e79-a82c-9e2f667c69d3'];
        $this->actingAs($actor)
            ->post(route('contratos.borradores.document-preview.request', $draft), $intent)
            ->assertRedirect(route('contratos.borradores.document-preview', $draft));

        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.request', $draft), $intent)->assertRedirect();

        $this->assertDatabaseHas('contract_document_versions', [
            'contract_draft_version_id' => $draft->current_version_id,
            'document_version' => 1,
            'template_key' => 'lease_without_guarantor',
            'status' => ContractDocumentVersion::STATUS_NOT_REQUESTED,
            'created_by' => $actor->id,
        ]);
        $this->assertDatabaseCount('contract_document_versions', 1);
    }

    public function test_viewer_and_guest_cannot_preview_or_prepare_document_request(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = $this->draft($actor);
        $this->get(route('contratos.borradores.document-preview', $draft))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->post(route('contratos.borradores.document-preview.request', $draft))
            ->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->post(route('contratos.borradores.document-preview.generate', $draft), ['expected_draft_version_id' => $draft->current_version_id, 'idempotency_key' => '571b3ace-71b5-4b60-ae2e-2c6e3c9fe392'])
            ->assertForbidden();
    }

    public function test_incomplete_draft_cannot_prepare_document_request(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = app(ContractDraftVersioningService::class)->createDraft(
            app(ContractDraftPayload::class)->empty(),
            ['source' => 'laravel', 'status' => 'draft', 'created_by' => $actor->id],
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'created',
            $actor->id,
        );

        $this->actingAs($actor)
            ->post(route('contratos.borradores.document-preview.request', $draft), ['expected_draft_version_id' => $draft->current_version_id, 'idempotency_key' => 'd8bbfb70-f5ae-42e8-a1b8-9f8e20d1a9ea'])
            ->assertSessionHasErrors('document');
        $this->assertDatabaseCount('contract_document_versions', 0);
    }

    public function test_blocked_or_review_document_cannot_call_google_generation(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = app(ContractDraftVersioningService::class)->createDraft(
            app(ContractDraftPayload::class)->empty(),
            ['source' => 'laravel', 'status' => 'draft', 'created_by' => $actor->id],
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'created',
            $actor->id,
        );
        $google = new FakeGoogleContractDocumentClient();
        $this->app->instance(GoogleContractDocumentClient::class, $google);

        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.generate', $draft), ['expected_draft_version_id' => $draft->current_version_id, 'idempotency_key' => 'd8bbfb70-f5ae-42e8-a1b8-9f8e20d1a9ea'])
            ->assertSessionHasErrors('document');
        $this->assertSame([], $google->copiedTemplateIds);
        $this->assertDatabaseCount('contract_document_versions', 0);
    }

    public function test_stale_preview_cannot_prepare_a_document_for_a_newer_snapshot(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = $this->draft($actor);
        $previewed = $draft->current_version_id;
        app(ContractDraftVersioningService::class)->appendVersion($draft, $draft->currentVersion->canonical_payload, null, ContractDraftPayload::SCHEMA_VERSION, 'saved', $actor->id);

        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.request', $draft), ['expected_draft_version_id' => $previewed, 'idempotency_key' => '28aeef31-2b09-470c-9ba5-9cd2324e8b45'])->assertStatus(409);
        $this->assertDatabaseCount('contract_document_versions', 0);
    }

    public function test_stale_preview_cannot_generate_or_call_google(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = $this->draft($actor);
        $previewed = $draft->current_version_id;
        app(ContractDraftVersioningService::class)->appendVersion($draft, $draft->currentVersion->canonical_payload, null, ContractDraftPayload::SCHEMA_VERSION, 'saved', $actor->id);
        $google = new FakeGoogleContractDocumentClient();
        $this->app->instance(GoogleContractDocumentClient::class, $google);

        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.generate', $draft), ['expected_draft_version_id' => $previewed, 'idempotency_key' => '35fb34b8-1afc-46f2-84cc-d8da7f5c052e'])->assertStatus(409);
        $this->assertSame([], $google->copiedTemplateIds);
        $this->assertDatabaseCount('contract_document_versions', 0);
    }

    public function test_ready_preview_generates_only_a_copy_and_reuses_the_same_document_for_an_idempotent_submit(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = $this->draft($actor);
        $google = new FakeGoogleContractDocumentClient();
        $this->app->instance(GoogleContractDocumentClient::class, $google);
        config([
            'services.google_contracts.templates.lease_without_guarantor' => 'template-without-guarantor',
            'services.google_contracts.destination_folder_id' => 'sandbox-folder',
        ]);
        $intent = ['expected_draft_version_id' => $draft->current_version_id, 'idempotency_key' => '1df6cc8c-5ac4-4c1f-8e50-bbf312198b5d'];

        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.generate', $draft), $intent)->assertRedirect();
        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.generate', $draft), $intent)->assertRedirect();

        $this->assertDatabaseCount('contract_document_versions', 1);
        $this->assertDatabaseHas('contract_document_versions', [
            'status' => ContractDocumentVersion::STATUS_GENERATED,
            'drive_folder_id' => 'folder-1',
            'drive_file_id' => 'copy-1',
            'attempts' => 1,
        ]);
        $this->assertSame(['template-without-guarantor'], $google->copiedTemplateIds);
        $this->assertSame(['copy-1'], $google->operationDocumentIds);
        $this->assertNotContains('template-without-guarantor', $google->operationDocumentIds);
    }

    public function test_failed_generation_retries_the_same_document_and_reuses_the_partial_copy(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = $this->draft($actor);
        $google = new FakeGoogleContractDocumentClient([['{{clausula_deposito}}'], []]);
        $this->app->instance(GoogleContractDocumentClient::class, $google);
        config([
            'services.google_contracts.templates.lease_without_guarantor' => 'template-without-guarantor',
            'services.google_contracts.destination_folder_id' => 'sandbox-folder',
        ]);
        $intent = ['expected_draft_version_id' => $draft->current_version_id, 'idempotency_key' => '577191c4-3c33-4d82-8229-1232de4f6da8'];

        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.generate', $draft), $intent)->assertRedirect();
        $documentVersion = ContractDocumentVersion::firstOrFail();
        $this->assertSame(ContractDocumentVersion::STATUS_FAILED, $documentVersion->status);
        $this->assertSame('copy-1', $documentVersion->drive_file_id);

        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.retry', [$draft, $documentVersion]))->assertRedirect();
        $this->assertSame(ContractDocumentVersion::STATUS_GENERATED, $documentVersion->fresh()->status);
        $this->assertSame(2, $documentVersion->fresh()->attempts);
        $this->assertSame(['template-without-guarantor'], $google->copiedTemplateIds);
        $this->assertSame(1, $google->folderCalls);
    }

    public function test_bank_table_header_is_not_treated_as_an_unresolved_marker_when_transfer_is_preserved(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = $this->draft($actor);
        $payload = $draft->currentVersion->canonical_payload;
        $payload['payment'] = [
            'method' => 'bank_transfer',
            'bank_name' => 'Banco de Prueba',
            'beneficiary' => 'Beneficiario de Prueba',
            'clabe' => '012345678901234567',
        ];
        $version = app(ContractDraftVersioningService::class)->appendVersion(
            $draft,
            $payload,
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'saved',
            $actor->id,
        );
        $google = new FakeGoogleContractDocumentClient();
        $this->app->instance(GoogleContractDocumentClient::class, $google);
        config([
            'services.google_contracts.templates.lease_without_guarantor' => 'template-without-guarantor',
            'services.google_contracts.destination_folder_id' => 'sandbox-folder',
        ]);

        $this->actingAs($actor)->post(route('contratos.borradores.document-preview.generate', $draft), [
            'expected_draft_version_id' => $version->id,
            'idempotency_key' => '036f832e-0221-4a86-9c17-1db123f5ef59',
        ])->assertRedirect();

        $this->assertDatabaseHas('contract_document_versions', [
            'status' => ContractDocumentVersion::STATUS_GENERATED,
        ]);
        $this->assertNotContains('INSTITUCIÓN BANCARIA', $google->lastRemainingMarkers);
    }

    private function draft(User $actor)
    {
        $payload = app(ContractDraftPayload::class)->empty();
        $payload['metadata']['contract_date'] = '2026-09-15';
        foreach (['lessor' => 'Arrendador', 'lessee' => 'Arrendatario'] as $party => $name) {
            $payload[$party]['person'] = ['person_type' => 'fisica', 'full_name' => $name, 'legal_name' => null, 'rfc' => 'XAXX010101000', 'nationality' => 'Mexicana', 'birth_place' => 'México', 'birth_date' => '1990-02-28', 'marital_status' => 'Soltero', 'occupation' => 'Comerciante', 'address' => 'Domicilio', 'identification_type' => 'INE', 'phone' => '5555555555', 'email' => 'anon@example.test', 'incorporation_deed' => null, 'contact_email_entered' => null, 'contact_phone_entered' => null];
        }
        $payload['leased_property']['address'] = 'Domicilio';
        $payload['leased_property']['property_use_codes'] = ['residential'];
        $payload['term']['start_date'] = '2026-01-01';
        $payload['term']['end_date'] = '2027-01-01';
        $payload['amounts']['monthly_rent'] = '10000';
        $payload['amounts']['total_rent'] = '120000';
        $payload['amounts']['security_deposit'] = '10000';
        $payload['term']['duration_label'] = '12 meses';
        $payload['term']['rent_due_rule']['raw_text'] = '05 a 10';

        return app(ContractDraftVersioningService::class)->createDraft(
            $payload,
            ['source' => 'laravel', 'status' => 'draft', 'created_by' => $actor->id],
            null,
            ContractDraftPayload::SCHEMA_VERSION,
            'created',
            $actor->id,
        );
    }
}

class FakeGoogleContractDocumentClient implements GoogleContractDocumentClient
{
    /** @var list<string> */
    public array $copiedTemplateIds = [];
    /** @var list<string> */
    public array $operationDocumentIds = [];
    public int $folderCalls = 0;
    /** @var list<string> */
    public array $lastRemainingMarkers = [];
    /** @var list<list<string>> */
    private array $remainingResponses;

    /** @param list<list<string>> $remainingResponses */
    public function __construct(array $remainingResponses = [[]])
    {
        $this->remainingResponses = $remainingResponses;
    }

    public function createFolder(string $parentFolderId, string $name): array
    {
        $this->folderCalls++;
        return ['id' => 'folder-1', 'url' => 'https://drive.google.com/drive/folders/folder-1'];
    }

    public function copyTemplate(string $templateId, string $name, string $folderId): array
    {
        $this->copiedTemplateIds[] = $templateId;
        return ['id' => 'copy-1', 'url' => 'https://docs.google.com/document/d/copy-1/edit'];
    }

    public function applyOperations(string $documentId, array $operations): void
    {
        $this->operationDocumentIds[] = $documentId;
    }

    public function remainingMarkers(string $documentId, array $markers): array
    {
        $this->lastRemainingMarkers = $markers;
        return array_shift($this->remainingResponses) ?? [];
    }
}
