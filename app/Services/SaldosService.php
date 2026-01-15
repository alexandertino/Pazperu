<?php

namespace App\Services;

use App\Models\Movimiento;
use App\Models\Subcuenta;
use Illuminate\Support\Facades\DB;
use App\Models\CuentaGeneral;
use App\Models\SubcuentaMovimiento;

class SaldosService
{
    /**
     * Recalcula TODOS los saldos de una cuenta
     * ORDEN CONTABLE: numero ASC, id ASC
     */
    public static function recalcularCuenta($cuentaId)
    {
        return DB::transaction(function () use ($cuentaId) {

            $cuenta = CuentaGeneral::find($cuentaId);
            if (!$cuenta) {
                return 0;
            }

            $saldo = floatval($cuenta->saldo_inicial ?? 0);

            // 👉 ORDEN CORRECTO (NO por ID)
            $movimientos = Movimiento::where('cuenta_general_id', $cuentaId)
                ->orderBy('numero', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($movimientos as $movimiento) {

                $deudor   = floatval($movimiento->deudor ?? 0);
                $acreedor = floatval($movimiento->acreedor ?? 0);

                $saldo += ($deudor - $acreedor);

                // Guardar saldo acumulado
                $movimiento->saldo = $saldo;
                $movimiento->save();

                // Recalcular subcuenta si aplica
                if ($movimiento->subcuenta_id) {
                    self::recalcularSubcuenta($movimiento->subcuenta_id);
                }
            }

            return $saldo;
        });
    }

    /**
     * Recalcula los saldos de una subcuenta
     * (se mantiene por ID porque no tienes campo numero allí)
     */
    public static function recalcularSubcuenta($subcuentaId)
    {
        return DB::transaction(function () use ($subcuentaId) {

            $subcuenta = Subcuenta::find($subcuentaId);
            if (!$subcuenta) {
                return 0;
            }

            $saldo = floatval($subcuenta->saldo_inicial ?? 0);

            $movimientos = SubcuentaMovimiento::where('subcuenta_id', $subcuentaId)
                ->orderBy('id', 'asc')
                ->get();

            foreach ($movimientos as $movimiento) {

                $deudor   = floatval($movimiento->deudor ?? 0);
                $acreedor = floatval($movimiento->acreedor ?? 0);

                $saldo += ($deudor - $acreedor);

                $movimiento->saldo = $saldo;
                $movimiento->save();
            }

            $subcuenta->saldo_actual = $saldo;
            $subcuenta->save();

            return $saldo;
        });
    }

    /**
     * Obtiene el saldo actual de una cuenta (sin modificar BD)
     * MISMO ORDEN CONTABLE
     */
    public static function obtenerSaldoActualCuenta($cuentaId)
    {
        $cuenta = CuentaGeneral::find($cuentaId);
        if (!$cuenta) {
            return 0;
        }

        $saldo = floatval($cuenta->saldo_inicial ?? 0);

        $movimientos = Movimiento::where('cuenta_general_id', $cuentaId)
            ->orderBy('numero', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($movimientos as $movimiento) {
            $deudor   = floatval($movimiento->deudor ?? 0);
            $acreedor = floatval($movimiento->acreedor ?? 0);
            $saldo += ($deudor - $acreedor);
        }

        return $saldo;
    }
}
