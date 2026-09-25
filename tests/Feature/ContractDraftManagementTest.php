<?php

namespace Tests\Feature;

use App\Models\ContractDraft;
use App\Models\User;
use App\Services\ContractDraftPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractDraftManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_viewer_cannot_access_internal_drafts(): void
    {
        $this->get(route('contratos.borradores.index'))->assertRedirect(route('login'));
        $this->actingAs($this->viewer())->get(route('contratos.borradores.index'))->assertForbidden();
    }

    public function test_guest_and_viewer_cannot_create_update_or_read_versions_directly(): void
    {
        $this->post(route('contratos.borradores.store'), ['payload' => $this->payload()])->assertRedirect(route('login'));
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $this->actingAs($agent)->post(route('contratos.borradores.store'), ['payload' => $this->payload()]);
        $draft = ContractDraft::with('currentVersion')->sole();
        $viewer = $this->viewer();

        $this->app['auth']->guard()->logout();
        $this->put(route('contratos.borradores.update', $draft), ['payload' => [], 'expected_version_id' => $draft->currentVersion->id])->assertRedirect(route('login'));
        $this->actingAs($viewer)->post(route('contratos.borradores.store'), ['payload' => $this->payload()])->assertForbidden();
        $this->actingAs($viewer)->put(route('contratos.borradores.update', $draft), ['payload' => [], 'expected_version_id' => $draft->currentVersion->id])->assertForbidden();
        $this->actingAs($viewer)->get(route('contratos.borradores.versions', $draft))->assertForbidden();
        $this->actingAs($viewer)->get(route('contratos.borradores.version', [$draft, $draft->currentVersion]))->assertForbidden();
    }

    public function test_admin_and_agent_can_list_internal_drafts(): void
    {
        foreach (['admin', 'agent'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('contratos.borradores.index'))
                ->assertOk()
                ->assertSee('Borradores y solicitudes de contrato');
        }
    }

    public function test_authorized_user_can_render_the_create_and_edit_forms(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $this->actingAs($actor)->get(route('contratos.borradores.create'))
            ->assertOk()
            ->assertSee('Nuevo borrador interno')
            ->assertSee('Regla de pago (texto literal)');

        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $this->payload()]);
        $draft = ContractDraft::sole();
        $this->actingAs($actor)->get(route('contratos.borradores.edit', $draft))
            ->assertOk()
            ->assertSee('Guardar nueva versión');
    }

    public function test_authorized_user_creates_an_incomplete_draft_with_version_one_only(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $payload = $this->payload();
        $payload['term']['rent_due_rule']['raw_text'] = '05 a 10';
        $payload['leased_property']['property_use_codes'] = ['commercial', 'residential'];

        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $payload])
            ->assertRedirect();

        $draft = ContractDraft::with('currentVersion')->sole();
        $this->assertSame('laravel', $draft->source);
        $this->assertSame(ContractDraft::STATUS_DRAFT, $draft->status);
        $this->assertSame(1, $draft->currentVersion->draft_version);
        $this->assertSame($actor->id, $draft->currentVersion->created_by);
        $this->assertSame('05 a 10', $draft->currentVersion->canonical_payload['term']['rent_due_rule']['raw_text']);
        $this->assertSame(['commercial', 'residential'], $draft->currentVersion->canonical_payload['leased_property']['property_use_codes']);
        $this->assertDatabaseCount('contratos', 0);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('propiedades', 0);
        $this->assertDatabaseCount('inquilinos', 0);
    }

    public function test_internal_form_literal_production_scenario_creates_a_canonical_draft(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $formPayload = [
            'metadata' => ['contract_reference' => '12 prueba', 'contract_date' => '2026-09-25'],
            'lessor' => ['person' => ['person_type' => 'fisica', 'full_name' => 'JOSE ADALBERTO GONZALEZ HERNANDEZ', 'rfc' => '123456777777777', 'email' => 'J_PABLO37@HOTMAIL.COM']],
            'lessee' => ['person' => ['person_type' => 'moral', 'legal_name' => 'PRENTIS GDL SA DE CV', 'rfc' => '.3432452', 'email' => 'JPADILLA@DORANTESARANDA.COM']],
            'guarantor' => ['type' => 'fisica', 'person' => ['person_type' => 'fisica', 'full_name' => 'MARTHA ESPINOSA']],
            'guarantee_property' => ['exists' => 'yes', 'address' => 'PRUEBA DE DOMICILIO DE GARANTIA'],
            'leased_property' => ['alias' => 'TORRE CELTIS V212 PRUEBA', 'address' => 'REAL ACUEDUCTO 240 INTERIOR 8', '_property_use_codes_submitted' => '1', 'property_use_codes' => ['commercial']],
            'term' => ['start_date' => '2026-09-25', 'end_date' => '2027-09-24', 'rent_due_rule' => ['raw_text' => '25 al 30']],
            'amounts' => ['monthly_rent' => '23000', 'security_deposit' => '23000'],
            'payment' => ['method' => 'unspecified'],
            'maintenance' => ['exists' => 'yes', 'payer' => 'lessor'],
            'renewal' => ['is_renewal' => 'no'],
        ];

        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $formPayload])->assertRedirect();

        $draft = ContractDraft::with('currentVersion')->sole();
        $stored = $draft->currentVersion->canonical_payload;
        $this->assertSame('laravel', $draft->source);
        $this->assertSame(ContractDraft::STATUS_DRAFT, $draft->status);
        $this->assertSame(1, $draft->currentVersion->draft_version);
        $this->assertSame('PRENTIS GDL SA DE CV', $stored['lessee']['person']['legal_name']);
        $this->assertNull($stored['lessee']['person']['full_name']);
        $this->assertSame('23000', $stored['amounts']['monthly_rent']);
        $this->assertSame('23000', $stored['amounts']['security_deposit']);
    }

    public function test_formatted_money_is_normalized_without_losing_decimal_precision(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $payload = $this->payload();
        $payload['amounts']['monthly_rent'] = '$23,000.50';
        $payload['amounts']['security_deposit'] = '$23,000.00';

        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $payload])->assertRedirect();

        $stored = ContractDraft::with('currentVersion')->sole()->currentVersion->canonical_payload;
        $this->assertSame('23000.50', $stored['amounts']['monthly_rent']);
        $this->assertSame('23000.00', $stored['amounts']['security_deposit']);
    }

    public function test_form_errors_are_rendered_next_to_the_relevant_amount_and_old_input_is_preserved(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $payload = $this->payload();
        $payload['amounts']['monthly_rent'] = '$23,00.00';

        $this->actingAs($actor)->from(route('contratos.borradores.create'))
            ->post(route('contratos.borradores.store'), ['payload' => $payload])
            ->assertRedirect(route('contratos.borradores.create'))
            ->assertSessionHasErrors('payload.amounts.monthly_rent');

        $this->actingAs($actor)->get(route('contratos.borradores.create'))
            ->assertOk()
            ->assertSee('El importe debe ser numérico o null.')
            ->assertSee('$23,00.00', false);
    }

    public function test_edit_creates_an_immutable_second_version_with_the_editor_as_actor(): void
    {
        $creator = User::factory()->create(['role' => User::ROLE_AGENT]);
        $editor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($creator)->post(route('contratos.borradores.store'), ['payload' => $this->payload()]);
        $draft = ContractDraft::with('currentVersion')->sole();
        $firstVersion = $draft->currentVersion;
        $updated = $firstVersion->canonical_payload;
        $updated['amounts']['monthly_rent'] = '12500.00';

        $this->actingAs($editor)->put(route('contratos.borradores.update', $draft), ['payload' => $updated, 'expected_version_id' => $firstVersion->id])
            ->assertRedirect(route('contratos.borradores.show', $draft));

        $draft->refresh()->load('currentVersion');
        $this->assertSame(2, $draft->currentVersion->draft_version);
        $this->assertSame($editor->id, $draft->currentVersion->created_by);
        $this->assertSame($draft->currentVersion->id, $draft->current_version_id);
        $this->assertSame(null, $firstVersion->fresh()->canonical_payload['amounts']['monthly_rent']);
        $this->assertSame('12500.00', $draft->currentVersion->canonical_payload['amounts']['monthly_rent']);
    }

    public function test_technically_invalid_payload_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['leased_property']['property_use_codes'] = ['not-a-real-use'];

        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->from(route('contratos.borradores.create'))
            ->post(route('contratos.borradores.store'), ['payload' => $payload])
            ->assertRedirect(route('contratos.borradores.create'))
            ->assertSessionHasErrors('payload.leased_property.property_use_codes.0');

        $this->assertDatabaseCount('contract_drafts', 0);
    }

    public function test_malformed_dto_nodes_return_controlled_validation_errors(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);

        foreach ([
            'lessor' => 'x',
            'payment' => 123,
            'guarantor' => 'foo',
        ] as $key => $invalidValue) {
            $payload = $this->payload();
            $payload[$key] = $invalidValue;

            $this->actingAs($actor)->postJson(route('contratos.borradores.store'), ['payload' => $payload])
                ->assertUnprocessable()
                ->assertJsonValidationErrors("payload.{$key}");
        }

        $this->assertDatabaseCount('contract_drafts', 0);
    }

    public function test_explicit_empty_lists_are_rejected_for_root_object_nodes_without_creating_a_version(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $draft = $this->createDraft($actor, $this->payload());
        $currentVersionId = $draft->currentVersion->id;

        foreach (['guarantor', 'lessor'] as $node) {
            $this->actingAs($actor)->putJson(route('contratos.borradores.update', $draft), [
                'expected_version_id' => $currentVersionId,
                'payload' => [$node => []],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors("payload.{$node}");

            $draft->refresh();
            $this->assertSame($currentVersionId, $draft->current_version_id);
            $this->assertDatabaseCount('contract_draft_versions', 1);
        }
    }

    public function test_reserved_document_and_audit_nodes_reject_injected_data(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        foreach (['document_generation', 'audit'] as $key) {
            $payload = $this->payload();
            $payload[$key] = ['unexpected' => 'value'];
            $this->actingAs($actor)->postJson(route('contratos.borradores.store'), ['payload' => $payload])
                ->assertUnprocessable()
                ->assertJsonValidationErrors("payload.{$key}");
        }
    }

    public function test_internal_capture_rejects_an_external_id_in_the_canonical_payload(): void
    {
        $payload = $this->payload();
        $payload['metadata']['external_id'] = 'outside-response-id';

        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->postJson(route('contratos.borradores.store'), ['payload' => $payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload.metadata.external_id');

        $this->assertDatabaseCount('contract_drafts', 0);
    }

    public function test_calendar_dates_are_validated_without_imposing_term_business_rules(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        foreach (['2026-99-99', '2026-02-30', '2026-13-01'] as $invalidDate) {
            $payload = $this->payload();
            $payload['term']['start_date'] = $invalidDate;
            $this->actingAs($actor)->postJson(route('contratos.borradores.store'), ['payload' => $payload])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('payload.term.start_date');
        }

        foreach (['2026-02-28', '2028-02-29'] as $validDate) {
            $payload = $this->payload();
            $payload['term']['start_date'] = $validDate;
            $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $payload])->assertRedirect();
        }
    }

    public function test_guarantor_none_cleans_non_applicable_person_and_guarantee_data(): void
    {
        $payload = $this->payload();
        $payload['guarantor'] = [
            'type' => 'none',
            'person' => ['person_type' => 'fisica', 'full_name' => 'Dato no aplicable'],
            'representative' => ['full_name' => 'Dato no aplicable'],
        ];
        $payload['guarantee_property'] = ['exists' => 'yes', 'address' => 'Dato no aplicable', 'title_deed' => 'Dato no aplicable'];

        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->post(route('contratos.borradores.store'), ['payload' => $payload]);

        $stored = ContractDraft::with('currentVersion')->sole()->currentVersion->canonical_payload;
        $this->assertEquals(['type' => 'none', 'person' => null, 'representative' => null], $stored['guarantor']);
        $this->assertEquals(['exists' => 'no', 'address' => null, 'title_deed' => null], $stored['guarantee_property']);
    }

    public function test_editing_visible_fields_preserves_existing_fields_not_yet_exposed_by_the_minimal_ui(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $payload = $this->payload();
        $payload['lessor']['person']['nationality'] = 'Mexicana';
        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $payload]);
        $draft = ContractDraft::with('currentVersion')->sole();

        $this->actingAs($actor)->put(route('contratos.borradores.update', $draft), [
            'payload' => ['amounts' => ['monthly_rent' => '12500.00']],
            'expected_version_id' => $draft->currentVersion->id,
        ])->assertRedirect();

        $stored = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertSame('Mexicana', $stored['lessor']['person']['nationality']);
        $this->assertSame('12500.00', $stored['amounts']['monthly_rent']);
    }

    public function test_property_use_codes_preserve_when_omitted_replace_as_a_list_and_can_be_cleared(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $payload = $this->payload();
        $payload['leased_property']['property_use_codes'] = ['commercial', 'residential'];
        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $payload]);
        $draft = ContractDraft::with('currentVersion')->sole();

        $this->saveDraft($actor, $draft, ['amounts' => ['monthly_rent' => '100.00']]);
        $draft->refresh()->load('currentVersion');
        $this->assertSame(['commercial', 'residential'], $draft->currentVersion->canonical_payload['leased_property']['property_use_codes']);

        $this->saveDraft($actor, $draft, ['leased_property' => ['property_use_codes' => ['industrial', 'residential']]]);
        $draft->refresh()->load('currentVersion');
        $this->assertSame(['industrial', 'residential'], $draft->currentVersion->canonical_payload['leased_property']['property_use_codes']);

        $this->saveDraft($actor, $draft, ['leased_property' => ['_property_use_codes_submitted' => '1']]);
        $this->assertSame([], $draft->fresh()->currentVersion->canonical_payload['leased_property']['property_use_codes']);
    }

    public function test_person_type_transitions_clear_only_the_previous_branch_specific_data(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        foreach (['lessor', 'lessee'] as $partyKey) {
            $payload = $this->payload();
            $payload[$partyKey]['person'] = array_merge($payload[$partyKey]['person'], [
                'person_type' => 'fisica', 'full_name' => 'Persona física', 'nationality' => 'Mexicana',
                'rfc' => 'RFC-COMPARTIDO', 'address' => 'Domicilio', 'phone' => '555', 'email' => 'test@example.test',
            ]);
            $draft = $this->createDraft($actor, $payload);
            $this->saveDraft($actor, $draft, [$partyKey => ['person' => ['person_type' => 'moral', 'legal_name' => 'Sociedad', 'incorporation_deed' => 'Acta']]]);
            $moral = $draft->fresh()->currentVersion->canonical_payload[$partyKey];
            $this->assertNull($moral['person']['full_name']);
            $this->assertNull($moral['person']['nationality']);
            $this->assertSame('RFC-COMPARTIDO', $moral['person']['rfc']);
            $this->assertSame('Sociedad', $moral['person']['legal_name']);

            $this->saveDraft($actor, $draft, [$partyKey => ['person' => ['person_type' => 'fisica', 'full_name' => 'Nueva persona']]]);
            $fisica = $draft->fresh()->currentVersion->canonical_payload[$partyKey];
            $this->assertNull($fisica['person']['legal_name']);
            $this->assertNull($fisica['person']['incorporation_deed']);
            $this->assertNull($fisica['representative']);
            $this->assertSame('Nueva persona', $fisica['person']['full_name']);
        }
    }

    public function test_guarantor_type_transitions_and_none_clean_the_previous_branch(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $payload = $this->payload();
        $payload['guarantor'] = ['type' => 'fisica', 'person' => array_merge($payload['lessor']['person'], ['person_type' => 'fisica', 'full_name' => 'Fiador físico']), 'representative' => null];
        $payload['guarantee_property'] = ['exists' => 'yes', 'address' => 'Garantía', 'title_deed' => 'Título'];
        $draft = $this->createDraft($actor, $payload);

        $this->saveDraft($actor, $draft, ['guarantor' => ['type' => 'moral', 'person' => ['person_type' => 'moral', 'legal_name' => 'Fiador moral']]]);
        $moral = $draft->fresh()->currentVersion->canonical_payload['guarantor'];
        $this->assertSame('moral', $moral['type']);
        $this->assertNull($moral['person']['full_name']);
        $this->assertSame('Fiador moral', $moral['person']['legal_name']);

        $this->saveDraft($actor, $draft, ['guarantor' => ['type' => 'fisica', 'person' => ['person_type' => 'fisica', 'full_name' => 'Nuevo fiador']]]);
        $fisica = $draft->fresh()->currentVersion->canonical_payload['guarantor'];
        $this->assertNull($fisica['person']['legal_name']);
        $this->assertNull($fisica['representative']);

        $this->saveDraft($actor, $draft, ['guarantor' => ['type' => 'none']]);
        $stored = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertEquals(['type' => 'none', 'person' => null, 'representative' => null], $stored['guarantor']);
        $this->assertEquals(['exists' => 'no', 'address' => null, 'title_deed' => null], $stored['guarantee_property']);
    }

    public function test_payment_maintenance_and_renewal_cleanup_rules_are_applied(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $payload = $this->payload();
        $payload['payment'] = ['method' => 'bank_transfer', 'bank_name' => 'Banco', 'beneficiary' => 'Beneficiario', 'clabe' => '123'];
        $payload['maintenance'] = ['exists' => 'yes', 'payer' => 'lessee'];
        $payload['renewal'] = ['is_renewal' => 'yes', 'previous_contract_id' => 99, 'deposit_treatment' => ['legacy' => 'x'], 'legacy_deposit_clause_snapshot' => 'Cláusula'];
        $draft = $this->createDraft($actor, $payload);

        $this->saveDraft($actor, $draft, [
            'payment' => ['method' => 'cash'],
            'maintenance' => ['exists' => 'no'],
            'renewal' => ['is_renewal' => 'no'],
        ]);
        $stored = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertEquals(['method' => 'cash', 'bank_name' => null, 'beneficiary' => null, 'clabe' => null], $stored['payment']);
        $this->assertEquals(['exists' => 'no', 'payer' => null], $stored['maintenance']);
        $this->assertEquals(['is_renewal' => 'no', 'previous_contract_id' => null, 'deposit_treatment' => null, 'legacy_deposit_clause_snapshot' => null], $stored['renewal']);
    }

    public function test_stale_expected_version_is_rejected_without_a_third_version_or_lost_data(): void
    {
        $firstEditor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $secondEditor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $draft = $this->createDraft($firstEditor, $this->payload());
        $expectedVersionId = $draft->currentVersion->id;

        $this->actingAs($firstEditor)->put(route('contratos.borradores.update', $draft), [
            'expected_version_id' => $expectedVersionId,
            'payload' => ['amounts' => ['monthly_rent' => '12000.00']],
        ])->assertRedirect();

        $conflict = $this->actingAs($secondEditor)->from(route('contratos.borradores.edit', $draft))->put(route('contratos.borradores.update', $draft), [
            'expected_version_id' => $expectedVersionId,
            'payload' => ['term' => ['rent_due_rule' => ['raw_text' => '05 a 10']]],
        ]);
        $conflict->assertRedirect(route('contratos.borradores.edit', $draft))
            ->assertSessionHasErrors('expected_version_id');
        $this->actingAs($secondEditor)->get(route('contratos.borradores.edit', $draft))
            ->assertOk()
            ->assertSee('El borrador fue actualizado por otro usuario. Recarga la página antes de guardar.');

        $draft->refresh()->load('currentVersion');
        $this->assertDatabaseCount('contract_draft_versions', 2);
        $this->assertSame(2, $draft->currentVersion->draft_version);
        $this->assertSame('12000.00', $draft->currentVersion->canonical_payload['amounts']['monthly_rent']);
        $this->assertNull($draft->currentVersion->canonical_payload['term']['rent_due_rule']['raw_text']);
    }

    public function test_historical_version_from_another_draft_returns_not_found(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $first = $this->createDraft($actor, $this->payload());
        $second = $this->createDraft($actor, $this->payload());

        $this->actingAs($actor)->get(route('contratos.borradores.version', [$first, $second->currentVersion]))->assertNotFound();
    }

    public function test_history_and_historical_snapshot_are_read_only(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_AGENT]);
        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $this->payload()]);
        $draft = ContractDraft::with('currentVersion')->sole();
        $first = $draft->currentVersion;
        $changed = $first->canonical_payload;
        $changed['term']['rent_due_rule']['raw_text'] = '15';
        $this->actingAs($actor)->put(route('contratos.borradores.update', $draft), ['payload' => $changed, 'expected_version_id' => $first->id]);

        $this->actingAs($actor)->get(route('contratos.borradores.versions', $draft))
            ->assertOk()->assertSee('Versión')->assertSee((string) $first->payload_hash);
        $this->actingAs($actor)->get(route('contratos.borradores.version', [$draft, $first]))
            ->assertOk()->assertSee('sólo lectura')->assertDontSee('Editar borrador');
        $this->actingAs($actor)->patch(route('contratos.borradores.version', [$draft, $first]))->assertStatus(405);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return app(ContractDraftPayload::class)->empty();
    }

    /** @param array<string, mixed> $payload */
    private function createDraft(User $actor, array $payload): ContractDraft
    {
        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $payload])->assertRedirect();

        return ContractDraft::with('currentVersion')->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $payload */
    private function saveDraft(User $actor, ContractDraft $draft, array $payload): void
    {
        $expectedVersionId = $draft->fresh()->currentVersion->id;
        $this->actingAs($actor)->put(route('contratos.borradores.update', $draft), [
            'payload' => $payload,
            'expected_version_id' => $expectedVersionId,
        ])->assertRedirect();
    }

    private function viewer(): User
    {
        return User::factory()->create(['role' => User::ROLE_VIEWER]);
    }
}
