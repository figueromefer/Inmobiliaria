<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquilino;
use App\Models\Contrato;
use App\Models\Propiedad;
use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FormsIntakeController extends Controller
{
    public function storeContrato(Request $request)
    {
        $payload = $request->all();

        // 0) Convertir "" a null (muy importante para 'nullable')
        foreach ($payload as $k => $v) {
            if ($v === '') $payload[$k] = null;
        }

        // Helpers
        $toNumber = function($v) {
            if ($v === null || $v === '') return null;
            $v = preg_replace('/[^\d,.\-]/', '', trim((string)$v));
            $commas = substr_count($v, ',');
            $dots   = substr_count($v, '.');
            if ($commas && $dots) {
                $lastComma = strrpos($v, ','); $lastDot = strrpos($v, '.');
                if ($lastComma > $lastDot) { $v = str_replace('.', '', $v); $v = str_replace(',', '.', $v); }
                else { $v = str_replace(',', '', $v); }
            } elseif ($commas && !$dots) {
                $v = str_replace(',', '.', $v);
            }
            return is_numeric($v) ? (float)$v : null;
        };
        $toInt = function($v) {
            if ($v === null || $v === '') return null;
            $v = preg_replace('/\D+/', '', (string)$v);
            return $v === '' ? null : (int)$v;
        };

        // 1) Normaliza numéricos
        $payload['comision_renta']   = $toNumber($payload['comision_renta']   ?? null);
        $payload['comision_mensual'] = $toNumber($payload['comision_mensual'] ?? null);
        $payload['monto_total']      = $toNumber($payload['monto_total']      ?? null);
        $payload['monto_mensual']    = $toNumber($payload['monto_mensual']    ?? null);
        $payload['monto_deposito']   = $toNumber($payload['monto_deposito']   ?? null);
        $payload['dias_pago']        = $toInt(   $payload['dias_pago']        ?? null);

        // 1.1) % a fracción
        if ($payload['comision_mensual'] !== null && str_contains((string)$request->input('comision_mensual'), '%')) {
            // Si viene con %, simplemente quitamos el símbolo pero no dividimos entre 100
            $payload['comision_mensual'] = $payload['comision_mensual'] * 1;
        }

        // 2) Validación con nombres REALES del payload
        $v = \Validator::make($payload, [
            'fk_cliente'   => ['nullable','integer','exists:clientes,pk_cliente'],
            'fk_propiedad' => ['nullable','integer','exists:propiedades,pk_propiedad'],

            'tipo_solicitante'     => ['nullable','string'],
            'tipo_complementaria'  => ['nullable','string'],
            'tipo_tercero'         => ['nullable','string'],

            'fecha_inicio_contrato'      => ['nullable','date'],
            'fecha_terminacion_contrato' => ['nullable','date','after_or_equal:fecha_inicio_contrato'],

            'comision_renta'   => ['nullable','numeric','min:0'],
            'comision_mensual' => ['nullable','numeric','min:0'],
            'dias_pago'        => ['nullable','integer','min:0'],
            'monto_total'      => ['nullable','numeric','min:0'],
            'monto_mensual'    => ['nullable','numeric','min:0'],
            'monto_deposito'   => ['nullable','numeric','min:0'],

            'editUrl'          => ['nullable','url'],
            'urldoc'          => ['nullable','url'],

            'nombre_solicitante'               => ['nullable','string'],
            'domicilio_inmueble_arrendamiento' => ['nullable','string'],

            'nombre_complementaria'       => ['nullable','string'],
            'nacionalidad_complementaria' => ['nullable','string'],
            'domicilio_complementaria'    => ['nullable','string'],
            'telefono_complementaria'     => ['nullable','string'],
            'correo_complementaria'       => ['nullable','email'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        // 3) Fallbacks FKs
        if (empty($data['fk_cliente']) && !empty($data['nombre_solicitante'])) {
            $cliente = \App\Models\Cliente::where('nombre', $data['nombre_solicitante'])->first();
            if ($cliente) $data['fk_cliente'] = $cliente->pk_cliente;
        }

        if (empty($data['fk_propiedad']) && !empty($data['fk_cliente'])) {
            $propQuery = \App\Models\Propiedad::where('fk_cliente', $data['fk_cliente']);
            $prop = null;
            if (!empty($data['domicilio_inmueble_arrendamiento'])) {
                $prop = $propQuery->where('domicilio','like','%'.$data['domicilio_inmueble_arrendamiento'].'%')->first();
            }
            if (!$prop) $prop = $propQuery->first();
            if ($prop) $data['fk_propiedad'] = $prop->pk_propiedad;
        }

        // Si BD exige NOT NULL en fk_cliente/fk_propiedad, corta bonito:
        if (empty($data['fk_cliente'])) {
            return response()->json(['ok'=>false,'errors'=>['fk_cliente'=>['No se pudo resolver el cliente.']]], 422);
        }
        // if (empty($data['fk_propiedad'])) { return response()->json([...], 422); }

        // 4) Inquilino
        $inquilinoId = $data['inquilino_id'] ?? null;
        $nombreInquilino = $data['nombre_complementaria'] ?? null;
        $solicitudId  = null;
        $solicitudUrl = null;

        if (!empty($nombreInquilino)) {
            // Construir la URL de consulta codificando el nombre
            $url = 'http://dainvestigaciones.com/Sistema/getsol.php?nombre=' . urlencode($nombreInquilino);

            // Realizar la solicitud GET y decodificar la respuesta (JSON)
            // file_get_contents() puede leer documentos remotos y json_decode() lo convierte en un array u objeto:contentReference[oaicite:2]{index=2}
            $response = @file_get_contents($url);
            if ($response !== false) {
                $jsonData = json_decode($response, true);
                // Comprobamos si la respuesta indica éxito y tiene 'id'
                if (!empty($jsonData['success']) && !empty($jsonData['id'])) {
                    $solicitudId  = $jsonData['id'];
                    // Armamos la URL al reporte de resultados
                    $solicitudUrl = 'https://dainvestigaciones.com/Sistema/view/reporte_resultados.php?id=' . $solicitudId;
                }
            }
        }


        if (!$inquilinoId && !empty($data['nombre_complementaria'])) {
            $inq = \App\Models\Inquilino::create([
                'nombre'        => $data['nombre_complementaria'],
                'nacionalidad'  => $data['nacionalidad_complementaria'] ?? null,
                'domicilio'     => $data['domicilio_complementaria']    ?? null,
                'telefono'      => $data['telefono_complementaria']     ?? null,
                'correo'        => $data['correo_complementaria']       ?? null,
                'solicitud_id'  => $solicitudId                         ?? null,
                'solicitud_url' => $solicitudUrl                        ?? null,
            ]);
            $inquilinoId = $inq->id;
        }

        try {
            $contrato = \App\Models\Contrato::create([
                'fk_cliente'        => $data['fk_cliente'] ?? null,          // <- usa ?? null
                'fk_propiedad'      => $data['fk_propiedad'] ?? null,
                'tipo_solicitante'  => $data['tipo_solicitante']     ?? null,
                'tipo_complementaria'=> $data['tipo_complementaria'] ?? null,
                'tipo_tercero'      => $data['tipo_tercero']         ?? null,
                'fecha'             => now(),
                'inquilino_id'      => $inquilinoId,
                'domicilio_inmueble'=> $data['domicilio_inmueble_arrendamiento'] ?? null,
                'fecha_inicio'      => $data['fecha_inicio_contrato']            ?? null,
                'fecha_fin'         => $data['fecha_terminacion_contrato']       ?? null,
                'dias_pago'         => $data['dias_pago']                        ?? null,
                'monto_total'       => $data['monto_total']                      ?? null,
                'monto_mensual'     => $data['monto_mensual']                    ?? null,
                'monto_deposito'    => $data['monto_deposito']                   ?? null,
                'comision_renta'    => $data['comision_renta']                   ?? null,
                'comision_mensual'  => $data['comision_mensual']                 ?? null,
                'edit_url'          => $data['editUrl']                          ?? null,
                'urldoc'            => $data['urldoc']                          ?? null,
            ]);
        } catch (\Throwable $e) {
            // TEMPORAL: te devuelve el mensaje para ubicar el 500 exacto
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }

        return response()->json([
            'ok'           => true,
            'contrato_id'  => $contrato->id,
            'inquilino_id' => $inquilinoId,
        ], 201);
    }

}
