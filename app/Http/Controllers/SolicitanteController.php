<?php

namespace App\Http\Controllers;

use App\Models\Solicitante;
use Illuminate\Http\Request;

class SolicitanteController extends Controller
{
    public function index()
    {
        return response()->json(Solicitante::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:solicitantes,nombre',
        ]);

        $solicitante = Solicitante::create([
            'nombre' => $request->nombre,
        ]);

        return response()->json($solicitante, 201);
    }

    public function update(Request $request, Solicitante $solicitante)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:solicitantes,nombre,' . $solicitante->id,
        ]);

        $solicitante->update(['nombre' => $request->nombre]);

        return response()->json($solicitante);
    }

    public function destroy(Solicitante $solicitante)
    {
        $solicitante->delete();

        return response()->json(['message' => 'Solicitante eliminado correctamente']);
    }
}
