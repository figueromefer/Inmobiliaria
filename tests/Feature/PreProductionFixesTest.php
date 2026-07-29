<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Models\Propiedad;
use App\Models\Task;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PreProductionFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_resolve_form_with_get_without_processing_pending_contract(): void
    {
        $pending = ContratoPendiente::create($this->pendingPayload());
        $pending->forceFill(['mapped_payload' => ['nombre_solicitante' => 'MAPEO ANTERIOR']])->save();
        $mappedPayloadBeforeGet = $pending->fresh()->mapped_payload;

        $response = $this->actingAs($this->user('agent'))
            ->get(route('contratos.pendientes.resolver.form', $pending));

        $response->assertOk();
        $response->assertSee('Resolver contrato pendiente');
        $response->assertSee('CLIENTE JA');
        $this->assertSame('pendiente_match', $pending->fresh()->estado);
        $this->assertSame($mappedPayloadBeforeGet, $pending->fresh()->mapped_payload);
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_resolving_pending_contract_requires_alias_for_new_property_and_keeps_address_separate(): void
    {
        $this->fakeGeocoding();
        $longAddress = str_repeat('AVENIDA MUY LARGA ', 22);
        $pending = ContratoPendiente::create($this->pendingPayload([
            'domicilio_inmueble_arrendamiento' => $longAddress,
            'propiedad_domicilio' => $longAddress,
        ]));

        $response = $this->actingAs($this->user('agent'))->post(route('contratos.pendientes.resolver', $pending), [
            'cliente_action' => 'new',
            'propiedad_action' => 'new',
            'propiedad_alias' => 'Casa López',
            'inquilino_action' => 'new',
        ]);

        $response->assertRedirect(route('contratos.index'));
        $propiedad = Propiedad::where('alias', 'Casa López')->firstOrFail();
        $storedAddress = trim($longAddress);
        $contrato = Contrato::firstOrFail();

        $this->assertGreaterThan(255, strlen($storedAddress));
        $this->assertSame($storedAddress, $propiedad->domicilio);
        $this->assertNotSame($propiedad->domicilio, $propiedad->alias);
        $this->assertSame($storedAddress, $contrato->domicilio_inmueble);
        $this->assertDatabaseHas('contratos_pendientes', [
            'id' => $pending->id,
            'estado' => 'importado',
            'matched_propiedad_id' => $propiedad->pk_propiedad,
        ]);
        $task = Task::query()->where('source_type', 'propiedad')->firstOrFail();
        $this->assertSame('Revisar propiedad de contrato pendiente #'.$pending->id, $task->title);
        $this->assertLessThanOrEqual(255, strlen($task->title));
        $this->assertStringContainsString($storedAddress, $task->description);
    }

    public function test_pending_contract_resolution_rejects_excessively_long_property_address_without_partial_records(): void
    {
        $this->fakeGeocoding();
        $pending = ContratoPendiente::create($this->pendingPayload([
            'domicilio_inmueble_arrendamiento' => str_repeat('A', 2001),
            'propiedad_domicilio' => str_repeat('A', 2001),
        ]));

        $this->actingAs($this->user('agent'))
            ->from(route('contratos.pendientes.resolver.form', $pending))
            ->post(route('contratos.pendientes.resolver', $pending), [
                'cliente_action' => 'new',
                'propiedad_action' => 'new',
                'propiedad_alias' => 'Casa Dirección Larga',
                'inquilino_action' => 'new',
            ])
            ->assertRedirect(route('contratos.pendientes.resolver.form', $pending))
            ->assertSessionHasErrors('domicilio_inmueble_arrendamiento');

        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('propiedades', 0);
        $this->assertDatabaseCount('contratos', 0);
        $this->assertSame('pendiente_match', $pending->fresh()->estado);
    }

    public function test_pending_contract_resolution_keeps_property_alias_limited_to_real_column_length(): void
    {
        $pending = ContratoPendiente::create($this->pendingPayload());

        $this->actingAs($this->user('agent'))
            ->from(route('contratos.pendientes.resolver.form', $pending))
            ->post(route('contratos.pendientes.resolver', $pending), [
                'cliente_action' => 'new',
                'propiedad_action' => 'new',
                'propiedad_alias' => str_repeat('A', 256),
                'inquilino_action' => 'new',
            ])
            ->assertRedirect(route('contratos.pendientes.resolver.form', $pending))
            ->assertSessionHasErrors('propiedad_alias');

        $this->assertDatabaseCount('propiedades', 0);
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_alias_is_required_only_when_creating_new_property_from_pending_contract(): void
    {
        $pending = ContratoPendiente::create($this->pendingPayload());

        $this->actingAs($this->user('agent'))
            ->from(route('contratos.pendientes.resolver.form', $pending))
            ->post(route('contratos.pendientes.resolver', $pending), [
                'cliente_action' => 'new',
                'propiedad_action' => 'new',
                'inquilino_action' => 'new',
            ])
            ->assertRedirect(route('contratos.pendientes.resolver.form', $pending))
            ->assertSessionHasErrors('propiedad_alias');

        $this->assertDatabaseCount('propiedades', 0);
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_existing_property_does_not_require_alias_when_resolving_pending_contract(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $pending = ContratoPendiente::create($this->pendingPayload());

        $this->actingAs($this->user('agent'))->post(route('contratos.pendientes.resolver', $pending), [
            'cliente_action' => 'existing',
            'fk_cliente' => $cliente->pk_cliente,
            'propiedad_action' => 'existing',
            'fk_propiedad' => $propiedad->pk_propiedad,
            'inquilino_action' => 'new',
        ])->assertRedirect(route('contratos.index'));

        $this->assertDatabaseHas('contratos_pendientes', [
            'id' => $pending->id,
            'estado' => 'importado',
            'matched_propiedad_id' => $propiedad->pk_propiedad,
        ]);
    }

    public function test_resolved_pending_contract_is_not_processed_again(): void
    {
        $pending = ContratoPendiente::create($this->pendingPayload(['estado' => 'importado']));

        $response = $this->actingAs($this->user('agent'))->post(route('contratos.pendientes.resolver', $pending), [
            'cliente_action' => 'new',
            'propiedad_action' => 'new',
            'propiedad_alias' => 'Casa Norte',
            'inquilino_action' => 'new',
        ]);

        $response->assertRedirect(route('contratos.pendientes.index'));
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_property_with_contract_is_archived_without_deleting_historical_contract(): void
    {
        $admin = $this->user('admin');
        [$cliente, $propiedad] = $this->clientAndProperty();
        Contrato::create([
            'fk_cliente' => $cliente->pk_cliente,
            'fk_propiedad' => $propiedad->pk_propiedad,
            'fecha' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('propiedades.destroy', $propiedad));

        $response->assertRedirect(route('propiedades.index'));
        $this->assertSoftDeleted('propiedades', ['pk_propiedad' => $propiedad->pk_propiedad]);
        $this->assertDatabaseHas('contratos', ['fk_propiedad' => $propiedad->pk_propiedad]);
        $this->assertNotNull(Contrato::with('propiedad')->firstOrFail()->propiedad);
    }

    public function test_archived_property_can_be_restored_by_admin(): void
    {
        $admin = $this->user('admin');
        [, $propiedad] = $this->clientAndProperty();
        $propiedad->delete();

        $this->actingAs($admin)
            ->patch(route('archivados.propiedades.restore', $propiedad->pk_propiedad))
            ->assertRedirect(route('archivados.index'));

        $this->assertFalse(Propiedad::withTrashed()->findOrFail($propiedad->pk_propiedad)->trashed());
    }

    public function test_active_property_restore_request_is_handled_without_changes(): void
    {
        $admin = $this->user('admin');
        [, $propiedad] = $this->clientAndProperty();

        $this->actingAs($admin)
            ->patch(route('archivados.propiedades.restore', $propiedad->pk_propiedad))
            ->assertRedirect();

        $this->assertFalse($propiedad->fresh()->trashed());
    }

    public function test_archived_property_does_not_appear_in_new_movement_selector(): void
    {
        [, $propiedad] = $this->clientAndProperty();
        $propiedad->delete();

        $response = $this->actingAs($this->user('agent'))->get(route('movimientos.create'));

        $response->assertOk();
        $response->assertDontSee($propiedad->alias);
    }

    public function test_non_admin_cannot_archive_property(): void
    {
        [, $propiedad] = $this->clientAndProperty();

        $this->actingAs($this->user('agent'))
            ->delete(route('propiedades.destroy', $propiedad))
            ->assertForbidden();
    }

    public function test_agent_can_access_contract_creation_and_justice_alternative_import_actions(): void
    {
        $response = $this->actingAs($this->user('agent'))->get(route('contratos.index'));

        $response->assertOk();
        $response->assertSee('+ Nuevo contrato privado');
        $response->assertSee('Traer contrato de justicia alternativa');

        $this->actingAs($this->user('agent'))
            ->get(route('contratos.justicia-alternativa'))
            ->assertOk();
    }

    public function test_viewer_cannot_access_justice_alternative_import(): void
    {
        $this->actingAs($this->user('viewer'))
            ->get(route('contratos.justicia-alternativa'))
            ->assertForbidden();
    }

    public function test_pending_contract_resolution_rolls_back_if_task_creation_fails(): void
    {
        $this->fakeGeocoding();
        $pending = ContratoPendiente::create($this->pendingPayload());

        Task::creating(function () {
            throw new \RuntimeException('task fail');
        });

        try {
            $this->actingAs($this->user('agent'))->post(route('contratos.pendientes.resolver', $pending), [
                'cliente_action' => 'new',
                'propiedad_action' => 'new',
                'propiedad_alias' => 'Casa Rollback',
                'inquilino_action' => 'new',
            ])->assertServerError();
        } finally {
            Task::flushEventListeners();
        }

        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('propiedades', 0);
        $this->assertDatabaseCount('contratos', 0);
        $this->assertSame('pendiente_match', $pending->fresh()->estado);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function clientAndProperty(): array
    {
        $cliente = Cliente::create([
            'nombre' => 'Cliente Prueba',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio cliente',
        ]);

        $propiedad = Propiedad::create([
            'fk_cliente' => $cliente->pk_cliente,
            'alias' => 'Casa Histórica',
            'domicilio' => 'Domicilio propiedad',
            'estatus_informacion' => 'completo',
        ]);

        return [$cliente, $propiedad];
    }

    private function pendingPayload(array $mappedOverrides = []): array
    {
        $mapped = array_merge([
            'expediente' => '1178-2026',
            'nombre_solicitante' => 'CLIENTE JA',
            'rfc_solicitante' => 'XAXX010101000',
            'correo_solicitante' => 'cliente@example.test',
            'telefono_solicitante' => '3312345678',
            'domicilio_solicitante' => 'Domicilio cliente',
            'nombre_complementaria' => 'INQUILINO JA',
            'correo_complementaria' => 'inquilino@example.test',
            'telefono_complementaria' => '3311112222',
            'domicilio_inmueble_arrendamiento' => 'ANTEA 1135, ZAPOPAN, JALISCO',
            'propiedad_domicilio' => 'ANTEA 1135, ZAPOPAN, JALISCO',
        ], $mappedOverrides);

        $raw = [
            'Número de expediente' => $mapped['expediente'],
            'Nombre completo de la Parte Solicitante' => $mapped['nombre_solicitante'],
            'RFC de la Sociedad de la Parte Solicitante' => $mapped['rfc_solicitante'],
            'Correo electrónico de la Parte Solicitante' => $mapped['correo_solicitante'],
            'Teléfono de la Parte Solicitante' => $mapped['telefono_solicitante'],
            'Domicilio completo de la Parte Solicitante' => $mapped['domicilio_solicitante'],
            'Nombre completo de la Parte Complementaria:' => $mapped['nombre_complementaria'],
            'Correo electrónico de la Parte Complementaria:' => $mapped['correo_complementaria'],
            'Teléfono de la Parte Complementaria:' => $mapped['telefono_complementaria'],
            'Domicilio completo del Inmueble en Arrendamiento' => $mapped['domicilio_inmueble_arrendamiento'],
        ];

        return [
            'origen' => 'justicia_alternativa',
            'external_id' => '1178-2026',
            'expediente' => '1178-2026',
            'estado' => $mapped['estado'] ?? 'pendiente_match',
            'raw_payload' => $raw,
            'mapped_payload' => $mapped,
        ];
    }

    private function fakeGeocoding(): void
    {
        $this->app->instance(GeocodingService::class, new class extends GeocodingService {
            public function geocode(?string $address): ?array
            {
                return null;
            }
        });
    }
}
