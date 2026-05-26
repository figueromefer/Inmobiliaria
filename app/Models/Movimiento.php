<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'movimientos';

    protected $fillable = [
        'cliente_id',
        'propiedad_id',
        'concepto',
        'fecha',
        'importe',
        'forma_pago',
        'notas',
        'comprobante',
        'approval_status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'pk_cliente');
    }

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id', 'pk_propiedad');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', self::STATUS_APPROVED);
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
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
