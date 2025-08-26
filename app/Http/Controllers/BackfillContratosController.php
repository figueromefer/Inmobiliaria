<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contrato;
use App\Models\Cliente;
use App\Models\Propiedad;
use Illuminate\Support\Facades\DB;

class BackfillContratosController extends Controller
{
    // Protege con un token simple en .env (BACKFILL_TOKEN=loquesea)
    public function run(Request $request)
    {
        $token = $request->query('token');
        if (!$token || $token !== config('app.backfill_token')) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $dry = filter_var($request->query('dry', 'true'), FILTER_VALIDATE_BOOLEAN);
        $limit = (int) $request->query('limit', 1000); // por si quieres cortar
        $updated = 0; $unmatched = [];

        Contrato::query()
            ->orderBy('id')
            ->limit($limit)
            ->chunkById(500, function($contratos) use (&$updated, &$unmatched, $dry) {
                foreach ($contratos as $c) {
                    $changed = false;

                    // 1) Por nombre exacto: solicitante ↔ clientes.nombre
                    if (!$c->fk_cliente && $c->solicitante) {
                        $cliente = Cliente::where('nombre', $c->solicitante)->first();
                        if ($cliente) {
                            $c->fk_cliente = $cliente->pk_cliente;
                            $changed = true;
                        }
                    }

                    // 2) Por propiedad a partir del cliente (primera del cliente)
                    if (!$c->fk_propiedad && $c->fk_cliente) {
                        $prop = Propiedad::where('fk_cliente', $c->fk_cliente)->first();
                        if ($prop) {
                            $c->fk_propiedad = $prop->pk_propiedad;
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        if (!$dry) $c->save();
                        $updated++;
                    } else {
                        if (!$c->fk_cliente || !$c->fk_propiedad) {
                            $unmatched[] = $c->id;
                        }
                    }
                }
            });

        return response()->json([
            'ok' => true,
            'dry' => $dry,
            'updated' => $updated,
            'unmatched_ids' => $unmatched,
        ]);
    }
}
