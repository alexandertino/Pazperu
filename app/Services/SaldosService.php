<?php

namespace App\Services;

use App\Models\CuentaGeneral;
use App\Models\Movimiento;
use App\Models\Subcuenta;
use App\Models\SubcuentaMovimiento;
use Illuminate\Support\Facades\DB;

class SaldosService
{
    // Recalcula saldos de todos los movimientos de una cuenta
    public static function recalcularCuenta(int $cuentaId): void
    {
        DB::transaction(function () use ($cuentaId) {
            $cuenta = CuentaGeneral::findOrFail($cuentaId);
            $movs = Movimiento::where('cuenta_general_id', $cuentaId)
                        ->orderBy('fecha_operacion')
                        ->orderBy('id')
                        ->get();

            $saldo = floatval($cuenta->saldo_inicial ?? 0);

            foreach ($movs as $m) {
                $saldo = $saldo + floatval($m->deudor) - floatval($m->acreedor);
                if ((string)$m->saldo !== (string)($saldo)) {
                    $m->saldo = $saldo;
                    $m->saveQuietly();
                }
            }
        });
    }

    // Recalcula saldos de una subcuenta
    public static function recalcularSubcuenta(int $subcuentaId): void
    {
        DB::transaction(function () use ($subcuentaId) {
            $sub = Subcuenta::findOrFail($subcuentaId);
            $movs = SubcuentaMovimiento::where('subcuenta_id', $subcuentaId)
                        ->orderBy('fecha')
                        ->orderBy('id')
                        ->get();

            $saldo = floatval($sub->saldo_inicial ?? 0);

            foreach ($movs as $m) {
                $saldo = $saldo + floatval($m->deudor) - floatval($m->acreedor);
                if ((string)$m->saldo !== (string)($saldo)) {
                    $m->saldo = $saldo;
                    $m->saveQuietly();
                }
            }
        });
    }
}
