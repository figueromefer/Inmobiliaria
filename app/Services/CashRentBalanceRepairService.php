<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CashRentBalanceRepairService
{
    public function candidates(): Builder
    {
        return Movimiento::query()->where('concepto', 'renta')->where('forma_pago', 'efectivo')->where('afecta_saldo_cliente', false);
    }

    public function preview(): array
    {
        $candidates = $this->candidates();
        $byPaymentState = (clone $candidates)->selectRaw("COALESCE(estado_pago, 'sin_estado') estado, COUNT(*) total")->groupBy('estado')->pluck('total', 'estado')->all();
        $byApproval = (clone $candidates)->selectRaw("COALESCE(approval_status, 'sin_estado') estado, COUNT(*) total")->groupBy('estado')->pluck('total', 'estado')->all();
        $monthExpression = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', fecha)" : "DATE_FORMAT(fecha, '%Y-%m')";
        $byMonth = (clone $candidates)->selectRaw("{$monthExpression} mes, COUNT(*) total")->groupBy('mes')->orderBy('mes')->pluck('total', 'mes')->all();
        $impact = (clone $candidates)->where('approval_status', Movimiento::STATUS_APPROVED)->where(function ($query) {
            $query->whereNull('estado_pago')->orWhere('estado_pago', '!=', Movimiento::PAYMENT_CANCELED);
        });

        return ['total' => (clone $candidates)->count(), 'by_payment_state' => $byPaymentState, 'by_approval_status' => $byApproval, 'by_month' => $byMonth, 'estimated_impact' => (float) $impact->sum('importe'), 'affected_clients' => (clone $impact)->whereNotNull('cliente_id')->distinct('cliente_id')->count('cliente_id')];
    }

    public function repair(User $user, ?string $ipAddress = null, ?string $userAgent = null): int
    {
        return DB::transaction(function () use ($user, $ipAddress, $userAgent) {
            $movimientos = $this->candidates()->lockForUpdate()->get(['id']);
            foreach ($movimientos as $movimiento) {
                DB::table('movimientos')->where('id', $movimiento->id)->update(['afecta_saldo_cliente' => true]);
                ActivityLog::create(['user_id' => $user->id, 'action' => 'movement_cash_rent_balance_repair', 'model_type' => Movimiento::class, 'model_id' => $movimiento->id, 'module' => 'Movimiento', 'old_values' => ['afecta_saldo_cliente' => false], 'new_values' => ['afecta_saldo_cliente' => true, 'reason' => 'Corrección histórica de rentas en efectivo según regla de saldo del cliente.'], 'ip_address' => $ipAddress, 'user_agent' => $userAgent]);
            }
            return $movimientos->count();
        });
    }
}
