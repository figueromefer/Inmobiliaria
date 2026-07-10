<?php

namespace App\Services;

use App\Models\Movimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PerfilMovimientosService
{
    public function __construct(private ReporteFinancieroService $reportes)
    {
    }

    public function forCliente(int $clienteId, Request $request): array
    {
        return $this->build(
            $request,
            fn (Builder $query) => $query->where('cliente_id', $clienteId),
            fn (array $filters) => $this->reportes->generarPorCliente($clienteId, $filters['fecha_inicio'], $filters['fecha_fin'], $filters),
        );
    }

    public function forPropiedad(int $propiedadId, Request $request): array
    {
        return $this->build(
            $request,
            fn (Builder $query) => $query->where('propiedad_id', $propiedadId),
            fn (array $filters) => $this->reportes->generarPorPropiedad($propiedadId, $filters['fecha_inicio'], $filters['fecha_fin'], $filters),
        );
    }

    public function forInquilino(int $inquilinoId, Request $request): array
    {
        return $this->build(
            $request,
            fn (Builder $query) => $query->where('inquilino_id', $inquilinoId),
            fn (array $filters) => $this->reportes->generarPorInquilino($inquilinoId, $filters['fecha_inicio'], $filters['fecha_fin'], $filters),
        );
    }

    private function build(Request $request, callable $scope, callable $reportCallback): array
    {
        $filters = $this->filters($request);
        $query = Movimiento::query()->with(['cliente', 'propiedad', 'inquilino']);
        $scope($query);
        $this->applyFilters($query, $filters);

        $movimientos = $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'movimientos_page')
            ->withQueryString();

        return [
            'filters' => $filters,
            'movimientos' => $movimientos,
            'reporte' => $reportCallback($filters),
        ];
    }

    private function filters(Request $request): array
    {
        $fechaInicio = $this->validDate((string) $request->query('fecha_inicio'))
            ?: now()->startOfMonth()->toDateString();
        $fechaFin = $this->validDate((string) $request->query('fecha_fin'))
            ?: now()->endOfMonth()->toDateString();

        if ($fechaFin < $fechaInicio) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'concepto' => $this->allowed((string) $request->query('concepto'), $this->conceptos()),
            'estado_pago' => $this->allowed((string) $request->query('estado_pago'), $this->estadosPago()),
            'approval_status' => $this->allowed((string) $request->query('approval_status'), $this->approvalStatuses()),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query->whereBetween('fecha', [$filters['fecha_inicio'], $filters['fecha_fin']]);

        foreach (['concepto', 'estado_pago', 'approval_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
    }

    private function validDate(string $value): ?string
    {
        if (! preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function allowed(string $value, array $allowed): ?string
    {
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function conceptos(): array
    {
        return ['deposito', 'renta', 'gasto', 'gasto_cliente', 'iguala', 'pago_cliente'];
    }

    private function estadosPago(): array
    {
        return [Movimiento::PAYMENT_PENDING, Movimiento::PAYMENT_LIQUIDATED, Movimiento::PAYMENT_CANCELED];
    }

    private function approvalStatuses(): array
    {
        return [Movimiento::STATUS_PENDING, Movimiento::STATUS_APPROVED, Movimiento::STATUS_REJECTED];
    }
}
