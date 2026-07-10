<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class CorteMensualService
{
    public function __construct(private ReporteFinancieroService $reportes)
    {
    }

    public function calcularCorteClienteMes(int $clienteId, string $yyyymm): array
    {
        [$start, $end] = [
            Carbon::createFromFormat('Y-m', $yyyymm)->startOfMonth(),
            Carbon::createFromFormat('Y-m', $yyyymm)->endOfMonth(),
        ];

        $reporte = $this->reportes->generarPorCliente($clienteId, $start, $end);
        $saldoAnterior = (float) $reporte['saldo_anterior'];
        $totalMes = (float) $reporte['periodo']['saldo_periodo_contable'];
        $totalIncluyeSaldos = (float) $reporte['saldo_contable'];

        return [
            'saldo_anterior'       => $saldoAnterior,
            'total_mes'            => $totalMes,
            'total_incluye_saldos' => $totalIncluyeSaldos,
            'tieneSaldoAnterior'   => $saldoAnterior > 0,
        ];
    }
}
