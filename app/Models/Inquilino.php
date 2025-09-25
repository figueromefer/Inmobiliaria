<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inquilino extends Model
{
    protected $table = 'inquilinos';

    protected $fillable = [
        'nombre', 'nacionalidad', 'domicilio', 'telefono', 'correo', 'solicitud_id', 'solicitud_url'
    ];

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'inquilino_id');
    }
}
