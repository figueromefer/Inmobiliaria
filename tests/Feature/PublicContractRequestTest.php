<?php

namespace Tests\Feature;

use App\Models\ContractDraft;
use App\Models\ContractPublicRequest;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContractRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_starts_and_continues_a_token_protected_public_request(): void
    {
        $response = $this->post(route('contrato.solicitud.start'), ['website' => '']);
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        preg_match('#/contrato/solicitud/([^/]+)/([^/]+)/generales$#', (string) $location, $matches);
        $this->assertCount(3, $matches);
        $public = ContractPublicRequest::firstOrFail();
        $this->assertSame('public_form', $public->draft->source);
        $this->assertSame(64, strlen($public->token_hash));
        $this->get($location)->assertOk()->assertSee('Solicitud de contrato');
        $this->get(route('contrato.solicitud.step', [$matches[1], str_repeat('x', 64), 'generales']))->assertNotFound();
    }

    public function test_public_save_versions_and_incomplete_submission_is_rejected(): void
    {
        $this->post(route('contrato.solicitud.start'), ['website' => '']);
        $public = ContractPublicRequest::firstOrFail()->load('draft.currentVersion');
        $token = null;
        // El token no se persiste: se toma del redirect de un nuevo inicio para esta prueba.
        $response = $this->post(route('contrato.solicitud.start'), ['website' => '']);
        preg_match('#/contrato/solicitud/([^/]+)/([^/]+)/generales$#', (string) $response->headers->get('Location'), $matches);
        $public = ContractPublicRequest::where('public_reference', $matches[1])->firstOrFail()->load('draft.currentVersion');
        $this->put(route('contrato.solicitud.save', [$matches[1], $matches[2], 'generales']), [
            'expected_version_id' => $public->draft->current_version_id,
            'website' => '',
            'payload' => ['metadata' => ['contract_date' => '2026-09-17'], 'leased_property' => ['alias' => 'Sandbox', 'address' => 'Domicilio sandbox']],
        ])->assertRedirect();
        $this->assertSame(2, $public->draft->fresh()->versions()->count());
        $this->post(route('contrato.solicitud.submit', [$matches[1], $matches[2]]), ['website' => ''])->assertSessionHasErrors('submission');
        $this->assertSame(ContractDraft::STATUS_DRAFT, $public->draft->fresh()->status);
        $this->assertNull($public->fresh()->submitted_at);
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_expired_or_revoked_public_request_cannot_be_opened_or_saved(): void
    {
        [$reference, $token, $public] = $this->startPublicRequest();
        $public->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->get(route('contrato.solicitud.step', [$reference, $token, 'generales']))->assertNotFound();
        $this->put(route('contrato.solicitud.save', [$reference, $token, 'generales']), [
            'expected_version_id' => $public->draft->current_version_id,
            'website' => '',
            'payload' => [],
        ])->assertNotFound();

        [$reference, $token, $public] = $this->startPublicRequest();
        $public->forceFill(['revoked_at' => now()])->save();
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'generales']))->assertNotFound();
    }

    public function test_honeypot_and_administrative_identifiers_do_not_create_public_versions(): void
    {
        [$reference, $token, $public] = $this->startPublicRequest();
        $versionId = $public->draft->current_version_id;

        $this->put(route('contrato.solicitud.save', [$reference, $token, 'generales']), [
            'expected_version_id' => $versionId,
            'website' => 'bot',
            'payload' => ['metadata' => ['contract_date' => '2026-09-17']],
        ])->assertSessionHasErrors('website');

        $this->put(route('contrato.solicitud.save', [$reference, $token, 'generales']), [
            'expected_version_id' => $versionId,
            'website' => '',
            'payload' => ['metadata' => ['source' => 'laravel']],
        ])->assertSessionHasErrors('payload');

        $this->put(route('contrato.solicitud.save', [$reference, $token, 'generales']), [
            'expected_version_id' => $versionId,
            'website' => '',
            'payload' => ['leased_property' => ['master_property_id' => 123]],
        ])->assertSessionHasErrors('payload');

        $this->assertSame(1, $public->draft->fresh()->versions()->count());
    }

    public function test_stale_public_save_is_rejected_without_overwriting_the_latest_version(): void
    {
        [$reference, $token, $public] = $this->startPublicRequest();
        $expectedVersionId = $public->draft->current_version_id;
        app(ContractDraftVersioningService::class)->appendVersion($public->draft, app(ContractDraftPayload::class)->empty());

        $this->put(route('contrato.solicitud.save', [$reference, $token, 'generales']), [
            'expected_version_id' => $expectedVersionId,
            'website' => '',
            'payload' => ['metadata' => ['contract_date' => '2026-09-17']],
        ])->assertSessionHasErrors('expected_version_id');

        $draft = $public->draft->fresh();
        $this->assertSame(2, $draft->versions()->count());
        $this->assertNotSame($expectedVersionId, $draft->current_version_id);
    }

    public function test_complete_public_request_is_submitted_without_creating_a_contract_or_document(): void
    {
        [$reference, $token, $public] = $this->startPublicRequest();
        $this->put(route('contrato.solicitud.save', [$reference, $token, 'generales']), [
            'expected_version_id' => $public->draft->current_version_id,
            'website' => '',
            'payload' => $this->completePublicPayload(),
        ])->assertRedirect();
        $this->assertSame('public_form', $public->draft->fresh('currentVersion')->currentVersion->canonical_payload['metadata']['source']);

        $this->post(route('contrato.solicitud.submit', [$reference, $token]), ['website' => ''])
            ->assertRedirect(route('contrato.solicitud.recibida', $reference));

        $public->refresh();
        $this->assertNotNull($public->submitted_at);
        $this->assertSame(ContractDraft::STATUS_SUBMITTED, $public->draft->fresh()->status);
        $this->assertDatabaseCount('contratos', 0);
        $this->assertDatabaseCount('contract_document_versions', 0);
        $this->get(route('contrato.solicitud.recibida', $reference))->assertOk()->assertSee('Solicitud recibida');
        $this->get(route('contrato.solicitud.step', [$reference, $token, 'resumen']))->assertNotFound();
        $this->post(route('contrato.solicitud.submit', [$reference, $token]), ['website' => ''])->assertNotFound();
    }

    /** @return array{0:string,1:string,2:ContractPublicRequest} */
    private function startPublicRequest(): array
    {
        $response = $this->post(route('contrato.solicitud.start'), ['website' => '']);
        preg_match('#/contrato/solicitud/([^/]+)/([^/]+)/generales$#', (string) $response->headers->get('Location'), $matches);

        return [$matches[1], $matches[2], ContractPublicRequest::where('public_reference', $matches[1])->firstOrFail()->load('draft.currentVersion')];
    }

    /** @return array<string, mixed> */
    private function completePayload(): array
    {
        $payload = app(ContractDraftPayload::class)->empty();
        $payload['metadata']['contract_date'] = '2026-09-17';
        $payload['metadata']['source'] = 'public_form';
        foreach (['lessor' => 'Arrendador Prueba', 'lessee' => 'Arrendatario Prueba'] as $party => $name) {
            $payload[$party]['person'] = [
                'person_type' => 'fisica', 'full_name' => $name, 'legal_name' => null,
                'rfc' => 'XAXX010101000', 'nationality' => 'Mexicana', 'birth_place' => 'México',
                'birth_date' => '1990-02-28', 'marital_status' => 'Soltero', 'occupation' => 'Comerciante',
                'address' => 'Domicilio de prueba', 'identification_type' => 'INE', 'phone' => '5555555555',
                'email' => 'prueba@example.test', 'incorporation_deed' => null,
                'contact_email_entered' => null, 'contact_phone_entered' => null,
            ];
        }
        $payload['leased_property'] = ['alias' => 'Casa Prueba', 'address' => 'Domicilio de prueba', 'property_use_codes' => ['residential'], 'master_property_id' => null];
        $payload['term'] = ['start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'duration_label' => '12 meses', 'rent_due_rule' => ['raw_text' => '05 de cada mes', 'day_from' => null, 'day_to' => null, 'day_of_month' => null]];
        $payload['amounts']['total_rent'] = '120000';
        $payload['amounts']['monthly_rent'] = '10000';
        $payload['amounts']['security_deposit'] = '10000';

        return $payload;
    }

    /** @return array<string, mixed> */
    private function completePublicPayload(): array
    {
        $payload = $this->completePayload();
        unset(
            $payload['schema_version'], $payload['document_generation'], $payload['audit'],
            $payload['metadata']['contract_kind'], $payload['metadata']['source'],
            $payload['metadata']['external_id'], $payload['metadata']['google_form_edit_url'],
            $payload['metadata']['cliente_id'], $payload['metadata']['propiedad_id'], $payload['metadata']['inquilino_id'],
            $payload['leased_property']['master_property_id'],
        );

        return $payload;
    }
}
