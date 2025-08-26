<?php

namespace App\Http\Controllers\Api;

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
        $v = Validator::make($payload, [
            'fk_cliente'   => ['nullable','integer','exists:clientes,pk_cliente'],
            'fk_propiedad' => ['nullable','integer','exists:propiedades,pk_propiedad'],
            'tipo_solicitante'     => ['nullable','string'],
            'tipo_complementaria'  => ['nullable','string'],
            'tipo_tercero'         => ['nullable','string'],
            'solicitante'          => ['nullable','string'],
            'fecha_inicio'         => ['nullable','date'],
            'fecha_fin'            => ['nullable','date','after_or_equal:fecha_inicio'],
            'comision_renta'       => ['nullable','numeric','min:0'],
            'comision_mensual'     => ['nullable','numeric','min:0'],
            'dias_pago'            => ['nullable','integer','min:0'],
            'monto_total'          => ['nullable','numeric','min:0'],
            'monto_mensual'        => ['nullable','numeric','min:0'],
            'monto_deposito'       => ['nullable','numeric','min:0'],
            'edit_url'             => ['nullable','url'],
            'inquilino_id'         => ['nullable','integer','exists:inquilinos,id'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        // 2) Resolver FKs si no se proporcionan
        if (empty($data['fk_cliente']) && !empty($payload['nombre_solicitante'])) {
            $cliente = Cliente::where('nombre', $payload['nombre_solicitante'])->first();
            if ($cliente) {
                $data['fk_cliente'] = $cliente->pk_cliente;
            }
        }

        if (empty($data['fk_propiedad']) && !empty($data['fk_cliente'])) {
            $propQuery = Propiedad::where('fk_cliente', $data['fk_cliente']);

            // Opción 1: buscar por domicilio del inmueble si lo tienes en el payload
            if (!empty($payload['domicilio_inmueble_arrendamiento'])) {
                $prop = $propQuery
                    ->where('domicilio', 'like', '%' . $payload['domicilio_inmueble_arrendamiento'] . '%')
                    ->first();
            }
            // Opción 2: tomar la primera propiedad del cliente
            if (empty($prop)) {
                $prop = $propQuery->first();
            }

            if ($prop) {
                $data['fk_propiedad'] = $prop->pk_propiedad;
            }
        }

        // 3) Crear o asignar Inquilino (igual que ahora)
        $inquilinoId = null;
        if (!empty($payload['nombre_complementaria'])) {
            $inquilino = Inquilino::create([
                'nombre'       => $payload['nombre_complementaria'],
                'nacionalidad' => $payload['nacionalidad_complementaria'],
                'domicilio'    => $payload['domicilio_complementaria'],
                'telefono'     => $payload['telefono_complementaria'],
                'correo'       => $payload['correo_complementaria'],
            ]);
            $inquilinoId = $inquilino->id;
        }

        // 4) Crear el contrato con las FKs nuevas
        $contrato = Contrato::create([
            'fk_cliente'   => $data['fk_cliente'],
            'fk_propiedad' => $data['fk_propiedad'],
            'tipo_solicitante'    => $payload['tipo_solicitante']    ?? null,
            'tipo_complementaria' => $payload['tipo_complementaria'] ?? null,
            'tipo_tercero'        => $payload['tipo_tercero']        ?? null,
            'solicitante'         => $payload['nombre_solicitante']  ?? null,
            'fecha'               => now(),
            'inquilino_id'        => $inquilinoId,
            'domicilio_inmueble'  => $payload['domicilio_inmueble_arrendamiento'] ?? null,
            'fecha_inicio'        => $payload['fecha_inicio_contrato']            ?? null,
            'fecha_fin'           => $payload['fecha_terminacion_contrato']       ?? null,
            'dias_pago'           => $payload['dias_pago']                        ?? null,
            'monto_total'         => $payload['monto_total']                      ?? null,
            'monto_mensual'       => $payload['monto_mensual']                    ?? null,
            'monto_deposito'      => $payload['monto_deposito']                   ?? null,
            'edit_url'            => $payload['editUrl']                          ?? ($payload['edicion'] ?? null),
        ]);

        return response()->json([
            'ok'           => true,
            'contrato_id'  => $contrato->id,
            'inquilino_id' => $inquilinoId,
        ], 201);
    }
}
