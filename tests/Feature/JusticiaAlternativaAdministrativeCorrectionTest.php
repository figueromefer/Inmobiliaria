<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JusticiaAlternativaAdministrativeCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private ?User $manager = null;

    public function test_ja_has_limited_correction_and_private_contract_keeps_its_actions(): void
    {
        $ja = $this->contract();
        $this->actingAs($this->manager())->get(route('contratos.show', $ja))->assertOk()
            ->assertSee('Corregir datos administrativos')->assertDontSee('Editar contrato')->assertDontSee('Renovar contrato');
        $private = $this->contract(['origen' => 'privado', 'expediente_justicia_alternativa' => null]);
        $this->actingAs($this->manager())->get(route('contratos.show', $private))->assertSee('Editar contrato')->assertSee('Renovar contrato')
            ->assertDontSee('Corregir datos administrativos');
    }

    public function test_manager_corrects_only_final_contract_and_audits_before_after_and_reason(): void
    {
        $contract = $this->contract();
        $pending = ContratoPendiente::create(['origen'=>'justicia_alternativa','external_id'=>'JA-CORRECCION-1','expediente'=>'JA-CORRECCION-1','estado'=>'importado','contrato_id'=>$contract->id,'raw_payload'=>['renta'=>5750],'mapped_payload'=>['monto_mensual'=>5750],'manual_overrides'=>['monto_mensual'=>5750]]);
        $source = $contract->raw_justicia_alternativa;
        $pendingSnapshot = [$pending->raw_payload, $pending->mapped_payload, $pending->manual_overrides];
        $movement = Movimiento::create(['cliente_id'=>$contract->fk_cliente,'propiedad_id'=>$contract->fk_propiedad,'contrato_id'=>$contract->id,'asignado_a_tipo'=>'propiedad','concepto'=>'renta','fecha'=>'2026-03-05','importe'=>5750,'forma_pago'=>'efectivo','approval_status'=>Movimiento::STATUS_APPROVED,'estado_pago'=>Movimiento::PAYMENT_LIQUIDATED,'auto_generated'=>false]);

        $this->actingAs($this->manager())->get(route('contratos.justicia-alternativa.correccion.edit', $contract))->assertSee('movimiento(s) histórico(s)');
        $this->actingAs($this->manager())->put(route('contratos.justicia-alternativa.correccion.update', $contract), $this->payload(['monto_mensual'=>'57500.00','motivo'=>'Error de captura manual al vincular renovación JA.']))
            ->assertRedirect(route('contratos.show', $contract));

        $this->assertSame(57500.0, (float) $contract->fresh()->monto_mensual);
        $this->assertSame($source, $contract->fresh()->raw_justicia_alternativa);
        $this->assertSame($pendingSnapshot, [$pending->fresh()->raw_payload, $pending->fresh()->mapped_payload, $pending->fresh()->manual_overrides]);
        $this->assertSame(5750.0, (float) $movement->fresh()->importe);
        $log = ActivityLog::where('action', 'ja_administrative_correction')->sole();
        $this->assertSame($this->manager()->id, $log->user_id);
        $this->assertSame(5750.0, (float) $log->old_values['monto_mensual']);
        $this->assertSame(57500.0, (float) $log->new_values['monto_mensual']);
        $this->assertSame('Error de captura manual al vincular renovación JA.', $log->new_values['motivo']);
    }

    public function test_correction_screen_explains_that_the_ja_source_is_unchanged(): void
    {
        $this->actingAs($this->manager())->get(route('contratos.justicia-alternativa.correccion.edit', $this->contract()))
            ->assertOk()->assertSee('Esta corrección no modifica el expediente ni la información original recibida de Justicia Alternativa.')
            ->assertSee('Guardar corrección');
    }

    public function test_ja_cannot_use_the_generic_monthly_commission_endpoint(): void
    {
        $contract = $this->contract();
        $this->actingAs($this->manager())->put(route('contratos.comision-mensual.update', $contract), ['comision_mensual' => 15])
            ->assertNotFound();
        $this->assertSame(10.0, (float) $contract->fresh()->comision_mensual);
    }

    public function test_viewer_is_forbidden_and_validation_requires_reason_and_valid_administrative_values(): void
    {
        $contract = $this->contract();
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_VIEWER]))->get(route('contratos.justicia-alternativa.correccion.edit', $contract))->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_VIEWER]))->put(route('contratos.justicia-alternativa.correccion.update', $contract), $this->payload())->assertForbidden();
        $this->actingAs($this->manager())->from(route('contratos.justicia-alternativa.correccion.edit', $contract))
            ->put(route('contratos.justicia-alternativa.correccion.update', $contract), $this->payload(['fecha_inicio'=>'2026-12-31','fecha_fin'=>'2026-01-01','dias_pago'=>32,'monto_mensual'=>-1,'monto_total'=>-1,'monto_deposito'=>-1,'comision_mensual'=>0,'motivo'=>'']))
            ->assertSessionHasErrors(['fecha_fin','dias_pago','monto_mensual','monto_total','monto_deposito','comision_mensual','motivo']);
    }

    private function payload(array $override = []): array { return array_merge(['fecha_inicio'=>'2026-01-01','fecha_fin'=>'2026-12-31','dias_pago'=>5,'monto_mensual'=>'5750.00','monto_total'=>'69000.00','monto_deposito'=>'5750.00','comision_mensual'=>'10.00','domicilio_inmueble'=>'Calle Justicia 10','motivo'=>'Corrección administrativa.'], $override); }
    private function manager(): User { return $this->manager ??= User::factory()->create(['role'=>User::ROLE_AGENT]); }
    private function contract(array $override = []): Contrato { $client=Cliente::create(['nombre'=>'Propietario JA','rfc'=>'XAXX010101000','domicilio'=>'Domicilio']); $property=Propiedad::create(['fk_cliente'=>$client->pk_cliente,'alias'=>'Casa JA '.$client->pk_cliente,'domicilio'=>'Calle Justicia 10']); return Contrato::create(array_merge(['fk_cliente'=>$client->pk_cliente,'fk_propiedad'=>$property->pk_propiedad,'fecha'=>now(),'fecha_inicio'=>'2026-01-01','fecha_fin'=>'2026-12-31','dias_pago'=>5,'monto_mensual'=>5750,'monto_total'=>69000,'monto_deposito'=>5750,'comision_mensual'=>10,'domicilio_inmueble'=>'Calle Justicia 10','origen'=>'justicia_alternativa','expediente_justicia_alternativa'=>'JA-CORRECCION-1','raw_justicia_alternativa'=>['renta'=>5750]], $override)); }
}
