<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Inquilino;
use App\Models\Propiedad;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class JusticiaAlternativaImportService
{
    public function fetchByExpediente(string $expediente): array
    {
        $url = config('services.justicia_alternativa.url');
        $token = config('services.justicia_alternativa.token');
        $timeout = (int) config('services.justicia_alternativa.timeout', 20);

        if (blank($url) || blank($token)) {
            return [
                'ok' => false,
                'status' => 'config_missing',
                'message' => 'Falta configurar JUSTICIA_ALTERNATIVA_WEBAPP_URL o JUSTICIA_ALTERNATIVA_TOKEN.',
            ];
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($url, [
                    'token' => $token,
                    'expediente' => trim($expediente),
                ]);

            if (!$response->successful()) {
                return [
                    'ok' => false,
                    'status' => 'http_error',
                    'message' => 'Error consultando Justicia Alternativa. HTTP '.$response->status(),
                ];
            }

            return $response->json() ?: [
                'ok' => false,
                'status' => 'invalid_json',
                'message' => 'La respuesta de Justicia Alternativa no es JSON válido.',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 'exception',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function resolveMatches(array $mapped): array
    {
        return [
            'cliente' => $this->findCliente($mapped),
            'propiedad' => $this->findPropiedad($mapped),
            'inquilino' => $this->findInquilino($mapped),
        ];
    }

    public function mapPayload(array $row): array
    {
        return [
            'expediente' => $row['Número de expediente:'] ?? null,
            'fecha_firma' => $this->parseDate($row['Fecha de firma de documentación:'] ?? null),
            'tipo_solicitante' => $row['La Parte Solicitante (Arrendador) es:'] ?? null,
            'nombre_solicitante' => $row['Nombre completo de la Parte Solicitante:'] ?? null,
            'correo_solicitante' => $row['Correo electrónico de la Parte Solicitante:'] ?? null,
            'rfc_solicitante' => $row['RFC de la Sociedad de la Parte Solicitante'] ?? null,
            'domicilio_solicitante' => $row['Domicilio completo de la Parte Solicitante:'] ?? null,
            'tipo_complementaria' => $row['La Parte Complementaria (Arrendatario) es:'] ?? null,
            'nombre_complementaria' => $row['Nombre completo de la Parte Complementaria:'] ?? null,
            'nacionalidad_complementaria' => $row['Nacionalidad de la Parte Complementaria:'] ?? null,
            'domicilio_complementaria' => $row['Domicilio completo de la Parte Complementaria:'] ?? null,
            'correo_complementaria' => $row['Correo electrónico de la Parte Complementaria:'] ?? null,
            'telefono_complementaria' => $row['Teléfono de la Parte Complementaria:'] ?? null,
            'domicilio_inmueble_arrendamiento' => $row['Domicilio completo del Inmueble en Arrendamiento'] ?? null,
            'uso_inmueble' => $row['Indica el uso que tendrá el Inmueble en Arrendamiento'] ?? null,
            'fecha_inicio_contrato' => $this->parseDate($row['Fecha de inicio de vigencia del Contrato'] ?? null),
            'fecha_terminacion_contrato' => $this->parseDate($row['Fecha de terminación de vigencia del Contrato'] ?? null),
            'meses_vigencia' => $this->parseInt($row['Meses de vigencia del Contrato'] ?? null),
            'dias_pago' => $this->parseInt($row['Días de pago de la renta'] ?? null),
            'monto_total' => $this->parseMoney($row['Monto por concepto de Renta Total'] ?? null),
            'monto_mensual' => $this->parseMoney($row['Monto por concepto de Renta Mensual'] ?? null),
            'monto_deposito' => $this->parseMoney($row['Monto por concepto de Depósito en Garantía'] ?? null),
            'forma_pago' => $row['Forma de pago'] ?? null,
            'institucion_bancaria' => $row['Institución Bancaria'] ?? null,
            'beneficiario' => $row['Beneficiario'] ?? null,
            'clabe' => $row['CLABE'] ?? null,
            'lleva_iva' => $row['¿Lleva IVA?'] ?? null,
        ];
    }

    private function findCliente(array $mapped): ?Cliente
    {
        if (!empty($mapped['rfc_solicitante'])) {
            $cliente = Cliente::where('rfc', trim($mapped['rfc_solicitante']))->first();
            if ($cliente) return $cliente;
        }

        if (!empty($mapped['correo_solicitante'])) {
            $cliente = Cliente::where('correo', trim($mapped['correo_solicitante']))->first();
            if ($cliente) return $cliente;
        }

        if (!empty($mapped['nombre_solicitante'])) {
            return Cliente::where('nombre', 'like', '%'.$mapped['nombre_solicitante'].'%')->first();
        }

        return null;
    }

    private function findPropiedad(array $mapped): ?Propiedad
    {
        if (empty($mapped['domicilio_inmueble_arrendamiento'])) {
            return null;
        }

        return Propiedad::where('domicilio', 'like', '%'.$mapped['domicilio_inmueble_arrendamiento'].'%')->first();
    }

    private function findInquilino(array $mapped): ?Inquilino
    {
        if (!empty($mapped['correo_complementaria'])) {
            $inquilino = Inquilino::where('correo', trim($mapped['correo_complementaria']))->first();
            if ($inquilino) return $inquilino;
        }

        if (!empty($mapped['nombre_complementaria'])) {
            return Inquilino::where('nombre', 'like', '%'.$mapped['nombre_complementaria'].'%')->first();
        }

        return null;
    }

    private function parseMoney($value): ?float
    {
        if (blank($value)) return null;

        $value = preg_replace('/[^\d,.-]/', '', (string) $value);
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

    private function parseInt($value): ?int
    {
        if (blank($value)) return null;

        $value = preg_replace('/\D+/', '', (string) $value);

        return $value === '' ? null : (int) $value;
    }

    private function parseDate($value): ?string
    {
        if (blank($value)) return null;

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
