<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    protected $table = 'contratos';

    protected $fillable = [
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
        'comision_mensual'.
        'dias_pago',
        'monto_total',
        'monto_mensual',
        'monto_deposito',
        'edit_url',
    ];

    protected $casts = [
        'fecha'        => 'datetime',
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function inquilino()
    {
        return $this->belongsTo(Inquilino::class, 'inquilino_id');
    }
    
}
