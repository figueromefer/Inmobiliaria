<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovimientoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_edit_movement_and_change_returns_to_pending_approval(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $movimiento = $this->movement();

        $this->actingAs($agent)
            ->get(route('movimientos.edit', $movimiento))
            ->assertOk()
            ->assertSee('Editar movimiento')
            ->assertSee($movimiento->folio);

        $this->actingAs($agent)
            ->put(route('movimientos.update', $movimiento), $this->movementPayload([
                'importe' => '2450.50',
                'notas' => 'Importe corregido',
            ]))
            ->assertRedirect(route('movimientos.index'))
            ->assertSessionHas('ok', 'Movimiento actualizado y pendiente de aprobación.');

        $movimiento->refresh();

        $this->assertSame('2450.50', $movimiento->importe);
        $this->assertSame('Importe corregido', $movimiento->notas);
        $this->assertSame(Movimiento::STATUS_PENDING, $movimiento->approval_status);
        $this->assertNull($movimiento->approved_by);
        $this->assertNull($movimiento->approved_at);
        $this->assertTrue(ActivityLog::query()
            ->where('model_type', Movimiento::class)
            ->where('model_id', $movimiento->id)
            ->where('action', 'updated')
            ->exists());
    }

    public function test_admin_edit_keeps_current_values_approved(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $movimiento = $this->movement();

        $this->actingAs($admin)
            ->put(route('movimientos.update', $movimiento), $this->movementPayload([
                'importe' => '3200.00',
            ]))
            ->assertRedirect(route('movimientos.index'))
            ->assertSessionHas('ok', 'Movimiento actualizado y aprobado.');

        $movimiento->refresh();

        $this->assertSame('3200.00', $movimiento->importe);
        $this->assertSame(Movimiento::STATUS_APPROVED, $movimiento->approval_status);
        $this->assertSame($admin->id, $movimiento->approved_by);
        $this->assertNotNull($movimiento->approved_at);
    }

    public function test_viewer_cannot_edit_movement(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $movimiento = $this->movement();

        $this->actingAs($viewer)
            ->get(route('movimientos.edit', $movimiento))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('movimientos.update', $movimiento), $this->movementPayload())
            ->assertForbidden();
    }

    public function test_admin_can_delete_movement_and_deletion_is_logged(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $movimiento = $this->movement();
        $movementId = $movimiento->id;

        $this->actingAs($admin)
            ->delete(route('movimientos.destroy', $movimiento))
            ->assertRedirect(route('movimientos.index'))
            ->assertSessionHas('ok', "Movimiento {$movimiento->folio} eliminado correctamente.");

        $this->assertDatabaseMissing('movimientos', ['id' => $movementId]);
        $this->assertTrue(ActivityLog::query()
            ->where('model_type', Movimiento::class)
            ->where('model_id', $movementId)
            ->where('action', 'deleted')
            ->exists());
    }

    public function test_agent_cannot_delete_movement(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $movimiento = $this->movement();

        $this->actingAs($agent)
            ->delete(route('movimientos.destroy', $movimiento))
            ->assertForbidden();

        $this->assertDatabaseHas('movimientos', ['id' => $movimiento->id]);
    }

    private function movement(): Movimiento
    {
        $cliente = Cliente::create([
            'nombre' => 'Cliente Movimiento',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio de prueba',
        ]);

        return Movimiento::create([
            'cliente_id' => $cliente->pk_cliente,
            'propiedad_id' => null,
            'inquilino_id' => null,
            'asignado_a_tipo' => 'cliente',
            'folio' => 'MOV-000001',
            'concepto' => 'renta',
            'fecha' => '2026-08-25',
            'importe' => '2000.00',
            'forma_pago' => 'transferencia',
            'notas' => 'Movimiento original',
            'approval_status' => Movimiento::STATUS_APPROVED,
            'estado_pago' => Movimiento::PAYMENT_LIQUIDATED,
            'fecha_liquidacion' => '2026-08-25',
            'afecta_saldo_cliente' => true,
        ]);
    }

    private function movementPayload(array $overrides = []): array
    {
        return array_merge([
            'asignado_a_tipo' => 'cliente',
            'cliente_id' => Cliente::query()->value('pk_cliente'),
            'concepto' => 'renta',
            'fecha' => '2026-08-25',
            'importe' => '2000.00',
            'forma_pago' => 'transferencia',
            'estado_pago' => Movimiento::PAYMENT_LIQUIDATED,
            'fecha_liquidacion' => '2026-08-25',
            'afecta_saldo_cliente' => '1',
            'notas' => 'Movimiento original',
        ], $overrides);
    }
}
