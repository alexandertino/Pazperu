<?php

namespace App\Http\Controllers\Fondos;

use App\Models\Subcuenta;
use App\Http\Controllers\Controller;
use App\Models\CuentaGeneral;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Movimiento;
use Illuminate\Support\Facades\DB;
use App\Services\SaldosService;
use App\Models\SubcuentaMovimiento;

class SubcuentaController extends Controller
{
    // LISTA DE FONDOS
    public function index()
    {
        return Inertia::render("Fondos/Index", [
            "fondos" => Subcuenta::with("cuenta")->get(),
        ]);
    }

    // FORM CREAR
    public function create()
    {
        return Inertia::render("Fondos/Create", [
            "cuentas" => CuentaGeneral::all(),
        ]);
    }
    public function show($id)
    {
        $subcuenta = Subcuenta::findOrFail($id);

        // Movimientos SOLO de esta subcuenta
        $movimientos = Movimiento::where('subcuenta_id', $id)
                    ->orderBy('fecha_operacion')
                    ->orderBy('id')
                    ->get();

        return Inertia::render('Subcuentas/Show', [
            'subcuenta' => $subcuenta,
            'movimientos' => $movimientos,
        ]);
    }
    // GUARDAR
    public function store(Request $request)
    {
        $data = $request->validate([
            "cuenta_id"      => "required|exists:cuentas_generales,id",
            "nombre"         => "required|string",
            "saldo_inicial"  => "required|numeric",
            "descripcion"    => "nullable|string",
        ]);

        return DB::transaction(function () use ($data) {

            $sub = Subcuenta::create([
                "cuenta_id"     => $data["cuenta_id"],
                "nombre"        => $data["nombre"],
                "saldo_inicial" => $data["saldo_inicial"],
                "descripcion"   => $data["descripcion"] ?? null,
            ]);

            SubcuentaMovimiento::create([
                "subcuenta_id" => $sub->id,
                "fecha"        => now(),
                "descripcion"  => "Saldo inicial",
                "deudor"       => $data["saldo_inicial"],
                "acreedor"     => 0,
                "saldo"        => $data["saldo_inicial"],
            ]);

            return redirect()->route("fondos.index");
        });
    }



    // EDITAR
    public function edit($id)
    {
        return Inertia::render("Fondos/Edit", [
            "fondo"   => Subcuenta::findOrFail($id),
            "cuentas" => CuentaGeneral::all(),
        ]);
    }

    // ACTUALIZAR
    public function update(Request $request, $id)
    {
        $sub = Subcuenta::findOrFail($id);

        $data = $request->validate([
            "cuenta_id" => "required|exists:cuenta_id",
            "nombre"            => "required|string",
            "descripcion"       => "nullable|string",
        ]);

        $sub->update($data);

        return redirect()->route("fondos.index");
    }

    // BORRAR
    public function destroy($id)
    {
        Subcuenta::findOrFail($id)->delete();
        return redirect()->route("fondos.index");
    }
}
