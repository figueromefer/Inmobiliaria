<?php

namespace Tests\Feature;

use App\Contracts\GoogleContractDocumentClient;
use App\Exceptions\ContractDraftPublishedException;
use App\Exceptions\ContractFinalizationException;
use App\Models\Cliente;
use App\Models\ContractDocumentVersion;
use App\Models\ContractDraft;
use App\Models\Contrato;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\User;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftReconciliationService;
use App\Services\ContractDraftVersioningService;
use App\Services\ContractFinalizationService;
use App\Services\ContractPublicationMapper;
use App\Services\GoogleContractDocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_only_publication_columns_are_available_without_requiring_historical_links(): void
    {
        foreach ([
            'finalization_key', 'finalization_draft_version_id',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('contract_drafts', $column));
        }

        foreach ([
            'contract_draft_version_id', 'contract_document_version_id', 'previous_contract_id',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('contratos', $column));
        }
    }

    public function test_it_publishes_a_physical_person_snapshot_once_and_preserves_the_raw_payment_range(): void
    {
        [$draft, $actor] = $this->draft();
        $google = $this->google();

        $contrato = $this->service($google)->finalize($draft, $draft->current_version_id, $actor->id);

        $this->assertSame('fisica', $contrato->tipo_solicitante);
        $this->assertSame('fisica', $contrato->tipo_complementaria);
        $this->assertSame('none', $contrato->tipo_tercero);
        $this->assertSame('Domicilio congelado', $contrato->domicilio_inmueble);
        $this->assertSame('2026-01-01', $contrato->fecha_inicio->toDateString());
        $this->assertSame('2026-12-31', $contrato->fecha_fin->toDateString());
        $this->assertNull($contrato->dias_pago);
        $this->assertSame('25 al 30', $draft->fresh()->finalizationDraftVersion->canonical_payload['term']['rent_due_rule']['raw_text']);
        $this->assertSame('privado', $contrato->origen);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(1, $google->copyCalls);
        $this->assertSame(ContractDraft::STATUS_PUBLISHED, $draft->fresh()->status);
        $this->assertSame($contrato->id, $draft->fresh()->contrato_id);
    }

    public function test_it_publishes_a_moral_person_snapshot(): void
    {
        [$draft, $actor] = $this->draft(true);
        $contrato = $this->service($this->google())->finalize($draft, $draft->current_version_id, $actor->id);

        $this->assertSame('moral', $contrato->tipo_solicitante);
        $this->assertSame('moral', $contrato->tipo_complementaria);
        $this->assertSame('moral', $contrato->tipo_tercero);
    }

    public function test_publication_uses_snapshot_contractual_data_not_a_mutated_master(): void
    {
        [$draft, $actor, $cliente] = $this->draft();
        $cliente->update(['nombre' => 'Nombre maestro cambiado', 'domicilio' => 'Domicilio maestro cambiado']);

        $contrato = $this->service($this->google())->finalize($draft, $draft->current_version_id, $actor->id);

        $this->assertSame('Domicilio congelado', $contrato->domicilio_inmueble);
        $this->assertSame($cliente->pk_cliente, $contrato->fk_cliente);
    }

    public function test_repeated_finalization_reuses_one_contract_document_folder_and_file(): void
    {
        [$draft, $actor] = $this->draft();
        $google = $this->google();
        $service = $this->service($google);

        $first = $service->finalize($draft, $draft->current_version_id, $actor->id);
        $second = $service->finalize($draft, $draft->current_version_id, $actor->id);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('contratos', 1);
        $this->assertDatabaseCount('contract_document_versions', 1);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(1, $google->copyCalls);
    }

    public function test_google_failure_keeps_draft_recoverable_and_retry_reuses_folder_and_document(): void
    {
        [$draft, $actor] = $this->draft();
        $google = $this->google();
        $google->failOperations = true;
        $service = $this->service($google);

        try {
            $service->finalize($draft, $draft->current_version_id, $actor->id);
            $this->fail('Se esperaba fallo controlado de generación.');
        } catch (ContractFinalizationException) {
            $this->assertDatabaseCount('contratos', 0);
        }

        $this->assertDatabaseHas('contract_document_versions', ['status' => ContractDocumentVersion::STATUS_FAILED]);
        $google->failOperations = false;
        $contrato = $service->finalize($draft, $draft->current_version_id, $actor->id);

        $this->assertNotNull($contrato->id);
        $this->assertDatabaseCount('contract_document_versions', 1);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(1, $google->copyCalls);
    }

    public function test_database_failure_after_google_generation_retries_publication_without_duplicate_drive_assets(): void
    {
        [$draft, $actor] = $this->draft();
        $google = $this->google();
        $mapper = new FailOnceContractPublicationMapper;
        $service = $this->service($google, $mapper);

        try {
            $service->finalize($draft, $draft->current_version_id, $actor->id);
            $this->fail('Se esperaba el fallo de persistencia simulado.');
        } catch (ContractFinalizationException) {
            $this->assertDatabaseCount('contratos', 0);
        }

        $contrato = $service->finalize($draft, $draft->current_version_id, $actor->id);
        $this->assertNotNull($contrato->id);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(1, $google->copyCalls);
    }

    public function test_published_draft_rejects_new_versions_and_reconciliation_changes(): void
    {
        [$draft, $actor, $cliente] = $this->draft();
        $this->service($this->google())->finalize($draft, $draft->current_version_id, $actor->id);

        $this->expectException(ContractDraftPublishedException::class);
        app(ContractDraftVersioningService::class)->appendVersion($draft->fresh(), $draft->currentVersion->canonical_payload);

        // The reconciliation guard is covered separately because expectException ends this test.
    }

    public function test_published_draft_rejects_reconciliation_changes(): void
    {
        [$draft, $actor, $cliente] = $this->draft();
        $this->service($this->google())->finalize($draft, $draft->current_version_id, $actor->id);

        $this->expectException(ContractDraftPublishedException::class);
        app(ContractDraftReconciliationService::class)->unlink($draft->fresh(), 'cliente', $actor, Request::create('/'));
    }

    public function test_historical_and_justice_alternative_contracts_keep_null_publication_links_and_previous_contract_relation_works(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente histórico', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio histórico']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Histórica', 'domicilio' => 'Domicilio']);
        $historical = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'justicia_alternativa']);
        $renewal = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'privado', 'previous_contract_id' => $historical->id]);

        $this->assertNull($historical->contract_draft_version_id);
        $this->assertNull($historical->contract_document_version_id);
        $this->assertSame($historical->id, $renewal->previousContract->id);
        $this->assertTrue($historical->renewals->contains($renewal));
    }

    /** @return array{0: ContractDraft, 1: User, 2: Cliente} */
    private function draft(bool $moral = false): array
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $cliente = Cliente::create(['nombre' => 'Cliente maestro', 'domicilio' => 'Domicilio maestro', 'rfc' => 'XAXX010101000']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa congelada', 'domicilio' => 'Domicilio maestro']);
        $inquilino = Inquilino::create(['nombre' => 'Inquilino maestro']);
        $payload = app(ContractDraftPayload::class)->empty();
        $payload['metadata']['contract_date'] = '2026-01-01';
        $payload['lessor'] = $moral ? $this->moralParty('Arrendador SA') : $this->physicalParty('Arrendador congelado');
        $payload['lessee'] = $moral ? $this->moralParty('Arrendatario SA') : $this->physicalParty('Arrendatario congelado');
        $payload['guarantor'] = $moral ? ['type' => 'moral', ...$this->moralParty('Fiador SA')] : ['type' => 'none', 'person' => null, 'representative' => null];
        $payload['leased_property'] = ['alias' => 'Casa congelada', 'address' => 'Domicilio congelado', 'property_use_codes' => ['residential'], 'master_property_id' => null];
        $payload['term'] = ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'duration_label' => '12 meses', 'rent_due_rule' => ['raw_text' => '25 al 30', 'day_from' => null, 'day_to' => null, 'day_of_month' => null]];
        $payload['amounts'] = ['total_rent' => '120000.00', 'monthly_rent' => '10000.00', 'security_deposit' => '10000.00', 'rental_commission' => '10000.00', 'monthly_commission_value' => '10.00', 'monthly_commission_unit' => 'percent'];

        $draft = app(ContractDraftVersioningService::class)->createDraft($payload, ['source' => 'laravel', 'created_by' => $actor->id]);
        $draft->forceFill(['cliente_id' => $cliente->pk_cliente, 'propiedad_id' => $propiedad->pk_propiedad, 'inquilino_id' => $inquilino->id])->save();

        return [$draft->fresh(['currentVersion']), $actor, $cliente];
    }

    private function physicalParty(string $name): array
    {
        return ['person' => ['person_type' => 'fisica', 'full_name' => $name, 'legal_name' => null, 'rfc' => 'XAXX010101000', 'nationality' => 'Mexicana', 'birth_place' => 'México', 'birth_date' => '1990-01-01', 'marital_status' => 'Soltero', 'occupation' => 'Comerciante', 'address' => 'Domicilio', 'identification_type' => 'INE', 'phone' => '3311111111', 'email' => 'persona@example.test', 'incorporation_deed' => null, 'contact_email_entered' => null, 'contact_phone_entered' => null], 'representative' => null];
    }

    private function moralParty(string $name): array
    {
        return ['person' => ['person_type' => 'moral', 'full_name' => null, 'legal_name' => $name, 'rfc' => 'XAXX010101000', 'nationality' => null, 'birth_place' => null, 'birth_date' => null, 'marital_status' => null, 'occupation' => null, 'address' => 'Domicilio moral', 'identification_type' => null, 'phone' => '3322222222', 'email' => 'moral@example.test', 'incorporation_deed' => 'Acta constitutiva', 'contact_email_entered' => null, 'contact_phone_entered' => null], 'representative' => ['full_name' => 'Representante', 'nationality' => 'Mexicana', 'birth_place' => 'México', 'birth_date' => '1980-01-01', 'occupation' => 'Apoderado', 'address' => 'Domicilio representante', 'identification_type' => 'INE', 'authority_deed' => 'Poder notarial']];
    }

    private function google(): PublicationGoogleClient
    {
        return new PublicationGoogleClient;
    }

    private function service(PublicationGoogleClient $google, ?ContractPublicationMapper $mapper = null): ContractFinalizationService
    {
        $this->app->instance(GoogleContractDocumentClient::class, $google);
        config(['services.google_contracts.templates.lease_without_guarantor' => 'template-pf', 'services.google_contracts.templates.lease_with_guarantor' => 'template-pm', 'services.google_contracts.destination_folder_id' => 'parent-folder']);

        return new ContractFinalizationService(
            app(\App\Services\ContractDocumentPayloadBuilder::class),
            app(\App\Services\ContractDocumentVersioningService::class),
            app(GoogleContractDocumentRenderer::class),
            $mapper ?: app(ContractPublicationMapper::class),
        );
    }
}

class FailOnceContractPublicationMapper extends ContractPublicationMapper
{
    private bool $fails = true;

    public function create(ContractDraft $draft, \App\Models\ContractDraftVersion $version, ContractDocumentVersion $document): Contrato
    {
        if ($this->fails) {
            $this->fails = false;
            throw new ContractFinalizationException('Fallo DB simulado.');
        }

        return parent::create($draft, $version, $document);
    }
}

class PublicationGoogleClient implements GoogleContractDocumentClient
{
    public int $folderCalls = 0;

    public int $copyCalls = 0;

    public bool $failOperations = false;

    public function createFolder(string $parentFolderId, string $name): array
    {
        $this->folderCalls++;

        return ['id' => 'folder-fixed'];
    }

    public function copyTemplate(string $templateId, string $name, string $folderId): array
    {
        $this->copyCalls++;

        return ['id' => 'file-fixed', 'url' => 'https://docs.google.test/file-fixed'];
    }

    public function applyOperations(string $documentId, array $operations): void
    {
        if ($this->failOperations) {
            throw new \RuntimeException('Google simulado falló');
        }
    }

    public function remainingMarkers(string $documentId, array $markers): array
    {
        return [];
    }
}
