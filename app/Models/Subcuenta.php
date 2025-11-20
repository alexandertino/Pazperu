<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subcuenta extends Model
{
    protected $table = "subcuentas";

    protected $fillable = [
        "cuenta_id",
        "nombre",
        "saldo_inicial",
        "descripcion",
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
    ];

    protected $appends = ['saldo'];   // 👈 Necesario

    public function cuenta()
    {
        return $this->belongsTo(CuentaGeneral::class, "cuenta_id");
    }

    public function movimientos()
    {
        return $this->hasMany(SubcuentaMovimiento::class);
    }

    // 👇 CALCULA EL SALDO REAL
    public function getSaldoAttribute()
    {
        $ultimo = $this->movimientos()->orderByDesc('id')->first();

        if ($ultimo) {
            return $ultimo->saldo;
        }

        return $this->saldo_inicial ?? 0;
    }
}
