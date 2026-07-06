<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeocodingService
{
    public function geocode(?string $address): ?array
    {
        $address = trim((string) $address);

        if ($address === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => $this->userAgent(),
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $this->queryForAddress($address),
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 0,
                ]);

            if (! $response->successful()) {
                Log::warning('Geocoding request failed.', [
                    'status' => $response->status(),
                    'address_hash' => sha1($address),
                ]);

                return null;
            }

            $result = $response->json()[0] ?? null;

            if (! is_array($result) || ! isset($result['lat'], $result['lon'])) {
                return null;
            }

            if (! is_numeric($result['lat']) || ! is_numeric($result['lon'])) {
                return null;
            }

            return [
                'latitud' => (string) $result['lat'],
                'longitud' => (string) $result['lon'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Geocoding request exception.', [
                'address_hash' => sha1($address),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function queryForAddress(string $address): string
    {
        $normalized = Str::of($address)->lower()->ascii()->toString();

        if (Str::contains($normalized, ['jalisco', 'mexico'])) {
            return $address;
        }

        return $address . ', Jalisco, México';
    }

    private function userAgent(): string
    {
        $appName = config('app.name', 'Inmobiliaria');
        $appUrl = config('app.url');

        return trim($appName . '/1.0' . ($appUrl ? ' (' . $appUrl . ')' : ''));
    }
}
