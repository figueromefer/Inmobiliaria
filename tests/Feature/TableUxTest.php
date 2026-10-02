<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Inquilino;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_searches_and_safe_sorting_are_server_side_for_primary_tables(): void
    {
        $agent = $this->agent();
        $cliente = Cliente::create(['nombre' => 'Cliente buscable', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa buscable', 'domicilio' => 'Calle buscable']);
        $inquilino = Inquilino::create(['nombre' => 'Inquilino buscable', 'correo' => 'inquilino@example.test']);
        Documento::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fk_inquilino' => $inquilino->id, 'titulo' => 'Documento buscable', 'tipo' => 'otro', 'archivo' => 'documentos/x.pdf']);
        Movimiento::create(['cliente_id' => $cliente->pk_cliente, 'propiedad_id' => $propiedad->pk_propiedad, 'asignado_a_tipo' => 'propiedad', 'concepto' => 'renta', 'fecha' => now(), 'importe' => 100, 'forma_pago' => 'efectivo']);
        $first = Cliente::create(['nombre' => 'A cliente ordenado', 'rfc' => 'XAXX010101001', 'domicilio' => 'Domicilio']);
        $last = Cliente::create(['nombre' => 'Z cliente ordenado', 'rfc' => 'XAXX010101002', 'domicilio' => 'Domicilio']);

        $this->actingAs($agent)->get(route('clientes.index', ['search' => 'buscable', 'sort' => 'correo', 'dir' => 'desc']))->assertOk()->assertSee('Cliente buscable');
        $this->actingAs($agent)->get(route('propiedades.index', ['q' => 'Cliente buscable', 'sort' => 'cliente', 'dir' => 'asc']))->assertOk()->assertSee('Casa buscable');
        $this->actingAs($agent)->get(route('movimientos.index', ['q' => 'Cliente buscable', 'sort' => 'importe', 'dir' => 'asc']))->assertOk()->assertSee('Cliente buscable');
        $this->actingAs($agent)->get(route('documentos.index', ['q' => 'Inquilino buscable', 'sort' => 'tipo', 'dir' => 'asc']))->assertOk()->assertSee('Documento buscable');
        $this->actingAs($agent)->get(route('clientes.index', ['sort' => 'DROP TABLE clientes']))->assertOk();
        $this->actingAs($agent)->get(route('clientes.index', ['search' => 'cliente ordenado', 'sort' => 'nombre', 'dir' => 'asc']))->assertOk()->assertSeeInOrder([$first->nombre, $last->nombre]);
        $this->actingAs($agent)->get(route('clientes.index', ['search' => 'cliente ordenado', 'sort' => 'nombre', 'dir' => 'desc']))->assertOk()->assertSeeInOrder([$last->nombre, $first->nombre]);
    }

    public function test_inquilino_list_shows_only_current_contract_properties_and_searches_them(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $actual = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa vigente']);
        $futura = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa futura']);
        $inquilino = Inquilino::create(['nombre' => 'Inquilino con contrato']);
        Inquilino::create(['nombre' => 'Inquilino sin contrato']);
        Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $actual->pk_propiedad, 'inquilino_id' => $inquilino->id, 'fecha' => now(), 'fecha_inicio' => now()->subMonth(), 'fecha_fin' => now()->addMonth(), 'origen' => 'privado']);
        Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $futura->pk_propiedad, 'inquilino_id' => $inquilino->id, 'fecha' => now(), 'fecha_inicio' => now()->addMonth(), 'fecha_fin' => now()->addMonths(2), 'origen' => 'privado']);

        $response = $this->actingAs($this->agent())->get(route('inquilinos.index', ['q' => 'Casa vigente']));
        $response->assertOk()->assertSee('Casa vigente')->assertDontSee('Casa futura');
        $this->actingAs($this->agent())->get(route('inquilinos.index', ['q' => 'Casa futura']))->assertOk()->assertDontSee('Inquilino con contrato');
        $this->actingAs($this->agent())->get(route('inquilinos.index'))->assertOk()->assertSee('Sin propiedad vigente');
    }

    public function test_client_document_rows_show_only_persisted_property_and_tenant_relations(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa documento']);
        $inquilino = Inquilino::create(['nombre' => 'Inquilino documento']);
        Documento::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fk_inquilino' => $inquilino->id, 'titulo' => 'CFE', 'tipo' => 'cfe', 'archivo' => 'documentos/cfe.pdf']);
        Documento::create(['fk_cliente' => $cliente->pk_cliente, 'titulo' => 'General', 'tipo' => 'otro', 'archivo' => 'documentos/general.pdf']);

        $this->actingAs($this->agent())->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertSee('Casa documento')
            ->assertSee('Inquilino documento')
            ->assertSee('Propiedad: ', false)
            ->assertSee('—');
    }

    public function test_searchable_selects_preserve_the_placeholder_first_in_the_dom_and_tom_select_order(): void
    {
        $cliente = Cliente::create(['nombre' => 'Zeta cliente', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);

        $this->actingAs($this->agent())->get(route('movimientos.create'))
            ->assertOk()
            ->assertSeeInOrder(['<option value="">— Selecciona —</option>', 'Zeta cliente'], false);

        $this->assertStringContainsString("field: '\$order'", file_get_contents(resource_path('views/layouts/app.blade.php')));
    }

    public function test_live_search_forms_preserve_table_state_except_the_page(): void
    {
        $agent = $this->agent();
        $cliente = Cliente::create(['nombre' => 'Cliente estado', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Casa estado']);
        $inquilino = Inquilino::create(['nombre' => 'Inquilino estado']);
        $contrato = Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'inquilino_id' => $inquilino->id, 'fecha' => now(), 'origen' => 'privado']);
        Documento::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fk_inquilino' => $inquilino->id, 'contrato_id' => $contrato->id, 'titulo' => 'Documento estado', 'tipo' => 'otro', 'archivo' => 'documentos/estado.pdf']);
        ContractDraft::create(['source' => 'internal', 'status' => ContractDraft::STATUS_DRAFT]);

        $this->actingAs($agent)->get(route('clientes.index', ['search' => 'estado', 'sort' => 'correo', 'dir' => 'desc', 'page' => 2]))
            ->assertOk()->assertSee('name="sort" value="correo"', false)->assertSee('name="dir" value="desc"', false)->assertDontSee('name="page"', false);
        $this->actingAs($agent)->get(route('propiedades.index', ['q' => 'estado', 'estatus_informacion' => 'pendiente', 'sort' => 'cliente', 'dir' => 'desc']))
            ->assertOk()->assertSee('name="sort" value="cliente"', false)->assertSee('name="dir" value="desc"', false)->assertSee('name="estatus_informacion"', false);
        $this->actingAs($agent)->get(route('movimientos.index', ['q' => 'estado', 'perPage' => 50, 'sort' => 'importe', 'dir' => 'asc']))
            ->assertOk()->assertSee('name="perPage"', false)->assertSee('name="sort" value="importe"', false)->assertSee('name="dir" value="asc"', false);
        $this->actingAs($agent)->get(route('contratos.index', ['q' => 'estado', 'tipo' => 'privado', 'desde' => '2026-01-01', 'hasta' => '2026-12-31', 'perPage' => 50, 'sort' => 'cliente', 'dir' => 'asc']))
            ->assertOk()->assertSee('name="sort" value="cliente"', false)->assertSee('name="dir" value="asc"', false)->assertSee('name="tipo"', false)->assertSee('name="desde"', false)->assertSee('name="hasta"', false);
        $this->actingAs($agent)->get(route('documentos.index', ['q' => 'estado', 'cliente' => $cliente->pk_cliente, 'propiedad' => $propiedad->pk_propiedad, 'inquilino' => $inquilino->id, 'contrato' => $contrato->id, 'sort' => 'tipo', 'dir' => 'asc']))
            ->assertOk()->assertSee('name="sort" value="tipo"', false)->assertSee('name="dir" value="asc"', false)->assertSee('name="inquilino" value="'.$inquilino->id.'"', false)->assertSee('name="contrato" value="'.$contrato->id.'"', false);
        $this->actingAs($agent)->get(route('contratos.borradores.index', ['q' => 'internal', 'sort' => 'source', 'dir' => 'asc']))
            ->assertOk()->assertSee('name="sort" value="source"', false)->assertSee('name="dir" value="asc"', false);
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => User::ROLE_AGENT]);
    }
}
