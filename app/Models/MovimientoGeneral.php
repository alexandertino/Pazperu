<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoGeneral extends Model
{
    use HasFactory;

    protected $table = 'movimientos_generales';

    protected $fillable = [
        'cuenta_id',
        'fecha',
        'medio_pago',
        'descripcion',
        'deudor',
        'acreedor'
    ];

    public function cuenta()
    {
        return $this->belongsTo(CuentaGeneral::class, 'cuenta_id');
    }
}
