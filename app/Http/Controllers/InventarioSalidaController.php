<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventarioSalidaController extends Controller
{
    public function show($id)
    {
        $proyecto = Proyecto::findOrFail($id);

        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $inventarios = DB::table($tablaInventario)->orderBy('fecha', 'asc')->get();
        $salidas = DB::table($tablaSalidas)->orderBy('fecha', 'asc')->get();

        return inertia('InventarioSalidas', [
            'proyecto' => $proyecto,
            'inventarios' => $inventarios,
            'salidas' => $salidas
        ]);
    }
}
