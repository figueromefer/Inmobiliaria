<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\Contrato;
use App\Models\Propiedad;
use App\Models\User;
use App\Services\ContractDraftPayload;
use App\Services\ContractDraftVersioningService;
use App\Services\PropertyOperationalStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PropertyOccupancyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_status_and_monthly_report_use_the_same_definitive_contractual_occupancy_rule(): void
    {
        $cliente = Cliente::create(['nombre' => 'Cliente ocupación', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $withoutContract = $this->property($cliente, 'Sin contrato');
        $occupiedWithoutRent = $this->property($cliente, 'Vigente sin renta');
        $future = $this->property($cliente, 'Futura');
        $expired = $this->property($cliente, 'Vencida');
        $draftOnly = $this->property($cliente, 'Sólo borrador');
        $date = Carbon::parse('2026-09-15');

        $this->contract($cliente, $occupiedWithoutRent, '2026-01-01', '2026-12-31');
        $this->contract($cliente, $future, '2026-10-01', '2027-09-30');
        $this->contract($cliente, $expired, '2025-01-01', '2026-08-31');
        $draft = app(ContractDraftVersioningService::class)->createDraft(app(ContractDraftPayload::class)->empty(), ['source' => 'internal']);
        $draft->forceFill(['propiedad_id' => $draftOnly->pk_propiedad])->save();

        $status = app(PropertyOperationalStatusService::class);
        $this->assertSame('desocupada', $status->for($withoutContract, $date)['key']);
        $this->assertNotSame('desocupada', $status->for($occupiedWithoutRent, $date)['key']);
        $this->assertSame('desocupada', $status->for($future, $date)['key']);
        $this->assertSame('desocupada', $status->for($expired, $date)['key']);
        $this->assertSame('desocupada', $status->for($draftOnly, $date)['key']);
        $this->assertNull($draft->fresh()->contrato_id);

        $report = null;
        View::composer('reportes.mensual', function ($view) use (&$report): void { $report = $view->getData(); });
        $this->actingAs(User::factory()->create())->get(route('reportes.mensual', ['cliente_id' => $cliente->pk_cliente, 'mes' => '2026-09']))->assertOk();
        $vacantIds = $report['desocupadas']->pluck('pk_propiedad')->all();
        $this->assertContains($withoutContract->pk_propiedad, $vacantIds);
        $this->assertContains($future->pk_propiedad, $vacantIds);
        $this->assertContains($expired->pk_propiedad, $vacantIds);
        $this->assertContains($draftOnly->pk_propiedad, $vacantIds);
        $this->assertNotContains($occupiedWithoutRent->pk_propiedad, $vacantIds);
    }

    private function property(Cliente $cliente, string $alias): Propiedad
    {
        return Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => $alias]);
    }

    private function contract(Cliente $cliente, Propiedad $propiedad, string $start, string $end): Contrato
    {
        return Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => $start, 'fecha_inicio' => $start, 'fecha_fin' => $end, 'dias_pago' => 31, 'origen' => 'privado']);
    }
}
