<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubcuentaMovimiento extends Model
{
    protected $table = 'subcuentas_movimientos';

    protected $fillable = [
        'subcuenta_id',
        'fecha',
        'descripcion',
        'deudor',
        'acreedor',
        'saldo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'deudor' => 'decimal:2',
        'acreedor' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function subcuenta()
    {
        return $this->belongsTo(Subcuenta::class, 'subcuenta_id');
    }
}
