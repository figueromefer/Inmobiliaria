<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Contracts\GoogleContractDocumentClient;
use App\Models\ContractDocumentVersion;
use App\Models\ContractDraft;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Models\User;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use App\Services\ContractDocumentPayloadBuilder;
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
        $initialCommissionCount = \App\Models\Movimiento::query()->where('contrato_id', $contract->id)->where('automation_type', 'initial_commission')->count();
        $oldSnapshot = $contract->draftVersion->canonical_payload;
        $oldDocument = $contract->contract_document_version_id;
        $contract->cliente->update(['nombre' => 'Maestro cambiado después de publicar']);
        ContratoPendiente::create([
            'origen' => 'privado', 'external_id' => 'must-not-be-read-'.$contract->id, 'estado' => 'importado', 'contrato_id' => $contract->id,
            'raw_payload' => ['tipo_solicitante' => 'Persona Moral', 'cliente_nombre' => 'Payload legacy que no debe usarse'],
        ]);

        $this->actingAs($agent)->post(route('contratos.revision.start', $contract))
            ->assertRedirect();
        $revision = ContractDraft::query()->where('editing_contract_id', $contract->id)->sole()->load('currentVersion');
        $this->assertSame('revision', $revision->purpose);
        $this->assertSame('Persona completa', data_get($revision->currentVersion->canonical_payload, 'lessor.person.full_name'));
        $this->assertSame('fisica', data_get($revision->currentVersion->canonical_payload, 'lessor.person.person_type'));
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
        $this->assertSame($initialCommissionCount, \App\Models\Movimiento::query()->where('contrato_id', $contract->id)->where('automation_type', 'initial_commission')->count());
        $this->assertDatabaseCount('contract_document_versions', 2);
        $this->assertSame(1, $google->folderCalls);
        $this->assertSame(2, $google->copyCalls);
        $this->assertSame('Persona completa', data_get($draft->fresh()->currentVersion->canonical_payload, 'lessor.person.full_name'));
        $this->assertSame($oldSnapshot, $draft->fresh()->currentVersion->canonical_payload);
        $this->actingAs($agent)->post(route('contratos.privados.revision.publish', $revision), ['expected_version_id' => $revision->current_version_id]);
        $this->assertDatabaseCount('contract_document_versions', 2);
        $this->assertSame(2, $google->copyCalls);
        $this->actingAs($agent)->get(route('contratos.show', $contract))
            ->assertSee('Versiones del contrato')->assertSee('Actual')->assertSee('Anterior')->assertSee('Abrir carpeta en Drive');
    }

    public function test_published_revision_reconciles_the_new_property_into_the_definitive_contract(): void
    {
        [$draft, $agent] = $this->completeDraft();
        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $draft->current_version_id]);
        $contract = $draft->fresh()->contrato;
        $previousPropertyId = $contract->fk_propiedad;
        $replacement = Propiedad::create(['fk_cliente' => $contract->fk_cliente, 'alias' => 'Casa reemplazo', 'domicilio' => 'Calle nueva']);

        $this->actingAs($agent)->post(route('contratos.revision.start', $contract))->assertRedirect();
        $revision = ContractDraft::query()->where('editing_contract_id', $contract->id)->sole();
        $this->reconcile($agent, $revision, 'propiedad', $replacement->pk_propiedad);
        $revision->refresh();
        $this->save($agent, $revision, 'vigencia', [
            'term' => ['start_date' => '2026-02-01', 'end_date' => '2026-12-31'],
            'amounts' => [
                'monthly_rent' => '10460.00',
                'rental_commission' => '4321.00',
                'monthly_commission_value' => '1000.00',
                'monthly_commission_unit' => 'fixed',
            ],
        ]);
        $revision->refresh()->load('currentVersion');

        $this->assertSame($replacement->pk_propiedad, $revision->propiedad_id);
        $this->assertSame($replacement->pk_propiedad, data_get($revision->currentVersion->canonical_payload, 'leased_property.master_property_id'));
        $this->assertSame('1000.00', data_get($revision->currentVersion->canonical_payload, 'amounts.monthly_commission_value'));
        $this->assertSame('fixed', data_get($revision->currentVersion->canonical_payload, 'amounts.monthly_commission_unit'));
        $this->assertSame($previousPropertyId, $contract->fresh()->fk_propiedad);
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$revision, 'vigencia']))
            ->assertOk()->assertSee('value="1000.00"', false)->assertSee('value="fixed" selected', false);

        $this->actingAs($agent)->post(route('contratos.privados.revision.publish', $revision), ['expected_version_id' => $revision->current_version_id])
            ->assertRedirect(route('contratos.show', $contract));
        $contract->refresh();
        $this->assertSame($replacement->pk_propiedad, $contract->fk_propiedad);
        $this->assertSame(10460.0, (float) $contract->monto_mensual);
        $this->assertSame(4321.0, (float) $contract->comision_renta);
        $this->assertSame(1000.0, (float) $contract->comision_mensual);
        $this->assertSame('fixed', $contract->comision_mensual_unidad);
        $this->assertSame('2026-02-01', $contract->fecha_inicio->toDateString());
        $this->assertSame('2026-12-31', $contract->fecha_fin->toDateString());
        $this->actingAs($agent)->get(route('contratos.show', $contract))->assertSee('$1,000.00 fijo');
        $status = app(\App\Services\PropertyOperationalStatusService::class);
        $this->assertSame('desocupada', $status->for(Propiedad::findOrFail($previousPropertyId), \Carbon\Carbon::parse('2026-10-19'))['key']);
        $this->assertNotSame('desocupada', $status->for($replacement, \Carbon\Carbon::parse('2026-10-19'))['key']);
    }

    public function test_legacy_revision_recovers_linked_historical_facts_once_then_uses_its_published_snapshot(): void
    {
        [$templateDraft, $agent] = $this->completeDraft();
        $cliente = $templateDraft->cliente;
        $propiedad = $templateDraft->propiedad;
        $inquilino = $templateDraft->inquilino;
        $legacy = Contrato::create([
            'fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'inquilino_id' => $inquilino->id,
            'fecha' => '2026-01-01', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'dias_pago' => 5,
            'monto_total' => 120000, 'monto_mensual' => 10000, 'monto_deposito' => 10000, 'comision_renta' => 10000, 'comision_mensual' => 10,
            'origen' => 'privado', 'tipo_solicitante' => 'Persona Física', 'tipo_complementaria' => 'Persona Física',
        ]);
        $pending = ContratoPendiente::create([
            'origen' => 'privado', 'external_id' => 'legacy-source-'.$legacy->id, 'estado' => 'importado', 'contrato_id' => $legacy->id,
            'raw_payload' => [
                'tipo_solicitante' => 'Persona Física', 'tipo_complementaria' => 'Persona Física',
                'cliente_nombre' => 'Arrendadora RAW', 'cliente_rfc' => 'RAW010101RAW',
                'nombre_complementaria' => 'Arrendatario RAW', 'nacionalidad_complementaria' => 'Mexicana',
                'telefono_complementaria' => '3311111111', 'correo_complementaria' => 'raw@example.test',
            ],
            'mapped_payload' => [
                'tipo_solicitante' => 'Persona Física', 'tipo_complementaria' => 'Persona Física',
                'rfc_solicitante' => 'MAP010101MAP', 'fecha_inicio_contrato' => '2026-02-01',
                'fecha_terminacion_contrato' => '2027-01-31', 'dias_pago' => '05 de cada mes',
            ],
        ]);

        $this->actingAs($agent)->post(route('contratos.revision.start', $legacy))->assertRedirect();
        $first = ContractDraft::query()->where('editing_contract_id', $legacy->id)->where('status', 'draft')->sole()->load('currentVersion');
        $payload = $first->currentVersion->canonical_payload;
        $this->assertSame('MAP010101MAP', data_get($payload, 'lessor.person.rfc'));
        $this->assertSame('Arrendadora RAW', data_get($payload, 'lessor.person.full_name'));
        $this->assertSame('Arrendatario RAW', data_get($payload, 'lessee.person.full_name'));
        $this->assertSame('Mexicana', data_get($payload, 'lessee.person.nationality'));
        $this->assertSame('2026-02-01', data_get($payload, 'term.start_date'));
        $this->assertNull(data_get($payload, 'lessor.person.birth_place'));
        $this->assertSame([], data_get($payload, 'leased_property.property_use_codes'));
        $this->assertNull(data_get($payload, 'term.duration_label'));
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$first, 'generales']))
            ->assertSee('sistema anterior')->assertSee('deben completarse una sola vez');

        $complete = $templateDraft->currentVersion->canonical_payload;
        $complete['lessor']['person']['rfc'] = 'MAP010101MAP';
        $complete['lessee']['person']['birth_place'] = 'Zapopan';
        $complete['leased_property']['property_use_codes'] = ['residential'];
        $complete['term']['duration_label'] = '12 meses';
        $this->save($agent, $first, 'vigencia', $complete);
        $first->refresh()->load('currentVersion');
        $this->actingAs($agent)->post(route('contratos.privados.revision.publish', $first), ['expected_version_id' => $first->current_version_id])
            ->assertRedirect(route('contratos.show', $legacy));
        $legacy->refresh();
        $this->assertSame($first->current_version_id, $legacy->contract_draft_version_id);

        $pending->update(['raw_payload' => ['tipo_solicitante' => 'Persona Moral', 'cliente_nombre' => 'NO DEBE USARSE']]);

        $this->actingAs($agent)->post(route('contratos.revision.start', $legacy))->assertRedirect();
        $second = ContractDraft::query()->where('editing_contract_id', $legacy->id)->where('status', 'draft')->sole()->load('currentVersion');
        $this->assertSame('MAP010101MAP', data_get($second->currentVersion->canonical_payload, 'lessor.person.rfc'));
        $this->assertSame('Zapopan', data_get($second->currentVersion->canonical_payload, 'lessee.person.birth_place'));
        $this->assertSame(['residential'], data_get($second->currentVersion->canonical_payload, 'leased_property.property_use_codes'));
        $this->assertSame('Persona completa', data_get($second->currentVersion->canonical_payload, 'lessor.person.full_name'));
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$second, 'generales']))
            ->assertDontSee('sistema anterior');
    }

    public function test_legacy_person_types_keep_physical_and_moral_branches_separate(): void
    {
        $service = app(\App\Services\LegacyContractRevisionPayloadService::class);
        $payloads = app(ContractDraftPayload::class);

        $cases = [
            ['Persona Física', 'Persona Física', 'fisica', 'fisica'],
            ['Persona Moral', 'Persona Física', 'moral', 'fisica'],
            ['Persona Física', 'Persona Moral', 'fisica', 'moral'],
        ];
        foreach ($cases as [$lessorInput, $lesseeInput, $lessorType, $lesseeType]) {
            $cliente = Cliente::create(['nombre' => 'Cliente de tipo '.$lessorType, 'rfc' => 'TYPE-'.$lessorType.'-'.$lesseeType, 'domicilio' => 'Domicilio de tipo']);
            $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Propiedad '.$lessorType.' '.$lesseeType, 'domicilio' => 'Domicilio de tipo']);
            $contrato = Contrato::create([
                'fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'privado',
                'tipo_solicitante' => $lessorInput, 'tipo_complementaria' => $lesseeInput,
            ]);
            ContratoPendiente::create([
                'origen' => 'privado', 'external_id' => 'types-'.$contrato->id, 'estado' => 'importado', 'contrato_id' => $contrato->id,
                'raw_payload' => [
                    'tipo_solicitante' => $lessorInput, 'tipo_complementaria' => $lesseeInput,
                    'cliente_nombre' => 'Sociedad o persona arrendadora', 'nombre_complementaria' => 'Sociedad o persona arrendataria',
                    'nacionalidad_complementaria' => 'Mexicana',
                ],
            ]);

            $payload = $service->build($contrato, $payloads);
            $this->assertSame($lessorType, data_get($payload, 'lessor.person.person_type'));
            $this->assertSame($lesseeType, data_get($payload, 'lessee.person.person_type'));
            $this->assertNull(data_get($payload, 'lessor.representative'));
            if ($lessorType === 'moral') {
                $this->assertSame('Sociedad o persona arrendadora', data_get($payload, 'lessor.person.legal_name'));
                $this->assertNull(data_get($payload, 'lessor.person.full_name'));
            } else {
                $this->assertSame('Sociedad o persona arrendadora', data_get($payload, 'lessor.person.full_name'));
                $this->assertNull(data_get($payload, 'lessor.person.legal_name'));
            }
            if ($lesseeType === 'moral') {
                $this->assertSame('Sociedad o persona arrendataria', data_get($payload, 'lessee.person.legal_name'));
                $this->assertSame('Mexicana', data_get($payload, 'lessee.representative.nationality'));
                $this->assertNull(data_get($payload, 'lessee.person.nationality'));
            } else {
                $this->assertSame('Sociedad o persona arrendataria', data_get($payload, 'lessee.person.full_name'));
                $this->assertSame('Mexicana', data_get($payload, 'lessee.person.nationality'));
                $this->assertNull(data_get($payload, 'lessee.representative'));
            }
        }
    }

    public function test_legacy_pm_without_a_persisted_representative_keeps_it_null(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente moral', 'rfc' => 'MORA010101AAA', 'domicilio' => 'Domicilio moral']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Propiedad moral', 'domicilio' => 'Domicilio moral']);
        $contrato = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'privado', 'tipo_solicitante' => 'Persona Moral', 'tipo_complementaria' => 'Persona Física']);
        ContratoPendiente::create([
            'origen' => 'privado', 'external_id' => 'pm-without-representative', 'estado' => 'importado', 'contrato_id' => $contrato->id,
            'raw_payload' => ['tipo_solicitante' => 'Persona Moral', 'tipo_complementaria' => 'Persona Física', 'cliente_nombre' => 'Sociedad sin representante'],
        ]);

        $payload = app(\App\Services\LegacyContractRevisionPayloadService::class)->build($contrato, app(ContractDraftPayload::class));

        $this->assertSame('moral', data_get($payload, 'lessor.person.person_type'));
        $this->assertSame('Sociedad sin representante', data_get($payload, 'lessor.person.legal_name'));
        $this->assertNull(data_get($payload, 'lessor.representative'));
    }

    public function test_legacy_source_priority_never_erases_a_valid_fact_with_an_empty_lower_or_higher_payload(): void
    {
        $cliente = Cliente::create(['nombre' => 'Maestro', 'rfc' => 'MASTER010101AAA', 'domicilio' => 'Domicilio maestro']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Propiedad prioridad', 'domicilio' => 'Domicilio maestro']);
        $contrato = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'privado', 'tipo_solicitante' => 'Persona Física']);
        $pending = ContratoPendiente::create([
            'origen' => 'privado', 'external_id' => 'priority-'.$contrato->id, 'estado' => 'importado', 'contrato_id' => $contrato->id,
            'raw_payload' => ['tipo_solicitante' => 'Persona Física', 'cliente_rfc' => 'RAW010101RAW'],
            'mapped_payload' => ['tipo_solicitante' => '', 'rfc_solicitante' => ''],
        ]);
        $service = app(\App\Services\LegacyContractRevisionPayloadService::class);

        $this->assertSame('RAW010101RAW', data_get($service->build($contrato, app(ContractDraftPayload::class)), 'lessor.person.rfc'));
        $pending->update(['raw_payload' => ['tipo_solicitante' => 'Persona Física', 'cliente_rfc' => ''], 'mapped_payload' => ['rfc_solicitante' => '']]);
        $this->assertSame('MASTER010101AAA', data_get($service->build($contrato->fresh(), app(ContractDraftPayload::class)), 'lessor.person.rfc'));
        $pending->update(['raw_payload' => ['tipo_solicitante' => 'Persona Física', 'cliente_rfc' => 'RAW010101RAW'], 'mapped_payload' => ['rfc_solicitante' => 'MAP010101MAP']]);
        $this->assertSame('MAP010101MAP', data_get($service->build($contrato->fresh(), app(ContractDraftPayload::class)), 'lessor.person.rfc'));
    }

    public function test_multiple_linked_pendings_are_not_combined_or_arbitrarily_selected(): void
    {
        $cliente = Cliente::create(['nombre' => 'Maestro seguro', 'rfc' => 'MASTER010101AAA', 'domicilio' => 'Domicilio maestro']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Propiedad segura', 'domicilio' => 'Domicilio maestro']);
        $contrato = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'privado', 'tipo_solicitante' => 'Persona Física']);
        foreach (['one', 'two'] as $suffix) {
            ContratoPendiente::create([
                'origen' => 'privado', 'external_id' => 'duplicate-'.$suffix, 'estado' => 'importado', 'contrato_id' => $contrato->id,
                'raw_payload' => ['tipo_solicitante' => 'Persona Moral', 'cliente_nombre' => 'No usar '.$suffix],
            ]);
        }

        $payload = app(\App\Services\LegacyContractRevisionPayloadService::class)->build($contrato, app(ContractDraftPayload::class));
        $this->assertSame('fisica', data_get($payload, 'lessor.person.person_type'));
        $this->assertSame('Maestro seguro', data_get($payload, 'lessor.person.full_name'));
        $this->assertSame('MASTER010101AAA', data_get($payload, 'lessor.person.rfc'));
    }

    public function test_lessee_rfc_has_one_form_control_and_survives_wizard_versions_for_pf_and_pm(): void
    {
        [$draft, $agent] = $this->completeDraft();
        $rfcName = 'payload[lessee][person][rfc]';

        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'arrendatario']))
            ->assertOk()->assertSee($rfcName, false);
        $this->assertSame(1, substr_count($this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'arrendatario']))->getContent(), $rfcName));

        $this->save($agent, $draft, 'arrendatario', ['lessee' => ['person' => ['person_type' => 'fisica', 'rfc' => 'ABCD010101ABC']]]);
        $draft->refresh()->load('currentVersion');
        $this->assertSame('ABCD010101ABC', data_get($draft->currentVersion->canonical_payload, 'lessee.person.rfc'));
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'arrendatario']))
            ->assertSee('value="ABCD010101ABC"', false);

        $inquilino = Inquilino::create(['nombre' => 'Autocomplete sin RFC']);
        $this->reconcile($agent, $draft, 'inquilino', $inquilino->id);
        $draft->refresh()->load('currentVersion');
        $this->assertSame('ABCD010101ABC', data_get($draft->currentVersion->canonical_payload, 'lessee.person.rfc'));

        $this->save($agent, $draft, 'mantenimiento', ['maintenance' => ['exists' => 'no']]);
        $draft->refresh()->load('currentVersion');
        $plan = app(ContractDocumentPayloadBuilder::class)->build($draft->currentVersion);
        $this->assertSame('ABCD010101ABC', $plan['placeholders']['{{arrendatario_rfc}}']);
        $this->assertNotContains('arrendatario: RFC', $plan['missing_fields']);

        $this->save($agent, $draft, 'arrendatario', ['lessee' => ['person' => ['person_type' => 'moral', 'legal_name' => 'Arrendataria SA', 'rfc' => 'XYZ010101XYZ']]]);
        $draft->refresh()->load('currentVersion');
        $this->assertSame('XYZ010101XYZ', data_get($draft->currentVersion->canonical_payload, 'lessee.person.rfc'));
        $this->assertSame('XYZ010101XYZ', app(ContractDocumentPayloadBuilder::class)->build($draft->currentVersion)['placeholders']['{{arrendatario_rfc}}']);
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
