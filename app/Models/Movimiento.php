<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $table = 'movimientos';

    protected $fillable = [
        'cliente_id', 'propiedad_id', 'concepto', 'fecha', 'importe', 'forma_pago', 'notas','comprobante', 
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

  public function cliente()   { return $this->belongsTo(Cliente::class, 'cliente_id', 'pk_cliente'); }
public function propiedad() { return $this->belongsTo(Propiedad::class, 'propiedad_id', 'pk_propiedad'); }

}
