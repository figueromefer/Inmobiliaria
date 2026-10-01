<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Contracts\GoogleContractDocumentClient;
use App\Models\ContractDocumentVersion;
use App\Models\ContractDraft;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\Contrato;
use App\Models\User;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateContractWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepared_snapshot_is_rendered_by_the_shared_internal_capture_form(): void
    {
        [$draft, $agent] = $this->draft();
        $cliente = Cliente::create(['nombre' => 'Propietaria conciliada', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio dueño', 'celular' => '3311111111', 'correo' => 'dueno@example.test']);
        $inquilino = Inquilino::create(['nombre' => 'Inquilino conciliado', 'nacionalidad' => 'Mexicana', 'domicilio' => 'Domicilio inquilino', 'telefono' => '3322222222', 'correo' => 'inquilino@example.test']);

        $this->reconcile($agent, $draft, 'cliente', $cliente->pk_cliente);
        $this->reconcile($agent, $draft->fresh(), 'inquilino', $inquilino->id);
        $draft->refresh()->load('currentVersion');

        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'arrendador']))
            ->assertOk()->assertSee('Captura de contrato privado')->assertSee('value="Propietaria conciliada"', false)
            ->assertSee('value="dueno@example.test"', false);
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'arrendatario']))
            ->assertOk()->assertSee('value="Inquilino conciliado"', false)->assertSee('value="Mexicana"', false);
    }

    public function test_internal_save_creates_an_immutable_version_and_navigation_uses_private_routes(): void
    {
        [$draft, $agent] = $this->draft();
        $first = $draft->currentVersion;

        $this->actingAs($agent)->put(route('contratos.privados.wizard.save', [$draft, 'generales']), [
            'expected_version_id' => $draft->current_version_id,
            'next' => 1,
            'payload' => ['metadata' => ['contract_date' => '2026-10-01', 'contract_reference' => 'INTERNA-4'], 'leased_property' => ['alias' => 'Casa compartida', 'address' => 'Calle 1']],
        ])->assertRedirect(route('contratos.privados.wizard.show', [$draft, 'arrendador']));

        $draft->refresh()->load('currentVersion');
        $this->assertSame(2, $draft->currentVersion->draft_version);
        $this->assertSame('INTERNA-4', data_get($draft->currentVersion->canonical_payload, 'metadata.contract_reference'));
        $this->assertNull(data_get($first->fresh()->canonical_payload, 'metadata.contract_reference'));
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'arrendador']))
            ->assertSee('Anterior')->assertSee('Preparación')->assertSee('Guardar y salir');
    }

    public function test_internal_pf_pm_and_guarantor_branches_are_normalized_without_hidden_branch_data(): void
    {
        [$draft, $agent] = $this->draft();

        $this->save($agent, $draft, 'arrendador', ['lessor' => ['person' => ['person_type' => 'moral', 'full_name' => 'No debe conservarse', 'legal_name' => 'Arrendadora PM', 'rfc' => 'RFC', 'address' => 'A'], 'representative' => ['full_name' => 'Representante']]]);
        $this->save($agent, $draft->fresh(), 'tercero', ['guarantor' => ['type' => 'none', 'person' => ['person_type' => 'moral', 'legal_name' => 'No debe conservarse']]]);

        $payload = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertSame('Arrendadora PM', data_get($payload, 'lessor.person.legal_name'));
        $this->assertNull(data_get($payload, 'lessor.person.full_name'));
        $this->assertSame('none', data_get($payload, 'guarantor.type'));
        $this->assertNull(data_get($payload, 'guarantor.person.legal_name'));
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'tercero']))
            ->assertSee('data-guarantor-type', false)->assertSee('data-party-branch', false)->assertSee('control.disabled=!visible', false);
    }

    public function test_stale_private_save_does_not_replace_current_version_and_published_draft_is_not_editable(): void
    {
        [$draft, $agent] = $this->draft();
        $stale = $draft->current_version_id;
        app(ContractDraftVersioningService::class)->appendVersion($draft, $draft->currentVersion->canonical_payload, null, ContractDraftPayload::SCHEMA_VERSION, 'other', $agent->id);

        $this->actingAs($agent)->put(route('contratos.privados.wizard.save', [$draft, 'generales']), [
            'expected_version_id' => $stale,
            'payload' => ['metadata' => ['contract_reference' => 'STALE']],
        ])->assertSessionHasErrors('expected_version_id');
        $this->assertNull(data_get($draft->fresh()->currentVersion->canonical_payload, 'metadata.contract_reference'));

        $draft->forceFill(['status' => ContractDraft::STATUS_PUBLISHED])->save();
        $this->actingAs($agent)->put(route('contratos.privados.wizard.save', [$draft, 'generales']), [
            'expected_version_id' => $draft->fresh()->current_version_id, 'payload' => ['metadata' => ['contract_reference' => 'NO']],
        ])->assertStatus(409);
    }

    public function test_internal_capture_requires_its_own_authenticated_permission_and_summary_has_no_technical_controls(): void
    {
        [$draft, $agent] = $this->draft();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->get(route('contratos.privados.wizard.show', [$draft, 'generales']))->assertForbidden();
        $this->app['auth']->guard()->logout();
        $this->get(route('contratos.privados.wizard.show', [$draft, 'resumen']))->assertRedirect(route('login'));
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'resumen']))
            ->assertOk()->assertSee('Faltan datos para generar el contrato.')->assertSee('button-secondary', false)
            ->assertDontSee('expected_version_id')->assertDontSee('idempotency');
    }

    public function test_ready_summary_generates_one_contract_and_redirects_to_the_list_with_a_visible_flash(): void
    {
        [$draft, $agent, $google] = $this->completeDraft();

        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'resumen']))
            ->assertOk()->assertSee('Generar contrato')->assertSee('data-contract-finalization', false)
            ->assertSee('data-generation-overlay', false)->assertDontSee('Preparar contrato');

        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), [
            'expected_version_id' => $draft->current_version_id,
        ])->assertRedirect(route('contratos.index'))->assertSessionHas('success', 'Contrato generado correctamente.');

        $draft->refresh();
        $this->assertNotNull($draft->contrato_id);
        $this->assertDatabaseCount('contratos', 1);
        $this->assertDatabaseCount('contract_document_versions', 1);
        $contrato = $draft->contrato;
        $this->assertSame('https://docs.google.test/file', $contrato->urldoc);
        $this->assertSame($draft->current_version_id, $contrato->contract_draft_version_id);
        $this->assertNotNull($contrato->contract_document_version_id);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(1, $google->copyCalls);
        $this->actingAs($agent)->get(route('contratos.index'))->assertSee('Contrato generado correctamente.');

        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), [
            'expected_version_id' => $draft->current_version_id,
        ])->assertRedirect(route('contratos.index'));
        $this->assertDatabaseCount('contratos', 1);
        $this->assertDatabaseCount('contract_document_versions', 1);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(1, $google->copyCalls);
    }

    public function test_blocked_or_review_summary_does_not_offer_generation_or_create_assets(): void
    {
        [$draft, $agent] = $this->draft();
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'resumen']))
            ->assertOk()->assertSee('Faltan datos para generar el contrato.')->assertDontSee('Generar contrato');
        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $draft->current_version_id])
            ->assertRedirect(route('contratos.privados.wizard.show', [$draft, 'resumen']));
        $this->assertDatabaseCount('contratos', 0);
        $this->assertDatabaseCount('contract_document_versions', 0);

        [$review, $reviewAgent] = $this->completeDraft(['other']);
        $this->actingAs($reviewAgent)->get(route('contratos.privados.wizard.show', [$review, 'resumen']))
            ->assertOk()->assertSee('requiere revisión')->assertDontSee('Generar contrato');
    }

    public function test_failed_google_generation_is_humanized_and_retry_reuses_the_same_drive_identity(): void
    {
        [$draft, $agent, $google] = $this->completeDraft();
        $google->fail = true;
        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $draft->current_version_id])
            ->assertRedirect(route('contratos.privados.wizard.show', [$draft, 'resumen']))
            ->assertSessionHas('generation_error', 'No fue posible generar el documento del contrato. Puedes volver a intentarlo sin perder la información capturada.');
        $this->assertDatabaseCount('contratos', 0);
        $this->assertDatabaseHas('contract_document_versions', ['status' => ContractDocumentVersion::STATUS_FAILED]);

        $google->fail = false;
        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft->fresh()), ['expected_version_id' => $draft->fresh()->current_version_id])
            ->assertRedirect(route('contratos.index'));
        $this->assertDatabaseCount('contratos', 1);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(1, $google->copyCalls);
    }

    public function test_finalization_rejects_a_stale_snapshot_and_viewer_cannot_post_it(): void
    {
        [$draft, $agent] = $this->completeDraft();
        $stale = $draft->current_version_id;
        app(ContractDraftVersioningService::class)->appendVersion($draft, $draft->currentVersion->canonical_payload, null, ContractDraftPayload::SCHEMA_VERSION, 'other', $agent->id);

        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $stale])
            ->assertSessionHasErrors('expected_version_id');
        $this->assertDatabaseCount('contratos', 0);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $draft->fresh()->current_version_id])
            ->assertForbidden();
    }

    public function test_private_contract_revision_keeps_the_same_contract_and_preserves_prior_snapshot_document_and_folder(): void
    {
        [$draft, $agent, $google] = $this->completeDraft();
        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $draft->current_version_id]);
        $contract = $draft->fresh()->contrato;
        $oldSnapshot = $contract->draftVersion->canonical_payload;
        $oldDocument = $contract->contract_document_version_id;
        $contract->cliente->update(['nombre' => 'Maestro cambiado después de publicar']);

        $this->actingAs($agent)->post(route('contratos.revision.start', $contract))
            ->assertRedirect();
        $revision = ContractDraft::query()->where('editing_contract_id', $contract->id)->sole()->load('currentVersion');
        $this->assertSame('revision', $revision->purpose);
        $this->assertSame('Persona completa', data_get($revision->currentVersion->canonical_payload, 'lessor.person.full_name'));
        $this->assertSame($oldDocument, $contract->fresh()->contract_document_version_id);
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$revision, 'generales']))
            ->assertSee('Editando contrato #'.$contract->id)->assertSee('versión anterior permanecerá en el historial');

        $this->actingAs($agent)->put(route('contratos.privados.wizard.save', [$revision, 'vigencia']), [
            'expected_version_id' => $revision->current_version_id,
            'payload' => ['amounts' => ['monthly_rent' => '11000.00']],
        ])->assertRedirect();
        $revision->refresh()->load('currentVersion');
        $this->assertSame($oldDocument, $contract->fresh()->contract_document_version_id);

        $this->actingAs($agent)->post(route('contratos.privados.revision.publish', $revision), ['expected_version_id' => $revision->current_version_id])
            ->assertRedirect(route('contratos.show', $contract));
        $contract->refresh();
        $this->assertSame($contract->id, $revision->fresh()->editing_contract_id);
        $this->assertNotSame($oldDocument, $contract->contract_document_version_id);
        $this->assertSame(11000.0, (float) $contract->monto_mensual);
        $this->assertDatabaseCount('contratos', 1);
        $this->assertDatabaseCount('contract_document_versions', 2);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(2, $google->copyCalls);
        $this->assertSame('Persona completa', data_get($draft->fresh()->currentVersion->canonical_payload, 'lessor.person.full_name'));
        $this->assertSame($oldSnapshot, $draft->fresh()->currentVersion->canonical_payload);
        $this->actingAs($agent)->post(route('contratos.privados.revision.publish', $revision), ['expected_version_id' => $revision->current_version_id]);
        $this->assertDatabaseCount('contract_document_versions', 2);
        $this->assertSame(2, $google->copyCalls);
        $this->actingAs($agent)->get(route('contratos.show', $contract))
            ->assertSee('Versiones del contrato')->assertSee('Actual')->assertSee('Anterior')->assertSee('Carpeta Drive');
    }

    public function test_legacy_private_contract_starts_an_auditable_fallback_revision_without_inventing_data(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $cliente = Cliente::create(['nombre' => 'Cliente histórico', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio histórico']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Histórica', 'domicilio' => 'Calle histórica']);
        $legacy = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'privado', 'monto_mensual' => '9000.00']);

        $this->actingAs($agent)->post(route('contratos.revision.start', $legacy))->assertRedirect();
        $revision = ContractDraft::query()->where('editing_contract_id', $legacy->id)->sole()->load('currentVersion');
        $this->assertSame('revision_started_from_legacy_fallback', $revision->currentVersion->action);
        $this->assertSame('legacy_fallback', data_get($revision->currentVersion->raw_legacy_payload, 'revision_origin'));
        $this->assertSame(9000.0, (float) data_get($revision->currentVersion->canonical_payload, 'amounts.monthly_rent'));
        $this->assertNull(data_get($revision->currentVersion->canonical_payload, 'lessee.person.rfc'));
    }

    private function draft(): array
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $this->actingAs($agent)->get(route('contratos.privados.create'));

        return [ContractDraft::query()->sole()->load('currentVersion'), $agent];
    }

    private function reconcile(User $agent, ContractDraft $draft, string $entity, int $id): void
    {
        $this->actingAs($agent)->post(route('contratos.privados.preparacion.conciliacion', [$draft, $entity]), [
            'expected_version_id' => $draft->current_version_id, 'entity_id' => $id,
        ])->assertRedirect();
    }

    private function save(User $agent, ContractDraft $draft, string $step, array $payload): void
    {
        $this->actingAs($agent)->put(route('contratos.privados.wizard.save', [$draft, $step]), [
            'expected_version_id' => $draft->current_version_id, 'payload' => $payload,
        ])->assertRedirect();
    }

    /** @return array{0: ContractDraft, 1: User, 2: PrivateFlowGoogleClient} */
    private function completeDraft(array $uses = ['residential']): array
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $cliente = Cliente::create(['nombre' => 'Cliente publicación', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa publicación', 'domicilio' => 'Calle publicación']);
        $inquilino = Inquilino::create(['nombre' => 'Inquilino publicación']);
        $payload = app(ContractDraftPayload::class)->empty();
        $party = ['person' => ['person_type' => 'fisica', 'full_name' => 'Persona completa', 'legal_name' => null, 'rfc' => 'XAXX010101000', 'nationality' => 'Mexicana', 'birth_place' => 'México', 'birth_date' => '1990-01-01', 'marital_status' => 'Soltero', 'occupation' => 'Comerciante', 'address' => 'Domicilio', 'identification_type' => 'INE', 'phone' => '3311111111', 'email' => 'persona@example.test', 'incorporation_deed' => null, 'contact_email_entered' => null, 'contact_phone_entered' => null], 'representative' => null];
        $payload['metadata']['contract_date'] = '2026-01-01';
        $payload['lessor'] = $party;
        $payload['lessee'] = $party;
        $payload['leased_property'] = ['alias' => 'Casa publicación', 'address' => 'Calle publicación', 'property_use_codes' => $uses, 'master_property_id' => null];
        $payload['term'] = ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'duration_label' => '12 meses', 'rent_due_rule' => ['raw_text' => '5', 'day_from' => null, 'day_to' => null, 'day_of_month' => 5]];
        $payload['amounts'] = ['total_rent' => '120000.00', 'monthly_rent' => '10000.00', 'security_deposit' => '10000.00', 'rental_commission' => '10000.00', 'monthly_commission_value' => '10.00', 'monthly_commission_unit' => 'percent'];
        $draft = app(ContractDraftVersioningService::class)->createDraft($payload, ['source' => 'internal', 'created_by' => $agent->id]);
        $draft->forceFill(['cliente_id' => $cliente->pk_cliente, 'propiedad_id' => $propiedad->pk_propiedad, 'inquilino_id' => $inquilino->id])->save();
        $google = new PrivateFlowGoogleClient;
        $this->app->instance(GoogleContractDocumentClient::class, $google);
        config(['services.google_contracts.templates.lease_without_guarantor' => 'template', 'services.google_contracts.destination_folder_id' => 'parent']);

        return [$draft->fresh(['currentVersion']), $agent, $google];
    }
}

class PrivateFlowGoogleClient implements GoogleContractDocumentClient
{
    public int $folderCalls = 0;
    public int $copyCalls = 0;
    public bool $fail = false;
    public function createFolder(string $parentFolderId, string $name): array { $this->folderCalls++; return ['id' => 'folder']; }
    public function copyTemplate(string $templateId, string $name, string $folderId): array { $this->copyCalls++; return ['id' => 'file', 'url' => 'https://docs.google.test/file']; }
    public function applyOperations(string $documentId, array $operations): void { if ($this->fail) throw new \RuntimeException('Google simulado falló'); }
    public function remainingMarkers(string $documentId, array $markers): array { return []; }
}
