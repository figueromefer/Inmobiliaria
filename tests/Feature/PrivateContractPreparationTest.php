<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\User;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrivateContractPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_private_contract_creates_one_internal_draft_and_initial_contract_data_version(): void
    {
        $agent = $this->agent();

        $first = $this->actingAs($agent)->get(route('contratos.privados.create'));
        $draft = ContractDraft::query()->sole();
        $second = $this->actingAs($agent)->get(route('contratos.privados.create'));

        $first->assertRedirect(route('contratos.privados.preparacion.show', $draft));
        $second->assertRedirect(route('contratos.privados.preparacion.show', $draft));
        $this->assertDatabaseCount('contract_drafts', 1);
        $this->assertSame('internal', $draft->source);
        $this->assertSame(1, $draft->currentVersion->draft_version);
        $this->assertSame(ContractDraftPayload::SCHEMA_VERSION, $draft->currentVersion->schema_version);
    }

    public function test_reconciling_lessor_copies_available_client_data_without_inventing_missing_fields(): void
    {
        [$draft, $agent] = $this->draft();
        $cliente = Cliente::create([
            'nombre' => 'Arrendadora existente',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio de cliente',
            'celular' => '3311111111',
            'correo' => 'cliente@example.test',
        ]);

        $this->actingAs($agent)->post(route('contratos.privados.preparacion.conciliacion', [$draft, 'cliente']), [
            'expected_version_id' => $draft->current_version_id,
            'entity_id' => $cliente->pk_cliente,
        ])->assertRedirect();

        $draft->refresh()->load('currentVersion');
        $payload = $draft->currentVersion->canonical_payload;
        $this->assertSame($cliente->pk_cliente, $draft->cliente_id);
        $this->assertSame('Arrendadora existente', data_get($payload, 'lessor.person.full_name'));
        $this->assertSame($cliente->rfc, data_get($payload, 'lessor.person.rfc'));
        $this->assertSame($cliente->domicilio, data_get($payload, 'lessor.person.address'));
        $this->assertSame($cliente->celular, data_get($payload, 'lessor.person.phone'));
        $this->assertSame($cliente->correo, data_get($payload, 'lessor.person.email'));
        $this->assertNull(data_get($payload, 'lessor.person.birth_date'));
        $this->assertNull(data_get($payload, 'lessor.representative'));
    }

    public function test_general_preparation_data_creates_a_new_version_and_next_keeps_the_existing_wizard(): void
    {
        [$draft, $agent] = $this->draft();

        $this->actingAs($agent)->put(route('contratos.privados.preparacion.generales', $draft), [
            'expected_version_id' => $draft->current_version_id,
            'contract_date' => '2026-10-01',
            'contract_reference' => 'REF-INTERNA-1',
            'property_alias' => 'Alias preparado',
            'property_address' => 'Domicilio preparado',
        ])->assertRedirect();

        $draft->refresh()->load('currentVersion');
        $this->assertSame(2, $draft->currentVersion->draft_version);
        $this->assertSame('2026-10-01', data_get($draft->currentVersion->canonical_payload, 'metadata.contract_date'));
        $this->assertSame('REF-INTERNA-1', data_get($draft->currentVersion->canonical_payload, 'metadata.contract_reference'));

        $this->actingAs($agent)->post(route('contratos.privados.preparacion.next', $draft))
            ->assertRedirect(route('contratos.privados.wizard.show', [$draft, 'generales']));
    }

    public function test_reconciliation_only_fills_blank_snapshot_fields(): void
    {
        [$draft, $agent] = $this->draft();
        $payload = $draft->currentVersion->canonical_payload;
        $payload['lessor']['person']['full_name'] = 'Nombre capturado manualmente';
        $payload['lessor']['person']['email'] = 'manual@example.test';
        app(ContractDraftVersioningService::class)->appendVersion($draft, $payload, null, ContractDraftPayload::SCHEMA_VERSION, 'manual', $agent->id);
        $cliente = Cliente::create(['nombre' => 'Cliente maestro', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio', 'correo' => 'master@example.test']);

        $this->actingAs($agent)->post(route('contratos.privados.preparacion.conciliacion', [$draft, 'cliente']), [
            'expected_version_id' => $draft->fresh()->current_version_id,
            'entity_id' => $cliente->pk_cliente,
        ])->assertRedirect();

        $payload = $draft->fresh()->currentVersion->canonical_payload;
        $this->assertSame('Nombre capturado manualmente', data_get($payload, 'lessor.person.full_name'));
        $this->assertSame('manual@example.test', data_get($payload, 'lessor.person.email'));
        $this->assertSame($cliente->rfc, data_get($payload, 'lessor.person.rfc'));
    }

    public function test_reconciling_property_copies_property_data_without_replacing_a_different_lessor(): void
    {
        [$draft, $agent] = $this->draft();
        $selectedCliente = $this->cliente('Cliente seleccionado');
        $propertyOwner = $this->cliente('Dueño de propiedad');
        $propiedad = Propiedad::create(['fk_cliente' => $propertyOwner->pk_cliente, 'alias' => 'Casa Norte', 'domicilio' => 'Calle Norte 10']);

        $this->reconcile($agent, $draft, 'cliente', $selectedCliente->pk_cliente);
        $this->reconcile($agent, $draft->fresh(), 'propiedad', $propiedad->pk_propiedad);

        $draft->refresh()->load('currentVersion');
        $payload = $draft->currentVersion->canonical_payload;
        $this->assertSame($selectedCliente->pk_cliente, $draft->cliente_id);
        $this->assertSame($propiedad->pk_propiedad, $draft->propiedad_id);
        $this->assertSame('Casa Norte', data_get($payload, 'leased_property.alias'));
        $this->assertSame('Calle Norte 10', data_get($payload, 'leased_property.address'));
        $this->actingAs($agent)->get(route('contratos.privados.preparacion.show', $draft))
            ->assertSee('El arrendador seleccionado se conserva y no fue reemplazado.');
    }

    public function test_reconciling_tenant_copies_available_data(): void
    {
        [$draft, $agent] = $this->draft();
        $inquilino = Inquilino::create(['nombre' => 'Inquilino existente', 'nacionalidad' => 'Mexicana', 'domicilio' => 'Domicilio inquilino', 'telefono' => '3322222222', 'correo' => 'inquilino@example.test']);

        $this->reconcile($agent, $draft, 'inquilino', $inquilino->id);

        $draft->refresh()->load('currentVersion');
        $payload = $draft->currentVersion->canonical_payload;
        $this->assertSame($inquilino->id, $draft->inquilino_id);
        $this->assertSame($inquilino->nombre, data_get($payload, 'lessee.person.full_name'));
        $this->assertSame($inquilino->nacionalidad, data_get($payload, 'lessee.person.nationality'));
        $this->assertSame($inquilino->domicilio, data_get($payload, 'lessee.person.address'));
        $this->assertSame($inquilino->telefono, data_get($payload, 'lessee.person.phone'));
        $this->assertSame($inquilino->correo, data_get($payload, 'lessee.person.email'));
        $this->assertNull(data_get($payload, 'lessee.person.rfc'));
    }

    public function test_preparation_supports_no_guarantor_or_manual_capture_without_creating_a_master(): void
    {
        [$draft, $agent] = $this->draft();

        $this->actingAs($agent)->put(route('contratos.privados.preparacion.fiador', $draft), [
            'expected_version_id' => $draft->current_version_id,
            'guarantor_mode' => 'manual',
        ])->assertRedirect();
        $this->assertSame('fisica', data_get($draft->fresh()->currentVersion->canonical_payload, 'guarantor.type'));

        $this->actingAs($agent)->put(route('contratos.privados.preparacion.fiador', $draft), [
            'expected_version_id' => $draft->fresh()->current_version_id,
            'guarantor_mode' => 'none',
        ])->assertRedirect();
        $this->assertSame('none', data_get($draft->fresh()->currentVersion->canonical_payload, 'guarantor.type'));
        $this->assertFalse(Schema::hasTable('guarantors'));
    }

    public function test_stale_preparation_update_is_rejected_and_published_draft_cannot_change(): void
    {
        [$draft, $agent] = $this->draft();
        $cliente = $this->cliente('Cliente stale');
        $stale = $draft->current_version_id;
        app(ContractDraftVersioningService::class)->appendVersion($draft, $draft->currentVersion->canonical_payload, null, ContractDraftPayload::SCHEMA_VERSION, 'other', $agent->id);

        $this->actingAs($agent)->post(route('contratos.privados.preparacion.conciliacion', [$draft, 'cliente']), [
            'expected_version_id' => $stale,
            'entity_id' => $cliente->pk_cliente,
        ])->assertSessionHasErrors('expected_version_id');
        $this->assertNull($draft->fresh()->cliente_id);

        $draft->forceFill(['status' => ContractDraft::STATUS_PUBLISHED])->save();
        $this->actingAs($agent)->put(route('contratos.privados.preparacion.fiador', $draft), [
            'expected_version_id' => $draft->fresh()->current_version_id,
            'guarantor_mode' => 'manual',
        ])->assertSessionHasErrors('expected_version_id');
    }

    public function test_preparation_searches_are_server_side_paginated_and_viewer_cannot_operate(): void
    {
        [$draft, $agent] = $this->draft();
        foreach (range(1, 11) as $number) {
            $this->cliente('Cliente buscable '.$number);
        }

        $this->actingAs($agent)->get(route('contratos.privados.preparacion.show', [$draft, 'cliente_q' => 'buscable']))
            ->assertOk()
            ->assertSee('Cliente buscable 1')
            ->assertSee('cliente_page=2');

        $this->actingAs($this->viewer())->get(route('contratos.privados.create'))->assertForbidden();
        $this->actingAs($this->viewer())->get(route('contratos.privados.preparacion.show', $draft))->assertForbidden();
    }

    private function reconcile(User $agent, ContractDraft $draft, string $entity, int $entityId): void
    {
        $this->actingAs($agent)->post(route('contratos.privados.preparacion.conciliacion', [$draft, $entity]), [
            'expected_version_id' => $draft->current_version_id,
            'entity_id' => $entityId,
        ])->assertRedirect();
    }

    private function draft(): array
    {
        $agent = $this->agent();
        $this->actingAs($agent)->get(route('contratos.privados.create'));

        return [ContractDraft::query()->sole()->load('currentVersion'), $agent];
    }

    private function cliente(string $name): Cliente
    {
        return Cliente::create(['nombre' => $name, 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio '.$name]);
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => User::ROLE_AGENT]);
    }

    private function viewer(): User
    {
        return User::factory()->create(['role' => User::ROLE_VIEWER]);
    }
}
