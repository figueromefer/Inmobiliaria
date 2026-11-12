<?php

namespace App\Services;

use App\Models\Movimiento;

class MovementBalanceImpactService
{
    public function requiredValue(?string $concepto, ?string $formaPago): ?bool
    {
        if ($concepto === 'pago_cliente_da') return true;
        if ($concepto !== 'renta') return null;

        return match ($formaPago) {
            'efectivo' => true,
            'transferencia' => false,
            default => null,
        };
    }

    public function apply(array $attributes): array
    {
        $required = $this->requiredValue($attributes['concepto'] ?? null, $attributes['forma_pago'] ?? null);
        if ($required !== null) $attributes['afecta_saldo_cliente'] = $required;
        return $attributes;
    }

    public function applyTo(Movimiento $movimiento): void
    {
        $required = $this->requiredValue($movimiento->concepto, $movimiento->forma_pago);
        if ($required !== null) $movimiento->afecta_saldo_cliente = $required;
    }
}
