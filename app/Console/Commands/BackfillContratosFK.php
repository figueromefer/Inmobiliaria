<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contrato;
use App\Models\Cliente;
use App\Models\Propiedad;
use Illuminate\Support\Facades\DB;

class BackfillContratosFK extends Command
{
    protected $signature = 'contratos:backfill-fk {--dry-run}';
    protected $description = 'Asigna fk_cliente y fk_propiedad en contratos a partir de solicitante y relaciones conocidas';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $updated = 0; $unmatched = [];

        Contrato::query()->chunkById(500, function($contratos) use (&$updated, &$unmatched, $dry) {
            foreach ($contratos as $c) {
                // 1) por nombre exacto
                $cliente = $c->fk_cliente ? null : Cliente::where('nombre', $c->solicitante)->first();

                if ($cliente && !$c->fk_cliente) {
                    $c->fk_cliente = $cliente->pk_cliente;
                }

                // 2) por propiedad: si ya hay lógica para inferir propiedad (ajústalo a tu realidad)
                if (!$c->fk_propiedad && $c->fk_cliente) {
                    // Buscar la(s) propiedad(es) del cliente
                    $prop = Propiedad::where('fk_cliente', $c->fk_cliente)->first();
                    if ($prop) $c->fk_propiedad = $prop->pk_propiedad;
                }

                if ($c->fk_cliente || $c->fk_propiedad) {
                    if (!$dry) $c->save();
                    $updated++;
                } else {
                    $unmatched[] = $c->id;
                }
            }
        });

        $this->info("Contratos actualizados: $updated");
        if ($unmatched) {
            $this->warn("Sin match para contratos: " . implode(',', $unmatched));
        }
        if ($dry) $this->info('Ejecutado en modo --dry-run (sin guardar).');
        return 0;
    }
}
