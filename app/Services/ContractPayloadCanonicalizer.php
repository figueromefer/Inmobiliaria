<?php

namespace App\Services;

use JsonException;

class ContractPayloadCanonicalizer
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function canonicalize(array $payload): array
    {
        /** @var array<string, mixed> $canonical */
        $canonical = $this->canonicalizeValue($payload);

        return $canonical;
    }

    /** @param array<string, mixed> $canonicalPayload */
    public function encodeCanonical(array $canonicalPayload): string
    {
        try {
            return json_encode(
                $canonicalPayload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('El DTO canónico no se puede serializar.', 0, $exception);
        }
    }

    /** @param array<string, mixed> $canonicalPayload */
    public function hashCanonical(array $canonicalPayload): string
    {
        return hash('sha256', $this->encodeCanonical($canonicalPayload));
    }

    /** @param array<string, mixed> $payload */
    public function hash(array $payload): string
    {
        return $this->hashCanonical($this->canonicalize($payload));
    }

    private function canonicalizeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new \InvalidArgumentException('El DTO canónico contiene un número no finito.');
            }

            return $value;
        }

        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('El DTO canónico contiene texto UTF-8 inválido.');
            }

            return $value;
        }

        if (! is_array($value)) {
            throw new \InvalidArgumentException('El DTO canónico contiene un tipo de valor no admitido.');
        }

        $kind = $this->arrayKind($value);
        if ($kind === 'list') {
            return array_map(fn (mixed $item): mixed => $this->canonicalizeValue($item), $value);
        }

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[(string) $key] = $this->canonicalizeValue($item);
        }
        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    private function arrayKind(array $value): string
    {
        if ($value === []) {
            return 'list';
        }

        $keys = array_keys($value);
        $integerKeys = array_filter($keys, 'is_int');

        if (count($integerKeys) === count($keys)) {
            if ($keys !== range(0, count($keys) - 1)) {
                throw new \InvalidArgumentException('El DTO canónico contiene una lista numérica no densa.');
            }

            return 'list';
        }

        if ($integerKeys !== []) {
            throw new \InvalidArgumentException('El DTO canónico contiene claves mixtas ambiguas.');
        }

        foreach ($keys as $key) {
            if ($key === '' || preg_match('//u', $key) !== 1) {
                throw new \InvalidArgumentException('El DTO canónico contiene una clave asociativa inválida.');
            }
        }

        return 'object';
    }
}
