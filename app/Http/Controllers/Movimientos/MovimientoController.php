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
     * Crear movimiento (API). Guarda movimiento principal y (opcional) movimiento de subcuenta,
     * recalcula saldos y devuelve el movimiento actualizado.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'cuenta_general_id' => 'required|exists:cuentas_generales,id',
            'fecha_operacion'   => 'required|date',
            'medio_pago'        => 'nullable|string|max:30',
            'descripcion'       => 'nullable|string',
            'deudor'            => 'nullable|numeric',
            'acreedor'          => 'nullable|numeric',
            'subcuenta_id'      => 'nullable|exists:subcuentas,id',
        ]);

        return DB::transaction(function () use ($data) {
            // calcular numero secuencial (opcional)
            $lastNumero = Movimiento::where('cuenta_general_id', $data['cuenta_general_id'])
                ->max('numero');

            $numero = $lastNumero ? $lastNumero + 1 : 1;

            // crear movimiento principal
            $mov = Movimiento::create([
                'cuenta_general_id' => $data['cuenta_general_id'],
                'numero'            => $numero,
                'fecha_operacion'   => $data['fecha_operacion'],
                'medio_pago'        => $data['medio_pago'] ?? null,
                'descripcion'       => $data['descripcion'] ?? null,
                'deudor'            => $data['deudor'] ?? 0,
                'acreedor'          => $data['acreedor'] ?? 0,
                // saldo lo dejará SaldosService al recalcular
                'saldo'             => 0,
                'subcuenta_id'      => $data['subcuenta_id'] ?? null,
            ]);

            // si tiene subcuenta, crear registro en subcuentas_movimientos
            if (!empty($data['subcuenta_id'])) {
                SubcuentaMovimiento::create([
                    'subcuenta_id' => $data['subcuenta_id'],
                    'fecha'        => $data['fecha_operacion'],
                    'descripcion'  => $data['descripcion'] ?? null,
                    'deudor'       => $data['deudor'] ?? 0,
                    'acreedor'     => $data['acreedor'] ?? 0,
                    // saldo calculado por SaldosService
                    'saldo'        => 0,
                ]);
            }

            // recalcular saldos (primero subcuenta, luego cuenta)
            if (!empty($data['subcuenta_id'])) {
                SaldosService::recalcularSubcuenta($data['subcuenta_id']);
            }
            SaldosService::recalcularCuenta($data['cuenta_general_id']);

            // devolver movimiento ya "fresh" con relaciones
            $movRefreshed = Movimiento::with('subcuenta', 'cuenta')->find($mov->id);

            return response()->json($movRefreshed, 201);
        });
    }

    /**
     * Mostrar formulario (Inertia) para crear movimiento:
     * trae la cuenta con movimientos y subcuentas + movimientos para que Vue tenga todo.
     */
    public function create(Request $request)
    {
        $cuenta = CuentaGeneral::with([
            'movimientos' => function ($q) {
                $q->orderBy('fecha_operacion')->orderBy('id');
            },
            'subcuentas.movimientos' => function ($q) {
                $q->orderBy('id');
            }
        ])->findOrFail($request->cuenta);

        return Inertia::render('Movimientos/Create', [
            'cuenta' => $cuenta
        ]);
    }

    /**
     * Editar (vista Inertia) - carga el movimiento y la cuenta con subcuentas
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
     * Mostrar movimiento (API)
     */
    public function show($id)
    {
        $mov = Movimiento::with('subcuenta', 'cuenta')->findOrFail($id);
        return response()->json($mov);
    }


    /**
     * Update movimiento, recalcular saldos y retornar a la vista.
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'fecha_operacion' => 'required|date',
            'medio_pago' => 'nullable|string',
            'descripcion' => 'nullable|string',
            'deudor' => 'nullable|numeric',
            'acreedor' => 'nullable|numeric',
            'subcuenta_id' => 'nullable|exists:subcuentas,id',
        ]);

        // --- CORRECCIÓN IMPORTANTE ---
        // Si viene vacío, convertir a 0
        $data['deudor'] = $data['deudor'] === null ? 0 : $data['deudor'];
        $data['acreedor'] = $data['acreedor'] === null ? 0 : $data['acreedor'];

        $mov = Movimiento::findOrFail($id);
        $mov->update($data);

        return redirect()->route('cuentas.show', $mov->cuenta_general_id);
    }


    /**
     * Eliminar movimiento, recalcular saldos y responder.
     */
    public function destroy($id)
    {
        $mov = Movimiento::findOrFail($id);

        $cuentaId = $mov->cuenta_general_id;
        $subId = $mov->subcuenta_id;

        $mov->delete();

        // recalcular saldos
        if ($subId) {
            SaldosService::recalcularSubcuenta($subId);
        }
        SaldosService::recalcularCuenta($cuentaId);

        // 🔥 INERTIA necesita redirect, NO JSON
        return redirect()->back()->with('success', 'Movimiento eliminado correctamente');
    }
}
