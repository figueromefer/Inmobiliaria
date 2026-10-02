<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Movimiento;
use App\Models\Propiedad;
use Carbon\Carbon;

class PropertyOperationalStatusService
{
    public function for(Propiedad $propiedad, ?Carbon $date = null): array
    {
        $date ??= Carbon::today();
        $contract = Contrato::query()->with('draftVersion')
            ->where('fk_propiedad', $propiedad->pk_propiedad)
            ->whereDate('fecha_inicio', '<=', $date)
            ->where(function ($q) use ($date) { $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $date); })
            ->orderByDesc('fecha_inicio')->orderByDesc('id')->first();
        if (! $contract) return ['key' => 'desocupada', 'label' => 'Desocupada'];

        $rule = data_get($contract->draftVersion?->canonical_payload, 'term.rent_due_rule.raw_text');
        $day = $this->lastDay($rule) ?? (is_numeric($contract->dias_pago) ? (int) $contract->dias_pago : null);
        if (! $day || $day < 1 || $day > 31) return ['key' => 'sin_regla', 'label' => 'Sin regla de pago'];

        $paid = Movimiento::query()->where('propiedad_id', $propiedad->pk_propiedad)->where('concepto', 'renta')
            ->whereYear('fecha', $date->year)->whereMonth('fecha', $date->month)
            ->where('approval_status', Movimiento::STATUS_APPROVED)
            ->where('estado_pago', '!=', Movimiento::PAYMENT_CANCELED)->exists();
        if ($paid || $date->day <= min($day, $date->daysInMonth)) return ['key' => 'corriente', 'label' => 'Al corriente'];
        return ['key' => 'atrasada', 'label' => 'Atrasada'];
    }

    private function lastDay(?string $rule): ?int
    {
        if (! $rule || ! preg_match_all('/\d{1,2}/', $rule, $matches)) return null;
        $days = array_map('intval', $matches[0]);
        return count($days) <= 2 && max($days) <= 31 ? max($days) : null;
    }
}
