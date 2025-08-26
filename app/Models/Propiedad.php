<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Propiedad extends Model
{
    use HasFactory;

    protected $table = 'propiedades';

    protected $primaryKey = 'pk_propiedad'; // ← Clave primaria personalizada

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'fk_cliente',
        'alias',
        'domicilio',
        'siapa',
        'cfe',
        'predial',
        'mantenimiento_banco',
        'mantenimiento_cuenta',
        'mantenimiento_monto',
        'latitud',
        'longitud',
    ];

    // Relación inversa con Cliente
    public function cliente() { return $this->belongsTo(Cliente::class, 'fk_cliente', 'pk_cliente'); }
public function contratos() { return $this->hasMany(Contrato::class, 'fk_propiedad', 'pk_propiedad'); }

}
