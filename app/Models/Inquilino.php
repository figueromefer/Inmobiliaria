<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inquilino extends Model
{
    protected $table = 'inquilinos';

    protected $fillable = [
        'nombre', 'nacionalidad', 'domicilio', 'telefono', 'correo',
    ];

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'inquilino_id');
    }
}
