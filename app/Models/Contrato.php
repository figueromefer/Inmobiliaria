<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Contrato extends Model
{
    protected $table = 'contratos';

    // Incluye todas tus columnas originales más las FKs nuevas
    protected $fillable = [
        'fk_cliente',
        'fk_propiedad',

        'tipo_solicitante',
        'tipo_complementaria',
        'tipo_tercero',
        'solicitante',
        'fecha',
        'inquilino_id',
        'domicilio_inmueble',
        'fecha_inicio',
        'fecha_fin',
        'comision_renta',
        'comision_mensual',
        'dias_pago',
        'monto_total',
        'monto_mensual',
        'monto_deposito',
        'edit_url',
        'urldoc'
    ];

    // Casts (opcional) para fechas y números
    protected $casts = [
        'fecha'        => 'datetime',
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    // Relaciones Eloquent
    public function cliente()   { return $this->belongsTo(Cliente::class, 'fk_cliente', 'pk_cliente'); }
    public function propiedad() { return $this->belongsTo(Propiedad::class, 'fk_propiedad', 'pk_propiedad'); }
    public function inquilino() { return $this->belongsTo(Inquilino::class, 'inquilino_id'); }

    // Scope para encontrar contratos activos en un mes (útil para reportes)
    public function scopeActivosEnMes($query, Carbon $mes)
    {
        $inicio = $mes->copy()->startOfMonth();
        $fin    = $mes->copy()->endOfMonth();
        return $query
            ->where(function ($q) use ($fin) {
                $q->whereNull('fecha_inicio')
                  ->orWhere('fecha_inicio', '<=', $fin);
            })
            ->where(function ($q) use ($inicio) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $inicio);
            });
    }

    public function getComisionMensualFractionAttribute(): float
    {
        $v = (float) ($this->comision_mensual ?? 0);
        return $v > 1 ? $v / 100 : $v;
    }
}
