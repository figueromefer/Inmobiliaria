<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Documento extends Model {
    use HasFactory;

    protected $table = 'documentos';
    protected $primaryKey = 'pk_documento';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'fk_cliente',
        'fk_propiedad',
        'titulo',
        'archivo',
    ];

    public function cliente() {
        return $this->belongsTo(Cliente::class, 'fk_cliente', 'pk_cliente');
    }

    public function propiedad() {
        return $this->belongsTo(Propiedad::class, 'fk_propiedad', 'pk_propiedad');
    }
}
