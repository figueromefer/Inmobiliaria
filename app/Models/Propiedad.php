<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Propiedad extends Model
{
    use HasFactory;

    protected $table = 'propiedades';
    protected $primaryKey = 'pk_propiedad';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'fk_cliente',
        'alias',
        'domicilio',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'municipio',
        'estado',
        'siapa',
        'cfe',
        'predial',
        'mantenimiento_banco',
        'mantenimiento_cuenta',
        'referencia',
        'clabe',
        'mantenimiento_monto',
        'mantenimiento_fecha_pago',
        'latitud',
        'longitud',
        'estatus_informacion',
    ];

    protected $casts = [
        'mantenimiento_fecha_pago' => 'date',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'fk_cliente', 'pk_cliente');
    }

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'fk_propiedad', 'pk_propiedad');
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'fk_propiedad', 'pk_propiedad');
    }

    public function tickets()
    {
        return $this->hasMany(MaintenanceTicket::class, 'property_id', 'pk_propiedad');
    }
}
