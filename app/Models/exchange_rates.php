<?php

// app/Models/ExchangeRate.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class exchange_rates extends Model
{
    protected $table = 'exchange_rates';
    protected $fillable = [
        'proyecto_id','year','month','from_currency','to_currency','rate','source','notes'
    ];

    // Busca tasa para proyecto+mes
    public static function currentForProject($proyectoId, $year, $month, $from='PEN', $to='EUR') {
        return self::where('proyecto_id', $proyectoId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->orderByDesc('created_at')
            ->first();
    }

    // Fallback global (proyecto_id = null)
    public static function fallbackGlobal($year, $month, $from='PEN', $to='EUR') {
        return self::whereNull('proyecto_id')
            ->where('year', $year)
            ->where('month', $month)
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->orderByDesc('created_at')
            ->first();
    }
}