<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salida extends Model
{
    protected $fillable = [
        'acta',
        'nombre',
        'lugar',
        'distrito',
        'fecha',
        'producto',
        'cantidad',
        'proyecto_id',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
    
}