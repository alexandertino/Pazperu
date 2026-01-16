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
        'es_pendiente',
        'pendiente_saldado',
        'movimiento_saldante_id',
        'movimiento_pendiente_id',
    ];

    protected $casts = [
        'fecha_operacion' => 'date',
        'deudor' => 'decimal:2',
        'acreedor' => 'decimal:2',
        'saldo' => 'decimal:2',
        'es_pendiente' => 'boolean',
        'pendiente_saldado' => 'boolean',
    ];

    public function cuenta()
    {
        return $this->belongsTo(CuentaGeneral::class, 'cuenta_general_id');
    }

    public function subcuenta()
    {
        return $this->belongsTo(Subcuenta::class, 'subcuenta_id');
    }

    // Relación con el movimiento que salda este pendiente
    public function movimientoSaldante()
    {
        return $this->belongsTo(Movimiento::class, 'movimiento_saldante_id');
    }

    // Relación con el movimiento pendiente que este movimiento salda
    public function movimientoPendiente()
    {
        return $this->belongsTo(Movimiento::class, 'movimiento_pendiente_id');
    }

    // Pendientes que este movimiento salda
    public function pendientesSaldados()
    {
        return $this->hasMany(Movimiento::class, 'movimiento_saldante_id');
    }

    // Método para verificar si es un pendiente activo
    public function esPendienteActivo()
    {
        return $this->es_pendiente && !$this->pendiente_saldado;
    }

    // Método para obtener el monto pendiente
    public function getMontoPendienteAttribute()
    {
        if ($this->es_pendiente) {
            return abs($this->deudor - $this->acreedor);
        }
        return 0;
    }
}