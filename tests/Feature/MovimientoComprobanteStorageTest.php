<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovimientoComprobanteStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        config()->set('movimientos.comprobantes.disk', 'r2');
        Storage::fake('r2');
        Storage::fake('public');
    }

    public function test_valid_pdf_is_stored_in_the_configured_r2_disk_with_metadata(): void
    {
        $response = $this->actingAs($this->agent())->post(route('movimientos.store'), $this->payload([
            'comprobante' => UploadedFile::fake()->create('recibo.pdf', 128, 'application/pdf'),
        ]));

        $response->assertRedirect(route('movimientos.index'));
        $movimiento = Movimiento::query()->latest('id')->firstOrFail();

        $this->assertSame('r2', $movimiento->comprobante_disk);
        $this->assertSame('recibo.pdf', $movimiento->comprobante_nombre_original);
        $this->assertSame('application/pdf', $movimiento->comprobante_mime);
        $this->assertSame(131072, $movimiento->comprobante_size);
        $this->assertMatchesRegularExpression('#^movimientos/comprobantes/\d{4}/\d{2}/[0-9a-f-]{36}\.pdf$#', $movimiento->comprobante);
        Storage::disk('r2')->assertExists($movimiento->comprobante);
    }

    public function test_jpg_and_png_are_accepted(): void
    {
        foreach ([['foto.jpg', 'image/jpeg'], ['foto.png', 'image/png']] as [$name, $mime]) {
            $this->actingAs($this->agent())->post(route('movimientos.store'), $this->payload([
                'comprobante' => UploadedFile::fake()->create($name, 16, $mime),
            ]))->assertRedirect(route('movimientos.index'));
        }

        $this->assertSame(2, Movimiento::query()->whereNotNull('comprobante')->count());
    }

    public function test_file_of_exactly_50_mib_is_accepted(): void
    {
        $this->actingAs($this->agent())
            ->post(route('movimientos.store'), $this->payload([
                'comprobante' => UploadedFile::fake()->create('maximo.pdf', 51200, 'application/pdf'),
            ]))
            ->assertRedirect(route('movimientos.index'));
    }

    public function test_file_larger_than_50_mib_and_disallowed_type_are_rejected(): void
    {
        $this->actingAs($this->agent())
            ->from(route('movimientos.create'))
            ->post(route('movimientos.store'), $this->payload([
                'comprobante' => UploadedFile::fake()->create('grande.pdf', 51201, 'application/pdf'),
            ]))
            ->assertRedirect(route('movimientos.create'))
            ->assertSessionHasErrors('comprobante');

        $this->actingAs($this->agent())
            ->from(route('movimientos.create'))
            ->post(route('movimientos.store'), $this->payload([
                'comprobante' => UploadedFile::fake()->create('malicioso.txt', 10, 'text/plain'),
            ]))
            ->assertRedirect(route('movimientos.create'))
            ->assertSessionHasErrors('comprobante');
    }

    public function test_comprobante_download_requires_authentication_and_historical_public_file_remains_available(): void
    {
        $movimiento = $this->movement([
            'comprobante' => 'comprobantes/historico.pdf',
            'comprobante_disk' => 'public',
            'comprobante_nombre_original' => 'histórico.pdf',
            'comprobante_mime' => 'application/pdf',
        ]);
        Storage::disk('public')->put($movimiento->comprobante, 'contenido histórico');

        $this->get(route('movimientos.comprobante', $movimiento))->assertRedirect(route('login'));
        $this->actingAs($this->agent())->get(route('movimientos.comprobante', $movimiento))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_r2_comprobante_download_uses_the_r2_disk(): void
    {
        $movimiento = $this->movement([
            'comprobante' => 'movimientos/comprobantes/2026/09/r2.pdf',
            'comprobante_disk' => 'r2',
            'comprobante_nombre_original' => 'r2.pdf',
            'comprobante_mime' => 'application/pdf',
        ]);
        Storage::disk('r2')->put($movimiento->comprobante, 'contenido R2');

        $this->actingAs($this->agent())->get(route('movimientos.comprobante', $movimiento))
            ->assertRedirect();
    }

    public function test_replacing_a_comprobante_deletes_the_old_file_only_after_update(): void
    {
        $movimiento = $this->movement([
            'comprobante' => 'comprobantes/anterior.pdf',
            'comprobante_disk' => 'public',
        ]);
        Storage::disk('public')->put($movimiento->comprobante, 'anterior');

        $this->actingAs($this->agent())->put(route('movimientos.update', $movimiento), $this->payload([
            'comprobante' => UploadedFile::fake()->create('nuevo.pdf', 20, 'application/pdf'),
        ]))->assertRedirect(route('movimientos.index'));

        $movimiento->refresh();
        Storage::disk('public')->assertMissing('comprobantes/anterior.pdf');
        Storage::disk('r2')->assertExists($movimiento->comprobante);
    }

    public function test_deleting_a_movement_deletes_its_comprobante_from_its_stored_disk(): void
    {
        $movimiento = $this->movement([
            'comprobante' => 'movimientos/comprobantes/2026/09/borrar.pdf',
            'comprobante_disk' => 'r2',
        ]);
        Storage::disk('r2')->put($movimiento->comprobante, 'borrar');

        $this->actingAs($this->admin())->delete(route('movimientos.destroy', $movimiento))
            ->assertRedirect(route('movimientos.index'));

        Storage::disk('r2')->assertMissing('movimientos/comprobantes/2026/09/borrar.pdf');
        $this->assertDatabaseMissing('movimientos', ['id' => $movimiento->id]);
    }

    public function test_viewer_does_not_gain_management_access(): void
    {
        $movimiento = $this->movement();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->put(route('movimientos.update', $movimiento), $this->payload())
            ->assertForbidden();
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => User::ROLE_AGENT]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function movement(array $overrides = []): Movimiento
    {
        $cliente = $this->cliente();

        return Movimiento::create(array_merge([
            'cliente_id' => $cliente->pk_cliente,
            'asignado_a_tipo' => 'cliente',
            'folio' => 'MOV-000001',
            'concepto' => 'renta',
            'fecha' => '2026-09-01',
            'importe' => '1000.00',
            'forma_pago' => 'transferencia',
            'approval_status' => Movimiento::STATUS_APPROVED,
            'estado_pago' => Movimiento::PAYMENT_LIQUIDATED,
            'fecha_liquidacion' => '2026-09-01',
            'afecta_saldo_cliente' => true,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        $cliente = Cliente::query()->first() ?: $this->cliente();

        return array_merge([
            'asignado_a_tipo' => 'cliente',
            'cliente_id' => $cliente->pk_cliente,
            'concepto' => 'renta',
            'fecha' => '2026-09-01',
            'importe' => '1000.00',
            'forma_pago' => 'transferencia',
            'estado_pago' => Movimiento::PAYMENT_LIQUIDATED,
            'fecha_liquidacion' => '2026-09-01',
            'afecta_saldo_cliente' => '1',
        ], $overrides);
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'nombre' => 'Cliente de comprobantes',
            'rfc' => 'XAXX010101000',
            'domicilio' => 'Domicilio de prueba',
        ]);
    }
}
