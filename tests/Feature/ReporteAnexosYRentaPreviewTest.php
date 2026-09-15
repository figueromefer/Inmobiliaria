<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Movimiento;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReporteAnexosYRentaPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_renta_preview_returns_amount_only_for_a_current_contract_of_the_property(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'fecha' => now(), 'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay(), 'monto_mensual' => 12500]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('movimientos.renta-vigente', $propiedad))
            ->assertOk()
            ->assertJsonPath('monto_mensual', 12500);

        $this->actingAs(User::factory()->create())
            ->getJson(route('movimientos.renta-vigente', 99999))
            ->assertOk()
            ->assertJsonPath('monto_mensual', null);
    }

    public function test_renta_preview_returns_amount_for_current_tenant_contract_or_null_without_one(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $inquilino = Inquilino::create(['nombre' => 'Inquilino anexos']);
        Contrato::create(['fk_cliente' => $cliente->pk_cliente, 'fk_propiedad' => $propiedad->pk_propiedad, 'inquilino_id' => $inquilino->id, 'fecha' => now(), 'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay(), 'monto_mensual' => 9800]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('movimientos.inquilino-renta-vigente', $inquilino))
            ->assertOk()->assertJsonPath('monto_mensual', 9800);
        $this->actingAs(User::factory()->create())
            ->getJson(route('movimientos.inquilino-renta-vigente', 99999))
            ->assertOk()->assertJsonPath('monto_mensual', null);
    }

    public function test_movement_form_includes_informational_rent_preview_and_global_file_upload_indicator(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('movimientos.create'));
        $response->assertOk()
            ->assertSee('renta-vigente-preview')
            ->assertSee('actualizarRentaVigente')
            ->assertSee('movimientos/inquilinos', false)
            ->assertSee('searchable-selects:ready', false)
            ->assertSee('Cargando archivo… no cierres esta página.', false)
            ->assertSee('input[type="file"]', false)
            ->assertSee('event.preventDefault();', false)
            ->assertSee('form.requestSubmit()', false);

        $this->assertSame(2, substr_count($response->getContent(), 'requestAnimationFrame(function () {'));
        $this->assertMatchesRegularExpression(
            '/requestAnimationFrame\(function \(\) \{\s*requestAnimationFrame\(function \(\) \{\s*form\.requestSubmit\(\);/s',
            $response->getContent()
        );
        $this->assertStringNotContainsString('fileUploadSubmitting', file_get_contents(resource_path('js/app.js')));
    }

    public function test_zip_contains_report_pdf_index_and_only_report_movement_receipt(): void
    {
        Storage::fake('public');
        [$cliente, $propiedad] = $this->clientAndProperty();
        $included = $this->movement($cliente, $propiedad, 'MOV-INCLUIDO', now()->startOfMonth()->toDateString(), 'comprobantes/incluido.pdf');
        $excluded = $this->movement($cliente, $propiedad, 'MOV-EXCLUIDO', now()->subMonth()->startOfMonth()->toDateString(), 'comprobantes/excluido.pdf');
        Storage::disk('public')->put($included->comprobante, 'comprobante incluido');
        Storage::disk('public')->put($excluded->comprobante, 'comprobante excluido');

        $response = $this->actingAs(User::factory()->create())->get(route('reportes.mensual.anexos', ['cliente_id' => $cliente->pk_cliente, 'mes' => now()->format('Y-m')]));
        $response->assertOk();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $this->assertNotFalse($zip->locateName('indice-anexos.txt'));
        $this->assertNotFalse($zip->locateName('reporte_mensual_cliente_anexos_'.now()->format('Y-m').'.pdf'));
        $index = $zip->getFromName('indice-anexos.txt');
        $this->assertStringContainsString('MOV-INCLUIDO', $index);
        $this->assertStringNotContainsString('MOV-EXCLUIDO', $index);
        $this->assertStringContainsString('comprobante incluido', $zip->getFromName('comprobantes/incluido.pdf'));
        $zip->close();
    }

    public function test_missing_receipt_is_reported_in_index_and_guest_cannot_download_zip(): void
    {
        [$cliente, $propiedad] = $this->clientAndProperty();
        $this->movement($cliente, $propiedad, 'MOV-FALTA', now()->startOfMonth()->toDateString(), 'comprobantes/falta.pdf');
        $url = route('reportes.mensual.anexos', ['cliente_id' => $cliente->pk_cliente, 'mes' => now()->format('Y-m')]);
        $this->get($url)->assertRedirect(route('login'));
        $response = $this->actingAs(User::factory()->create())->get($url);
        $zip = new \ZipArchive();
        $zip->open($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('No se pudo leer el comprobante.', $zip->getFromName('indice-anexos.txt'));
        $zip->close();
    }

    private function clientAndProperty(): array
    {
        $cliente = Cliente::create(['nombre' => 'Cliente anexos', 'rfc' => 'XAXX010101000', 'domicilio' => 'Domicilio']);
        $propiedad = Propiedad::create(['fk_cliente' => $cliente->pk_cliente, 'alias' => 'Propiedad anexos', 'domicilio' => 'Domicilio propiedad']);
        return [$cliente, $propiedad];
    }

    private function movement(Cliente $cliente, Propiedad $propiedad, string $folio, string $fecha, string $comprobante): Movimiento
    {
        return Movimiento::create(['cliente_id' => $cliente->pk_cliente, 'propiedad_id' => $propiedad->pk_propiedad, 'asignado_a_tipo' => 'propiedad', 'folio' => $folio, 'concepto' => 'renta', 'fecha' => $fecha, 'importe' => 1000, 'forma_pago' => 'transferencia', 'approval_status' => Movimiento::STATUS_APPROVED, 'estado_pago' => Movimiento::PAYMENT_LIQUIDATED, 'fecha_liquidacion' => $fecha, 'afecta_saldo_cliente' => true, 'comprobante' => $comprobante, 'comprobante_disk' => 'public', 'comprobante_nombre_original' => basename($comprobante)]);
    }
}
