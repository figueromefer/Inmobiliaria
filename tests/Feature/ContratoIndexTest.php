<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\Contrato;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratoIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_type_column_and_distinct_private_and_justice_badges(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $private = $this->contract($cliente, $propiedad, 'privado');
        $justice = $this->contract($cliente, $propiedad, 'justicia_alternativa');

        $response = $this->actingAs($this->agent())->get(route('contratos.index'));

        $response->assertOk()
            ->assertSee('Tipo')
            ->assertSee('Contrato privado')
            ->assertSee('Justicia alternativa')
            ->assertSee('contracts-badge-private')
            ->assertSee('contracts-badge-ja')
            ->assertSee('#'.$private->id)
            ->assertSee($justice->expediente_justicia_alternativa);
    }

    public function test_private_filter_includes_default_and_legacy_compatible_private_rows_but_excludes_justice(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $private = $this->contract($cliente, $propiedad, 'privado');
        $justice = $this->contract($cliente, $propiedad, 'justicia_alternativa');

        $response = $this->actingAs($this->agent())->get(route('contratos.index', ['tipo' => 'privado']));

        $response->assertOk()
            ->assertSee('#'.$private->id)
            ->assertDontSee($justice->expediente_justicia_alternativa);
    }

    public function test_justice_filter_and_pagination_preserve_type_query(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        foreach (range(1, 6) as $number) {
            $this->contract($cliente, $propiedad, 'justicia_alternativa', 'JA-PAG-'.$number);
        }
        $private = $this->contract($cliente, $propiedad, 'privado');

        $response = $this->actingAs($this->agent())->get(route('contratos.index', [
            'tipo' => 'justicia_alternativa',
            'perPage' => 5,
        ]));

        $response->assertOk()
            ->assertDontSee($private->domicilio_inmueble)
            ->assertSee('JA-PAG-')
            ->assertSee('tipo=justicia_alternativa', false);
    }

    public function test_new_private_contract_cta_stays_internal_and_authorization_remains_unchanged(): void
    {
        $response = $this->actingAs($this->agent())->get(route('contratos.index'));

        $response->assertOk()
            ->assertSee('+ Nuevo contrato privado')
            ->assertSee(route('contratos.privados.create'), false)
            ->assertDontSee('forms.gle')
            ->assertSee('Traer contrato de Justicia Alternativa')
            ->assertSee('Borradores internos');

        $this->actingAs($this->viewer())
            ->get(route('contratos.index'))
            ->assertOk()
            ->assertDontSee('+ Nuevo contrato privado')
            ->assertDontSee('Traer contrato de Justicia Alternativa')
            ->assertDontSee('Borradores internos');

        $start = $this->actingAs($this->agent())
            ->get(route('contratos.privados.create'));
        $start->assertRedirect(route('contratos.privados.preparacion.show', ContractDraft::query()->sole()));

        $this->actingAs($this->viewer())
            ->get(route('contratos.privados.create'))
            ->assertForbidden();
    }

    public function test_existing_search_sort_and_date_filters_remain_compatible_with_type_filter(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $contract = $this->contract($cliente, $propiedad, 'privado');

        $this->actingAs($this->agent())
            ->get(route('contratos.index', [
                'q' => $cliente->nombre,
                'desde' => now()->toDateString(),
                'hasta' => now()->toDateString(),
                'sort' => 'monto_mensual',
                'dir' => 'asc',
                'tipo' => 'privado',
            ]))
            ->assertOk()
            ->assertSee('#'.$contract->id);
    }

    private function clientAndProperty(): array
    {
        $cliente = Cliente::create([
            'nombre' => 'Cliente listado',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio listado',
        ]);
        $propiedad = Propiedad::create([
            'fk_cliente' => $cliente->pk_cliente,
            'alias' => 'Propiedad listado',
            'domicilio' => 'Domicilio listado',
        ]);

        return [$cliente, $propiedad];
    }

    private function contract(Cliente $cliente, Propiedad $propiedad, string $origin, ?string $expediente = null): Contrato
    {
        return Contrato::create([
            'fk_cliente' => $cliente->pk_cliente,
            'fk_propiedad' => $propiedad->pk_propiedad,
            'fecha' => now(),
            'monto_mensual' => 10000,
            'domicilio_inmueble' => $origin === 'privado' ? 'Domicilio exclusivo privado' : 'Domicilio Justicia Alternativa',
            'origen' => $origin,
            'expediente_justicia_alternativa' => $origin === 'justicia_alternativa'
                ? ($expediente ?: 'JA-'.uniqid())
                : null,
        ]);
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
