<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Persona;

class PersonaController extends Controller
{
    public function index()
    {
        return response()->json(Persona::all());
    }

    // 📌 Guardar nueva persona
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'lugar' => 'nullable|string|max:255',
            'distrito' => 'nullable|string|max:255',
        ]);

        $persona = Persona::create($validated);

        return response()->json([
            'message' => 'Persona registrada correctamente',
            'persona' => $persona
        ], 201);
    }
}
