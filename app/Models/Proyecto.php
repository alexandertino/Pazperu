<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proyecto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'estado',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
    ];

    // Relación con Inventario
    public function inventarios()
    {
        return $this->hasMany(Inventario::class, 'proyecto_id');
    }

    // Relación con Salida
    public function salidas()
    {
        return $this->hasMany(Salida::class, 'proyecto_id');
    }
}