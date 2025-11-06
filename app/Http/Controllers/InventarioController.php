<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\Categoria; 
use App\Models\UnidadMedida;         
use App\Models\Solicitante;
use App\Models\InventarioVinculado;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

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

    $categorias = Categoria::select('id','nombre')->orderBy('nombre')->get();
    $UnidadMedidas = UnidadMedida::select('id','nombre')->orderBy('nombre')->get();
    $solicitantes = Solicitante::select('id','nombre')->orderBy('nombre')->get();

    return Inertia::render('Inventarios/AgregarInventarios', [
        'proyecto' => $proyecto,
        'categorias' => $categorias,
        'UnidadMedida' => $UnidadMedidas,
        'solicitantes' => $solicitantes,
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
            'comentario' => 'nullable|string',
        ]);

        $proyecto = Proyecto::findOrFail($proyectoId);

        // name de la tabla inventario dinámica del proyecto (igual que tú)
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        // Tomar am_table y am_row_id desde query (puede venir por GET o como hidden input)
        $amTable = $request->query('am_table', $request->input('am_table', null));
        $amRowId = $request->query('am_row_id', $request->input('am_row_id', null));

        // Opcional: validar que si vienen, amRowId sea integer
        if ($amRowId !== null && !ctype_digit((string)$amRowId)) {
            return back()->withErrors(['am_row_id' => 'am_row_id inválido'])->withInput();
        }

        try {
            DB::beginTransaction();

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
                'comentario' => $request->comentario,
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

            // Si no vienen datos AM, sólo commit y redirect normal
            if (empty($amTable) || empty($amRowId)) {
                DB::commit();

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

            // --- Seguridad: verificar que am_table pertenece a este proyecto ---
            $expectedBase = \Illuminate\Support\Str::slug($proyecto->nombre, '_');
            $expectedBase = preg_replace('/[^a-z0-9_]/', '', strtolower($expectedBase));
            $expectedCaja  = 'am_caja_proyecto_' . $expectedBase;
            $expectedBanco = 'am_banco_proyecto_' . $expectedBase;

            if (! in_array($amTable, [$expectedCaja, $expectedBanco], true)) {
                // no coincide: rollback y error
                DB::rollBack();
                return back()->withErrors(['am_table' => 'La tabla AM no corresponde al proyecto.'])->withInput();
            }

            // Buscar placeholder existente (status draft) que coincida
            $inventarioVinculado = \App\Models\InventarioVinculado::where('proyecto_id', $proyectoId)
                ->where('am_table', $amTable)
                ->where('am_row_id', $amRowId)
                ->where('status', 'draft')
                ->first();

            if ($inventarioVinculado) {
                // Actualizar placeholder con referencia al inventario creado
                $inventarioVinculado->update([
                    'inventario_table' => $tablaInventario,
                    'inventario_row_id' => $id,
                    'cantidad' => $request->entradas,               // opcional
                    'descripcion' => $inventarioVinculado->descripcion ?? $request->descripcion,
                    'fecha' => $inventarioVinculado->fecha ?? $request->fecha,
                    'status' => 'linked', // puedes usar 'done' o 'linked' según tu flujo
                    'updated_by' => Auth::id(),
                ]);
            } else {
                // Si no existe placeholder, crear uno nuevo y vincularlo
                \App\Models\InventarioVinculado::create([
                    'proyecto_id' => $proyectoId,
                    'am_table'    => $amTable,
                    'am_row_id'   => $amRowId,
                    'inventario_table' => $tablaInventario,
                    'inventario_row_id' => $id,
                    'descripcion' => $request->descripcion,
                    'fecha' => $request->fecha,
                    'cantidad' => $request->entradas,
                    'meta' => null,
                    'status' => 'linked',
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Inventario agregado y vinculado correctamente.',
                    'id' => $id,
                    'am_table' => $amTable,
                    'am_row_id' => $amRowId,
                ], 201);
            }

            // Redirigir a la lista de inventarios del proyecto (o donde prefieras)
            return redirect()
                ->route('proyectos.inventarios', $proyectoId)
                ->with('success', 'Inventario agregado y vinculado correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error guardando inventario y vinculando AM: ' . $e->getMessage(), [
                'proyecto' => $proyectoId,
                'am_table' => $amTable,
                'am_row_id' => $amRowId,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Error al guardar inventario.', 'error' => $e->getMessage()], 500);
            }

            return back()->withErrors(['error' => 'Error al guardar inventario: ' . $e->getMessage()])->withInput();
        }
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
            'comentario' => 'nullable|string',
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
                'comentario' => $request->comentario,
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
    }


    public function destroy($proyectoId, $id)
    {
        DB::beginTransaction();

        try {
            $proyecto = Proyecto::findOrFail($proyectoId);
            $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

            $inventario = DB::table($tablaInventario)->where('id', $id)->first();

            if (!$inventario) {
                DB::rollBack();
                return response()->json(['message' => 'Inventario no encontrado'], 404);
            }

            DB::table($tablaInventario)->where('id', $id)->delete();

            $inventarioArray = (array) $inventario;

            ActivityLog::create([
                'user_id'  => Auth::id(),
                'action'   => 'delete',
                'model'    => $tablaInventario,
                'model_id' => $id,
                'changes'  => ['deleted' => $inventarioArray],
            ]);

            DB::commit();

            return response()->json(['message' => 'Inventario eliminado correctamente.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error eliminando inventario (proyecto: $proyectoId, id: $id): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al eliminar inventario', 'error' => $e->getMessage()], 500);
        }
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
    public function recibir(Proyecto $proyecto, Request $request)
    {
        return inertia('Inventarios/create', [
            'proyecto' => $proyecto,
            'datos_precargados' => $request->all(),
        ]);
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
