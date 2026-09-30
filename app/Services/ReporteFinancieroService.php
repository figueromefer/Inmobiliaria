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

        // La colección base conserva el criterio histórico de visibilidad
        // (afecta saldo), con la única excepción de transferencias directas
        // de renta o depósito, que son informativas para Dorantes.
        // Los saldos se calculan únicamente con el subconjunto financiero.
        $anterioresFinancieros = $this->movimientosQueAfectanSaldo($anteriores);
        $movimientosFinancieros = $this->movimientosQueAfectanSaldo($movimientos);

        $saldoAnteriorContable = $this->saldoNeto($anterioresFinancieros);
        $saldoAnteriorLiquidado = $this->saldoNeto($this->filtrarLiquidados($anterioresFinancieros));
        $periodo = $this->resumenPeriodo($movimientos, $movimientosFinancieros);
        $pendientes = $this->resumenPendientes($movimientosFinancieros);
        $liquidados = $this->resumenLiquidados($movimientosFinancieros);
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
            ->where(function (Builder $query) {
                $query->where('afecta_saldo_cliente', true)
                    ->orWhere(function (Builder $transferencias) {
                        $transferencias->where('forma_pago', 'transferencia')
                            ->whereIn('concepto', ['renta', 'deposito']);
                    });
            })
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

    private function resumenPeriodo(Collection $movimientos, Collection $movimientosFinancieros): array
    {
        $rentas = $this->sumarConcepto($movimientos, 'renta');
        $depositos = $this->sumarConcepto($movimientos, 'deposito');
        $transferenciasInformativas = $movimientos
            ->filter(fn (Movimiento $movimiento) => $this->esTransferenciaInformativa($movimiento))
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);
        $ingresosReportados = $rentas + $depositos;

        $ingresosAfectanSaldo = $movimientosFinancieros
            ->whereIn('concepto', ['renta', 'deposito'])
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);
        $gastos = $this->sumarConcepto($movimientosFinancieros, 'gasto');
        $gastosCliente = $this->sumarConcepto($movimientosFinancieros, 'gasto_cliente');
        $igualas = $this->sumarConcepto($movimientosFinancieros, 'iguala');
        $pagosCliente = $this->sumarConcepto($movimientosFinancieros, 'pago_cliente');
        $egresosTotal = $gastos + $gastosCliente + $igualas;
        $liquidados = $this->filtrarLiquidados($movimientosFinancieros);
        $ingresosLiquidados = $liquidados
            ->whereIn('concepto', ['renta', 'deposito'])
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);
        $egresosLiquidados = $liquidados
            ->whereIn('concepto', ['gasto', 'gasto_cliente', 'iguala'])
            ->sum(fn (Movimiento $movimiento) => (float) $movimiento->importe);
        $pagosClienteLiquidados = $this->sumarConcepto($liquidados, 'pago_cliente');
        $saldoPeriodoContable = $ingresosAfectanSaldo - $egresosTotal - $pagosCliente;
        $saldoPeriodoLiquidado = $ingresosLiquidados - $egresosLiquidados - $pagosClienteLiquidados;

        return [
            'rentas' => $rentas,
            'depositos' => $depositos,
            // ingresos_total se conserva como alias financiero para consumidores existentes.
            'ingresos_total' => (float) $ingresosAfectanSaldo,
            'ingresos_afectan_saldo' => (float) $ingresosAfectanSaldo,
            'ingresos_totales_reportados' => (float) $ingresosReportados,
            'transferencias_informativas' => (float) $transferenciasInformativas,
            'total_transferencias' => (float) $transferenciasInformativas,
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

    private function movimientosQueAfectanSaldo(Collection $movimientos): Collection
    {
        return $movimientos
            ->filter(fn (Movimiento $movimiento) => $movimiento->afecta_saldo_cliente)
            ->reject(fn (Movimiento $movimiento) => $this->esTransferenciaInformativa($movimiento))
            ->values();
    }

    private function esTransferenciaInformativa(Movimiento $movimiento): bool
    {
        return $movimiento->forma_pago === 'transferencia'
            && in_array($movimiento->concepto, ['renta', 'deposito'], true);
    }
}
