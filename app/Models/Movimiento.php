<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $table = 'movimientos';

    protected $fillable = [
        'cuenta_general_id',
        'numero',
        'fecha_operacion',
        'medio_pago',
        'descripcion',
        'deudor',
        'acreedor',
        'saldo',
        'subcuenta_id',
    ];

    protected $casts = [
        'fecha_operacion' => 'date',
        'deudor' => 'decimal:2',
        'acreedor' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function cuenta()
    {
        return $this->belongsTo(CuentaGeneral::class, 'cuenta_general_id');
    }


    public function subcuenta()
    {
        return $this->belongsTo(Subcuenta::class, 'subcuenta_id');
    }
}
