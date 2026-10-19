<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Movimiento;
use App\Models\Propiedad;
use Illuminate\Support\Facades\DB;

class AutomaticContractChargeService
{
    /** Returns a user-facing warning when a rent cannot be charged automatically. */
    public function reconcileMonthlyCommission(Movimiento $renta): ?string
    {
        if ($renta->concepto !== 'renta') { $this->removeOrCancelForSource($renta); return null; }

        return DB::transaction(function () use ($renta): ?string {
            $renta = Movimiento::lockForUpdate()->findOrFail($renta->id);
            if (! $renta->propiedad_id) {
                $this->removeOrCancelForSource($renta);

                return 'Renta registrada, pero no se generó la iguala porque no tiene una propiedad vinculada.';
            }

            $contracts = Contrato::query()
                ->where('fk_propiedad', $renta->propiedad_id)
                ->activosEnMes($renta->fecha)
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('id')
                ->get();

            if ($contracts->isEmpty()) {
                $this->removeOrCancelForSource($renta);
                $hasUnlinkedContract = Contrato::query()
                    ->where('fk_cliente', $renta->cliente_id)
                    ->whereNull('fk_propiedad')
                    ->activosEnMes($renta->fecha)
                    ->exists();

                return $hasUnlinkedContract
                    ? 'Renta registrada, pero no se generó la iguala porque existe un contrato vigente sin propiedad vinculada.'
                    : 'Renta registrada, pero no se generó la iguala porque no se encontró un contrato vigente vinculado a '.$this->propertyLabel($renta->propiedad_id).'.';
            }

            if ($contracts->count() > 1) {
                $this->removeOrCancelForSource($renta);

                return 'Renta registrada, pero no se generó la iguala porque hay más de un contrato vigente para '.$this->propertyLabel($renta->propiedad_id).'.';
            }

            $contract = $contracts->sole();
            if (! $contract->hasValidMonthlyCommission()) {
                $this->removeOrCancelForSource($renta);

                return 'Renta registrada, pero no se generó la iguala porque el contrato vigente no tiene porcentaje de comisión mensual.';
            }

            $existingCharge = Movimiento::query()->where('source_movimiento_id', $renta->id)->lockForUpdate()->first();
            if ($existingCharge && ! $existingCharge->auto_generated) {
                return 'Renta registrada, pero no se generó la iguala porque ya existe un movimiento manual ligado a esa renta.';
            }

            Movimiento::updateOrCreate(['source_movimiento_id' => $renta->id], [
                'cliente_id' => $renta->cliente_id, 'propiedad_id' => $renta->propiedad_id, 'inquilino_id' => $renta->inquilino_id,
                'asignado_a_tipo' => $renta->asignado_a_tipo, 'contrato_id' => $contract->id, 'concepto' => 'iguala',
                'automation_type' => 'monthly_commission', 'auto_generated' => true, 'fecha' => $renta->fecha,
                'importe' => round((float) $renta->importe * $contract->comision_mensual_fraction, 2), 'forma_pago' => 'efectivo',
                'notas' => 'Iguala automática de '.$renta->folio, 'afecta_saldo_cliente' => true,
                'approval_status' => $renta->approval_status, 'approved_by' => $renta->approved_by, 'approved_at' => $renta->approved_at,
                'estado_pago' => $renta->estado_pago, 'fecha_liquidacion' => $renta->fecha_liquidacion,
            ]);

            return null;
        });
    }

    private function propertyLabel(int $propertyId): string
    {
        return Propiedad::query()->find($propertyId)?->alias ?: 'la propiedad seleccionada';
    }
    public function removeOrCancelForSource(Movimiento $renta): void { Movimiento::where('source_movimiento_id', $renta->id)->where('auto_generated', true)->delete(); }
    public function syncApprovalFromSource(Movimiento $renta): void { $this->reconcileMonthlyCommission($renta); }
    public function ensureInitialCommission(Contrato $contract): void
    {
        if ((float) $contract->comision_renta <= 0 || ! $contract->fecha_inicio) return;
        DB::transaction(function () use ($contract) {
            $contract = Contrato::lockForUpdate()->findOrFail($contract->id);
            Movimiento::firstOrCreate(['contrato_id' => $contract->id, 'automation_type' => 'initial_commission'], [
                'cliente_id'=>$contract->fk_cliente,'propiedad_id'=>$contract->fk_propiedad,'inquilino_id'=>$contract->inquilino_id,'asignado_a_tipo'=>'propiedad','concepto'=>'iguala','auto_generated'=>true,'fecha'=>$contract->fecha_inicio,'importe'=>$contract->comision_renta,'forma_pago'=>'efectivo','afecta_saldo_cliente'=>true,'approval_status'=>Movimiento::STATUS_APPROVED,'estado_pago'=>Movimiento::PAYMENT_LIQUIDATED,'fecha_liquidacion'=>$contract->fecha_inicio,'notas'=>'Comisión inicial automática de contrato #'.$contract->id,
            ]);
        });
    }
}
