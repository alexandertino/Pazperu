<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subcuenta extends Model
{
    protected $table = 'subcuentas';

    protected $fillable = [
        'cuenta_id',
        'nombre',
        'saldo_inicial',
        'saldo_actual',
        'descripcion',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'saldo_actual'  => 'decimal:2',
    ];

    /**
     * Se expone como atributo calculado para Inertia / JSON
     */
    protected $appends = ['saldo'];

    /**
     * Relación con cuenta general
     */
    public function cuenta()
    {
        return $this->belongsTo(CuentaGeneral::class, 'cuenta_id');
    }

    /**
     * Movimientos de la subcuenta
     */
    public function movimientos()
    {
        return $this->hasMany(SubcuentaMovimiento::class);
    }

    /**
     * Saldo real de la subcuenta
     * 👉 Usa saldo_actual si existe
     * 👉 Si no hay movimientos aún, usa saldo_inicial
     */
    public function getSaldoAttribute()
    {
        if (!is_null($this->saldo_actual)) {
            return $this->saldo_actual;
        }

        return $this->saldo_inicial ?? 0;
    }
}
