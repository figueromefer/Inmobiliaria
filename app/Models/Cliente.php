<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';
    protected $primaryKey = 'pk_cliente';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'rfc',
        'domicilio',
        'domicilio_notificaciones',
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

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'fk_cliente', 'pk_cliente');
    }

    public function propiedades()
    {
        return $this->hasMany(Propiedad::class, 'fk_cliente', 'pk_cliente');
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'fk_cliente', 'pk_cliente');
    }
}
