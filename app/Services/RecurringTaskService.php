<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Propiedad;
use App\Models\Task;
use Carbon\Carbon;

class RecurringTaskService
{
    public function generateMaintenancePaymentTasks(?Carbon $today = null): int
    {
        $today = ($today ?: now())->startOfDay();
        $created = 0;

        Propiedad::query()
            ->whereNotNull('mantenimiento_fecha_pago')
            ->orderBy('pk_propiedad')
            ->chunkById(100, function ($propiedades) use ($today, &$created) {
                foreach ($propiedades as $propiedad) {
                    $created += $this->generateMaintenanceForProperty($propiedad, $today);
                }
            }, 'pk_propiedad');

        return $created;
    }

    public function generatePropertyTaxTasks(?Carbon $today = null): int
    {
        $today = ($today ?: now())->startOfDay();
        $year = (int) $today->year;
        $dueDate = Carbon::create($year, 1, 1)->startOfDay();
        $periodKey = (string) $year;
        $created = 0;

        if (! $today->isSameDay($dueDate) && ! $today->gt($dueDate)) {
            return 0;
        }

        Propiedad::query()
            ->orderBy('pk_propiedad')
            ->chunkById(100, function ($propiedades) use ($dueDate, $periodKey, &$created) {
                foreach ($propiedades as $propiedad) {
                    if (Task::alreadyExists(Task::TYPE_PROPERTY_TAX, $periodKey, Propiedad::class, $propiedad->pk_propiedad)) {
                        continue;
                    }

                    Task::create([
                        'title' => 'Pagar predial: ' . $propiedad->alias,
                        'description' => 'Pago anual de predial de la propiedad ' . $propiedad->alias . '.',
                        'due_date' => $dueDate->toDateString(),
                        'status' => 'pending',
                        'priority' => 'medium',
                        'task_type' => Task::TYPE_PROPERTY_TAX,
                        'period_key' => $periodKey,
                        'source_type' => Propiedad::class,
                        'source_id' => $propiedad->pk_propiedad,
                        'created_by' => null,
                    ]);

                    $created++;
                }
            }, 'pk_propiedad');

        return $created;
    }

    public function generateContractRenewalTasks(?Carbon $today = null): int
    {
        $today = ($today ?: now())->startOfDay();
        $created = 0;

        Contrato::query()
            ->with(['propiedad', 'inquilino'])
            ->whereNotNull('fecha_fin')
            ->orderBy('id')
            ->chunkById(100, function ($contratos) use ($today, &$created) {
                foreach ($contratos as $contrato) {
                    $created += $this->generateRenewalForContract($contrato, $today);
                }
            });

        return $created;
    }

    protected function generateRenewalForContract(Contrato $contrato, Carbon $today): int
    {
        $endDate = Carbon::parse($contrato->fecha_fin)->startOfDay();
        $reminderDate = $endDate->copy()->subMonthNoOverflow();

        if ($today->lt($reminderDate) || $today->gt($endDate)) {
            return 0;
        }

        $periodKey = $endDate->format('Y-m-d');

        if (Task::alreadyExists(Task::TYPE_CONTRACT_RENEWAL, $periodKey, Contrato::class, $contrato->id)) {
            return 0;
        }

        $propertyLabel = $contrato->propiedad?->alias ?: ($contrato->domicilio_inmueble ?: 'propiedad sin alias');
        $tenantLabel = $contrato->inquilino?->nombre ?: 'inquilino no asignado';

        Task::create([
            'title' => 'Renovar contrato: ' . $propertyLabel,
            'description' => 'El contrato de ' . $tenantLabel . ' finaliza el ' . $endDate->format('d/m/Y') . '.',
            'due_date' => $reminderDate->toDateString(),
            'status' => 'pending',
            'priority' => 'high',
            'task_type' => Task::TYPE_CONTRACT_RENEWAL,
            'period_key' => $periodKey,
            'source_type' => Contrato::class,
            'source_id' => $contrato->id,
            'created_by' => null,
        ]);

        return 1;
    }

    protected function generateMaintenanceForProperty(Propiedad $propiedad, Carbon $today): int
    {
        $paymentDate = Carbon::parse($propiedad->mantenimiento_fecha_pago);
        $paymentDay = (int) $paymentDate->day;

        $created = 0;

        foreach ([0, 1] as $monthOffset) {
            $month = $today->copy()->addMonthsNoOverflow($monthOffset);
            $dueDate = $this->dateForDayInMonth($month, $paymentDay);
            $availableFrom = $dueDate->copy()->subDays(10);

            if ($today->lt($availableFrom) || $today->gt($dueDate)) {
                continue;
            }

            $periodKey = $dueDate->format('Y-m');

            if (Task::alreadyExists(Task::TYPE_MAINTENANCE_PAYMENT, $periodKey, Propiedad::class, $propiedad->pk_propiedad)) {
                continue;
            }

            Task::create([
                'title' => 'Pagar mantenimiento: ' . $propiedad->alias,
                'description' => 'Pago mensual de mantenimiento de la propiedad ' . $propiedad->alias . '.',
                'due_date' => $dueDate->toDateString(),
                'status' => 'pending',
                'priority' => 'medium',
                'task_type' => Task::TYPE_MAINTENANCE_PAYMENT,
                'period_key' => $periodKey,
                'source_type' => Propiedad::class,
                'source_id' => $propiedad->pk_propiedad,
                'created_by' => null,
            ]);

            $created++;
        }

        return $created;
    }

    protected function dateForDayInMonth(Carbon $month, int $day): Carbon
    {
        $date = $month->copy()->startOfMonth();
        $lastDay = (int) $date->copy()->endOfMonth()->day;

        return $date->day(min($day, $lastDay));
    }
}
