<?php
namespace Tests\Feature;
use App\Http\Controllers\DocumentoController; use App\Models\Cliente; use App\Models\Contrato; use App\Models\Documento; use App\Models\Propiedad; use App\Models\User; use App\Services\MinimumDocumentChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class MinimumDocumentChecklistTest extends TestCase {
 use RefreshDatabase;
 public function test_checklists_derive_exact_types_from_configuration(): void {
  $cliente=Cliente::create(['nombre'=>'Cliente','rfc'=>'XAXX010101000','domicilio'=>'Domicilio']); $propiedad=Propiedad::create(['fk_cliente'=>$cliente->pk_cliente,'alias'=>'Casa']);
  $service=app(MinimumDocumentChecklistService::class);
  $initial=$service->for('cliente', collect()); $this->assertSame(config('documentos_minimos.cliente')[0]['type'],$initial[0]['type']); $this->assertFalse($initial[0]['present']);
  Documento::create(['fk_cliente'=>$cliente->pk_cliente,'tipo'=>'identificacion','archivo'=>'x.pdf']);
  $items=$service->for('cliente',$cliente->fresh('documentos')->documentos); $this->assertTrue($items[0]['present']); $this->assertFalse($items[1]['present']);
  $property=$service->for('propiedad',$propiedad->fresh('documentos')->documentos); $this->assertCount(4,$property); $this->assertFalse(collect($property)->contains('present',true));
 }
 public function test_every_configured_minimum_document_type_is_uploadable(): void {
  $configured=collect(config('documentos_minimos'))->flatten(1)->pluck('type');
  $this->assertTrue($configured->every(fn ($type) => array_key_exists($type, DocumentoController::$tipos)));
 }
 public function test_document_contract_context_is_available_to_managers_but_not_to_viewers(): void {
  $cliente=Cliente::create(['nombre'=>'Cliente','rfc'=>'XAXX010101000','domicilio'=>'Domicilio']); $propiedad=Propiedad::create(['fk_cliente'=>$cliente->pk_cliente,'alias'=>'Casa']); $contrato=Contrato::create(['fk_cliente'=>$cliente->pk_cliente,'fk_propiedad'=>$propiedad->pk_propiedad,'fecha'=>now(),'origen'=>'privado']);
  $agent=User::factory()->create(['role'=>User::ROLE_AGENT]); $viewer=User::factory()->create(['role'=>User::ROLE_VIEWER]);
  $this->actingAs($agent)->get(route('documentos.create',['contrato'=>$contrato->id,'context'=>'contrato','context_id'=>$contrato->id]))->assertOk()->assertSee('Contrato original firmado');
  $this->actingAs($viewer)->get(route('documentos.create',['contrato'=>$contrato->id,'context'=>'contrato','context_id'=>$contrato->id]))->assertForbidden();
 }
 public function test_document_deletion_requires_the_existing_destructive_permission(): void {
  $cliente=Cliente::create(['nombre'=>'Cliente','rfc'=>'XAXX010101000','domicilio'=>'Domicilio']); $documento=Documento::create(['fk_cliente'=>$cliente->pk_cliente,'tipo'=>'identificacion','archivo'=>'documentos/prueba.pdf']);
  $viewer=User::factory()->create(['role'=>User::ROLE_VIEWER]); $admin=User::factory()->create(['role'=>User::ROLE_ADMIN]);
  $this->actingAs($viewer)->delete(route('documentos.destroy',$documento))->assertForbidden();
  $this->actingAs($admin)->delete(route('documentos.destroy',['documento'=>$documento,'context'=>'cliente','context_id'=>$cliente->pk_cliente]))->assertRedirect(route('clientes.show',$cliente));
 }
}
