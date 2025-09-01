<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class InventarioController extends Controller
{
    public function index($proyectoId)
    {
        $proyecto = Proyecto::findOrFail($proyectoId);

        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $inventarios = DB::table($tablaInventario)->orderBy('fecha', 'asc')->get();

        return Inertia::render('Inventarios/Index', [
            'proyecto' => $proyecto,
            'inventarios' => $inventarios
        ]);
    }

    public function create($proyectoId)
    {
        $proyecto = Proyecto::findOrFail($proyectoId);

        return Inertia::render('Inventarios/AgregarInventarios', [
            'proyecto' => $proyecto
        ]);
    }

    public function store(Request $request, $proyectoId)
    {
        $request->validate([
            'codigo' => 'required|string|max:255',
            'fecha' => 'required|date',
            'descripcion' => 'required|string',
            'categoria' => 'required|string|max:50',
            'unidad_medida' => 'required|string|max:50',
            'entradas' => 'required|integer|min:0',
            'precio' => 'required|numeric|min:0',
            'solicitado_por' => 'nullable|string|max:255',
            'proyecto_lg' => 'nullable|string|max:255',
            'comentario' => 'nullable|string', // 👈 validación
        ]);

        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $id = DB::table($tablaInventario)->insertGetId([
            'codigo' => $request->codigo,
            'fecha' => $request->fecha,
            'descripcion' => $request->descripcion,
            'unidad_medida' => $request->unidad_medida,
            'categoria' => $request->categoria,
            'entradas' => $request->entradas,
            'salidas' => 0,
            'stock' => $request->entradas,
            'precio' => $request->precio,
            'solicitado_por' => $request->solicitado_por,
            'proyecto_lg' => $request->proyecto_lg,
            'comentario' => $request->comentario, // 👈 se guarda el comentario
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create',
            'model' => $tablaInventario,
            'model_id' => $id,
            'changes' => ['new' => $request->all()],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Inventario agregado correctamente.',
                'id' => $id,
            ], 201);
        }

        return redirect()
            ->route('proyectos.inventarios', $proyectoId)
            ->with('success', 'Inventario agregado correctamente.');
    }


    public function verificarCodigo($proyectoId, $codigo)
    {
        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $existe = DB::table($tablaInventario)->where('codigo', $codigo)->exists();

        return response()->json(['existe' => $existe]);
    }

    public function edit($proyectoId, $id)
    {

        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $inventario = DB::table($tablaInventario)->where('id', $id)->first();

        if (!$inventario) {
            abort(404);
        }

        return Inertia::render('Inventarios/EditarInventario', [
            'proyecto' => $proyecto,
            'inventario' => $inventario
        ]);
    }

    public function update(Request $request, $proyectoId, $id)
    {
        $request->validate([
            'fecha' => 'required|date',
            'descripcion' => 'required|string',
            'categoria' => 'required|string|max:50',
            'unidad_medida' => 'required|string|max:50',
            'entradas' => 'required|integer|min:0',
            'salidas' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'precio' => 'required|numeric|min:0',
            'solicitado_por' => 'nullable|string|max:255',
            'proyecto_lg' => 'nullable|string|max:255',
            'comentario' => 'nullable|string', // 👈 validación
        ]);

        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        // Datos antiguos
        $oldData = DB::table($tablaInventario)->where('id', $id)->first();

        DB::table($tablaInventario)
            ->where('id', $id)
            ->update([
                'fecha' => $request->fecha,
                'descripcion' => $request->descripcion,
                'categoria' => $request->categoria,
                'unidad_medida' => $request->unidad_medida,
                'entradas' => $request->entradas,
                'salidas' => $request->salidas,
                'stock' => $request->stock,
                'precio' => $request->precio,
                'solicitado_por' => $request->solicitado_por,
                'proyecto_lg' => $request->proyecto_lg,
                'comentario' => $request->comentario, // 👈 se actualiza el comentario
                'updated_at' => now(),
            ]);

        // Datos nuevos
        $newData = DB::table($tablaInventario)->where('id', $id)->first();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'model' => $tablaInventario,
            'model_id' => $id,
            'changes' => [
                'old' => $oldData,
                'new' => $newData,
            ],
        ]);

        return redirect()->route('proyectos.inventarios', $proyectoId)
            ->with('success', 'Inventario actualizado correctamente.');
    }


    public function destroy($proyectoId, $id)
    {
        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $inventario = DB::table($tablaInventario)->where('id', $id)->first();

        DB::table($tablaInventario)->where('id', $id)->delete();

        // Log de eliminación
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'model' => $tablaInventario,
            'model_id' => $id,
            'changes' => ['deleted' => $inventario],
        ]);

        return redirect()->route('proyectos.inventarios', $proyectoId)
            ->with('success', 'Inventario eliminado correctamente.');
    }

    // Exportar inventario a Excel
    public function exportarInventario($proyecto)
    {
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto)->lower()->replace(' ', '_');

        $datos = DB::table($tablaInventario)->get();

        $carpeta = public_path('excel_consulta');
        if (!File::exists($carpeta)) {
            File::makeDirectory($carpeta, 0755, true);
        }

        $fechaHora = Carbon::now()->format('Y-m-d_H-i-s');
        $nombreArchivo = "inventario_{$proyecto}_{$fechaHora}.xlsx";
        $rutaArchivo = $carpeta . '/' . $nombreArchivo;

        (new FastExcel($datos))->export($rutaArchivo);

        return response()->download($rutaArchivo);
    }

    // Importar inventario desde Excel
    public function importarInventario(Request $request, $proyecto)
    {
        $request->validate([
            'archivo_excel' => 'required|file|mimes:xlsx,csv,ods',
        ]);

        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto)->lower()->replace(' ', '_');

        (new FastExcel)->import($request->file('archivo_excel'), function ($line) use ($tablaInventario) {
            DB::table($tablaInventario)->insert([
                'codigo' => $line['codigo'],
                'fecha' => $line['fecha'],
                'descripcion' => $line['descripcion'],
                'um' => $line['um'],
                'categoria' => $line['categoria'],
                'entradas' => $line['entradas'],
                'salidas' => 0,
                'stock' => $line['entradas'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Inventario importado correctamente.');
    }
}
