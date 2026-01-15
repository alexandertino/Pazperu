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
    /**
     * LISTA DE FONDOS
     * 👉 ORDENADOS por la cuenta principal (NO por fecha)
     */
    public function index()
    {
        $fondos = Subcuenta::with('cuenta')
            ->orderBy('cuenta_id', 'asc') // orden de la cuenta principal
            ->orderBy('id', 'asc')        // orden interno estable
            ->get();

        return Inertia::render("Fondos/Index", [
            "fondos" => $fondos,
        ]);
    }

    /**
     * FORM CREAR
     */
    public function create()
    {
        return Inertia::render("Fondos/Create", [
            "cuentas" => CuentaGeneral::orderBy('id', 'asc')->get(),
        ]);
    }

    /**
     * DETALLE DE UN FONDO
     * 👉 Movimientos ordenados por ID (orden real)
     */
    public function show($id)
    {
        $subcuenta = Subcuenta::findOrFail($id);

        // Movimientos SOLO de esta subcuenta
        // ❌ NO por fecha
        // ✅ Por ID (orden lógico)
        $movimientos = Movimiento::where('subcuenta_id', $id)
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Subcuentas/Show', [
            'subcuenta' => $subcuenta,
            'movimientos' => $movimientos,
        ]);
    }

    /**
     * GUARDAR
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            "cuenta_id"      => "required|exists:cuentas_generales,id",
            "nombre"         => "required|string",
            "saldo_inicial"  => "required|numeric",
            "descripcion"    => "nullable|string",
        ]);

        return DB::transaction(function () use ($data) {

            // Crear subcuenta
            $sub = Subcuenta::create([
                "cuenta_id"     => $data["cuenta_id"],
                "nombre"        => $data["nombre"],
                "saldo_inicial" => $data["saldo_inicial"],
                "descripcion"   => $data["descripcion"] ?? null,
            ]);

            // Movimiento inicial de la subcuenta
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

    /**
     * EDITAR
     */
    public function edit($id)
    {
        return Inertia::render("Fondos/Edit", [
            "fondo"   => Subcuenta::findOrFail($id),
            "cuentas" => CuentaGeneral::orderBy('id', 'asc')->get(),
        ]);
    }

    /**
     * ACTUALIZAR
     */
    public function update(Request $request, $id)
    {
        $sub = Subcuenta::findOrFail($id);

        $data = $request->validate([
            "cuenta_id"   => "required|exists:cuentas_generales,id",
            "nombre"      => "required|string",
            "descripcion" => "nullable|string",
        ]);

        $sub->update($data);

        return redirect()->route("fondos.index");
    }
    public function detalle($id)
    {
        $movimientos = Movimiento::where('cuenta_general_id', $id)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->values(); // IMPORTANTE

        return Inertia::render('Fondos/Detalle', [
            'movimientos' => $movimientos,
        ]);
    }

    /**
     * BORRAR
     */
    public function destroy($id)
    {
        Subcuenta::findOrFail($id)->delete();
        return redirect()->route("fondos.index");
    }
    
}
