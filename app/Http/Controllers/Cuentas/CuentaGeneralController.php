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

        // Agregar el saldo actual calculado
        foreach ($cuentas as $c) {
            $saldo = $c->saldo_inicial;

            foreach ($c->movimientos as $m) {
                $saldo += ($m->deudor ?? 0) - ($m->acreedor ?? 0);
            }

            $c->saldo_actual = $saldo; // ← Se envía al frontend
        }

        return Inertia::render('Cuentas/Index', [
            'cuentas' => $cuentas
        ]);
    }


    public function show($id)
    {
        $cuenta = CuentaGeneral::findOrFail($id);

        $movimientos = Movimiento::with('subcuenta') // ← NECESARIO
                        ->where('cuenta_general_id', $id)
                        ->orderBy('fecha_operacion')
                        ->orderBy('id')
                        ->get();

        $fondos = Subcuenta::where('cuenta_id', $id)->get();

        return Inertia::render('Cuentas/Show', [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
            'fondos' => $fondos,
        ]);
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

        // ❗ NO CREAMOS movimiento de saldo inicial (evita duplicado)

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

        // Recalcular porque el saldo inicial sí afecta cálculos
        if (array_key_exists('saldo_inicial', $data)) {
            SaldosService::recalcularCuenta($c->id);
        }

        return redirect()->route('cuentas.show', $c->id);
    }

    public function destroy($id)
    {
        $c = CuentaGeneral::findOrFail($id);
        $c->delete();
        return redirect()->route('cuentas.index');
    }
}
