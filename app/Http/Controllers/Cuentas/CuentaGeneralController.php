<?php

namespace App\Http\Controllers\Cuentas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\CuentaGeneral;
use App\Models\Movimiento;
use App\Models\Subcuenta;
use App\Services\SaldosService;

class CuentaGeneralController extends Controller
{
    public function index()
    {
        $cuentas = CuentaGeneral::with('movimientos')->get();

        foreach ($cuentas as $c) {
            $c->saldo_actual = SaldosService::obtenerSaldoActualCuenta($c->id);
        }

        return Inertia::render('Cuentas/Index', [
            'cuentas' => $cuentas
        ]);
    }

    public function show($id)
    {
        $cuenta = CuentaGeneral::findOrFail($id);

        // 🔴 ORDEN CONTABLE CORRECTO: numero ASC, id ASC
        $movimientos = Movimiento::with('subcuenta')
            ->where('cuenta_general_id', $id)
            ->orderBy('numero', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($movimiento) use ($cuenta) {

                // Usar saldo de BD (ya recalculado correctamente)
                $saldo = floatval($movimiento->saldo ?? 0);

                return [
                    'id' => $movimiento->id,
                    'numero' => $movimiento->numero,
                    'fecha_operacion' => $movimiento->fecha_operacion,
                    'medio_pago' => $movimiento->medio_pago,
                    'descripcion' => $movimiento->descripcion,
                    'deudor' => floatval($movimiento->deudor ?? 0),
                    'acreedor' => floatval($movimiento->acreedor ?? 0),
                    'saldo' => $saldo,
                    'subcuenta_id' => $movimiento->subcuenta_id,
                    'subcuenta' => $movimiento->subcuenta,
                    'created_at' => $movimiento->created_at,
                    'updated_at' => $movimiento->updated_at,
                ];
            });

        $fondos = Subcuenta::where('cuenta_id', $id)->get();

        $saldo_actual = SaldosService::obtenerSaldoActualCuenta($id);

        return Inertia::render('Cuentas/Show', [
            'cuenta' => [
                'id' => $cuenta->id,
                'nombre' => $cuenta->nombre,
                'descripcion' => $cuenta->descripcion,
                'saldo_inicial' => floatval($cuenta->saldo_inicial ?? 0),
                'saldo_actual' => $saldo_actual,
                'created_at' => $cuenta->created_at,
                'updated_at' => $cuenta->updated_at,
            ],
            'movimientos' => $movimientos,
            'fondos' => $fondos,
            'ultimo_saldo' => $saldo_actual,
        ]);
    }

    /**
     * ⚠️ ESTE MÉTODO YA NO SE USA PARA SALDOS
     * Se mantiene SOLO para compatibilidad
     */
    private function calcularSaldoHastaMovimiento($movimientoId, $cuentaId)
    {
        $cuenta = CuentaGeneral::find($cuentaId);
        $saldo = floatval($cuenta->saldo_inicial ?? 0);

        $movimientos = Movimiento::where('cuenta_general_id', $cuentaId)
            ->orderBy('numero', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($movimientos as $mov) {
            if ($mov->id == $movimientoId) {
                break;
            }

            $saldo += (floatval($mov->deudor ?? 0) - floatval($mov->acreedor ?? 0));
        }

        return $saldo;
    }

    /**
     * Recalcular todos los saldos de la cuenta
     */
    public function recalcular($id)
    {
        try {
            $cuenta = CuentaGeneral::findOrFail($id);

            $saldoAnterior = SaldosService::obtenerSaldoActualCuenta($id);
            SaldosService::recalcularCuenta($id);
            $nuevoSaldo = SaldosService::obtenerSaldoActualCuenta($id);

            return redirect()->back()->with([
                'success' => 'Saldos recalculados correctamente',
                'data' => [
                    'success' => true,
                    'message' => 'Saldos recalculados correctamente',
                    'nuevo_saldo' => $nuevoSaldo,
                    'saldo_anterior' => $saldoAnterior,
                    'diferencia' => $nuevoSaldo - $saldoAnterior,
                    'cuenta' => $cuenta->nombre,
                    'fecha' => now()->format('d/m/Y H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'error' => 'Error al recalcular saldos: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Recalcular TODAS las cuentas
     */
    public function recalcularTodo()
    {
        try {
            $cuentas = CuentaGeneral::all();
            $resultados = [];

            foreach ($cuentas as $cuenta) {
                try {
                    $saldoAnterior = SaldosService::obtenerSaldoActualCuenta($cuenta->id);
                    $saldoNuevo = SaldosService::recalcularCuenta($cuenta->id);

                    $resultados[] = [
                        'id' => $cuenta->id,
                        'cuenta' => $cuenta->nombre,
                        'saldo_anterior' => $saldoAnterior,
                        'saldo_nuevo' => $saldoNuevo,
                        'diferencia' => $saldoNuevo - $saldoAnterior,
                        'estado' => 'success'
                    ];
                } catch (\Exception $e) {
                    $resultados[] = [
                        'id' => $cuenta->id,
                        'cuenta' => $cuenta->nombre,
                        'estado' => 'error',
                        'mensaje_error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Recálculo completado',
                'total_cuentas' => count($cuentas),
                'resultados' => $resultados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en recálculo global: ' . $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        return Inertia::render('Cuentas/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'saldo_inicial' => 'nullable|numeric',
        ]);

        CuentaGeneral::create($data);

        return redirect()->route('cuentas.index');
    }

    public function edit($id)
    {
        $cuenta = CuentaGeneral::findOrFail($id);
        return Inertia::render('Cuentas/Edit', ['cuenta' => $cuenta]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:150',
            'descripcion' => 'nullable|string',
            'saldo_inicial' => 'nullable|numeric',
        ]);

        $c = CuentaGeneral::findOrFail($id);
        $c->update($data);

        return redirect()->route('cuentas.show', $c->id);
    }

    public function destroy($id)
    {
        $c = CuentaGeneral::findOrFail($id);
        $c->delete();
        return redirect()->route('cuentas.index');
    }
}
