<?php

namespace Tests\Feature;

use App\Contracts\GoogleContractDocumentClient;
use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\Contrato;
use App\Models\User;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_copies_the_frozen_snapshot_and_reuses_one_open_draft(): void
    {
        [$original, $agent] = $this->publishedContract();
        $snapshot = $original->draftVersion->canonical_payload;
        $original->cliente->update(['nombre' => 'Maestro cambiado']);

        $this->actingAs($agent)->post(route('contratos.renew', $original))->assertRedirect();
        $this->actingAs($agent)->post(route('contratos.renew', $original))->assertRedirect();
        $draft = ContractDraft::query()->where('renewal_of_contract_id', $original->id)->sole()->load('currentVersion');
        $this->assertSame('renewal', $draft->purpose);
        $this->assertNull($draft->editing_contract_id);
        $this->assertSame('yes', data_get($draft->currentVersion->canonical_payload, 'renewal.is_renewal'));
        $this->assertSame($original->id, data_get($draft->currentVersion->canonical_payload, 'renewal.previous_contract_id'));
        $this->assertSame(data_get($snapshot, 'lessor.person.full_name'), data_get($draft->currentVersion->canonical_payload, 'lessor.person.full_name'));
        $this->actingAs($agent)->get(route('contratos.privados.wizard.show', [$draft, 'vigencia']))
            ->assertSee('Renovando contrato #'.$original->id)->assertSee('contrato anterior permanecerá intacto');
    }

    public function test_renewal_generates_a_new_contract_and_new_drive_folder_without_mutating_original(): void
    {
        [$original, $agent, $google] = $this->publishedContract();
        $originalValues = ['fecha_inicio' => $original->fecha_inicio->toDateString(), 'fecha_fin' => $original->fecha_fin->toDateString(), 'monto_mensual' => (float) $original->monto_mensual, 'contract_draft_version_id' => $original->contract_draft_version_id, 'contract_document_version_id' => $original->contract_document_version_id, 'urldoc' => $original->urldoc];
        $originalSnapshot = $original->draftVersion->canonical_payload;
        $this->actingAs($agent)->post(route('contratos.renew', $original));
        $draft = ContractDraft::query()->where('renewal_of_contract_id', $original->id)->sole()->load('currentVersion');
        $this->actingAs($agent)->put(route('contratos.privados.wizard.save', [$draft, 'vigencia']), [
            'expected_version_id' => $draft->current_version_id,
            'payload' => ['term' => ['start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'duration_label' => '12 meses'], 'amounts' => ['monthly_rent' => '12000.00']],
        ])->assertRedirect();
        $draft->refresh()->load('currentVersion');

        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $draft->current_version_id])
            ->assertRedirect(route('contratos.index'));
        $renewal = $draft->fresh()->contrato;
        $this->assertNotSame($original->id, $renewal->id);
        $this->assertSame($original->id, $renewal->previous_contract_id);
        $this->assertSame('2027-01-01', $renewal->fecha_inicio->toDateString());
        $this->assertSame(12000.0, (float) $renewal->monto_mensual);
        $freshOriginal = $original->fresh();
        $this->assertSame($originalValues, ['fecha_inicio' => $freshOriginal->fecha_inicio->toDateString(), 'fecha_fin' => $freshOriginal->fecha_fin->toDateString(), 'monto_mensual' => (float) $freshOriginal->monto_mensual, 'contract_draft_version_id' => $freshOriginal->contract_draft_version_id, 'contract_document_version_id' => $freshOriginal->contract_document_version_id, 'urldoc' => $freshOriginal->urldoc]);
        $this->assertSame($originalSnapshot, $original->fresh()->draftVersion->canonical_payload);
        $this->assertSame(2, $google->folderCalls);
        $this->assertSame(2, $google->copyCalls);
        $this->assertNotSame($original->documentVersion->drive_folder_id, $renewal->documentVersion->drive_folder_id);

        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $draft->current_version_id]);
        $this->assertDatabaseCount('contratos', 2);
        $this->assertSame(2, $google->folderCalls);
    }

    public function test_legacy_contract_uses_auditable_fallback_and_justice_alternative_or_viewer_cannot_renew(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $cliente = Cliente::create(['nombre' => 'Histórico', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa', 'domicilio' => 'Calle']);
        $legacy = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'privado', 'monto_mensual' => '8000']);
        $this->actingAs($agent)->post(route('contratos.renew', $legacy))->assertRedirect();
        $draft = ContractDraft::query()->where('renewal_of_contract_id', $legacy->id)->sole()->load('currentVersion');
        $this->assertSame('renewal_started_from_legacy_fallback', $draft->currentVersion->action);
        $this->assertSame('legacy_fallback', data_get($draft->currentVersion->raw_legacy_payload, 'renewal_origin'));
        $this->assertNull(data_get($draft->currentVersion->canonical_payload, 'lessee.person.rfc'));

        $ja = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'origen' => 'justicia_alternativa']);
        $this->actingAs($agent)->post(route('contratos.renew', $ja))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))->post(route('contratos.renew', $legacy))->assertForbidden();
    }

    public function test_vigency_remains_date_based_for_consecutive_and_overlapping_renewals(): void
    {
        [$original] = $this->publishedContract();
        $original->update(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']);
        $next = $original->replicate();
        $next->previous_contract_id = $original->id;
        $next->contract_draft_version_id = null;
        $next->contract_document_version_id = null;
        $next->fecha_inicio = '2027-01-01';
        $next->fecha_fin = '2027-12-31';
        $next->save();
        $this->assertTrue(Contrato::activosEnMes(Carbon::parse('2026-06-01'))->pluck('id')->contains($original->id));
        $this->assertFalse(Contrato::activosEnMes(Carbon::parse('2026-06-01'))->pluck('id')->contains($next->id));
        $this->assertTrue(Contrato::activosEnMes(Carbon::parse('2027-06-01'))->pluck('id')->contains($next->id));
        $overlap = $next->replicate();
        $overlap->contract_draft_version_id = null;
        $overlap->contract_document_version_id = null;
        $overlap->fecha_inicio = '2026-12-01';
        $overlap->fecha_fin = '2027-11-30';
        $overlap->save();
        $active = Contrato::activosEnMes(Carbon::parse('2026-12-01'))->pluck('id');
        $this->assertTrue($active->contains($original->id));
        $this->assertTrue($active->contains($overlap->id));
    }

    /** @return array{0: Contrato, 1: User, 2: RenewalGoogleClient} */
    private function publishedContract(): array
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $cliente = Cliente::create(['nombre' => 'Propietario congelado', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa', 'domicilio' => 'Calle']);
        $inquilino = Inquilino::create(['nombre' => 'Inquilino']);
        $payload = app(ContractDraftPayload::class)->empty();
        $party = ['person' => ['person_type' => 'fisica', 'full_name' => 'Persona congelada', 'legal_name' => null, 'rfc' => 'XAXX010101000', 'nationality' => 'Mexicana', 'birth_place' => 'México', 'birth_date' => '1990-01-01', 'marital_status' => 'Soltero', 'occupation' => 'Comerciante', 'address' => 'Domicilio', 'identification_type' => 'INE', 'phone' => '3311111111', 'email' => 'a@example.test', 'incorporation_deed' => null, 'contact_email_entered' => null, 'contact_phone_entered' => null], 'representative' => null];
        $payload['metadata']['contract_date'] = '2026-01-01'; $payload['lessor'] = $party; $payload['lessee'] = $party;
        $payload['leased_property'] = ['alias' => 'Casa', 'address' => 'Calle', 'property_use_codes' => ['residential'], 'master_property_id' => null];
        $payload['term'] = ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'duration_label' => '12 meses', 'rent_due_rule' => ['raw_text' => '5', 'day_from' => null, 'day_to' => null, 'day_of_month' => 5]];
        $payload['amounts'] = ['total_rent' => '120000.00', 'monthly_rent' => '10000.00', 'security_deposit' => '10000.00', 'rental_commission' => '10000.00', 'monthly_commission_value' => '10.00', 'monthly_commission_unit' => 'percent'];
        $draft = app(ContractDraftVersioningService::class)->createDraft($payload, ['source' => 'internal', 'created_by' => $agent->id, 'cliente_id' => $cliente->pk_cliente, 'propiedad_id' => $propiedad->pk_propiedad, 'inquilino_id' => $inquilino->id]);
        $google = new RenewalGoogleClient; $this->app->instance(GoogleContractDocumentClient::class, $google);
        config(['services.google_contracts.templates.lease_without_guarantor' => 'template', 'services.google_contracts.destination_folder_id' => 'parent']);
        $this->actingAs($agent)->post(route('contratos.privados.finalize', $draft), ['expected_version_id' => $draft->current_version_id]);

        return [$draft->fresh()->contrato, $agent, $google];
    }
}

class RenewalGoogleClient implements GoogleContractDocumentClient
{
    public int $folderCalls = 0; public int $copyCalls = 0;
    public function createFolder(string $parentFolderId, string $name): array { return ['id' => 'folder-'.++$this->folderCalls]; }
    public function copyTemplate(string $templateId, string $name, string $folderId): array { return ['id' => 'file-'.++$this->copyCalls, 'url' => 'https://docs.google.test/file-'.$this->copyCalls]; }
    public function applyOperations(string $documentId, array $operations): void {}
    public function remainingMarkers(string $documentId, array $markers): array { return []; }
}
