<?php

namespace Tests\Feature;

use App\Models\ContractDraft;
use App\Models\ContractPublicRequest;
use App\Models\User;
use App\Services\ContractDocumentPayloadBuilder;
use App\Services\ContractDraftPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContractRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_a_public_pf_pf_without_guarantor_completes_end_to_end(): void
    {
        [$reference, $token, $public] = $this->start();
        $initialVersion = $public->draft->current_version_id;
        $this->capturePublic($reference, $token, $this->fixtureA());
        $draft = $public->draft->fresh('currentVersion');

        $this->assertGreaterThan($initialVersion, $draft->current_version_id);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'resumen']))
            ->assertOk()->assertSee('Casa A')->assertSee('120000')->assertSee('cash');
        $this->post(route('contrato.solicitud.submit', [$reference, $token]), ['website' => ''])
            ->assertRedirect(route('contrato.solicitud.recibida', $reference));

        $public->refresh();
        $this->assertSame(ContractDraft::STATUS_SUBMITTED, $public->draft->fresh()->status);
        $this->assertNotNull($public->submitted_at);
        $this->assertSame('public_form', $public->draft->source);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'generales']))->assertNotFound();
        $this->assertDatabaseCount('contract_document_versions', 0);
        $this->assertDatabaseCount('contratos', 0);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('propiedades', 0);
        $this->assertDatabaseCount('inquilinos', 0);
        $this->actingAs($this->agent())->get(route('contratos.borradores.index'))
            ->assertOk()->assertSee('public_form')->assertSee('submitted');
    }

    public function test_fixture_b_public_pm_pm_guarantor_guarantee_and_transfer_completes_end_to_end(): void
    {
        [$reference, $token, $public] = $this->start();
        $this->capturePublic($reference, $token, $this->fixtureB());
        $draft = $public->draft->fresh('currentVersion');
        $plan = app(ContractDocumentPayloadBuilder::class)->build($draft->currentVersion);

        $this->assertSame('ready', $plan['status']);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'resumen']))
            ->assertOk()->assertSee('Garantía B')->assertSee('Banco B')->assertSee('Beneficiario B')->assertSee('lessee');
        $this->post(route('contrato.solicitud.submit', [$reference, $token]), ['website' => ''])->assertRedirect();
        $this->assertSame(ContractDraft::STATUS_SUBMITTED, $public->draft->fresh()->status);
        $this->assertDatabaseCount('contract_document_versions', 0);
        $this->assertDatabaseCount('contratos', 0);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('propiedades', 0);
        $this->assertDatabaseCount('inquilinos', 0);
    }

    public function test_fixture_a_and_b_are_canonically_equivalent_to_internal_wizard(): void
    {
        foreach ([$this->fixtureA(), $this->fixtureB()] as $fixture) {
            [$reference, $token, $public] = $this->start();
            $this->capturePublic($reference, $token, $fixture);
            $internal = $this->startInternal();
            $this->captureInternal($internal, $fixture);

            $this->assertSame($this->projection($internal->fresh('currentVersion')->currentVersion->canonical_payload), $this->projection($public->draft->fresh('currentVersion')->currentVersion->canonical_payload));
        }
    }

    public function test_public_party_guarantor_guarantee_payment_maintenance_and_renewal_transitions_are_cleaned(): void
    {
        [$reference, $token, $public] = $this->start();
        $this->savePublic($reference, $token, 'arrendador', ['lessor' => $this->physicalParty('PF')]);
        $this->savePublic($reference, $token, 'arrendador', ['lessor' => $this->moralParty('PM')]);
        $this->savePublic($reference, $token, 'arrendador', ['lessor' => $this->physicalParty('PF nuevo')]);
        $this->savePublic($reference, $token, 'tercero', ['guarantor' => ['type' => 'moral', ...$this->moralParty('Fiador PM')]]);
        $this->savePublic($reference, $token, 'tercero', ['guarantor' => ['type' => 'fisica', ...$this->physicalParty('Fiador PF')]]);
        $this->savePublic($reference, $token, 'garantia', ['guarantee_property' => ['exists' => 'yes', 'address' => 'Garantía', 'title_deed' => 'Título']]);
        $this->savePublic($reference, $token, 'tercero', ['guarantor' => ['type' => 'none']]);
        $this->savePublic($reference, $token, 'pago', ['payment' => ['method' => 'bank_transfer', 'bank_name' => 'Banco', 'beneficiary' => 'Bene', 'clabe' => '123']]);
        $this->savePublic($reference, $token, 'pago', ['payment' => ['method' => 'cash']]);
        $this->savePublic($reference, $token, 'pago', ['payment' => ['method' => 'bank_transfer']]);
        $this->savePublic($reference, $token, 'pago', ['payment' => ['method' => 'unspecified']]);
        $this->savePublic($reference, $token, 'mantenimiento', ['maintenance' => ['exists' => 'yes', 'payer' => 'lessor']]);
        $this->savePublic($reference, $token, 'mantenimiento', ['maintenance' => ['exists' => 'yes', 'payer' => 'lessee']]);
        $this->savePublic($reference, $token, 'mantenimiento', ['maintenance' => ['exists' => 'no']]);
        $this->savePublic($reference, $token, 'renovacion', ['renewal' => ['is_renewal' => 'yes', 'previous_contract_id' => 88]]);
        $this->savePublic($reference, $token, 'renovacion', ['renewal' => ['is_renewal' => 'no']]);

        $payload = $public->draft->fresh('currentVersion')->currentVersion->canonical_payload;
        $this->assertSame('fisica', $payload['lessor']['person']['person_type']);
        $this->assertNull($payload['lessor']['person']['legal_name']);
        $this->assertEquals(['type' => 'none', 'person' => null, 'representative' => null], $payload['guarantor']);
        $this->assertEquals(['exists' => 'no', 'address' => null, 'title_deed' => null], $payload['guarantee_property']);
        $this->assertEquals(['method' => 'unspecified', 'bank_name' => null, 'beneficiary' => null, 'clabe' => null], $payload['payment']);
        $this->assertEquals(['exists' => 'no', 'payer' => null], $payload['maintenance']);
        $this->assertSame('no', $payload['renewal']['is_renewal']);
        $this->assertNull($payload['renewal']['previous_contract_id']);
    }

    public function test_public_guarantee_yes_to_no_and_payment_transfer_reentry_do_not_preserve_old_data(): void
    {
        [$reference, $token, $public] = $this->start();
        $this->savePublic($reference, $token, 'tercero', ['guarantor' => ['type' => 'fisica', ...$this->physicalParty('Fiador')]]);
        $this->savePublic($reference, $token, 'garantia', ['guarantee_property' => ['exists' => 'yes', 'address' => 'Dirección', 'title_deed' => 'Escritura']]);
        $this->savePublic($reference, $token, 'garantia', ['guarantee_property' => ['exists' => 'no']]);
        $this->savePublic($reference, $token, 'pago', ['payment' => ['method' => 'bank_transfer', 'bank_name' => 'Banco viejo', 'beneficiary' => 'Bene viejo', 'clabe' => '999']]);
        $this->savePublic($reference, $token, 'pago', ['payment' => ['method' => 'cash']]);
        $this->savePublic($reference, $token, 'pago', ['payment' => ['method' => 'bank_transfer']]);
        $payload = $public->draft->fresh('currentVersion')->currentVersion->canonical_payload;

        $this->assertEquals(['exists' => 'no', 'address' => null, 'title_deed' => null], $payload['guarantee_property']);
        $this->assertSame('bank_transfer', $payload['payment']['method']);
        $this->assertNull($payload['payment']['bank_name']);
        $this->assertNull($payload['payment']['beneficiary']);
        $this->assertNull($payload['payment']['clabe']);
    }

    public function test_public_guarantor_transitions_directly_from_moral_to_none_and_cleans_guarantee(): void
    {
        [$reference, $token, $public] = $this->start();
        $this->savePublic($reference, $token, 'tercero', ['guarantor' => ['type' => 'moral', ...$this->moralParty('Fiador PM directo')]]);
        $this->savePublic($reference, $token, 'garantia', ['guarantee_property' => ['exists' => 'yes', 'address' => 'Garantía directa', 'title_deed' => 'Escritura directa']]);
        $this->savePublic($reference, $token, 'tercero', ['guarantor' => ['type' => 'none']]);

        $payload = $public->draft->fresh('currentVersion')->currentVersion->canonical_payload;
        $this->assertSame('none', $payload['guarantor']['type']);
        $this->assertNull($payload['guarantor']['person']);
        $this->assertNull($payload['guarantor']['representative']);
        $this->assertSame('no', $payload['guarantee_property']['exists']);
        $this->assertNull($payload['guarantee_property']['address']);
        $this->assertNull($payload['guarantee_property']['title_deed']);
    }

    public function test_public_routes_enforce_their_real_throttle_limit(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.240']);

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->post(route('contrato.solicitud.start'), ['website' => 'bot'])->assertRedirect();
        }

        $this->post(route('contrato.solicitud.start'), ['website' => 'bot'])->assertStatus(429);
    }

    public function test_public_mutations_require_csrf_when_the_real_middleware_is_enabled(): void
    {
        $environment = $this->app['env'];
        $this->withMiddleware();
        $this->app['env'] = 'local';

        try {
            $this->post(route('contrato.solicitud.start'), ['website' => ''])->assertStatus(419);
            $this->post(route('contrato.solicitud.start'), ['website' => '', '_token' => csrf_token()])->assertRedirect();
        } finally {
            $this->app['env'] = $environment;
        }
    }

    public function test_multiuse_persists_reopens_and_submits_as_requires_review(): void
    {
        [$reference, $token, $public] = $this->start();
        $fixture = $this->fixtureA();
        $fixture['uso']['leased_property']['property_use_codes'] = ['residential', 'commercial'];
        $this->capturePublic($reference, $token, $fixture);
        $versions = $public->draft->fresh()->versions()->count();
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'uso']))->assertOk()->assertSee('value="residential" checked', false)->assertSee('value="commercial" checked', false);
        $this->assertSame($versions, $public->draft->fresh()->versions()->count());
        $this->assertSame('requires_review', app(ContractDocumentPayloadBuilder::class)->build($public->draft->fresh('currentVersion')->currentVersion)['status']);
        $this->post(route('contrato.solicitud.submit', [$reference, $token]), ['website' => ''])->assertRedirect();
        $this->assertSame(ContractDraft::STATUS_SUBMITTED, $public->draft->fresh()->status);
    }

    public function test_all_invalid_tokens_and_submitted_token_deny_editing(): void
    {
        [$reference, $token, $public] = $this->start();
        [, $otherToken] = $this->start();
        foreach ([str_repeat('x', 64), $otherToken] as $invalid) $this->get(route('contrato.solicitud.step', [$reference, $invalid, 'generales']))->assertNotFound();
        $public->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'generales']))->assertNotFound();

        [$reference, $token, $public] = $this->start();
        $public->forceFill(['revoked_at' => now()])->save();
        $this->put(route('contrato.solicitud.save', [$reference, $token, 'generales']), ['expected_version_id' => 1, 'website' => '', 'payload' => []])->assertNotFound();

        [$reference, $token, $public] = $this->start();
        $this->capturePublic($reference, $token, $this->fixtureA());
        $this->post(route('contrato.solicitud.submit', [$reference, $token]), ['website' => ''])->assertRedirect();
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'generales']))->assertNotFound();
    }

    public function test_concurrent_public_save_keeps_n_plus_one_and_does_not_create_n_plus_two(): void
    {
        [$reference, $token, $public] = $this->start();
        $versionN = $public->draft->current_version_id;
        $this->savePublic($reference, $token, 'generales', ['leased_property' => ['alias' => 'B gana']]);
        $draft = $public->draft->fresh('currentVersion');
        $this->put(route('contrato.solicitud.save', [$reference, $token, 'generales']), ['expected_version_id' => $versionN, 'website' => '', 'payload' => ['leased_property' => ['alias' => 'A pierde']]])->assertSessionHasErrors('expected_version_id');
        $this->assertSame(2, $draft->versions()->count());
        $this->assertSame('B gana', $draft->fresh('currentVersion')->currentVersion->canonical_payload['leased_property']['alias']);
    }

    public function test_reopen_get_is_read_only_and_renders_persisted_branch_values_and_summary(): void
    {
        [$reference, $token, $public] = $this->start();
        $this->capturePublic($reference, $token, $this->fixtureB());
        $versionCount = $public->draft->fresh()->versions()->count();
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'tercero']))->assertOk()->assertSee('Fiador B')->assertSee('option value="moral" selected', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'garantia']))->assertOk()->assertSee('value="Garantía B"', false)->assertSee('value="Escritura B"', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'pago']))->assertOk()->assertSee('value="Banco B"', false)->assertSee('value="bank_transfer" selected', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'mantenimiento']))->assertOk()->assertSee('value="yes" selected', false)->assertSee('value="lessee" selected', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'renovacion']))->assertOk()->assertSee('value="yes" selected', false)->assertSee('value="77"', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'resumen']))->assertOk()->assertSee('Garantía B')->assertSee('120000')->assertSee('Banco B')->assertSee('lessee')->assertSee('77');
        $this->assertSame($versionCount, $public->draft->fresh()->versions()->count());
    }

    public function test_public_wizard_renders_persisted_party_and_conditional_branch_controls(): void
    {
        [$reference, $token] = $this->start();
        $this->capturePublic($reference, $token, $this->fixtureA());

        $this->get(route('contrato.solicitud.step', [$reference, $token, 'arrendador']))
            ->assertOk()
            ->assertSee('data-party-container="lessor"', false)
            ->assertSee('data-party-type="lessor"', false)
            ->assertSee('data-type="fisica"', false)
            ->assertSee('data-type="moral"', false)
            ->assertSee('option value="fisica" selected', false)
            ->assertSee('Arrendador A');
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'tercero']))
            ->assertOk()
            ->assertSee('data-guarantor-type', false)
            ->assertSee('option value="none" selected', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'garantia']))
            ->assertOk()->assertSee('data-conditional="guarantee-details"', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'pago']))
            ->assertOk()->assertSee('data-conditional="bank-details"', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'mantenimiento']))
            ->assertOk()->assertSee('data-conditional="maintenance-payer"', false);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'renovacion']))
            ->assertOk()->assertSee('data-conditional="previous-contract"', false);

        [$reference, $token] = $this->start();
        $this->capturePublic($reference, $token, $this->fixtureB());
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'arrendador']))
            ->assertOk()->assertSee('option value="moral" selected', false)->assertSee('Representante Arrendador B');
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'tercero']))
            ->assertOk()->assertSee('option value="moral" selected', false)->assertSee('Representante Fiador B');
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'pago']))
            ->assertOk()->assertSee('value="Banco B"', false)->assertSee('control.disabled=!visible', false);
    }

    public function test_incomplete_submit_keeps_request_editable_and_unsubmitted(): void
    {
        [$reference, $token, $public] = $this->start();
        $this->post(route('contrato.solicitud.submit', [$reference, $token]), ['website' => ''])->assertSessionHasErrors('submission');
        $public->refresh();
        $this->assertNull($public->submitted_at);
        $this->assertSame(ContractDraft::STATUS_DRAFT, $public->draft->fresh()->status);
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'generales']))->assertOk();
    }

    /** @return array{0:string,1:string,2:ContractPublicRequest} */
    private function start(): array
    {
        $response = $this->post(route('contrato.solicitud.start'), ['website' => '']);
        preg_match('#/contrato/solicitud/([^/]+)/([^/]+)/generales$#', (string) $response->headers->get('Location'), $matches);
        return [$matches[1], $matches[2], ContractPublicRequest::where('public_reference', $matches[1])->firstOrFail()->load('draft.currentVersion')];
    }

    private function savePublic(string $reference, string $token, string $step, array $payload): void
    {
        $public = ContractPublicRequest::where('public_reference', $reference)->firstOrFail();
        $this->put(route('contrato.solicitud.save', [$reference, $token, $step]), ['expected_version_id' => $public->draft->current_version_id, 'website' => '', 'payload' => $payload])->assertRedirect();
    }

    private function capturePublic(string $reference, string $token, array $fixture): void
    {
        foreach ($fixture as $step => $payload) $this->savePublic($reference, $token, $step, $payload);
    }

    private function startInternal(): ContractDraft
    {
        $actor = $this->agent();
        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => app(ContractDraftPayload::class)->empty()])->assertRedirect();
        return ContractDraft::latest('id')->firstOrFail();
    }

    private function captureInternal(ContractDraft $draft, array $fixture): void
    {
        $actor = $draft->createdBy;
        foreach ($fixture as $step => $payload) {
            $this->actingAs($actor)->put(route('contratos.borradores.wizard.save', [$draft, $step]), ['expected_version_id' => $draft->fresh()->current_version_id, 'payload' => $payload])->assertRedirect();
        }
    }

    /** @return array<string, mixed> */
    private function fixtureA(): array
    {
        return $this->fixture('A', $this->physicalParty('Arrendador A'), $this->physicalParty('Arrendatario A'), ['type' => 'none'], ['exists' => 'no'], ['method' => 'cash'], ['exists' => 'no'], ['is_renewal' => 'no']);
    }

    /** @return array<string, mixed> */
    private function fixtureB(): array
    {
        return $this->fixture('B', $this->moralParty('Arrendador B'), $this->moralParty('Arrendatario B'), ['type' => 'moral', ...$this->moralParty('Fiador B')], ['exists' => 'yes', 'address' => 'Garantía B', 'title_deed' => 'Escritura B'], ['method' => 'bank_transfer', 'bank_name' => 'Banco B', 'beneficiary' => 'Beneficiario B', 'clabe' => '012345678901234567'], ['exists' => 'yes', 'payer' => 'lessee'], ['is_renewal' => 'yes', 'previous_contract_id' => 77]);
    }

    /** @return array<string, mixed> */
    private function fixture(string $suffix, array $lessor, array $lessee, array $guarantor, array $guarantee, array $payment, array $maintenance, array $renewal): array
    {
        return [
            'generales' => ['metadata' => ['contract_date' => '2026-09-17'], 'leased_property' => ['alias' => "Casa {$suffix}", 'address' => "Domicilio {$suffix}"]],
            'arrendador' => ['lessor' => $lessor], 'arrendatario' => ['lessee' => $lessee], 'tercero' => ['guarantor' => $guarantor], 'garantia' => ['guarantee_property' => $guarantee],
            'vigencia' => ['term' => ['start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'duration_label' => '12 meses', 'rent_due_rule' => ['raw_text' => '05 de cada mes']], 'amounts' => ['total_rent' => '120000', 'monthly_rent' => '10000', 'security_deposit' => '10000', 'rental_commission' => '12000', 'monthly_commission_value' => '10', 'monthly_commission_unit' => 'percent']],
            'uso' => ['leased_property' => ['_property_use_codes_submitted' => '1', 'property_use_codes' => ['residential']]], 'pago' => ['payment' => $payment], 'mantenimiento' => ['maintenance' => $maintenance], 'renovacion' => ['renewal' => $renewal],
        ];
    }

    /** @return array<string, mixed> */
    private function physicalParty(string $name): array
    {
        return ['person' => ['person_type' => 'fisica', 'full_name' => $name, 'rfc' => 'XAXX010101000', 'nationality' => 'Mexicana', 'birth_place' => 'México', 'birth_date' => '1990-02-28', 'marital_status' => 'Soltero', 'occupation' => 'Comerciante', 'address' => 'Domicilio', 'identification_type' => 'INE', 'phone' => '5555555555', 'email' => 'prueba@example.test']];
    }

    /** @return array<string, mixed> */
    private function moralParty(string $name): array
    {
        return ['person' => ['person_type' => 'moral', 'legal_name' => $name, 'rfc' => 'XAXX010101000', 'incorporation_deed' => 'Acta constitutiva', 'address' => 'Domicilio', 'phone' => '5555555555', 'email' => 'prueba@example.test'], 'representative' => ['full_name' => "Representante {$name}", 'nationality' => 'Mexicana', 'birth_place' => 'México', 'birth_date' => '1990-02-28', 'occupation' => 'Apoderado', 'address' => 'Domicilio', 'identification_type' => 'INE', 'authority_deed' => 'Poder']];
    }

    /** @return array<string, mixed> */
    private function projection(array $payload): array
    {
        return array_intersect_key($payload, array_flip(['lessor', 'lessee', 'guarantor', 'guarantee_property', 'leased_property', 'term', 'amounts', 'payment', 'maintenance', 'renewal']));
    }

    private function agent(): User { return User::factory()->create(['role' => User::ROLE_AGENT]); }
}
