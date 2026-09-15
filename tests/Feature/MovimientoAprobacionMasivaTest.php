<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovimientoAprobacionMasivaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_multiple_eligible_movements(): void
    {
        $admin = $this->admin();
        $first = $this->movement(Movimiento::STATUS_PENDING, 'MOV-000101');
        $second = $this->movement(Movimiento::STATUS_PENDING, 'MOV-000102');

        $this->actingAs($admin)
            ->patch(route('movimientos.approve-bulk'), ['movimientos' => [$first->id, $second->id]])
            ->assertRedirect(route('movimientos.index'))
            ->assertSessionHas('ok', '2 movimientos aprobados correctamente.');

        foreach ([$first, $second] as $movimiento) {
            $movimiento->refresh();
            $this->assertSame(Movimiento::STATUS_APPROVED, $movimiento->approval_status);
            $this->assertSame($admin->id, $movimiento->approved_by);
            $this->assertNotNull($movimiento->approved_at);
            $this->assertTrue(ActivityLog::query()
                ->where('model_type', Movimiento::class)
                ->where('model_id', $movimiento->id)
                ->where('action', 'updated')
                ->where('user_id', $admin->id)
                ->exists());
        }
    }

    public function test_non_eligible_movement_is_not_approved_when_sent_manually(): void
    {
        $admin = $this->admin();
        $pending = $this->movement(Movimiento::STATUS_PENDING, 'MOV-000103');
        $approved = $this->movement(Movimiento::STATUS_APPROVED, 'MOV-000104');

        $this->actingAs($admin)
            ->patch(route('movimientos.approve-bulk'), ['movimientos' => [$pending->id, $approved->id]])
            ->assertRedirect(route('movimientos.index'))
            ->assertSessionHas('ok', '1 movimiento aprobado correctamente. Se omitieron 1: MOV-000104 (ya no está pendiente).');

        $this->assertSame(Movimiento::STATUS_APPROVED, $pending->fresh()->approval_status);
        $this->assertNull($approved->fresh()->approved_by);
    }

    public function test_guest_and_non_admin_cannot_execute_bulk_approval(): void
    {
        $movimiento = $this->movement(Movimiento::STATUS_PENDING, 'MOV-000105');

        $this->patch(route('movimientos.approve-bulk'), ['movimientos' => [$movimiento->id]])
            ->assertRedirect(route('login'));

        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $this->actingAs($viewer)
            ->patch(route('movimientos.approve-bulk'), ['movimientos' => [$movimiento->id]])
            ->assertForbidden();

        $this->assertSame(Movimiento::STATUS_PENDING, $movimiento->fresh()->approval_status);
    }

    public function test_individual_approval_remains_available(): void
    {
        $admin = $this->admin();
        $movimiento = $this->movement(Movimiento::STATUS_PENDING, 'MOV-000106');

        $this->actingAs($admin)
            ->patch(route('movimientos.approve', $movimiento))
            ->assertRedirect(route('movimientos.index'))
            ->assertSessionHas('ok', 'Movimiento aprobado correctamente.');

        $this->assertSame(Movimiento::STATUS_APPROVED, $movimiento->fresh()->approval_status);
    }

    public function test_bulk_controls_are_only_shown_to_admin_for_pending_movements(): void
    {
        $this->movement(Movimiento::STATUS_PENDING, 'MOV-000107');
        $this->movement(Movimiento::STATUS_APPROVED, 'MOV-000108');

        $adminResponse = $this->actingAs($this->admin())->get(route('movimientos.index'));
        $adminResponse->assertOk()->assertSee('Aprobar seleccionados')->assertSee('select-all-pending');
        $this->assertSame(1, substr_count($adminResponse->getContent(), 'data-bulk-approval-checkbox aria-label'));

        $viewerResponse = $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->get(route('movimientos.index'));
        $viewerResponse->assertOk()->assertDontSee('Aprobar seleccionados')->assertDontSee('select-all-pending');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function movement(string $approvalStatus, string $folio): Movimiento
    {
        $cliente = Cliente::firstOrCreate(
            ['rfc' => 'XAXX010101000'],
            ['nombre' => 'Cliente movimientos', 'domicilio' => 'Domicilio de prueba']
        );

        return Movimiento::create([
            'cliente_id' => $cliente->pk_cliente,
            'propiedad_id' => null,
            'inquilino_id' => null,
            'asignado_a_tipo' => 'cliente',
            'folio' => $folio,
            'concepto' => 'renta',
            'fecha' => '2026-09-01',
            'importe' => '2000.00',
            'forma_pago' => 'transferencia',
            'approval_status' => $approvalStatus,
            'estado_pago' => Movimiento::PAYMENT_LIQUIDATED,
            'fecha_liquidacion' => '2026-09-01',
            'afecta_saldo_cliente' => true,
        ]);
    }
}
