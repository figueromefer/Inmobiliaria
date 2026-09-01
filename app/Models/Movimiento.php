<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const PAYMENT_PENDING = 'pendiente';
    public const PAYMENT_LIQUIDATED = 'liquidado';
    public const PAYMENT_CANCELED = 'cancelado';

    protected $table = 'movimientos';

    protected $fillable = [
        'cliente_id',
        'propiedad_id',
        'inquilino_id',
        'asignado_a_tipo',
        'folio',
        'concepto',
        'fecha',
        'importe',
        'forma_pago',
        'notas',
        'comprobante',
        'comprobante_disk',
        'comprobante_nombre_original',
        'comprobante_mime',
        'comprobante_size',
        'approval_status',
        'estado_pago',
        'fecha_liquidacion',
        'afecta_saldo_cliente',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_liquidacion' => 'date',
        'importe' => 'decimal:2',
        'afecta_saldo_cliente' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'pk_cliente');
    }

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id', 'pk_propiedad')->withTrashed();
    }

    public function inquilino()
    {
        return $this->belongsTo(Inquilino::class, 'inquilino_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getAsignadoNombreAttribute(): string
    {
        return match ($this->asignado_a_tipo) {
            'propiedad' => $this->propiedad?->alias
                ?: $this->propiedad?->domicilio
                ?: 'Propiedad #' . ($this->propiedad_id ?: 'sin id'),
            'inquilino' => $this->inquilino?->nombre
                ?: 'Inquilino #' . ($this->inquilino_id ?: 'sin id'),
            default => $this->cliente?->nombre
                ?: 'Cliente #' . ($this->cliente_id ?: 'sin id'),
        };
    }

    public function getClienteFinalAttribute(): ?Cliente
    {
        return $this->cliente;
    }

    public static function formatFolio(int $id): string
    {
        return 'MOV-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function ensureFolio(): void
    {
        if ($this->folio) {
            return;
        }

        $this->forceFill(['folio' => self::formatFolio($this->id)])->saveQuietly();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', self::STATUS_APPROVED);
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function isLiquidado(): bool
    {
        return $this->estado_pago === self::PAYMENT_LIQUIDATED;
    }

    public function isPendiente(): bool
    {
        return $this->estado_pago === self::PAYMENT_PENDING;
    }

    public function isCancelado(): bool
    {
        return $this->estado_pago === self::PAYMENT_CANCELED;
    }

    public function approveBy(User $user): void
    {
        $this->forceFill([
            'approval_status' => self::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ])->save();
    }
}
