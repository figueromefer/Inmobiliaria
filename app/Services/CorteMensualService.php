<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Movimiento;
use Illuminate\Support\Carbon;

class CorteMensualService
{
    public function calcularCorteClienteMes(int $clienteId, string $yyyymm): array
    {
        [$start, $end] = [
            Carbon::createFromFormat('Y-m', $yyyymm)->startOfMonth(),
            Carbon::createFromFormat('Y-m', $yyyymm)->endOfMonth(),
        ];

        $ingresosEfectivo = Movimiento::where('cliente_id',$clienteId)
            ->whereIn('concepto',['renta','deposito'])
            ->where('forma_pago','efectivo')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->sum('importe');

        $pagosClienteEfectivo = Movimiento::where('cliente_id',$clienteId)
            ->where('concepto','pago_cliente')
            ->where('forma_pago','efectivo')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->sum('importe');

        // IGUALA del mes
        $rentasMes = Movimiento::where('concepto','renta')
            ->where('forma_pago','efectivo')
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->whereHas('propiedad', fn($q)=>$q->where('fk_cliente',$clienteId))
            ->orderBy('fecha')->get();

        $igualaMes = 0.0;
        foreach ($rentasMes as $r) {
            $contrato = Contrato::where('fk_cliente',$r->propiedad->fk_cliente)
                ->whereDate('fecha_inicio','<=',$r->fecha)
                ->where(fn($w)=>$w->whereNull('fecha_fin')->orWhereDate('fecha_fin','>=',$r->fecha))
                ->orderBy('fecha_inicio','desc')->first();
            if ($contrato) {
                $igualaMes += $r->importe * (float)$contrato->comision_mensual_fraction;
                $ini = Carbon::parse($contrato->fecha_inicio);
                $rm  = Carbon::parse($r->fecha);
                if ($ini->isSameMonth($rm) && $ini->isSameYear($rm)) {
                    $igualaMes += (float)($contrato->comision_renta ?? 0);
                }
            }
        }

        // Saldo anterior hasta el mes previo
        $prevEnd = $start->copy()->subDay();
        $ingresosPrev = Movimiento::where('cliente_id',$clienteId)
            ->whereIn('concepto',['renta','deposito'])
            ->where('forma_pago','efectivo')
            ->whereDate('fecha','<=',$prevEnd->toDateString())
            ->sum('importe');

        $rentasPrev = Movimiento::where('cliente_id',$clienteId)
            ->where('concepto','renta')
            ->where('forma_pago','efectivo')
            ->whereDate('fecha','<=',$prevEnd->toDateString())
            ->orderBy('fecha')->get();

        $igualaPrev = 0.0;
        foreach ($rentasPrev as $r) {
            $contrato = Contrato::where('fk_cliente',$clienteId)
                ->whereDate('fecha_inicio','<=',$r->fecha)
                ->where(fn($w)=>$w->whereNull('fecha_fin')->orWhereDate('fecha_fin','>=',$r->fecha))
                ->orderBy('fecha_inicio','desc')->first();
            if ($contrato) {
                $igualaPrev += $r->importe * (float)$contrato->comision_mensual_fraction;
                $ini = Carbon::parse($contrato->fecha_inicio);
                $rm  = Carbon::parse($r->fecha);
                if ($ini->isSameMonth($rm) && $ini->isSameYear($rm)) {
                    $igualaPrev += (float)($contrato->comision_renta ?? 0);
                }
            }
        }

        $pagosPrev = Movimiento::where('cliente_id',$clienteId)
            ->where('concepto','pago_cliente')
            ->whereDate('fecha','<=',$prevEnd->toDateString())
            ->sum('importe');

        $saldoAnterior = max(0, $ingresosPrev - $igualaPrev - $pagosPrev);
        $totalMes = $ingresosEfectivo - $igualaMes - $pagosClienteEfectivo;
        $totalIncluyeSaldos = $saldoAnterior + $totalMes;

        return [
            'saldo_anterior'       => $saldoAnterior,
            'total_mes'            => $totalMes,
            'total_incluye_saldos' => $totalIncluyeSaldos,
            'tieneSaldoAnterior'   => $saldoAnterior > 0,
        ];
    }
}
