<?php
namespace Tests\Feature;
use App\Models\ContratoPendiente;
use App\Services\JusticiaAlternativaEffectivePayloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class JusticiaAlternativaOverridesTest extends TestCase {
 use RefreshDatabase;
 public function test_overrides_are_persisted_without_mutating_raw_payload_and_win_recursively(): void {
  $raw=['Fecha inicio'=>'original']; $mapped=['fecha_inicio_contrato'=>'2026-01-01','party'=>['name'=>'JA','email'=>'ja@test']];
  $pending=ContratoPendiente::create(['origen'=>'justicia_alternativa','external_id'=>'JA-1','estado'=>'pendiente_match','raw_payload'=>$raw,'mapped_payload'=>$mapped,'manual_overrides'=>['fecha_inicio_contrato'=>'2026-02-01','party'=>['name'=>'Manual']]]);
  $effective=app(JusticiaAlternativaEffectivePayloadService::class)->effective($pending);
  $this->assertSame($raw,$pending->fresh()->raw_payload); $this->assertSame('2026-02-01',$effective['fecha_inicio_contrato']); $this->assertSame('Manual',$effective['party']['name']); $this->assertSame('ja@test',$effective['party']['email']);
 }
 public function test_refreshing_mapped_payload_keeps_manual_overrides(): void {
  $pending=ContratoPendiente::create(['origen'=>'justicia_alternativa','external_id'=>'JA-2','estado'=>'pendiente_match','raw_payload'=>['x'=>1],'mapped_payload'=>['monto_mensual'=>100],'manual_overrides'=>['monto_mensual'=>120]]);
  $pending->update(['mapped_payload'=>['monto_mensual'=>90,'fecha_inicio_contrato'=>'2026-01-01']]);
  $effective=app(JusticiaAlternativaEffectivePayloadService::class)->effective($pending->fresh());
  $this->assertSame(120,$effective['monto_mensual']); $this->assertSame('2026-01-01',$effective['fecha_inicio_contrato']);
 }
}
