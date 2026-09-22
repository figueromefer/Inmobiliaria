<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\ActivityLog;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\User;
use App\Services\ContractDraftPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractDraftReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_viewer_cannot_search_or_change_reconciliation_links(): void
    {
        $agent = $this->agent();
        $draft = $this->draft($agent);
        $cliente = $this->cliente();

        $this->app['auth']->guard()->logout();
        $this->get(route('contratos.borradores.show', [$draft, 'cliente_q' => 'Cliente']))->assertRedirect(route('login'));
        $this->post(route('contratos.borradores.reconciliation.link', [$draft, 'cliente']), ['entity_id' => $cliente->pk_cliente])->assertRedirect(route('login'));

        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $this->actingAs($viewer)->get(route('contratos.borradores.show', [$draft, 'cliente_q' => 'Cliente']))->assertForbidden();
        $this->actingAs($viewer)->post(route('contratos.borradores.reconciliation.link', [$draft, 'cliente']), ['entity_id' => $cliente->pk_cliente])->assertForbidden();
        $this->actingAs($viewer)->delete(route('contratos.borradores.reconciliation.unlink', [$draft, 'cliente']))->assertForbidden();
    }

    public function test_admin_and_agent_can_search_existing_master_records_with_pagination(): void
    {
        $draft = $this->draft($this->agent());
        $cliente = $this->cliente(['nombre' => 'Cliente Buscable']);
        $propiedad = $this->propiedad($cliente, ['alias' => 'Propiedad Buscable']);
        $inquilino = $this->inquilino(['nombre' => 'Inquilino Buscable']);

        foreach ([User::ROLE_ADMIN, User::ROLE_AGENT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('contratos.borradores.show', [
                    $draft,
                    'cliente_q' => 'Buscable',
                    'propiedad_q' => 'Buscable',
                    'inquilino_q' => 'Buscable',
                ]))
                ->assertOk()
                ->assertSee($cliente->nombre)
                ->assertSee($propiedad->alias)
                ->assertSee($inquilino->nombre);
        }
    }

    public function test_valid_client_link_is_operational_only_and_can_be_removed(): void
    {
        $actor = $this->agent();
        $payload = $this->payload();
        $payload['lessor']['person']['full_name'] = 'Cliente Conciliado';
        $draft = $this->draft($actor, $payload);
        $cliente = $this->cliente(['nombre' => 'Cliente Conciliado']);
        $snapshot = $draft->currentVersion->canonical_payload;
        $versionId = $draft->current_version_id;
        $clienteBefore = $cliente->fresh()->getAttributes();

        $this->actingAs($actor)->post(route('contratos.borradores.reconciliation.link', [$draft, 'cliente']), ['entity_id' => $cliente->pk_cliente])->assertRedirect();

        $draft->refresh();
        $this->assertSame($cliente->pk_cliente, $draft->cliente_id);
        $this->assertSame($versionId, $draft->current_version_id);
        $this->assertSame($snapshot, $draft->currentVersion->canonical_payload);
        $this->assertSame($clienteBefore, $cliente->fresh()->getAttributes());
        $linkLog = ActivityLog::query()->where([
            'user_id' => $actor->id,
            'action' => 'linked',
            'model_type' => ContractDraft::class,
            'model_id' => $draft->id,
            'module' => 'contract_draft_reconciliation',
        ])->sole();
        $this->assertSame(['cliente_id' => null], $linkLog->old_values);
        $this->assertSame(['cliente_id' => $cliente->pk_cliente], $linkLog->new_values);
        $this->assertNull($linkLog->ip_address);
        $this->assertNull($linkLog->user_agent);
        $this->assertStringNotContainsString('canonical_payload', $linkLog->technical_detail_json);
        $this->assertStringNotContainsString('raw_legacy_payload', $linkLog->technical_detail_json);
        foreach ([$cliente->nombre, $cliente->rfc, $cliente->correo, $cliente->celular, $cliente->domicilio] as $pii) {
            $this->assertStringNotContainsString((string) $pii, $linkLog->technical_detail_json);
        }

        $this->actingAs($actor)->delete(route('contratos.borradores.reconciliation.unlink', [$draft, 'cliente']))->assertRedirect();
        $this->assertNull($draft->fresh()->cliente_id);
        $this->assertSame($versionId, $draft->fresh()->current_version_id);
        $unlinkLog = ActivityLog::query()->where([
            'user_id' => $actor->id,
            'action' => 'unlinked',
            'model_type' => ContractDraft::class,
            'model_id' => $draft->id,
            'module' => 'contract_draft_reconciliation',
        ])->sole();
        $this->assertSame(['cliente_id' => $cliente->pk_cliente], $unlinkLog->old_values);
        $this->assertSame(['cliente_id' => null], $unlinkLog->new_values);
        $this->assertNull($unlinkLog->ip_address);
        $this->assertNull($unlinkLog->user_agent);
    }

    public function test_nonexistent_master_ids_are_rejected_without_changing_the_draft(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $versionId = $draft->current_version_id;

        $this->actingAs($actor)->postJson(route('contratos.borradores.reconciliation.link', [$draft, 'cliente']), ['entity_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entity_id');

        $draft->refresh();
        $this->assertNull($draft->cliente_id);
        $this->assertSame($versionId, $draft->current_version_id);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_property_and_tenant_can_be_linked_or_unlinked_independently_without_creating_a_contract(): void
    {
        $actor = $this->agent();
        $draft = $this->draft($actor);
        $cliente = $this->cliente();
        $propiedad = $this->propiedad($cliente);
        $inquilino = $this->inquilino();
        $versionId = $draft->current_version_id;
        $propiedadBefore = $propiedad->fresh()->getAttributes();
        $inquilinoBefore = $inquilino->fresh()->getAttributes();

        $this->actingAs($actor)->post(route('contratos.borradores.reconciliation.link', [$draft, 'propiedad']), ['entity_id' => $propiedad->pk_propiedad])->assertRedirect();
        $this->actingAs($actor)->post(route('contratos.borradores.reconciliation.link', [$draft, 'inquilino']), ['entity_id' => $inquilino->id])->assertRedirect();

        $draft->refresh();
        $this->assertNull($draft->cliente_id);
        $this->assertSame($propiedad->pk_propiedad, $draft->propiedad_id);
        $this->assertSame($inquilino->id, $draft->inquilino_id);
        $this->assertSame($versionId, $draft->current_version_id);
        $this->assertDatabaseCount('contratos', 0);
        $this->assertSame($propiedadBefore, $propiedad->fresh()->getAttributes());
        $this->assertSame($inquilinoBefore, $inquilino->fresh()->getAttributes());

        $this->actingAs($actor)->delete(route('contratos.borradores.reconciliation.unlink', [$draft, 'propiedad']))->assertRedirect();
        $this->actingAs($actor)->delete(route('contratos.borradores.reconciliation.unlink', [$draft, 'inquilino']))->assertRedirect();
        $draft->refresh();
        $this->assertNull($draft->propiedad_id);
        $this->assertNull($draft->inquilino_id);
        $this->assertSame($versionId, $draft->current_version_id);
    }

    public function test_comparison_uses_visual_normalization_without_changing_snapshot_or_master(): void
    {
        $actor = $this->agent();
        $payload = $this->payload();
        $payload['lessor']['person'] = array_merge($payload['lessor']['person'], [
            'full_name' => '  MARÍA   LÓPEZ ', 'rfc' => 'abc 010101 xyz', 'phone' => '(33) 1234 5678', 'email' => 'MARIA@EXAMPLE.TEST', 'address' => 'Calle  Uno',
        ]);
        $payload['leased_property'] = ['alias' => 'Casa Norte', 'address' => 'Calle Dos', 'property_use_codes' => []];
        $payload['lessee']['person'] = array_merge($payload['lessee']['person'], ['full_name' => 'Inquilino distinto']);
        $draft = $this->draft($actor, $payload);
        $cliente = $this->cliente(['nombre' => 'maría lópez', 'rfc' => 'ABC010101XYZ', 'celular' => '3312345678', 'correo' => 'maria@example.test', 'domicilio' => 'calle uno']);
        $propiedad = $this->propiedad($cliente, ['alias' => 'CASA NORTE', 'domicilio' => 'Calle Dos']);
        $inquilino = $this->inquilino(['nombre' => 'Otro inquilino']);

        foreach ([['cliente', $cliente->pk_cliente], ['propiedad', $propiedad->pk_propiedad], ['inquilino', $inquilino->id]] as [$entity, $id]) {
            $this->actingAs($actor)->post(route('contratos.borradores.reconciliation.link', [$draft, $entity]), ['entity_id' => $id]);
        }

        $this->actingAs($actor)->get(route('contratos.borradores.show', $draft))
            ->assertOk()
            ->assertSee('coincide')
            ->assertSee('diferente');
    }

    /** @param array<string, mixed>|null $payload */
    private function draft(User $actor, ?array $payload = null): ContractDraft
    {
        $this->actingAs($actor)->post(route('contratos.borradores.store'), ['payload' => $payload ?? $this->payload()])->assertRedirect();

        return ContractDraft::with('currentVersion')->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return app(ContractDraftPayload::class)->empty();
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => User::ROLE_AGENT]);
    }

    /** @param array<string, mixed> $attributes */
    private function cliente(array $attributes = []): Cliente
    {
        return Cliente::create(array_merge([
            'nombre' => 'Cliente de prueba', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio de prueba', 'correo' => 'cliente@example.test', 'celular' => '3311111111',
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function propiedad(Cliente $cliente, array $attributes = []): Propiedad
    {
        return Propiedad::create(array_merge([
            'fk_cliente' => $cliente->pk_cliente, 'alias' => 'Propiedad de prueba', 'domicilio' => 'Domicilio de propiedad',
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function inquilino(array $attributes = []): Inquilino
    {
        return Inquilino::create(array_merge([
            'nombre' => 'Inquilino de prueba', 'correo' => 'inquilino@example.test', 'telefono' => '3322222222', 'domicilio' => 'Domicilio inquilino', 'nacionalidad' => 'Mexicana',
        ], $attributes));
    }
}
