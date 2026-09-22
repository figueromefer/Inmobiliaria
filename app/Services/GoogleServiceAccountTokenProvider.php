<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleServiceAccountTokenProvider
{
    /** @var list<string> */
    private const SCOPES = [
        'https://www.googleapis.com/auth/drive',
        'https://www.googleapis.com/auth/documents',
    ];

    public function accessToken(): string
    {
        return Cache::remember('google-contracts-service-account-token', now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => implode(' ', self::SCOPES),
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsigned = "{$header}.{$claims}";

            if (!openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('No fue posible firmar la autenticación de Google Service Account.');
            }

            $response = Http::asForm()->timeout((int) config('services.google_contracts.timeout', 20))
                ->post($credentials['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => "{$unsigned}.{$this->base64Url($signature)}",
                ]);

            if (!$response->successful() || !is_string($response->json('access_token'))) {
                throw new RuntimeException('Google rechazó la autenticación de la cuenta de servicio.');
            }

            return $response->json('access_token');
        });
    }

    /** @return array{client_email:string,private_key:string,token_uri:string} */
    private function credentials(): array
    {
        $json = config('services.google_contracts.service_account_json');
        $path = config('services.google_contracts.service_account_json_path');
        if (!is_string($json) || $json === '') {
            $json = is_string($path) && $path !== '' && is_readable($path) ? file_get_contents($path) : null;
        }
        if (!is_string($json) || $json === '') {
            throw new RuntimeException('No está configurada la credencial de Google Service Account.');
        }
        try {
            $credentials = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('La credencial de Google Service Account no tiene JSON válido.');
        }
        foreach (['client_email', 'private_key', 'token_uri'] as $key) {
            if (!is_string($credentials[$key] ?? null) || $credentials[$key] === '') {
                throw new RuntimeException('La credencial de Google Service Account está incompleta.');
            }
        }

        return $credentials;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
