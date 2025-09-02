<?php

namespace App\Http\Controllers;

use App\Models\UnidadMedida;
use Illuminate\Http\Request;

class UnidadMedidaController extends Controller
{
    public function index()
    {
        return response()->json(UnidadMedida::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:unidades_medida,nombre',
        ]);

        $unidad = UnidadMedida::create([
            'nombre' => $request->nombre,
        ]);

        return response()->json($unidad, 201);
    }

    public function update(Request $request, UnidadMedida $unidadMedida)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:unidades_medida,nombre,' . $unidadMedida->id,
        ]);

        $unidadMedida->update(['nombre' => $request->nombre]);

        return response()->json($unidadMedida);
    }

    public function destroy(UnidadMedida $unidadMedida)
    {
        $unidadMedida->delete();

        return response()->json(['message' => 'Unidad de medida eliminada correctamente']);
    }
}
