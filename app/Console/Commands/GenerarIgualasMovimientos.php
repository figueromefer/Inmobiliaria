<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Contrato;
use App\Models\Movimiento;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class GenerarIgualasMovimientos extends Command
{
    protected $signature = 'movimientos:generar-igualas
        {--desde= : Mes inicial en formato YYYY-MM}
        {--hasta= : Mes final en formato YYYY-MM}
        {--dry-run : Muestra igualas sugeridas sin guardar}
        {--confirm : Crea movimientos de iguala}
        {--fecha=renta : Fecha del movimiento: renta o fin-mes}';

    protected $description = 'Sugiere o genera movimientos de iguala a partir de rentas aprobadas y reglas legacy de contratos.';

    private const SUSPICIOUS_TERMS = [
        'iguala',
        'comision',
        'comisión',
        'honorario',
        'administracion',
        'administración',
    ];

    public function handle(): int
    {
        if ($this->option('confirm') && $this->option('dry-run')) {
            $this->error('Usa solo una opción: --dry-run o --confirm.');

            return self::FAILURE;
        }

        $dryRun = ! $this->option('confirm');
        $fechaMode = (string) $this->option('fecha');

        if (! in_array($fechaMode, ['renta', 'fin-mes'], true)) {
            $this->error('La opción --fecha solo acepta: renta o fin-mes.');

            return self::FAILURE;
        }

        [$inicio, $fin] = $this->parseRange();

        if (! $inicio || ! $fin) {
            return self::FAILURE;
        }

        if ($dryRun && ! $this->option('dry-run')) {
            $this->warn('No se indicó --confirm. Ejecutando en modo dry-run por seguridad.');
        }

        $this->info(($dryRun ? 'DRY-RUN' : 'CONFIRM') . " igualas {$inicio->format('Y-m')} a {$fin->format('Y-m')}");

        $summary = [
            'crear' => 0,
            'creadas' => 0,
            'omitidas' => 0,
            'ambiguo' => 0,
            'sin_contrato' => 0,
            'sin_comision' => 0,
            'duplicado' => 0,
            'sospechoso' => 0,
            'liquidadas' => 0,
            'pendientes' => 0,
        ];
        $rows = [];

        $this->rentasQuery($inicio, $fin)->chunkById(200, function ($rentas) use (&$summary, &$rows, $dryRun, $fechaMode) {
            foreach ($rentas as $renta) {
                $result = $this->evaluateRenta($renta, $fechaMode);

                if ($result['renta_estado'] === Movimiento::PAYMENT_PENDING) {
                    $summary['pendientes']++;
                } else {
                    $summary['liquidadas']++;
                }

                if ($result['action'] === 'crear') {
                    $summary['crear']++;

                    if (! $dryRun) {
                        $this->createIguala($result);
                        $summary['creadas']++;
                    }
                } else {
                    $summary[$result['action']] = ($summary[$result['action']] ?? 0) + 1;
                    $summary['omitidas']++;
                }

                $rows[] = [
                    'accion' => $result['action'],
                    'cliente' => $result['cliente'],
                    'propiedad' => $result['propiedad'],
                    'contrato' => $result['contrato_id'] ? '#' . $result['contrato_id'] : '—',
                    'renta' => $result['renta_folio'],
                    'fecha' => $result['fecha_renta'],
                    'renta $' => number_format($result['importe_renta'], 2),
                    'mensual $' => number_format($result['comision_mensual_calculada'], 2),
                    'renta fija $' => number_format($result['comision_renta_aplicada'], 2),
                    'iguala $' => number_format($result['total_iguala'], 2),
                    'estado' => $result['renta_estado'],
                    'motivo' => $result['reason'],
                ];
            }
        });

        if ($rows) {
            $this->table([
                'Accion',
                'Cliente',
                'Propiedad',
                'Contrato',
                'Renta',
                'Fecha',
                'Renta $',
                'Mensual $',
                'Renta fija $',
                'Iguala $',
                'Estado',
                'Motivo',
            ], $rows);
        } else {
            $this->line('No se encontraron rentas candidatas en el rango.');
        }

        $this->newLine();
        $this->info('Resumen');
        foreach ($summary as $key => $value) {
            $this->line("{$key}: {$value}");
        }

        if ($dryRun) {
            $this->warn('No se creó ningún movimiento. Ejecuta con --confirm para guardar.');
        }

        return self::SUCCESS;
    }

    private function parseRange(): array
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));

        if (! preg_match('/^\d{4}\-\d{2}$/', $desde) || ! preg_match('/^\d{4}\-\d{2}$/', $hasta)) {
            $this->error('Debes indicar --desde y --hasta en formato YYYY-MM.');

            return [null, null];
        }

        $inicio = Carbon::createFromFormat('Y-m', $desde)->startOfMonth();
        $fin = Carbon::createFromFormat('Y-m', $hasta)->endOfMonth();

        if ($fin->lt($inicio)) {
            $this->error('--hasta debe ser mayor o igual a --desde.');

            return [null, null];
        }

        return [$inicio, $fin];
    }

    private function rentasQuery(Carbon $inicio, Carbon $fin)
    {
        return Movimiento::query()
            ->with(['cliente', 'propiedad'])
            ->where('concepto', 'renta')
            ->where('approval_status', Movimiento::STATUS_APPROVED)
            ->where('afecta_saldo_cliente', true)
            ->where(function ($query) {
                $query->whereNull('estado_pago')
                    ->orWhere('estado_pago', '!=', Movimiento::PAYMENT_CANCELED);
            })
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->orderBy('fecha')
            ->orderBy('id');
    }

    private function evaluateRenta(Movimiento $renta, string $fechaMode): array
    {
        $fechaRenta = Carbon::parse($renta->fecha);
        $contracts = $this->resolveContracts($renta);
        $base = [
            'renta' => $renta,
            'cliente' => $renta->cliente?->nombre ?: 'Cliente #' . $renta->cliente_id,
            'propiedad' => $renta->propiedad?->alias ?: ($renta->propiedad_id ? 'Propiedad #' . $renta->propiedad_id : '—'),
            'renta_folio' => $renta->folio ?: '#' . $renta->id,
            'fecha_renta' => $fechaRenta->toDateString(),
            'importe_renta' => (float) $renta->importe,
            'renta_estado' => $renta->estado_pago ?: Movimiento::PAYMENT_LIQUIDATED,
            'contrato' => null,
            'contrato_id' => null,
            'comision_mensual_calculada' => 0.0,
            'comision_renta_aplicada' => 0.0,
            'total_iguala' => 0.0,
            'fecha_iguala' => $fechaMode === 'fin-mes' ? $fechaRenta->copy()->endOfMonth()->toDateString() : $fechaRenta->toDateString(),
            'reason' => '',
        ];

        if ($contracts->count() > 1) {
            return $this->withAction($base, 'ambiguo', 'Más de un contrato compatible.');
        }

        if ($contracts->isEmpty()) {
            return $this->withAction($base, 'sin_contrato', 'No se encontró contrato activo para la renta.');
        }

        $contrato = $contracts->first();
        $base['contrato'] = $contrato;
        $base['contrato_id'] = $contrato->id;

        if (! $this->hasCommission($contrato)) {
            return $this->withAction($base, 'sin_comision', 'Contrato sin comisión configurada.');
        }

        if ($this->hasExistingIguala($renta, $fechaRenta)) {
            return $this->withAction($base, 'duplicado', 'Ya existe iguala para cliente/propiedad/mes.');
        }

        if ($this->hasSuspiciousMovement($renta, $fechaRenta)) {
            return $this->withAction($base, 'sospechoso', 'Existe movimiento manual sospechoso de comisión/honorario.');
        }

        $monthly = (float) $renta->importe * $this->commissionFraction($contrato);
        $fixed = $this->shouldApplyFixedCommission($renta, $contrato)
            ? (float) ($contrato->comision_renta ?? 0)
            : 0.0;
        $total = round($monthly + $fixed, 2);

        $base['comision_mensual_calculada'] = round($monthly, 2);
        $base['comision_renta_aplicada'] = round($fixed, 2);
        $base['total_iguala'] = $total;

        if ($total <= 0) {
            return $this->withAction($base, 'sin_comision', 'La comisión calculada es cero.');
        }

        return $this->withAction($base, 'crear', 'Iguala sugerida.');
    }

    private function resolveContracts(Movimiento $renta): EloquentCollection
    {
        $fecha = Carbon::parse($renta->fecha)->toDateString();

        $query = Contrato::query()
            ->with(['cliente', 'propiedad'])
            ->where(function ($query) use ($fecha) {
                $query->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $fecha);
            })
            ->where(function ($query) use ($fecha) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fecha);
            })
            ->where(function ($query) {
                $query->whereNull('deleted_at');
            });

        if ($renta->propiedad_id) {
            return (clone $query)
                ->where('fk_propiedad', $renta->propiedad_id)
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('id')
                ->get();
        }

        return (clone $query)
            ->where('fk_cliente', $renta->cliente_id)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();
    }

    private function hasCommission(Contrato $contrato): bool
    {
        return (float) ($contrato->comision_mensual ?? 0) > 0
            || (float) ($contrato->comision_renta ?? 0) > 0;
    }

    private function commissionFraction(Contrato $contrato): float
    {
        $value = (float) ($contrato->comision_mensual ?? 0);

        return $value > 1 ? $value / 100 : $value;
    }

    private function hasExistingIguala(Movimiento $renta, Carbon $fechaRenta): bool
    {
        return $this->sameClientPropertyMonthQuery($renta, $fechaRenta)
            ->where('concepto', 'iguala')
            ->where(function ($query) {
                $query->whereNull('estado_pago')
                    ->orWhere('estado_pago', '!=', Movimiento::PAYMENT_CANCELED);
            })
            ->exists();
    }

    private function hasSuspiciousMovement(Movimiento $renta, Carbon $fechaRenta): bool
    {
        return $this->sameClientPropertyMonthQuery($renta, $fechaRenta)
            ->where('concepto', '!=', 'iguala')
            ->where(function ($query) {
                foreach (self::SUSPICIOUS_TERMS as $term) {
                    $query->orWhereRaw('LOWER(COALESCE(concepto, "")) LIKE ?', ['%' . $term . '%'])
                        ->orWhereRaw('LOWER(COALESCE(notas, "")) LIKE ?', ['%' . $term . '%']);
                }
            })
            ->exists();
    }

    private function sameClientPropertyMonthQuery(Movimiento $renta, Carbon $fechaRenta)
    {
        return Movimiento::query()
            ->where('cliente_id', $renta->cliente_id)
            ->whereYear('fecha', $fechaRenta->year)
            ->whereMonth('fecha', $fechaRenta->month)
            ->where(function ($query) use ($renta) {
                if ($renta->propiedad_id) {
                    $query->where('propiedad_id', $renta->propiedad_id);
                } else {
                    $query->whereNull('propiedad_id');
                }
            });
    }

    private function shouldApplyFixedCommission(Movimiento $renta, Contrato $contrato): bool
    {
        if ((float) ($contrato->comision_renta ?? 0) <= 0 || ! $contrato->fecha_inicio) {
            return false;
        }

        $fechaRenta = Carbon::parse($renta->fecha);
        $inicioContrato = Carbon::parse($contrato->fecha_inicio);

        if (! $inicioContrato->isSameMonth($fechaRenta) || ! $inicioContrato->isSameYear($fechaRenta)) {
            return false;
        }

        $firstRenta = Movimiento::query()
            ->where('concepto', 'renta')
            ->where('approval_status', Movimiento::STATUS_APPROVED)
            ->where('afecta_saldo_cliente', true)
            ->where(function ($query) {
                $query->whereNull('estado_pago')
                    ->orWhere('estado_pago', '!=', Movimiento::PAYMENT_CANCELED);
            })
            ->where('cliente_id', $renta->cliente_id)
            ->whereYear('fecha', $inicioContrato->year)
            ->whereMonth('fecha', $inicioContrato->month)
            ->where(function ($query) use ($renta) {
                if ($renta->propiedad_id) {
                    $query->where('propiedad_id', $renta->propiedad_id);
                } else {
                    $query->whereNull('propiedad_id');
                }
            })
            ->orderBy('fecha')
            ->orderBy('id')
            ->first();

        return $firstRenta?->id === $renta->id;
    }

    private function withAction(array $data, string $action, string $reason): array
    {
        $data['action'] = $action;
        $data['reason'] = $reason;

        return $data;
    }

    private function createIguala(array $result): Movimiento
    {
        /** @var Movimiento $renta */
        $renta = $result['renta'];
        /** @var Contrato $contrato */
        $contrato = $result['contrato'];

        $notas = sprintf(
            'Iguala generada por regla legacy. Contrato #%s, renta %s, comisión mensual $%s, comisión renta $%s.',
            $contrato->id,
            $renta->folio ?: '#' . $renta->id,
            number_format($result['comision_mensual_calculada'], 2, '.', ''),
            number_format($result['comision_renta_aplicada'], 2, '.', ''),
        );

        $movimiento = Movimiento::withoutEvents(fn () => Movimiento::create([
            'asignado_a_tipo' => $renta->propiedad_id ? 'propiedad' : 'cliente',
            'cliente_id' => $renta->cliente_id,
            'propiedad_id' => $renta->propiedad_id,
            'inquilino_id' => null,
            'concepto' => 'iguala',
            'fecha' => $result['fecha_iguala'],
            'importe' => $result['total_iguala'],
            'forma_pago' => 'transferencia',
            'notas' => $notas,
            'approval_status' => Movimiento::STATUS_APPROVED,
            'estado_pago' => Movimiento::PAYMENT_LIQUIDATED,
            'fecha_liquidacion' => $result['fecha_iguala'],
            'afecta_saldo_cliente' => true,
            'approved_by' => null,
            'approved_at' => now(),
        ]));

        $movimiento->ensureFolio();
        $this->logCreated($movimiento->fresh());

        return $movimiento;
    }

    private function logCreated(Movimiento $movimiento): void
    {
        ActivityLog::create([
            'user_id' => null,
            'action' => 'created',
            'model_type' => Movimiento::class,
            'model_id' => $movimiento->getKey(),
            'module' => class_basename($movimiento),
            'old_values' => null,
            'new_values' => ActivityLog::sanitizeValues($movimiento->toArray()),
            'ip_address' => null,
            'user_agent' => 'artisan movimientos:generar-igualas',
        ]);
    }
}
