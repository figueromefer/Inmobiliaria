<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContratoPendiente extends Model
{
    protected $table = 'contratos_pendientes';

    protected $fillable = [
        'origen',
        'external_id',
        'expediente',
        'estado',
        'raw_payload',
        'mapped_payload',
        'matched_cliente_id',
        'matched_propiedad_id',
        'matched_inquilino_id',
        'contrato_id',
        'processed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'mapped_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'matched_cliente_id', 'pk_cliente');
    }

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'matched_propiedad_id', 'pk_propiedad');
    }

    public function inquilino()
    {
        return $this->belongsTo(Inquilino::class, 'matched_inquilino_id');
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente_match');
    }
}
