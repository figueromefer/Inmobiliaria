<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquilino;
use App\Models\Contrato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FormsIntakeController extends Controller
{
    public function storeContrato(Request $request)
    {
        $payload = $request->all();

        // Inquilino (complementaria)
        $inq_nombre        = $payload['nombre_complementaria']       ?? null;
        $inq_nacionalidad  = $payload['nacionalidad_complementaria'] ?? null;
        $inq_domicilio     = $payload['domicilio_complementaria']    ?? null;
        $inq_telefono      = $payload['telefono_complementaria']     ?? null;
        $inq_correo        = $payload['correo_complementaria']       ?? null;

        // Contrato
        $tipo_solicitante    = $payload['tipo_solicitante']            ?? null;
        $tipo_complementaria = $payload['tipo_complementaria']         ?? null;
        $tipo_tercero        = $payload['tipo_tercero']                ?? null;
        $solicitante         = $payload['nombre_solicitante']          ?? null;
        $domicilio_inm       = $payload['domicilio_inmueble_arrendamiento'] ?? null;
        $fecha_inicio        = $payload['fecha_inicio_contrato']       ?? null; // YYYY-MM-DD
        $fecha_fin           = $payload['fecha_terminacion_contrato']  ?? null; // YYYY-MM-DD
        $dias_pago           = $payload['dias_pago']                    ?? null;
        $monto_total         = $payload['monto_total']                  ?? null;
        $monto_mensual       = $payload['monto_mensual']                ?? null;
        $monto_deposito      = $payload['monto_deposito']               ?? null;
        $edit_url            = $payload['editUrl']                      ?? ($payload['edicion'] ?? null);

        $ahora = Carbon::now();

        return DB::transaction(function () use (
            $inq_nombre,$inq_nacionalidad,$inq_domicilio,$inq_telefono,$inq_correo,
            $tipo_solicitante,$tipo_complementaria,$tipo_tercero,$solicitante,$domicilio_inm,
            $fecha_inicio,$fecha_fin,$dias_pago,$monto_total,$monto_mensual,$monto_deposito,$edit_url,$ahora
        ) {
            $inquilinoId = null;
            if ($inq_nombre) {
                $inquilinoId = Inquilino::create([
                    'nombre'       => $inq_nombre,
                    'nacionalidad' => $inq_nacionalidad,
                    'domicilio'    => $inq_domicilio,
                    'telefono'     => $inq_telefono,
                    'correo'       => $inq_correo,
                ])->id;
            }

            $contrato = Contrato::create([
                'tipo_solicitante'    => $tipo_solicitante,
                'tipo_complementaria' => $tipo_complementaria,
                'tipo_tercero'        => $tipo_tercero,
                'solicitante'         => $solicitante,
                'fecha'               => $ahora,
                'inquilino_id'        => $inquilinoId,
                'domicilio_inmueble'  => $domicilio_inm,
                'fecha_inicio'        => $fecha_inicio ?: null,
                'fecha_fin'           => $fecha_fin ?: null,
                'dias_pago'           => $dias_pago ?: null,
                'monto_total'         => $monto_total ?: null,
                'monto_mensual'       => $monto_mensual ?: null,
                'monto_deposito'      => $monto_deposito ?: null,
                'edit_url'            => $edit_url,
            ]);

            return response()->json([
                'ok'           => true,
                'contrato_id'  => $contrato->id,
                'inquilino_id' => $inquilinoId,
            ], 201);
        });
    }
}
