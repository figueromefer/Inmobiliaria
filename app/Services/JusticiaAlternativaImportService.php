<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Inquilino;
use App\Models\Propiedad;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
        $tipoSolicitante = $this->get($row, ['La Parte Solicitante (Arrendador) es']);
        $nombreSolicitante = $this->get($row, ['Nombre completo de la Parte Solicitante']);
        $correoSolicitante = $this->get($row, ['Correo electrónico de la Parte Solicitante']);
        $telefonoSolicitante = $this->get($row, ['Teléfono de la Parte Solicitante']);
        $domicilioSolicitante = $this->get($row, ['Domicilio completo de la Parte Solicitante']);

        return [
            'expediente' => $this->get($row, ['Número de expediente']),
            'fecha_firma' => $this->parseDate($this->get($row, ['Fecha de firma de documentación'])),

            'tipo_solicitante' => $tipoSolicitante,
            'nombre_solicitante' => $nombreSolicitante,
            'correo_solicitante' => $correoSolicitante,
            'telefono_solicitante' => $telefonoSolicitante,
            'rfc_solicitante' => $this->get($row, ['RFC de la Sociedad de la Parte Solicitante']),
            'domicilio_solicitante' => $domicilioSolicitante,

            'tipo_complementaria' => $tipoSolicitante,
            'nombre_complementaria' => $nombreSolicitante,
            'nacionalidad_complementaria' => $this->get($row, ['Nacionalidad de la Parte Solicitante']),
            'domicilio_complementaria' => $domicilioSolicitante,
            'correo_complementaria' => $correoSolicitante,
            'telefono_complementaria' => $telefonoSolicitante,

            'fiador_tipo' => $this->get($row, ['La Parte Complementaria']),
            'fiador_nombre' => $this->get($row, ['Nombre completo de la Parte Complementaria']),
            'fiador_nacionalidad' => $this->get($row, ['Nacionalidad de la Parte Complementaria']),
            'fiador_domicilio' => $this->get($row, ['Domicilio completo de la Parte Complementaria']),
            'fiador_correo' => $this->get($row, ['Correo electrónico de la Parte Complementaria']),
            'fiador_telefono' => $this->get($row, ['Teléfono de la Parte Complementaria']),

            'domicilio_inmueble_arrendamiento' => $this->get($row, ['Domicilio completo del Inmueble en Arrendamiento']),
            'uso_inmueble' => $this->get($row, ['Indica el uso que tendrá el Inmueble en Arrendamiento']),
            'fecha_inicio_contrato' => $this->parseDate($this->get($row, ['Fecha de inicio de vigencia del Contrato'])),
            'fecha_terminacion_contrato' => $this->parseDate($this->get($row, ['Fecha de terminación de vigencia del Contrato'])),
            'meses_vigencia' => $this->parseInt($this->get($row, ['Meses de vigencia del Contrato'])),
            'dias_pago' => $this->parseInt($this->get($row, ['Días de pago de la renta'])),
            'monto_total' => $this->parseMoney($this->get($row, ['Monto por concepto de Renta Total'])),
            'monto_mensual' => $this->parseMoney($this->get($row, ['Monto por concepto de Renta Mensual'])),
            'monto_deposito' => $this->parseMoney($this->get($row, ['Monto por concepto de Depósito en Garantía'])),
            'forma_pago' => $this->get($row, ['Forma de pago']),
            'institucion_bancaria' => $this->get($row, ['Institución Bancaria']),
            'beneficiario' => $this->get($row, ['Beneficiario']),
            'clabe' => $this->get($row, ['CLABE']),
            'lleva_iva' => $this->get($row, ['¿Lleva IVA?']),
        ];
    }

    private function get(array $row, array $needles): ?string
    {
        foreach ($needles as $needle) {
            $needleNorm = $this->normalizeHeader($needle);

            foreach ($row as $key => $value) {
                $keyNorm = $this->normalizeHeader($key);

                if ($keyNorm === $needleNorm || Str::contains($keyNorm, $needleNorm)) {
                    $value = is_string($value) ? trim($value) : $value;
                    return blank($value) ? null : (string) $value;
                }
            }
        }

        return null;
    }

    private function findCliente(array $mapped): ?Cliente
    {
        if (!empty($mapped['rfc_solicitante'])) {
            $cliente = Cliente::where('rfc', trim($mapped['rfc_solicitante']))->first();
            if ($cliente) return $cliente;
        }

        $correo = $this->normalizeEmail($mapped['correo_solicitante'] ?? null);
        if ($correo !== '') {
            $cliente = Cliente::all()->first(fn ($cliente) => $this->normalizeEmail($cliente->correo) === $correo);
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
        $correo = $this->normalizeEmail($mapped['correo_complementaria'] ?? null);
        if ($correo !== '') {
            $inquilino = Inquilino::all()->first(fn ($inquilino) => $this->normalizeEmail($inquilino->correo) === $correo);
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

        $value = preg_replace('/[^\d,.\-]/', '', (string) $value);
        if ($value === '' || $value === '-' || $value === '.' || $value === ',') return null;

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
            $parts = explode(',', $value);
            $last = end($parts);

            if ($commas > 1 || strlen($last) === 3) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace(',', '.', $value);
            }
        } elseif ($dots && !$commas) {
            $parts = explode('.', $value);
            $last = end($parts);

            if ($dots > 1 || strlen($last) === 3) {
                $value = str_replace('.', '', $value);
            }
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

        $value = $this->normalizeSpanishDate((string) $value);

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd/m/Y',
            'd-m-Y',
            'm/d/Y',
            'm-d-Y',
            'd/m/y',
            'd-m-y',
            'd F Y',
            'd M Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // probar siguiente formato
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeSpanishDate(string $value): string
    {
        $value = Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        $value = preg_replace('/\bde\b/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        $months = [
            'enero' => 'january',
            'febrero' => 'february',
            'marzo' => 'march',
            'abril' => 'april',
            'mayo' => 'may',
            'junio' => 'june',
            'julio' => 'july',
            'agosto' => 'august',
            'septiembre' => 'september',
            'setiembre' => 'september',
            'octubre' => 'october',
            'noviembre' => 'november',
            'diciembre' => 'december',
        ];

        return strtr($value, $months);
    }

    private function normalizeEmail($value): string
    {
        $email = strtolower(trim((string) $value));

        if (in_array($email, ['', '-', '--', '---', 'n/a', 'na', 'no aplica', 'sin correo', 'sin email'], true)) {
            return '';
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function normalizeHeader($value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
