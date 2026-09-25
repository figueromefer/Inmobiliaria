<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\Contrato;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\User;
use App\Services\ContractDraftPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractDraftWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_exposes_every_document_critical_capture_field(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);

        foreach ([
            'generales' => ['Fecha de firma', 'Domicilio del inmueble'],
            'arrendador' => ['Nombre completo', 'Razón social', 'Acta constitutiva', 'Teléfono', 'Representante', 'Acta de facultades'],
            'arrendatario' => ['Nombre completo', 'Razón social', 'Acta constitutiva', 'Teléfono', 'Representante', 'Acta de facultades'],
            'tercero' => ['Tipo de tercero', 'Nombre completo', 'Razón social', 'Acta constitutiva', 'Representante'],
            'garantia' => ['¿Existe inmueble en garantía?', 'Título de propiedad'],
            'vigencia' => ['Inicio', 'Fin', 'Duración', 'Regla de pago', 'Renta total', 'Renta mensual', 'Depósito'],
            'uso' => ['Residencial', 'Industrial', 'Comercial', 'Otro'],
            'pago' => ['Forma de pago', 'Banco', 'Beneficiario', 'CLABE'],
            'mantenimiento' => ['¿Existen cuotas?', 'Quién paga'],
        ] as $step => $labels) {
            $response = $this->actingAs($actor)->get(route('contratos.borradores.wizard.show', [$draft, $step]))->assertOk();
            foreach ($labels as $label) {
                $response->assertSee($label);
            }
        }
    }

    public function test_authorized_user_can_open_wizard_and_save_general_step_as_new_version(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $first = $draft->currentVersion;

        $this->actingAs($actor)->get(route('contratos.borradores.wizard.show', [$draft, 'generales']))
            ->assertOk()->assertSee('Captura de contrato')->assertSee('Borrador — no publicado');
        $this->save($actor, $draft, 'generales', ['metadata' => ['contract_reference' => 'REF-1', 'contract_date' => '2026-09-15'], 'leased_property' => ['alias' => 'Alias correcto', 'address' => 'Domicilio']]);

        $draft->refresh()->load('currentVersion');
        $this->assertSame(2, $draft->currentVersion->draft_version);
        $this->assertSame('REF-1', $draft->currentVersion->canonical_payload['metadata']['contract_reference']);
        $this->assertNull($first->fresh()->canonical_payload['metadata']['contract_reference']);
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_guest_and_viewer_cannot_access_or_save_wizard_steps(): void
    {
        $agent = $this->agent();
        $draft = $this->draft($agent);
        $this->app['auth']->guard()->logout();
        $this->get(route('contratos.borradores.wizard.show', [$draft, 'arrendador']))->assertRedirect(route('login'));
        $this->put(route('contratos.borradores.wizard.save', [$draft, 'arrendador']), ['expected_version_id' => $draft->current_version_id, 'payload' => []])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))->get(route('contratos.borradores.wizard.show', [$draft, 'arrendador']))->assertForbidden();
    }

    public function test_admin_and_agent_can_get_every_step_without_creating_versions(): void
    {
        $draft = $this->draft($this->agent());
        $versionId = $draft->current_version_id;
        $steps = ['generales', 'arrendador', 'arrendatario', 'tercero', 'garantia', 'vigencia', 'uso', 'pago', 'mantenimiento', 'renovacion', 'resumen'];

        foreach ([User::ROLE_ADMIN, User::ROLE_AGENT] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            foreach ($steps as $step) {
                $this->actingAs($actor)->get(route('contratos.borradores.wizard.show', [$draft, $step]))
                    ->assertOk()->assertSee($step === 'resumen' ? 'Resumen del borrador' : 'Captura de contrato');
            }
        }

        $this->assertSame($versionId, $draft->fresh()->current_version_id);
        $this->assertDatabaseCount('contract_draft_versions', 1);
    }

    public function test_wizard_captures_pf_pm_parties_and_cleans_previous_branch(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $this->save($actor, $draft, 'arrendador', ['lessor' => ['person' => ['person_type' => 'fisica', 'full_name' => 'Arrendador PF', 'rfc' => 'RFC', 'nationality' => 'Mexicana', 'address' => 'A', 'phone' => '1', 'email' => 'a@example.test']]]);
        $this->save($actor, $draft, 'arrendador', ['lessor' => ['person' => ['person_type' => 'moral', 'legal_name' => 'Arrendador PM', 'incorporation_deed' => 'Acta', 'rfc' => 'RFC', 'address' => 'A', 'phone' => '1', 'email' => 'a@example.test'], 'representative' => ['full_name' => 'Representante']]]);
        $this->save($actor, $draft, 'arrendatario', ['lessee' => ['person' => ['person_type' => 'fisica', 'full_name' => 'Arrendatario PF', 'rfc' => 'RFC2', 'nationality' => 'Mexicana', 'address' => 'B', 'phone' => '2', 'email' => 'b@example.test']]]);
        $this->save($actor, $draft, 'arrendatario', ['lessee' => ['person' => ['person_type' => 'moral', 'legal_name' => 'Arrendatario PM', 'rfc' => 'RFC2', 'address' => 'B', 'phone' => '2', 'email' => 'b@example.test'], 'representative' => ['full_name' => 'Rep']]]);

        $payload = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertSame('Arrendador PM', $payload['lessor']['person']['legal_name']);
        $this->assertNull($payload['lessor']['person']['full_name']);
        $this->assertSame('Representante', $payload['lessor']['representative']['full_name']);
        $this->assertSame('Arrendatario PM', $payload['lessee']['person']['legal_name']);
        $this->assertNull($payload['lessee']['person']['full_name']);
    }

    public function test_wizard_captures_guarantor_guarantee_payment_maintenance_renewal_and_literal_due_rule(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $this->save($actor, $draft, 'tercero', ['guarantor' => ['type' => 'moral', 'person' => ['person_type' => 'moral', 'legal_name' => 'Fiador SA', 'rfc' => 'RFC', 'address' => 'C', 'phone' => '3', 'email' => 'f@example.test'], 'representative' => ['full_name' => 'Rep fiador']]]);
        $this->save($actor, $draft, 'garantia', ['guarantee_property' => ['exists' => 'yes', 'address' => 'Garantía', 'title_deed' => 'Título']]);
        $this->save($actor, $draft, 'vigencia', ['term' => ['start_date' => '2026-02-28', 'end_date' => '2028-02-29', 'duration_label' => '24 meses', 'rent_due_rule' => ['raw_text' => '05 a 10']], 'amounts' => ['monthly_rent' => '10000', 'monthly_commission_value' => '10', 'monthly_commission_unit' => 'percent']]);
        $this->save($actor, $draft, 'uso', ['leased_property' => ['_property_use_codes_submitted' => '1', 'property_use_codes' => ['commercial', 'residential']]]);
        $this->save($actor, $draft, 'pago', ['payment' => ['method' => 'bank_transfer', 'bank_name' => 'Banco', 'beneficiary' => 'Beneficiario', 'clabe' => '123']]);
        $this->save($actor, $draft, 'mantenimiento', ['maintenance' => ['exists' => 'yes', 'payer' => 'lessee']]);
        $this->save($actor, $draft, 'renovacion', ['renewal' => ['is_renewal' => 'yes', 'previous_contract_id' => 10]]);

        $payload = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertSame('05 a 10', $payload['term']['rent_due_rule']['raw_text']);
        $this->assertSame(['commercial', 'residential'], $payload['leased_property']['property_use_codes']);
        $this->assertSame('bank_transfer', $payload['payment']['method']);
        $this->assertSame('yes', $payload['maintenance']['exists']);
        $this->assertSame('yes', $payload['renewal']['is_renewal']);

        $this->save($actor, $draft, 'pago', ['payment' => ['method' => 'cash']]);
        $this->save($actor, $draft, 'mantenimiento', ['maintenance' => ['exists' => 'no']]);
        $this->save($actor, $draft, 'renovacion', ['renewal' => ['is_renewal' => 'no']]);
        $this->save($actor, $draft, 'tercero', ['guarantor' => ['type' => 'none']]);
        $payload = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertNull($payload['payment']['bank_name']);
        $this->assertNull($payload['maintenance']['payer']);
        $this->assertNull($payload['renewal']['previous_contract_id']);
        $this->assertSame('none', $payload['guarantor']['type']);
        $this->assertSame('no', $payload['guarantee_property']['exists']);
    }

    public function test_wizard_cleans_guarantee_payment_maintenance_and_property_use_transitions(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $this->save($actor, $draft, 'tercero', ['guarantor' => ['type' => 'moral', 'person' => ['person_type' => 'moral', 'legal_name' => 'PM'], 'representative' => ['full_name' => 'Rep']]]);
        $this->save($actor, $draft, 'garantia', ['guarantee_property' => ['exists' => 'yes', 'address' => 'Dirección', 'title_deed' => 'Título']]);
        $this->save($actor, $draft, 'garantia', ['guarantee_property' => ['exists' => 'no']]);
        $this->save($actor, $draft, 'pago', ['payment' => ['method' => 'bank_transfer', 'bank_name' => 'Banco', 'beneficiary' => 'Bene', 'clabe' => '123']]);
        $this->save($actor, $draft, 'pago', ['payment' => ['method' => 'cash']]);
        $this->save($actor, $draft, 'pago', ['payment' => ['method' => 'bank_transfer']]);
        $this->save($actor, $draft, 'pago', ['payment' => ['method' => 'unspecified']]);
        $this->save($actor, $draft, 'mantenimiento', ['maintenance' => ['exists' => 'yes', 'payer' => 'lessor']]);
        $this->save($actor, $draft, 'mantenimiento', ['maintenance' => ['exists' => 'yes', 'payer' => 'lessee']]);
        $this->save($actor, $draft, 'mantenimiento', ['maintenance' => ['exists' => 'no']]);
        $this->save($actor, $draft, 'uso', ['leased_property' => ['_property_use_codes_submitted' => '1', 'property_use_codes' => ['industrial', 'residential']]]);
        $this->save($actor, $draft, 'uso', ['leased_property' => ['_property_use_codes_submitted' => '1']]);
        $this->save($actor, $draft, 'generales', ['metadata' => ['contract_reference' => 'otro paso']]);

        $payload = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertEquals(['exists' => 'no', 'address' => null, 'title_deed' => null], $payload['guarantee_property']);
        $this->assertEquals(['method' => 'unspecified', 'bank_name' => null, 'beneficiary' => null, 'clabe' => null], $payload['payment']);
        $this->assertNull($payload['maintenance']['payer']);
        $this->assertSame([], $payload['leased_property']['property_use_codes']);
    }

    public function test_wizard_transitions_guarantor_from_pm_to_pf_and_then_none(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $this->save($actor, $draft, 'tercero', ['guarantor' => ['type' => 'moral', 'person' => ['person_type' => 'moral', 'legal_name' => 'Fiador PM', 'incorporation_deed' => 'Acta'], 'representative' => ['full_name' => 'Representante']]]);
        $this->save($actor, $draft, 'tercero', ['guarantor' => ['type' => 'fisica', 'person' => ['person_type' => 'fisica', 'full_name' => 'Fiador PF']]]);
        $payload = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertNull($payload['guarantor']['person']['legal_name']);
        $this->assertNull($payload['guarantor']['person']['incorporation_deed']);
        $this->assertNull($payload['guarantor']['representative']);
        $this->save($actor, $draft, 'garantia', ['guarantee_property' => ['exists' => 'yes', 'address' => 'D', 'title_deed' => 'T']]);
        $this->save($actor, $draft, 'tercero', ['guarantor' => ['type' => 'none']]);
        $payload = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertEquals(['type' => 'none', 'person' => null, 'representative' => null], $payload['guarantor']);
        $this->assertEquals(['exists' => 'no', 'address' => null, 'title_deed' => null], $payload['guarantee_property']);
    }

    public function test_wizard_reopen_exit_non_sequential_navigation_and_summary_keep_saved_data(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $this->actingAs($actor)->put(route('contratos.borradores.wizard.save', [$draft, 'generales']), ['expected_version_id' => $draft->current_version_id, 'exit' => 1, 'payload' => ['metadata' => ['contract_reference' => 'SALIR'], 'leased_property' => ['alias' => 'Casa', 'address' => 'D']]])->assertRedirect(route('contratos.borradores.show', $draft));
        $versionAfterExit = $draft->fresh()->current_version_id;
        $this->actingAs($actor)->get(route('contratos.borradores.wizard.show', [$draft, 'vigencia']))->assertOk()->assertSee('Vigencia e importes');
        $this->save($actor, $draft, 'garantia', ['guarantee_property' => ['exists' => 'no']]);
        $this->assertSame('SALIR', $draft->fresh()->currentVersion->canonical_payload['metadata']['contract_reference']);
        $this->assertNotSame($versionAfterExit, $draft->fresh()->current_version_id);
        $this->save($actor, $draft, 'tercero', ['guarantor' => ['type' => 'fisica', 'person' => ['person_type' => 'fisica', 'full_name' => 'Fiador']]]);
        $this->save($actor, $draft, 'garantia', ['guarantee_property' => ['exists' => 'yes', 'address' => 'Dirección resumen', 'title_deed' => 'Título resumen']]);
        $this->save($actor, $draft, 'uso', ['leased_property' => ['_property_use_codes_submitted' => '1', 'property_use_codes' => ['commercial', 'other']]]);
        $this->save($actor, $draft, 'pago', ['payment' => ['method' => 'bank_transfer', 'bank_name' => 'Banco resumen', 'beneficiary' => 'Beneficiario', 'clabe' => '999']]);
        $this->save($actor, $draft, 'mantenimiento', ['maintenance' => ['exists' => 'yes', 'payer' => 'lessor']]);
        $this->save($actor, $draft, 'renovacion', ['renewal' => ['is_renewal' => 'yes', 'previous_contract_id' => 77]]);
        $this->actingAs($actor)->get(route('contratos.borradores.wizard.show', [$draft, 'resumen']))->assertOk()->assertSee('Comercial, Otro')->assertSee('Banco resumen')->assertSee('Mantenimiento')->assertSee('Contrato previo')->assertSee('Dirección resumen')->assertSee('Título resumen');
    }

    public function test_wizard_conflict_and_existing_master_links_are_preserved(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $cliente = Cliente::create(['nombre' => 'Cliente', 'rfc' => 'XAXX010101000', 'domicilio' => 'D']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'P', 'domicilio' => 'D']);
        $inquilino = Inquilino::create(['nombre' => 'I']);
        $draft->update(['cliente_id' => $cliente->pk_cliente, 'propiedad_id' => $propiedad->pk_propiedad, 'inquilino_id' => $inquilino->id]);
        $expected = $draft->current_version_id;
        $this->save($actor, $draft, 'generales', ['metadata' => ['contract_reference' => 'nuevo']]);
        $this->actingAs($actor)->from(route('contratos.borradores.wizard.show', [$draft, 'generales']))->put(route('contratos.borradores.wizard.save', [$draft, 'generales']), ['expected_version_id' => $expected, 'payload' => ['metadata' => ['contract_reference' => 'perdido']]])->assertRedirect();
        $draft->refresh();
        $this->assertSame($cliente->pk_cliente, $draft->cliente_id);
        $this->assertSame($propiedad->pk_propiedad, $draft->propiedad_id);
        $this->assertSame($inquilino->id, $draft->inquilino_id);
        $this->assertSame('nuevo', $draft->currentVersion->canonical_payload['metadata']['contract_reference']);
        $this->assertDatabaseCount('contract_draft_versions', 2);
        $this->assertDatabaseCount('contratos', 0);
    }

    private function save(User $actor, ContractDraft $draft, string $step, array $payload): void
    {
        $expected = $draft->fresh()->current_version_id;
        $this->actingAs($actor)->put(route('contratos.borradores.wizard.save', [$draft, $step]), ['expected_version_id' => $expected, 'payload' => $payload])->assertRedirect();
    }

    private function draft(User $actor): ContractDraft
    {
        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => app(ContractDraftPayload::class)->empty()])->assertRedirect();
        return ContractDraft::with('currentVersion')->latest('id')->firstOrFail();
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => User::ROLE_AGENT]);
    }
}
