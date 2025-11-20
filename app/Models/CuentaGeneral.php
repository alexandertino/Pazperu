<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaGeneral extends Model
{
    protected $table = 'cuentas_generales';

    protected $fillable = [
        'nombre',
        'descripcion',
        'saldo_inicial',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
    ];

    protected $appends = ['saldo']; // 👈 Para que siempre devuelva el saldo calculado

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'cuenta_general_id')
            ->orderBy('fecha_operacion')
            ->orderBy('id');
    }

    public function subcuentas()
    {
        return $this->hasMany(Subcuenta::class, 'cuenta_id');
    }

    // 👇 CALCULA SIEMPRE EL SALDO REAL
    public function getSaldoAttribute()
    {
        // Buscar último movimiento registrado
        $ultimo = $this->movimientos()->orderByDesc('id')->first();

        // Si existe, devolver su saldo
        if ($ultimo) {
            return $ultimo->saldo;
        }

        // Si no hay movimientos → devolver saldo inicial
        return $this->saldo_inicial ?? 0;
    }
}
