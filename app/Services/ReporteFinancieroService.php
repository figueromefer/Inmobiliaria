<?php

namespace App\Services;

use App\Models\Movimiento;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReporteFinancieroService
{
    public function generarPorCliente(int $clienteId, string|CarbonInterface $fechaInicio, string|CarbonInterface $fechaFin, array $filters = []): array
    {
        return $this->generar(
            $fechaInicio,
            $fechaFin,
            fn (Builder $query) => $query->where('cliente_id', $clienteId),
            ['tipo' => 'cliente', 'id' => $clienteId],
            $filters,
        );
    }

    public function generarPorPropiedad(int $propiedadId, string|CarbonInterface $fechaInicio, string|CarbonInterface $fechaFin, array $filters = []): array
    {
        return $this->generar(
            $fechaInicio,
            $fechaFin,
            fn (Builder $query) => $query->where('propiedad_id', $propiedadId),
            ['tipo' => 'propiedad', 'id' => $propiedadId],
            $filters,
        );
    }

    public function generarPorInquilino(int $inquilinoId, string|CarbonInterface $fechaInicio, string|CarbonInterface $fechaFin, array $filters = []): array
    {
        return $this->generar(
            $fechaInicio,
            $fechaFin,
            fn (Builder $query) => $query->where('inquilino_id', $inquilinoId),
            ['tipo' => 'inquilino', 'id' => $inquilinoId],
            $filters,
        );
    }

    private function generar(string|CarbonInterface $fechaInicio, string|CarbonInterface $fechaFin, callable $scope, array $entidad, array $filters = []): array
    {
        [$inicio, $fin] = $this->normalizarRango($fechaInicio, $fechaFin);

        $baseQuery = $this->baseQuery();
        $scope($baseQuery);
        $this->applyOptionalFilters($baseQuery, $filters);

        $anteriores = (clone $baseQuery)
            ->whereDate('fecha', '<', $inicio->toDateString())
            ->get();

        $movimientos = (clone $baseQuery)
            ->with(['cliente', 'propiedad', 'inquilino'])
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $saldoAnteriorContable = $this->saldoNeto($anteriores);
        $saldoAnteriorLiquidado = $this->saldoNeto($this->filtrarLiquidados($anteriores));
        $periodo = $this->resumenPeriodo($movimientos);
        $pendientes = $this->resumenPendientes($movimientos);
        $liquidados = $this->resumenLiquidados($movimientos);
        $saldoContable = $saldoAnteriorContable + $periodo['saldo_periodo_contable'];
        $saldoLiquidado = $saldoAnteriorLiquidado + $periodo['saldo_periodo_liquidado'];

        return [
            'entidad' => $entidad,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
            'saldo_anterior' => $saldoAnteriorContable,
            'saldo_anterior_contable' => $saldoAnteriorContable,
            'saldo_anterior_liquidado' => $saldoAnteriorLiquidado,
            'periodo' => $periodo,
            'pendientes' => $pendientes,
            'liquidados' => $liquidados,
            'saldo_final' => $saldoContable,
            'saldo_contable' => $saldoContable,
            'saldo_liquidado' => $saldoLiquidado,
            'saldo_por_pagar_cliente' => $saldoContable,
            'saldo_disponible_para_pago' => $saldoLiquidado,
            'movimientos' => $movimientos,
        ];
    }

    private function baseQuery(): Builder
    {
        return Movimiento::query()
            ->where('approval_status', Movimiento::STATUS_APPROVED)
            ->where('afecta_saldo_cliente', true)
            ->where(function (Builder $query) {
                $query->whereNull('estado_pago')
                    ->orWhere('estado_pago', '!=', Movimiento::PAYMENT_CANCELED);
            });
    }

    private function applyOptionalFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['concepto'])) {
            $query->where('concepto', $filters['concepto']);
        }

        if (! empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (! empty($filters['estado_pago'])) {
            if ($filters['estado_pago'] === Movimiento::PAYMENT_LIQUIDATED) {
                $query->where(function (Builder $paymentQuery) {
                    $paymentQuery->whereNull('estado_pago')
                        ->orWhere('estado_pago', Movimiento::PAYMENT_LIQUIDATED);
                });
            } else {
                $query->where('estado_pago', $filters['estado_pago']);
            }
        }
    }

    private function normalizarRango(string|CarbonInterface $fechaInicio, string|CarbonInterface $fechaFin): array
    {
        $inicio = $fechaInicio instanceof CarbonInterface
            ? Carbon::instance($fechaInicio)->startOfDay()
            : Carbon::parse($fechaInicio)->startOfDay();

        $fin = $fechaFin instanceof CarbonInterface
            ? Carbon::instance($fechaFin)->endOfDay()
            : Carbon::parse($fechaFin)->endOfDay();

        if ($fin->lt($inicio)) {
            [$inicio, $fin] = [$fin->copy()->startOfDay(), $inicio->copy()->endOfDay()];
        }

        return [$inicio, $fin];
    }

    private function resumenPeriodo(Collection $movimientos): array
    {
        $rentas = $this->sumarConcepto($movimientos, 'renta');
        $depositos = $this->sumarConcepto($movimientos, 'deposito');
        $gastos = $this->sumarConcepto($movimientos, 'gasto');
        $gastosCliente = $this->sumarConcepto($movimientos, 'gasto_cliente');
        $igualas = $this->sumarConcepto($movimientos, 'iguala');
        $pagosCliente = $this->sumarConcepto($movimientos, 'pago_cliente');
        $ingresosTotal = $rentas + $depositos;
        $egresosTotal = $gastos + $gastosCliente + $igualas;
        $liquidados = $this->filtrarLiquidados($movimientos);
        $ingresosLiquidados = $liquidados
            ->whereIn('concepto', ['renta', 'deposito'])
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);
        $egresosLiquidados = $liquidados
            ->whereIn('concepto', ['gasto', 'gasto_cliente', 'iguala'])
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);
        $pagosClienteLiquidados = $this->sumarConcepto($liquidados, 'pago_cliente');
        $saldoPeriodoContable = $ingresosTotal - $egresosTotal - $pagosCliente;
        $saldoPeriodoLiquidado = $ingresosLiquidados - $egresosLiquidados - $pagosClienteLiquidados;

        return [
            'rentas' => $rentas,
            'depositos' => $depositos,
            'ingresos_total' => $ingresosTotal,
            'gastos' => $gastos,
            'gastos_cliente' => $gastosCliente,
            'igualas' => $igualas,
            'egresos_total' => $egresosTotal,
            'pagos_cliente' => $pagosCliente,
            'saldo_periodo' => $saldoPeriodoContable,
            'saldo_periodo_contable' => $saldoPeriodoContable,
            'saldo_periodo_liquidado' => $saldoPeriodoLiquidado,
        ];
    }

    private function resumenPendientes(Collection $movimientos): array
    {
        $pendientes = $movimientos->where('estado_pago', Movimiento::PAYMENT_PENDING);

        return [
            'por_cobrar' => $pendientes
                ->whereIn('concepto', ['renta', 'deposito'])
                ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe),
            'por_pagar_o_liquidar' => $pendientes
                ->whereIn('concepto', ['gasto', 'gasto_cliente', 'iguala', 'pago_cliente'])
                ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe),
        ];
    }

    private function resumenLiquidados(Collection $movimientos): array
    {
        $liquidados = $this->filtrarLiquidados($movimientos);

        return [
            'ingresos' => $liquidados
                ->whereIn('concepto', ['renta', 'deposito'])
                ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe),
            'egresos' => $liquidados
                ->whereIn('concepto', ['gasto', 'gasto_cliente', 'iguala'])
                ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe),
            'pagos_cliente' => $this->sumarConcepto($liquidados, 'pago_cliente'),
        ];
    }

    private function saldoNeto(Collection $movimientos): float
    {
        $ingresos = $movimientos
            ->whereIn('concepto', ['renta', 'deposito'])
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);

        $egresos = $movimientos
            ->whereIn('concepto', ['gasto', 'gasto_cliente', 'iguala'])
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);

        $pagosCliente = $this->sumarConcepto($movimientos, 'pago_cliente');

        return (float) $ingresos - (float) $egresos - (float) $pagosCliente;
    }

    private function sumarConcepto(Collection $movimientos, string $concepto): float
    {
        return (float) $movimientos
            ->where('concepto', $concepto)
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);
    }

    private function filtrarLiquidados(Collection $movimientos): Collection
    {
        return $movimientos->filter(fn (Movimiento $movimiento) => $movimiento->estado_pago === Movimiento::PAYMENT_LIQUIDATED || $movimiento->estado_pago === null);
    }
}
