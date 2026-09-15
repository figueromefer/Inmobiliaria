<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratoDetalleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_contract_detail_with_available_relationships(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $inquilino = Inquilino::create([
            'nombre' => 'Inquilino de prueba',
            'correo' => 'inquilino@example.test',
            'telefono' => '3312345678',
            'nacionalidad' => 'Mexicana',
        ]);
        $contrato = $this->contract($cliente, $propiedad, $inquilino);
        ContratoPendiente::create([
            'origen' => 'justicia_alternativa',
            'external_id' => 'JA-DETALLE-1',
            'expediente' => 'JA-DETALLE-1',
            'estado' => 'importado',
            'contrato_id' => $contrato->id,
        ]);

        $response = $this->actingAs($this->user())->get(route('contratos.show', $contrato));

        $response->assertOk();
        $response->assertSee('Detalle de contrato');
        $response->assertSee('Contrato registrado');
        $response->assertDontSee('Registro activo');
        $response->assertSee('JA-DETALLE-1');
        $response->assertSee('Cliente de prueba');
        $response->assertSee('Casa de prueba');
        $response->assertSee('Inquilino de prueba');
        $response->assertSee('$12,500.00');
        $response->assertSee('Abrir documento del contrato');
        $response->assertSee('Registros de importación vinculados');
        $response->assertSee('Los movimientos se consultan desde el módulo Movimientos.');
    }

    public function test_contract_detail_handles_absent_optional_data(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $contrato = $this->contract($cliente, $propiedad, null, [
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'monto_mensual' => null,
            'monto_deposito' => null,
            'urldoc' => null,
        ]);

        $response = $this->actingAs($this->user())->get(route('contratos.show', $contrato));

        $response->assertOk();
        $response->assertSee('Sin inquilino registrado.');
        $response->assertSee('Sin documento asociado.');
        $response->assertSee('—');
    }

    public function test_contract_index_includes_visible_detail_action(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $contrato = $this->contract($cliente, $propiedad);

        $response = $this->actingAs($this->user())->get(route('contratos.index'));

        $response->assertOk();
        $response->assertSee('Ver detalle');
        $response->assertSee(route('contratos.show', $contrato), false);
    }

    public function test_guest_cannot_access_contract_detail(): void
    {
        $this->get(route('contratos.show', 1))->assertRedirect(route('login'));
    }

    private function user(): User
    {
        return User::factory()->create(['role' => User::ROLE_VIEWER]);
    }

    private function clientAndProperty(): array
    {
        $cliente = Cliente::create([
            'nombre' => 'Cliente de prueba',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio del cliente',
            'correo' => 'cliente@example.test',
            'celular' => '3398765432',
        ]);
        $propiedad = Propiedad::create([
            'fk_cliente' => $cliente->pk_cliente,
            'alias' => 'Casa de prueba',
            'domicilio' => 'Domicilio de la propiedad',
        ]);

        return [$cliente, $propiedad];
    }

    private function contract(Cliente $cliente, Propiedad $propiedad, ?Inquilino $inquilino = null, array $overrides = []): Contrato
    {
        return Contrato::create(array_merge([
            'fk_cliente' => $cliente->pk_cliente,
            'fk_propiedad' => $propiedad->pk_propiedad,
            'inquilino_id' => $inquilino?->id,
            'fecha' => now(),
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'dias_pago' => 5,
            'monto_total' => 150000,
            'monto_mensual' => 12500,
            'monto_deposito' => 12500,
            'comision_renta' => 12500,
            'comision_mensual' => 10,
            'domicilio_inmueble' => 'Domicilio de la propiedad',
            'origen' => 'justicia_alternativa',
            'expediente_justicia_alternativa' => 'JA-DETALLE-1',
            'urldoc' => 'https://drive.example.test/documento',
        ], $overrides));
    }
}
