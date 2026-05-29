<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ContratoPendiente;
use App\Models\Inquilino;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FormsIntakeController extends Controller
{
    public function storeContrato(Request $request)
    {
        $payload = $this->normalizePayload($request->all());
        $mapped = $this->mapPrivateContractPayload($payload);

        if (config('services.forms_contratos.mode', 'pending') === 'direct') {
            return $this->storeContratoDirecto($payload, $mapped);
        }

        return $this->storeContratoPendiente($payload, $mapped);
    }

    private function storeContratoPendiente(array $payload, array $mapped)
    {
        $externalId = $payload['external_id']
            ?? $payload['response_id']
            ?? $payload['editUrl']
            ?? $payload['urldoc']
            ?? 'privado-'.sha1(json_encode($payload));

        $pendiente = ContratoPendiente::updateOrCreate(
            [
                'origen' => 'privado',
                'external_id' => $externalId,
            ],
            [
                'expediente' => null,
                'estado' => 'pendiente_match',
                'raw_payload' => $payload,
                'mapped_payload' => $mapped,
            ]
        );

        return response()->json([
            'ok' => true,
            'mode' => 'pending',
            'pendiente_id' => $pendiente->id,
            'message' => 'Contrato recibido y registrado como pendiente de match.',
        ], $pendiente->wasRecentlyCreated ? 201 : 200);
    }

    private function storeContratoDirecto(array $payload, array $mapped)
    {
        $validator = Validator::make($payload, [
            'fk_cliente' => ['nullable', 'integer', 'exists:clientes,pk_cliente'],
            'fk_propiedad' => ['nullable', 'integer', 'exists:propiedades,pk_propiedad'],
            'tipo_solicitante' => ['nullable', 'string'],
            'tipo_complementaria' => ['nullable', 'string'],
            'tipo_tercero' => ['nullable', 'string'],
            'fecha_inicio_contrato' => ['nullable', 'date'],
            'fecha_terminacion_contrato' => ['nullable', 'date', 'after_or_equal:fecha_inicio_contrato'],
            'comision_renta' => ['nullable', 'numeric', 'min:0'],
            'comision_mensual' => ['nullable', 'numeric', 'min:0'],
            'dias_pago' => ['nullable', 'integer', 'min:0'],
            'monto_total' => ['nullable', 'numeric', 'min:0'],
            'monto_mensual' => ['nullable', 'numeric', 'min:0'],
            'monto_deposito' => ['nullable', 'numeric', 'min:0'],
            'editUrl' => ['nullable', 'url'],
            'urldoc' => ['nullable', 'url'],
            'nombre_solicitante' => ['nullable', 'string'],
            'domicilio_inmueble_arrendamiento' => ['nullable', 'string'],
            'nombre_complementaria' => ['nullable', 'string'],
            'nacionalidad_complementaria' => ['nullable', 'string'],
            'domicilio_complementaria' => ['nullable', 'string'],
            'telefono_complementaria' => ['nullable', 'string'],
            'correo_complementaria' => ['nullable', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (empty($data['fk_cliente']) && !empty($data['nombre_solicitante'])) {
            $cliente = Cliente::where('nombre', $data['nombre_solicitante'])->first();
            if ($cliente) $data['fk_cliente'] = $cliente->pk_cliente;
        }

        if (empty($data['fk_propiedad']) && !empty($data['fk_cliente'])) {
            $propQuery = Propiedad::where('fk_cliente', $data['fk_cliente']);
            $prop = null;
            if (!empty($data['domicilio_inmueble_arrendamiento'])) {
                $prop = $propQuery->where('domicilio', 'like', '%'.$data['domicilio_inmueble_arrendamiento'].'%')->first();
            }
            if (!$prop) $prop = $propQuery->first();
            if ($prop) $data['fk_propiedad'] = $prop->pk_propiedad;
        }

        if (empty($data['fk_cliente'])) {
            return response()->json(['ok' => false, 'errors' => ['fk_cliente' => ['No se pudo resolver el cliente.']]], 422);
        }

        $inquilinoId = $data['inquilino_id'] ?? null;
        if (!$inquilinoId && !empty($data['nombre_complementaria'])) {
            $inquilino = Inquilino::create([
                'nombre' => $data['nombre_complementaria'],
                'nacionalidad' => $data['nacionalidad_complementaria'] ?? null,
                'domicilio' => $data['domicilio_complementaria'] ?? null,
                'telefono' => $data['telefono_complementaria'] ?? null,
                'correo' => $data['correo_complementaria'] ?? null,
            ]);
            $inquilinoId = $inquilino->id;
        }

        try {
            $contrato = Contrato::create([
                'fk_cliente' => $data['fk_cliente'] ?? null,
                'fk_propiedad' => $data['fk_propiedad'] ?? null,
                'tipo_solicitante' => $data['tipo_solicitante'] ?? null,
                'tipo_complementaria' => $data['tipo_complementaria'] ?? null,
                'tipo_tercero' => $data['tipo_tercero'] ?? null,
                'fecha' => now(),
                'inquilino_id' => $inquilinoId,
                'domicilio_inmueble' => $data['domicilio_inmueble_arrendamiento'] ?? null,
                'fecha_inicio' => $data['fecha_inicio_contrato'] ?? null,
                'fecha_fin' => $data['fecha_terminacion_contrato'] ?? null,
                'dias_pago' => $data['dias_pago'] ?? null,
                'monto_total' => $data['monto_total'] ?? null,
                'monto_mensual' => $data['monto_mensual'] ?? null,
                'monto_deposito' => $data['monto_deposito'] ?? null,
                'comision_renta' => $data['comision_renta'] ?? null,
                'comision_mensual' => $data['comision_mensual'] ?? null,
                'edit_url' => $data['editUrl'] ?? null,
                'urldoc' => $data['urldoc'] ?? null,
                'origen' => 'privado',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'ok' => true,
            'mode' => 'direct',
            'contrato_id' => $contrato->id,
            'inquilino_id' => $inquilinoId,
        ], 201);
    }

    private function normalizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        foreach (['comision_renta', 'comision_mensual', 'monto_total', 'monto_mensual', 'monto_deposito'] as $field) {
            $payload[$field] = $this->toNumber($payload[$field] ?? null);
        }

        $payload['dias_pago'] = $this->toInt($payload['dias_pago'] ?? null);

        return $payload;
    }

    private function mapPrivateContractPayload(array $payload): array
    {
        return [
            'tipo_solicitante' => $payload['tipo_solicitante'] ?? null,
            'tipo_complementaria' => $payload['tipo_complementaria'] ?? null,
            'tipo_tercero' => $payload['tipo_tercero'] ?? null,
            'nombre_solicitante' => $payload['nombre_solicitante'] ?? $payload['cliente_nombre'] ?? $payload['razon_social_solicitante'] ?? null,
            'rfc_solicitante' => $payload['rfc_solicitante'] ?? $payload['cliente_rfc'] ?? null,
            'correo_solicitante' => $payload['correo_solicitante'] ?? $payload['cliente_correo'] ?? null,
            'telefono_solicitante' => $payload['telefono_solicitante'] ?? $payload['cliente_telefono'] ?? null,
            'domicilio_solicitante' => $payload['domicilio_solicitante'] ?? $payload['cliente_domicilio'] ?? null,
            'propiedad_alias' => $payload['propiedad_alias'] ?? null,
            'propiedad_domicilio' => $payload['propiedad_domicilio'] ?? $payload['domicilio_inmueble_arrendamiento'] ?? null,
            'domicilio_inmueble_arrendamiento' => $payload['domicilio_inmueble_arrendamiento'] ?? $payload['propiedad_domicilio'] ?? null,
            'nombre_complementaria' => $payload['nombre_complementaria'] ?? null,
            'nacionalidad_complementaria' => $payload['nacionalidad_complementaria'] ?? null,
            'domicilio_complementaria' => $payload['domicilio_complementaria'] ?? null,
            'telefono_complementaria' => $payload['telefono_complementaria'] ?? null,
            'correo_complementaria' => $payload['correo_complementaria'] ?? null,
            'fecha_inicio_contrato' => $payload['fecha_inicio_contrato'] ?? null,
            'fecha_terminacion_contrato' => $payload['fecha_terminacion_contrato'] ?? null,
            'dias_pago' => $payload['dias_pago'] ?? null,
            'monto_total' => $payload['monto_total'] ?? null,
            'monto_mensual' => $payload['monto_mensual'] ?? null,
            'monto_deposito' => $payload['monto_deposito'] ?? null,
            'comision_renta' => $payload['comision_renta'] ?? null,
            'comision_mensual' => $payload['comision_mensual'] ?? null,
            'edit_url' => $payload['editUrl'] ?? null,
            'urldoc' => $payload['urldoc'] ?? null,
        ];
    }

    private function toNumber($value): ?float
    {
        if ($value === null || $value === '') return null;

        $value = preg_replace('/[^\d,.\-]/', '', trim((string) $value));
        $commas = substr_count($value, ',');
        $dots = substr_count($value, '.');

        if ($commas && $dots) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');
            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($commas && !$dots) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function toInt($value): ?int
    {
        if ($value === null || $value === '') return null;

        $value = preg_replace('/\D+/', '', (string) $value);

        return $value === '' ? null : (int) $value;
    }
}
