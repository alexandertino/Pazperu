<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    
    protected $fillable = [
        'codigo',
        'fecha',
        'descripcion',
        'unidad_medida',
        'entradas',
        'salidas',
        'stock',
        'proyecto_id',
        'precio',
        'solicitado_por',
        'a_cargo',     
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}