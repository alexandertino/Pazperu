<?php

namespace App\Http\Controllers\Movimientos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Movimiento;
use App\Models\SubcuentaMovimiento;
use App\Services\SaldosService;
use App\Models\CuentaGeneral;
use Inertia\Inertia;

class MovimientoController extends Controller
{
    /**
     * Convierte valores monetarios con formato "S/1,000.00" a float
     */
    private function convertirValorMonetario($valor)
    {
        if (is_null($valor)) {
            return 0.00;
        }

        if (is_numeric($valor)) {
            return floatval($valor);
        }

        if (is_string($valor)) {
            $limpio = str_replace(['S/', ' ', ','], '', $valor);
            return floatval($limpio);
        }

        return 0.00;
    }

    /**
     * Lista API de movimientos (opcionalmente por cuenta).
     */
    public function index(Request $request)
    {
        $q = Movimiento::with('subcuenta', 'cuenta');

        if ($request->filled('cuenta')) {
            $q->where('cuenta_general_id', $request->get('cuenta'));
        }

        $movimientos = $q->orderBy('fecha_operacion')->orderBy('id')->get();

        return response()->json($movimientos);
    }

    /**
     * Mostrar formulario Inertia para crear movimiento.
     * IMPORTANTE: Usa 'Movimientos/Create' (con S)
     */
    public function create(Request $request)
    {
        $cuentaId = $request->id;

        if (!$cuentaId) {
            return redirect()->route('cuentas.index')
                ->with('error', 'Debe seleccionar una cuenta.');
        }

        // Obtener cuenta y subcuentas
        $cuenta = CuentaGeneral::with('subcuentas')->find($cuentaId);

        if (!$cuenta) {
            return redirect()->route('cuentas.index')
                ->with('error', 'Cuenta no encontrada.');
        }

        // Último movimiento
        $ultimoMovimiento = Movimiento::where('cuenta_general_id', $cuentaId)
            ->orderBy('id', 'desc')
            ->first();

        $ultimoSaldo = $ultimoMovimiento
            ? floatval($ultimoMovimiento->saldo)
            : floatval($cuenta->saldo_inicial);

        // ¡IMPORTANTE! Usar 'Movimientos/Create' (con S) para consistencia
        return Inertia::render('Movimientos/Create', [
            'cuenta' => $cuenta,
            'ultimo_saldo' => $ultimoSaldo,
        ]);
    }

    /**
     * Crear movimiento (store).
     * Recalcula TODA la cuenta después de crear.
     */
    public function store(Request $request)
    {
        $request->validate([
            'cuenta_general_id' => 'required|integer|exists:cuentas_generales,id',
            'fecha_operacion'   => 'required|date',
            'descripcion'       => 'required|string',
            'deudor'            => 'nullable|numeric',
            'acreedor'          => 'nullable|numeric',
            'medio_pago'        => 'nullable|string|max:50',
            'subcuenta_id'      => 'nullable|integer|exists:subcuentas,id',
        ]);

        return DB::transaction(function () use ($request) {
            $deudor = $request->deudor === null ? 0 : floatval($request->deudor);
            $acreedor = $request->acreedor === null ? 0 : floatval($request->acreedor);

            // 1. Obtener el último número y sumar 1
            // Se recomienda usar lockForUpdate() para evitar que dos procesos obtengan el mismo número simultáneamente
            $ultimoNumero = Movimiento::where('cuenta_general_id', $request->cuenta_general_id)
                ->lockForUpdate()
                ->max('numero') ?? 0;

            $nuevoNumero = $ultimoNumero + 1;

            // 2. Crear movimiento con el número correlativo
            $mov = Movimiento::create([
                'cuenta_general_id' => $request->cuenta_general_id,
                'numero'            => $nuevoNumero, // Nueva columna
                'fecha_operacion'   => $request->fecha_operacion,
                'descripcion'       => $request->descripcion,
                'deudor'            => $deudor,
                'acreedor'          => $acreedor,
                'saldo'             => 0,
                'medio_pago'        => $request->medio_pago,
                'subcuenta_id'      => $request->subcuenta_id,
            ]);

            if (!empty($request->subcuenta_id)) {
                SubcuentaMovimiento::create([
                    'subcuenta_id' => $request->subcuenta_id,
                    'fecha'        => $request->fecha_operacion,
                    'descripcion'  => $request->descripcion,
                    'deudor'       => $deudor,
                    'acreedor'     => $acreedor,
                    'saldo'        => 0,
                ]);
                SaldosService::recalcularSubcuenta($request->subcuenta_id);
            }

            SaldosService::recalcularCuenta($request->cuenta_general_id);

            return redirect()->route('cuentas.show', $request->cuenta_general_id)
                ->with('success', "Movimiento #{$nuevoNumero} registrado correctamente.");
        });
    }


