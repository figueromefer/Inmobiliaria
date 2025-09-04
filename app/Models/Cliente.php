<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes'; // Por claridad, aunque Laravel ya asumiría esto

    protected $primaryKey = 'pk_cliente'; // ← IMPORTANTE

    public $incrementing = true; // ← Si es autoincremental
    protected $keyType = 'int';  // ← Tipo de dato de la clave primaria

    protected $fillable = [
        'nombre',
        'rfc',
        'domicilio',
        'fijo',
        'celular',
        'correo',
        'banco',
        'cuenta',
        'clabe',
        'notas',
    ];

    public function getRouteKeyName()
    {
        return 'pk_cliente';
    }

    // Relación con propiedades

    public function contratos() { return $this->hasMany(Contrato::class, 'fk_cliente', 'pk_cliente'); }
public function propiedades() { return $this->hasMany(Propiedad::class, 'fk_cliente', 'pk_cliente'); }
}
