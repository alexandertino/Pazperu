<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class InventariosVinculadosController extends Controller
{
    /**
     * Listar inventarios vinculados del proyecto
     */
    public function index(Proyecto $proyecto)
    {
        $items = DB::table('inventarios_vinculados')
            ->where('proyecto_id', $proyecto->id)
            ->orderBy('id', 'desc')
            ->get();

        return inertia('proyectos/inventarios/index', [
            'proyecto' => $proyecto,
            'inventarios' => $items,
        ]);
    }

    /**
     * Mostrar formulario de creación/edición.
     * Si llega inventario_id -> cargamos ese registro para editar (placeholder).
     * Si llega am_table+am_row_id -> prefill desde el movimiento AM si pertenece al proyecto.
     */
    public function create(Proyecto $proyecto, Request $request)
    {
        $inventarioId = $request->query('inventario_id');
        $amTable = $request->query('am_table');
        $amRowId = $request->query('am_row_id');

        // Reconstruir tablas válidas para este proyecto (seguridad)
        $base = Str::slug($proyecto->nombre, '_');
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower($base));
        $expectedCaja  = 'am_caja_proyecto_' . $base;
        $expectedBanco = 'am_banco_proyecto_' . $base;

        $prefill = [
            'inventario_id' => null,
            'am_table' => null,
            'am_row_id' => null,
            'descripcion' => null,
            'fecha' => null,
            'cantidad' => 1,
            'meta' => null,
            'status' => null,
        ];

        if ($inventarioId) {
            $item = DB::table('inventarios_vinculados')->where('id', $inventarioId)->first();
            if (! $item || $item->proyecto_id != $proyecto->id) {
                return redirect()->route('proyectos.inventarios.index', $proyecto->id)
                    ->with('error', 'Inventario no encontrado o no corresponde al proyecto.');
            }

            $prefill = [
                'inventario_id' => $item->id,
                'am_table' => $item->am_table,
                'am_row_id' => $item->am_row_id,
                'descripcion' => $item->descripcion,
                'fecha' => $item->fecha,
                'cantidad' => $item->cantidad,
                'meta' => $item->meta ? json_decode($item->meta, true) : null,
                'status' => $item->status ?? null,
            ];

            return inertia('proyectos/inventarios/AgregarInventarios', [
                'proyecto' => $proyecto,
                'prefill' => $prefill,
            ]);
        }

        // Si no viene inventario_id, intentar prefill desde am_table/am_row_id (si válidos)
        if ($amTable && in_array($amTable, [$expectedCaja, $expectedBanco], true) && $amRowId) {
            if (Schema::hasTable($amTable)) {
                $am = DB::table($amTable)->where('id', $amRowId)->first();
                if ($am) {
                    $prefill['am_table'] = $amTable;
                    $prefill['am_row_id'] = $amRowId;
                    $prefill['descripcion'] = $am->descripcion ?? null;
                    $prefill['fecha'] = $am->fecha ?? null;
                }
            }
        }

        return inertia('proyectos/inventarios/AgregarInventarios', [
            'proyecto' => $proyecto,
            'prefill' => $prefill,
        ]);
    }

    /**
     * Store: crea inventario nuevo o actualiza placeholder existente (si inventario_id viene).
     * Al actualizar un placeholder se marca status='complete'.
     */
    public function store(Proyecto $proyecto, Request $request)
    {
        $data = $request->validate([
            'inventario_id' => 'nullable|integer',
            'descripcion' => 'nullable|string|max:255',
            'fecha' => 'nullable|date',
            'cantidad' => 'nullable|integer|min:1',
            'meta' => 'nullable',
            'am_table' => 'nullable|string',
            'am_row_id' => 'nullable|integer',
        ]);

        $inventarioId = $data['inventario_id'] ?? null;
        $descripcion = $data['descripcion'] ?? null;
        $fecha = $data['fecha'] ?? null;
        $cantidad = isset($data['cantidad']) ? (int)$data['cantidad'] : 1;
        $metaRaw = $data['meta'] ?? null;
        $amTable = $data['am_table'] ?? null;
        $amRowId = $data['am_row_id'] ?? null;

        // Reconstruir tablas válidas para seguridad
        $base = Str::slug($proyecto->nombre, '_');
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower($base));
        $expectedCaja  = 'am_caja_proyecto_' . $base;
        $expectedBanco = 'am_banco_proyecto_' . $base;
        $allowedTables = [$expectedCaja, $expectedBanco];

        if ($amTable && ! in_array($amTable, $allowedTables, true)) {
            return back()->with('error', 'Tabla AM inválida.')->withInput();
        }

        // Si hay AM vinculada, verificar existencia de tabla y fila
        if ($amTable) {
            if (! Schema::hasTable($amTable)) {
                return back()->with('error', 'La tabla AM indicada no existe.')->withInput();
            }
            if (! $amRowId || ! DB::table($amTable)->where('id', $amRowId)->exists()) {
                return back()->with('error', 'Registro AM no encontrado.')->withInput();
            }
        }

        // Normalizar meta a JSON o null
        $metaToStore = null;
        if (is_array($metaRaw)) {
            $metaToStore = json_encode($metaRaw);
        } elseif (is_string($metaRaw) && $metaRaw !== '') {
            $decoded = json_decode($metaRaw, true);
            $metaToStore = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : json_encode(['raw' => $metaRaw]);
        }

        try {
            DB::beginTransaction();

            if ($inventarioId) {
                // Actualizar placeholder existente
                $item = DB::table('inventarios_vinculados')->where('id', $inventarioId)->first();
                if (! $item || $item->proyecto_id != $proyecto->id) {
                    DB::rollBack();
                    return back()->with('error', 'Inventario inválido.')->withInput();
                }

                DB::table('inventarios_vinculados')->where('id', $inventarioId)->update([
                    'descripcion' => $descripcion ?? $item->descripcion,
                    'fecha' => $fecha ?? $item->fecha,
                    'cantidad' => $cantidad ?? $item->cantidad,
                    'meta' => $metaToStore ?? $item->meta,
                    'status' => 'complete',
                    'updated_at' => now(),
                ]);

                DB::commit();

                if ($request->expectsJson()) {
                    return response()->json([
                        'ok' => true,
                        'message' => 'Inventario actualizado y vinculación completada.',
                        'id' => $inventarioId,
                    ], 200);
                }

                return redirect()->route('proyectos.inventarios.show', [$proyecto->id, $inventarioId])
                    ->with('success', 'Inventario actualizado y vinculación completada.');
            }

            // Crear nuevo inventario (sin placeholder)
            $newId = DB::table('inventarios_vinculados')->insertGetId([
                'proyecto_id' => $proyecto->id,
                'am_table' => $amTable,
                'am_row_id' => $amRowId,
                'descripcion' => $descripcion,
                'fecha' => $fecha,
                'cantidad' => $cantidad,
                'meta' => $metaToStore,
                'status' => 'complete',
                'created_by' => optional($request->user())->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Inventario creado correctamente.',
                    'id' => $newId,
                ], 201);
            }

            return redirect()->route('proyectos.inventarios.show', [$proyecto->id, $newId])
                ->with('success', 'Inventario creado correctamente.');
        } catch (QueryException $qe) {
            DB::rollBack();
            Log::error('QueryException inventarios_vinculados store: ' . $qe->getMessage(), ['bindings' => $qe->getBindings() ?? null]);
            return back()->with('error', 'Error en base de datos: ' . $qe->getMessage())->withInput();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Exception inventarios_vinculados store: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Error al guardar inventario: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar inventario vinculado
     */
    public function show(Proyecto $proyecto, $id)
    {
        $item = DB::table('inventarios_vinculados')->where('id', $id)->first();
        if (! $item || $item->proyecto_id != $proyecto->id) {
            abort(404, 'Inventario no encontrado.');
        }

        return inertia('proyectos/inventarios/show', [
            'proyecto' => $proyecto,
            'inventario' => $item,
        ]);
    }

    /**
     * Edit (muestra formulario de edición para un inventario existente)
     */
    public function edit(Proyecto $proyecto, $id)
    {
        $item = DB::table('inventarios_vinculados')->where('id', $id)->first();
        if (! $item || $item->proyecto_id != $proyecto->id) {
            return redirect()->route('proyectos.inventarios.index', $proyecto->id)
                ->with('error', 'Inventario no encontrado.');
        }

        $prefill = [
            'inventario_id' => $item->id,
            'am_table' => $item->am_table,
            'am_row_id' => $item->am_row_id,
            'descripcion' => $item->descripcion,
            'fecha' => $item->fecha,
            'cantidad' => $item->cantidad,
            'meta' => $item->meta ? json_decode($item->meta, true) : null,
            'status' => $item->status ?? null,
        ];

        return inertia('proyectos/inventarios/AgregarInventarios', [
            'proyecto' => $proyecto,
            'prefill' => $prefill,
        ]);
    }

    /**
     * Update: alternativa si prefieres usar PUT/PATCH para actualizar
     */
    public function update(Proyecto $proyecto, Request $request, $id)
    {
        $data = $request->validate([
            'descripcion' => 'nullable|string|max:255',
            'fecha' => 'nullable|date',
            'cantidad' => 'nullable|integer|min:1',
            'meta' => 'nullable',
            'status' => 'nullable|string',
        ]);

        $item = DB::table('inventarios_vinculados')->where('id', $id)->first();
        if (! $item || $item->proyecto_id != $proyecto->id) {
            return back()->with('error', 'Inventario no encontrado.');
        }

        $metaRaw = $data['meta'] ?? null;
        $metaToStore = null;
        if (is_array($metaRaw)) {
            $metaToStore = json_encode($metaRaw);
        } elseif (is_string($metaRaw) && $metaRaw !== '') {
            $decoded = json_decode($metaRaw, true);
            $metaToStore = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : json_encode(['raw' => $metaRaw]);
        }

        try {
            DB::table('inventarios_vinculados')->where('id', $id)->update([
                'descripcion' => $data['descripcion'] ?? $item->descripcion,
                'fecha' => $data['fecha'] ?? $item->fecha,
                'cantidad' => $data['cantidad'] ?? $item->cantidad,
                'meta' => $metaToStore ?? $item->meta,
                'status' => $data['status'] ?? $item->status,
                'updated_at' => now(),
            ]);

            return redirect()->route('proyectos.inventarios.show', [$proyecto->id, $id])
                ->with('success', 'Inventario actualizado.');
        } catch (\Throwable $e) {
            Log::error('Error updating inventario: ' . $e->getMessage());
            return back()->with('error', 'Error al actualizar inventario.');
        }
    }

    /**
     * Destroy: eliminar inventario vinculado
     */
    public function destroy(Proyecto $proyecto, $id)
    {
        $item = DB::table('inventarios_vinculados')->where('id', $id)->first();
        if (! $item || $item->proyecto_id != $proyecto->id) {
            return back()->with('error', 'Inventario no encontrado.');
        }

        DB::table('inventarios_vinculados')->where('id', $id)->delete();

        return redirect()->route('proyectos.inventarios.index', $proyecto->id)
            ->with('success', 'Inventario eliminado.');
    }
}