    /**
     * Editar (vista Inertia) - carga el movimiento y la cuenta con subcuentas
     * IMPORTANTE: Usa 'Movimientos/Edit' (con S)
     */
    public function edit($id)
    {
        $mov = Movimiento::with('subcuenta')->findOrFail($id);
        $cuenta = CuentaGeneral::with('subcuentas')->findOrFail($mov->cuenta_general_id);

        return Inertia::render('Movimientos/Edit', [
            'movimiento' => $mov,
            'cuenta' => $cuenta
        ]);
    }

    /**
     * Update movimiento.
     * Recalcula TODA la cuenta después de editar.
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'fecha_operacion' => 'required|date',
            'medio_pago' => 'nullable|string',
            'descripcion' => 'required|string',
            'deudor' => 'nullable|numeric',
            'acreedor' => 'nullable|numeric',
            'subcuenta_id' => 'nullable|exists:subcuentas,id',
        ]);

        $data['deudor'] = $data['deudor'] ?? 0;
        $data['acreedor'] = $data['acreedor'] ?? 0;

        $mov = Movimiento::findOrFail($id);

        // Guardar ID de subcuenta anterior (si cambia)
        $subcuentaAnteriorId = $mov->subcuenta_id;
        $nuevaSubcuentaId = $data['subcuenta_id'] ?? null;

        // Actualizar movimiento
        $mov->update($data);

        // Si cambió la subcuenta, actualizar SubcuentaMovimiento
        if ($subcuentaAnteriorId != $nuevaSubcuentaId) {
            // Eliminar registro anterior si existía
            if ($subcuentaAnteriorId) {
                SubcuentaMovimiento::where('subcuenta_id', $subcuentaAnteriorId)
                    ->where('fecha', $mov->fecha_operacion)
                    ->where('descripcion', $mov->descripcion)
                    ->delete();

                // Recalcular subcuenta anterior
                SaldosService::recalcularSubcuenta($subcuentaAnteriorId);
            }

            // Crear nuevo registro si hay nueva subcuenta
            if ($nuevaSubcuentaId) {
                SubcuentaMovimiento::create([
                    'subcuenta_id' => $nuevaSubcuentaId,
                    'fecha'        => $mov->fecha_operacion,
                    'descripcion'  => $mov->descripcion,
                    'deudor'       => $mov->deudor,
                    'acreedor'     => $mov->acreedor,
                    'saldo'        => 0, // Provisional
                ]);
            }
        }

        // ¡IMPORTANTE! Recalcular TODA la cuenta
        SaldosService::recalcularCuenta($mov->cuenta_general_id);

        // Si hay subcuenta, también recalcularla
        if ($nuevaSubcuentaId) {
            SaldosService::recalcularSubcuenta($nuevaSubcuentaId);
        }

        return redirect()->route('cuentas.show', $mov->cuenta_general_id)
            ->with('success', 'Movimiento actualizado correctamente.');
    }

    /**
     * Mostrar movimiento (API)
     */
    public function show($id)
    {
        $mov = Movimiento::with('subcuenta', 'cuenta')->findOrFail($id);
        return response()->json($mov);
    }

    /**
     * Eliminar movimiento, recalcular saldos y responder.
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $mov = Movimiento::findOrFail($id);

            $cuentaId = $mov->cuenta_general_id;
            $subcuentaId = $mov->subcuenta_id;

            // Eliminar movimiento
            $mov->delete();

            // Si tenía subcuenta, eliminar registro y recalcular
            if ($subcuentaId) {
                SubcuentaMovimiento::where('subcuenta_id', $subcuentaId)
                    ->where('fecha', $mov->fecha_operacion)
                    ->where('descripcion', $mov->descripcion)
                    ->delete();

                SaldosService::recalcularSubcuenta($subcuentaId);
            }

            // ¡IMPORTANTE! Recalcular TODA la cuenta
            SaldosService::recalcularCuenta($cuentaId);

            return redirect()->back()->with('success', 'Movimiento eliminado correctamente');
        });
    }

    /**
     * Método para forzar recálculo de una cuenta (útil para debugging)
     */
    public function recalcular($cuentaId)
    {
        $saldoFinal = SaldosService::recalcularCuenta($cuentaId);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta recalculada correctamente',
            'saldo_final' => $saldoFinal
        ]);
    }
}
