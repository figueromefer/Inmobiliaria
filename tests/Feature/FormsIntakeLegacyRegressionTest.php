<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormsIntakeLegacyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_forms_intake_still_creates_and_updates_the_same_pending_record(): void
    {
        $payload = [
            'external_id' => 'legacy-form-response-001',
            'cliente_nombre' => 'Cliente de prueba',
            'tipo_solicitante' => 'Persona Física',
            'tipo_complementaria' => 'Persona Física',
            'tipo_tercero' => 'No hay Tercero Interesado',
            'dias_pago' => '05',
            'monto_mensual' => '$12,000.00',
        ];

        $this->postJson('/api/forms/contratos', $payload)
            ->assertCreated()
            ->assertJsonPath('mode', 'pending');

        $this->assertDatabaseHas('contratos_pendientes', [
            'origen' => 'privado',
            'external_id' => 'legacy-form-response-001',
            'estado' => 'pendiente_match',
        ]);
        $this->assertDatabaseCount('contract_drafts', 0);
        $this->assertDatabaseCount('contratos', 0);

        $firstPending = \App\Models\ContratoPendiente::query()->sole();
        $this->assertEquals(12000.0, $firstPending->raw_payload['monto_mensual']);
        $this->assertEquals(12000.0, $firstPending->mapped_payload['monto_mensual']);

        $this->postJson('/api/forms/contratos', array_merge($payload, ['monto_mensual' => '$13,000.00']))
            ->assertOk()
            ->assertJsonPath('mode', 'pending');

        $this->assertDatabaseCount('contratos_pendientes', 1);
        $this->assertDatabaseCount('contract_drafts', 0);
        $this->assertDatabaseCount('contratos', 0);

        $updatedPending = \App\Models\ContratoPendiente::query()->sole();
        $this->assertSame($firstPending->id, $updatedPending->id);
        $this->assertEquals(13000.0, $updatedPending->raw_payload['monto_mensual']);
        $this->assertEquals(13000.0, $updatedPending->mapped_payload['monto_mensual']);
    }
}
