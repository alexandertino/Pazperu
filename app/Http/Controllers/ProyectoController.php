<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Proyecto;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class ProyectoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'estado' => 'required|in:En Proceso,Pausado,Terminado',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'required|date',
        ]);

        // Guardar el proyecto
        $proyecto = Proyecto::create([
            'nombre' => $request->nombre,
            'estado' => $request->estado,
            'descripcion' => $request->descripcion,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->estado === 'Terminado' ? now() : null,
        ]);

        // Formato del nombre de tabla
        $tableInventario = 'inventario_proyecto_' . strtolower(str_replace(' ', '_', $request->nombre));
        $tableSalidas    = 'salidas_proyecto_' . strtolower(str_replace(' ', '_', $request->nombre));

        // Crear tabla Inventarios
        if (!Schema::hasTable($tableInventario)) {
            Schema::create($tableInventario, function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->unique();
                $table->date('fecha');
                $table->string('descripcion');
                $table->string("categoria");
                $table->string('unidad_medida');
                $table->integer('entradas')->default(0);
                $table->integer('salidas')->default(0);
                $table->integer('stock')->default(0);
                $table->decimal('precio', 10, 2)->default(0);
                $table->string('solicitado_por')->nullable();
                $table->string('proyecto_lg')->nullable();
                $table->text('comentario')->nullable();
                $table->timestamps();
            });
        }

        // Crear tabla Salidas
        if (!Schema::hasTable($tableSalidas)) {
            Schema::create($tableSalidas, function (Blueprint $table) {
                $table->id();
                $table->string('n_acta');
                $table->string('nombre');
                $table->string('lugar');
                $table->string('distrito');
                $table->date('fecha');
                $table->string('producto');
                $table->integer('cantidad');
                $table->timestamps();
            });
        }

        

        return redirect()->back()->with('success', 'Proyecto y tablas creados correctamente.');
    }
    public function index()
    {
        $proyectos = Proyecto::all();

        return inertia('Proyecto', [
            'proyectos' => $proyectos
        ]);
    }

}