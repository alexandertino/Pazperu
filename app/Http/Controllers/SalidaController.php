<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class SalidaController extends Controller
{
    public function index(Proyecto $proyecto)
    {
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaSalidas)) {
            abort(404, 'La tabla de salidas para este proyecto no existe.');
        }

        $salidas = DB::table($tablaSalidas)
            ->orderBy('fecha', 'asc')
            ->get();

        return Inertia::render('Salidas/Index', [
            'proyecto' => $proyecto,
            'salidas' => $salidas
        ]);
    }

    public function create(Proyecto $proyecto)
    {
        return Inertia::render('Salidas/AgregarSalidas', [
            'proyecto' => $proyecto
        ]);
    }

    public function store(Request $request, Proyecto $proyecto)
    {
        $isBulk = $request->has('items') && is_array($request->input('items'));

        $itemRules = [
            'n_acta'         => 'required|string|max:50',
            'nombre_encargado' => 'nullable|string|max:100',
            'nombre'         => 'required|string|max:100',
            'lugar'          => 'required|string|max:100',
            'distrito'       => 'required|string|max:100',
            'fecha'          => 'required|date',
            'producto_code'  => 'nullable|string|max:120',
            'producto'       => 'nullable|string|max:120',
            'producto_label' => 'nullable|string|max:255',
            'um'             => 'nullable|string|max:50',
            'cantidad'       => 'required|numeric|min:0.0001',
            'persona_id'     => 'nullable|integer',
        ];

        if ($isBulk) {
            $rules = ['items' => 'required|array|min:1'];
            foreach ($itemRules as $k => $r) {
                $rules["items.*.$k"] = $r;
            }
        } else {
            $rules = $itemRules;
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaSalidas)) {
            Log::error("store: tabla salidas no existe: {$tablaSalidas}");
            return response()->json(['error' => 'La tabla de salidas no existe'], 404);
        }
        if (!Schema::hasTable($tablaInventario)) {
            Log::error("store: tabla inventario no existe: {$tablaInventario}");
            return response()->json(['error' => 'La tabla de inventario no existe'], 404);
        }

        $items = $isBulk ? $request->input('items') : [$request->all()];
        $inserted = [];

        DB::beginTransaction();
        try {
            foreach ($items as $rawItem) {
                $it = is_array($rawItem) ? $rawItem : (array) $rawItem;

                if (empty($it['producto_code']) && !empty($it['producto'])) {
                    $it['producto_code'] = $it['producto'];
                }

                $it['nombre'] = trim((string)($it['nombre'] ?? ''));
                $it['producto_code'] = (string)($it['producto_code'] ?? '');
                $it['cantidad'] = isset($it['cantidad']) ? (float)$it['cantidad'] : 0;

                $personaId = $it['persona_id'] ?? null;
                if (!$personaId) {
                    $persona = DB::table('personas')->where('nombre', $it['nombre'])->first();
                    if (!$persona) {
                        $personaId = DB::table('personas')->insertGetId([
                            'nombre' => $it['nombre'],
                            'lugar' => $it['lugar'] ?? null,
                            'distrito' => $it['distrito'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    } else {
                        $personaId = $persona->id;
                        DB::table('personas')->where('id', $personaId)->update([
                            'lugar' => $it['lugar'] ?? $persona->lugar,
                            'distrito' => $it['distrito'] ?? $persona->distrito,
                            'updated_at' => now()
                        ]);
                    }
                }

                $dataToInsert = [
                    'n_acta' => $it['n_acta'],
                    'nombre_encargado' => $it['nombre_encargado'],
                    'persona_id' => $personaId,
                    'nombre' => $it['nombre'],
                    'lugar' => $it['lugar'] ?? null,
                    'distrito' => $it['distrito'] ?? null,
                    'fecha' => $it['fecha'],
                    'cantidad' => $it['cantidad'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn($tablaSalidas, 'producto_code')) $dataToInsert['producto_code'] = $it['producto_code'];
                if (Schema::hasColumn($tablaSalidas, 'producto_label')) $dataToInsert['producto_label'] = $it['producto_label'] ?? null;
                if (Schema::hasColumn($tablaSalidas, 'producto') && empty($dataToInsert['producto_code'])) $dataToInsert['producto'] = $it['producto'] ?? $it['producto_code'] ?? null;
                if (Schema::hasColumn($tablaSalidas, 'um')) $dataToInsert['um'] = $it['um'] ?? null;

                $insertId = DB::table($tablaSalidas)->insertGetId($dataToInsert);

                // Activity log
                try {
                    ActivityLog::create([
                        'user_id' => Auth::id(),
                        'action' => 'create',
                        'model' => $tablaSalidas,
                        'model_id' => $insertId,
                        'changes' => ['new' => $dataToInsert],
                    ]);
                } catch (\Throwable $logEx) {
                    Log::warning("ActivityLog::create falló: " . $logEx->getMessage());
                    if (Schema::hasTable('activity_logs')) {
                        DB::table('activity_logs')->insert([
                            'user_id' => Auth::id(),
                            'action' => 'create',
                            'model' => $tablaSalidas,
                            'model_id' => $insertId,
                            'changes' => json_encode(['new' => $dataToInsert], JSON_PARTIAL_OUTPUT_ON_ERROR),
                            'created_at' => now(),
                        ]);
                    }
                }

                $codigoBuscar = $it['producto_code'] ?: ($it['producto'] ?? null);
                $productoInv = $codigoBuscar ? DB::table($tablaInventario)->where('codigo', $codigoBuscar)->first() : null;
                if (!$productoInv && !empty($it['producto_label'])) {
                    $productoInv = DB::table($tablaInventario)->where('descripcion', $it['producto_label'])->first();
                }

                if (!$productoInv) {
                    throw new \Exception("Producto no encontrado en inventario (codigo/label): {$codigoBuscar} / {$it['producto_label']}");
                }

                $nuevoStock = max(0, ($productoInv->stock ?? 0) - $it['cantidad']);
                $nuevasSalidas = ($productoInv->salidas ?? 0) + $it['cantidad'];

                DB::table($tablaInventario)
                    ->where('id', $productoInv->id)
                    ->update([
                        'stock' => $nuevoStock,
                        'salidas' => $nuevasSalidas,
                        'updated_at' => now()
                    ]);

                $inserted[] = [
                    'id' => $insertId,
                    'producto_code' => $codigoBuscar,
                    'cantidad' => $it['cantidad'],
                    'nuevoStock' => $nuevoStock,
                ];
            }

            DB::commit();
            return response()->json(['success' => true, 'inserted' => $inserted]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('store - Error guardando salidas: ' . $e->getMessage(), [
                'exception' => $this->toLogSafe($e),
                'proyecto' => $this->toLogSafe($proyecto->id ?? null),
                'payload' => $this->toLogSafe($request->all())
            ]);
            return response()->json(['error' => 'Error guardando salidas', 'message' => $e->getMessage()], 500);
        }
    }

    public function edit($proyectoId, $id)
    {
        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $salida = DB::table($tablaSalidas)->where('id', $id)->first();

        if (!$salida) {
            abort(404);
        }

        return Inertia::render('Salidas/EditarSalida', [
            'proyecto' => $proyecto,
            'salida' => $salida
        ]);
    }

    public function update(Request $request, $proyectoId, $id)
    {
        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaSalidas)) {
            Log::error("update: tabla salidas no existe: {$tablaSalidas}");
            return response()->json(['error' => 'La tabla de salidas no existe'], 404);
        }
        if (!Schema::hasTable($tablaInventario)) {
            Log::warning("update: tabla inventario no existe: {$tablaInventario} — se continuará pero no se ajustará inventario.");
        }

        // obtengo oldData para logs / validaciones
        $oldData = DB::table($tablaSalidas)->where('id', $id)->first();
        if (!$oldData) {
            Log::error("update: salida id={$id} no encontrada en {$tablaSalidas}");
            return response()->json(['error' => 'Salida no encontrada'], 404);
        }

        /*
     * RAMA ESPECIAL: si la request sólo quiere actualizar 'estado'
     * hacemos una validación mínima y actualizamos únicamente ese campo.
     */
        if ($request->has('estado') && count($request->all()) === 1) {
            // Validar solo estado
            $validatorEstado = \Illuminate\Support\Facades\Validator::make($request->only('estado'), [
                'estado' => 'required|in:pendiente,aceptado',
            ]);

            if ($validatorEstado->fails()) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'errors' => $validatorEstado->errors()], 422);
                }
                return back()->withErrors($validatorEstado)->withInput();
            }

            DB::beginTransaction();
            try {
                DB::table($tablaSalidas)->where('id', $id)->update([
                    'estado' => $request->input('estado'),
                    'updated_at' => now(),
                ]);

                $newData = DB::table($tablaSalidas)->where('id', $id)->first();

                // Intentamos registrar activity log, pero no fallamos si falla el log.
                try {
                    ActivityLog::create([
                        'user_id' => Auth::id(),
                        'action' => 'update_estado',
                        'model' => $tablaSalidas,
                        'model_id' => $id,
                        'changes' => ['old' => $oldData, 'new' => $newData],
                    ]);
                } catch (\Throwable $logEx) {
                    Log::warning("update (estado): ActivityLog::create falló: " . $logEx->getMessage());
                }

                DB::commit();

                if ($request->wantsJson()) {
                    return response()->json(['success' => true, 'salida' => $newData]);
                }
                return redirect()->route('proyectos.salidas', $proyectoId)->with('success', 'Estado actualizado correctamente.');
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('update (estado) - excepción: ' . $e->getMessage(), [
                    'exception' => $e,
                    'proyecto' => $proyectoId,
                    'id' => $id,
                    'request' => $request->all(),
                    'oldData' => $oldData,
                ]);
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Error actualizando el estado', 'message' => $e->getMessage()], 500);
                }
                return back()->with('error', 'Error actualizando el estado: ' . $e->getMessage());
            }
        }

        /*
     * Si no fue la rama de 'estado solo', procedemos con la validación
     * y el flujo completo que ya tenías (ajustes de inventario, etc.).
     */

        $rules = [
            'n_acta'         => 'required|string|max:50',
            'nombre'         => 'required|string|max:100',
            'lugar'          => 'required|string|max:100',
            'distrito'       => 'required|string|max:100',
            'fecha'          => 'required|date',
            'producto_code'  => 'nullable|string|max:120',
            'producto'       => 'nullable|string|max:120',
            'producto_label' => 'nullable|string|max:255',
            'um'             => 'nullable|string|max:50',
            'cantidad'       => 'required|numeric|min:0.0001',
            'persona_id'     => 'nullable|integer',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }
        DB::beginTransaction();
        try {
            $newProductoCode = $request->input('producto_code') ?: $request->input('producto') ?: null;
            $newCantidad = (float)$request->input('cantidad', 0);
            $newNombre = $request->input('nombre');
            $newLugar = $request->input('lugar');
            $newDistrito = $request->input('distrito');
            $newFecha = $request->input('fecha');
            $newPersonaId = $request->input('persona_id') ?: null;
            $newProductoLabel = $request->input('producto_label') ?: null;
            $newUm = $request->input('um') ?: null;
            $newNActa = $request->input('n_acta');

            if (!$newPersonaId && !empty($newNombre)) {
                $p = DB::table('personas')->where('nombre', $newNombre)->first();
                if ($p) {
                    $newPersonaId = $p->id;
                } else {
                    $newPersonaId = DB::table('personas')->insertGetId([
                        'nombre' => $newNombre,
                        'lugar' => $newLugar,
                        'distrito' => $newDistrito,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $updateData = [
                'n_acta' => $newNActa,
                'persona_id' => $newPersonaId,
                'nombre' => $newNombre,
                'lugar' => $newLugar,
                'distrito' => $newDistrito,
                'fecha' => $newFecha,
                'cantidad' => $newCantidad,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn($tablaSalidas, 'producto_code')) $updateData['producto_code'] = $newProductoCode;
            if (Schema::hasColumn($tablaSalidas, 'producto_label')) $updateData['producto_label'] = $newProductoLabel;
            if (Schema::hasColumn($tablaSalidas, 'um')) $updateData['um'] = $newUm;
            if (Schema::hasColumn($tablaSalidas, 'producto') && empty($updateData['producto_code'])) {
                $updateData['producto'] = $request->input('producto') ?: $newProductoCode;
            }

            Log::info("update: actualizando salida id={$id} tabla={$tablaSalidas}", ['updateData' => $this->toLogSafe($updateData)]);

            DB::table($tablaSalidas)->where('id', $id)->update($updateData);

            $oldProductoCode = $oldData->producto_code ?? ($oldData->producto ?? null);
            $oldCantidad = (float)($oldData->cantidad ?? 0);

            if (!Schema::hasTable($tablaInventario)) {
                Log::warning("update: tabla inventario ausente, no se ajustará stock (proyecto={$proyectoId})");
            } else {
                if ($oldProductoCode && $newProductoCode && $oldProductoCode === $newProductoCode) {
                    $inv = DB::table($tablaInventario)->where('codigo', $newProductoCode)->first();
                    if (!$inv) {
                        throw new \Exception("Producto para ajuste no encontrado: codigo={$newProductoCode}");
                    }
                    $stockRestaurado = ($inv->stock ?? 0) + $oldCantidad;
                    $nuevoStock = max(0, $stockRestaurado - $newCantidad);
                    $nuevasSalidas = max(0, ($inv->salidas ?? 0) - $oldCantidad + $newCantidad);

                    Log::info("update: ajuste mismo producto codigo={$newProductoCode}", ['stockRestaurado' => $stockRestaurado, 'nuevoStock' => $nuevoStock, 'nuevasSalidas' => $nuevasSalidas]);

                    DB::table($tablaInventario)->where('id', $inv->id)->update([
                        'stock' => $nuevoStock,
                        'salidas' => $nuevasSalidas,
                        'updated_at' => now()
                    ]);
                } else {
                    if ($oldProductoCode) {
                        $invOld = DB::table($tablaInventario)->where('codigo', $oldProductoCode)->first();
                        if ($invOld) {
                            DB::table($tablaInventario)->where('id', $invOld->id)->update([
                                'stock' => ($invOld->stock ?? 0) + $oldCantidad,
                                'salidas' => max(0, ($invOld->salidas ?? 0) - $oldCantidad),
                                'updated_at' => now()
                            ]);
                            Log::info("update: restaurado stock producto antiguo codigo={$oldProductoCode}", ['restored' => $oldCantidad]);
                        } else {
                            Log::warning("update: no se encontró inventario para producto antiguo codigo={$oldProductoCode}");
                        }
                    }

                    if ($newProductoCode) {
                        $invNew = DB::table($tablaInventario)->where('codigo', $newProductoCode)->first();
                        if (!$invNew) {
                            throw new \Exception("Producto nuevo para ajuste no encontrado: codigo={$newProductoCode}");
                        }
                        DB::table($tablaInventario)->where('id', $invNew->id)->update([
                            'stock' => max(0, ($invNew->stock ?? 0) - $newCantidad),
                            'salidas' => ($invNew->salidas ?? 0) + $newCantidad,
                            'updated_at' => now()
                        ]);
                        Log::info("update: descontado stock producto nuevo codigo={$newProductoCode}", ['cantidad' => $newCantidad]);
                    }
                }
            }

            $newData = DB::table($tablaSalidas)->where('id', $id)->first();
            Log::info("update: newData obtenido", ['newData' => $this->toLogSafe($newData)]);

            try {
                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'update',
                    'model' => $tablaSalidas,
                    'model_id' => $id,
                    'changes' => ['old' => $oldData, 'new' => $newData],
                ]);
            } catch (\Throwable $logEx) {
                Log::warning("update: ActivityLog::create falló: " . $logEx->getMessage());
                if (Schema::hasTable('activity_logs')) {
                    DB::table('activity_logs')->insert([
                        'user_id' => Auth::id(),
                        'action' => 'update',
                        'model' => $tablaSalidas,
                        'model_id' => $id,
                        'changes' => json_encode(['old' => $this->toLogSafe($oldData), 'new' => $this->toLogSafe($newData)], JSON_PARTIAL_OUTPUT_ON_ERROR),
                        'created_at' => now(),
                    ]);
                }
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'salida' => $newData]);
            }
            return redirect()->route('proyectos.salidas', $proyectoId)->with('success', 'Salida actualizada correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();

            $ctx = [
                'exception' => $this->toLogSafe($e),
                'proyecto' => $this->toLogSafe($proyectoId),
                'id' => $this->toLogSafe($id),
                'request' => $this->toLogSafe($request->all()),
                'oldData' => isset($oldData) ? $this->toLogSafe($oldData) : null,
            ];

            Log::error('update - excepción: ' . $e->getMessage(), $ctx);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Error actualizando la salida', 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Error actualizando la salida: ' . $e->getMessage());
        }
    }
    public function aceptarMultiple(Request $request, $proyectoId)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer'
        ]);

        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaSalidas)) {
            return response()->json(['error' => 'Tabla de salidas no existe'], 404);
        }

        $ids = $request->input('ids', []);

        DB::beginTransaction();
        try {
            DB::table($tablaSalidas)
                ->whereIn('id', $ids)
                ->update(['estado' => 'aceptado', 'updated_at' => now()]);

            // Opcional: obtener filas actualizadas para respuesta
            $newData = DB::table($tablaSalidas)->whereIn('id', $ids)->get();

            // Activity log (opcional)
            try {
                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'bulk_update_estado',
                    'model' => $tablaSalidas,
                    'model_id' => null,
                    'changes' => ['ids' => $ids, 'new' => 'aceptado'],
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('ActivityLog bulk failed: ' . $logEx->getMessage());
            }

            DB::commit();
            return response()->json(['success' => true, 'updated_count' => count($ids), 'salidas' => $newData]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('aceptarMultiple error: ' . $e->getMessage(), ['ids' => $ids]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($proyectoId, $id)
    {
        $proyecto = Proyecto::findOrFail($proyectoId);

        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaSalidas)) {
            Log::error("destroy: tabla salidas no existe: {$tablaSalidas}");
            return response()->json(['error' => 'La tabla de salidas no existe'], 404);
        }

        $salida = DB::table($tablaSalidas)->where('id', $id)->first();
        if (!$salida) {
            Log::warning("destroy: salida id={$id} no encontrada en {$tablaSalidas}");
            return response()->json(['error' => 'Salida no encontrada'], 404);
        }

        DB::beginTransaction();
        try {
            $codigo = $salida->producto_code ?? ($salida->producto ?? null);
            $cantidad = (float)($salida->cantidad ?? 0);

            if (Schema::hasTable($tablaInventario) && $codigo) {
                $producto = DB::table($tablaInventario)->where('codigo', $codigo)->first();
                if ($producto) {
                    DB::table($tablaInventario)->where('id', $producto->id)->update([
                        'stock' => ($producto->stock ?? 0) + $cantidad,
                        'salidas' => max(0, ($producto->salidas ?? 0) - $cantidad),
                        'updated_at' => now()
                    ]);
                    Log::info("destroy: restaurado inventario codigo={$codigo}", ['cantidad' => $cantidad]);
                } else {
                    $label = $salida->producto_label ?? null;
                    if ($label) {
                        $producto2 = DB::table($tablaInventario)->where('descripcion', $label)->first();
                        if ($producto2) {
                            DB::table($tablaInventario)->where('id', $producto2->id)->update([
                                'stock' => ($producto2->stock ?? 0) + $cantidad,
                                'salidas' => max(0, ($producto2->salidas ?? 0) - $cantidad),
                                'updated_at' => now()
                            ]);
                            Log::info("destroy: restaurado inventario por label", ['label' => $label, 'cantidad' => $cantidad]);
                        } else {
                            Log::warning("destroy: producto no encontrado en inventario para codigo={$codigo} label={$label}");
                        }
                    } else {
                        Log::warning("destroy: producto no encontrado en inventario y sin label, codigo={$codigo}");
                    }
                }
            } else {
                Log::info("destroy: no se ajustará inventario (tabla faltante o codigo vacío).");
            }

            DB::table($tablaSalidas)->where('id', $id)->delete();

            try {
                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'delete',
                    'model' => $tablaSalidas,
                    'model_id' => $id,
                    'changes' => ['deleted' => $salida],
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('destroy: ActivityLog::create falló: ' . $logEx->getMessage());
                if (Schema::hasTable('activity_logs')) {
                    DB::table('activity_logs')->insert([
                        'user_id' => Auth::id(),
                        'action' => 'delete',
                        'model' => $tablaSalidas,
                        'model_id' => $id,
                        'changes' => json_encode(['deleted' => $this->toLogSafe($salida)], JSON_PARTIAL_OUTPUT_ON_ERROR),
                        'created_at' => now(),
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => 'Salida eliminada y stock restaurado']);
        } catch (\Throwable $e) {
            DB::rollBack();

            $ctx = [
                'exception' => $this->toLogSafe($e),
                'proyecto' => $this->toLogSafe($proyectoId),
                'id' => $this->toLogSafe($id),
                'salida' => isset($salida) ? $this->toLogSafe($salida) : null,
            ];

            Log::error('destroy - excepción: ' . $e->getMessage(), $ctx);

            return response()->json(['error' => 'Error eliminando la salida', 'message' => $e->getMessage()], 500);
        }
    }

    public function buscarProducto(Proyecto $proyecto, $codigo)
    {
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $producto = DB::table($tablaInventario)
            ->where('codigo', $codigo)
            ->first();

        if (!$producto) {
            return response()->json(['existe' => false], 404);
        }

        return response()->json([
            'existe' => true,
            'producto' => $producto
        ]);
    }

    public function exportarProyecto(Proyecto $proyecto)
    {
        $nombreTablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $nombreTablaSalidas    = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($nombreTablaInventario)) {
            Log::error("exportarProyecto: tabla inventario no existe: {$nombreTablaInventario}");
            return back()->with('error', 'Tabla de inventario inexistente para este proyecto.');
        }
        if (!Schema::hasTable($nombreTablaSalidas)) {
            Log::error("exportarProyecto: tabla salidas no existe: {$nombreTablaSalidas}");
            return back()->with('error', 'Tabla de salidas inexistente para este proyecto.');
        }

        $inventarioCols = ['codigo'];
        foreach (['fecha', 'descripcion', 'um', 'categoria', 'entradas', 'salidas', 'stock'] as $c) {
            if (Schema::hasColumn($nombreTablaInventario, $c)) $inventarioCols[] = $c;
        }

        $salidasCols = [];
        if (Schema::hasColumn($nombreTablaSalidas, 'n_acta')) $salidasCols[] = 'n_acta';
        if (Schema::hasColumn($nombreTablaSalidas, 'nombre')) $salidasCols[] = 'nombre';
        if (Schema::hasColumn($nombreTablaSalidas, 'lugar')) $salidasCols[] = 'lugar';
        if (Schema::hasColumn($nombreTablaSalidas, 'distrito')) $salidasCols[] = 'distrito';
        if (Schema::hasColumn($nombreTablaSalidas, 'fecha')) $salidasCols[] = 'fecha';
        if (Schema::hasColumn($nombreTablaSalidas, 'producto_code')) $salidasCols[] = 'producto_code as producto';
        elseif (Schema::hasColumn($nombreTablaSalidas, 'producto')) $salidasCols[] = 'producto';
        elseif (Schema::hasColumn($nombreTablaSalidas, 'producto_label')) $salidasCols[] = 'producto_label as producto';
        if (Schema::hasColumn($nombreTablaSalidas, 'cantidad')) $salidasCols[] = 'cantidad';

        $inventarios = DB::table($nombreTablaInventario)->select($inventarioCols)->get()->toArray();
        $salidas = DB::table($nombreTablaSalidas)->select($salidasCols)->get()->toArray();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', strtoupper("Proyecto: {$proyecto->nombre}"));
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Inventario');
        $sheet->getStyle('A3')->getFont()->setBold(true);

        $headersInventario = array_map(function ($col) {
            return ucfirst(str_replace('_', ' ', preg_replace('/ as .*$/', '', $col)));
        }, $inventarioCols);
        $sheet->fromArray($headersInventario, null, 'A4');
        $sheet->getStyle('A4:' . chr(64 + count($headersInventario)) . '4')->getFont()->setBold(true);

        $sheet->fromArray($inventarios, null, 'A5');

        $sheet->setCellValue('J3', 'Salidas');
        $sheet->getStyle('J3')->getFont()->setBold(true);

        $headersSalidas = array_map(function ($col) {
            $clean = preg_replace('/ as .*$/i', '', $col);
            return ucfirst(str_replace('_', ' ', $clean));
        }, $salidasCols);
        $sheet->fromArray($headersSalidas, null, 'J4');
        $sheet->fromArray($salidas, null, 'J5');

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fechaHora = Carbon::now()->format('Y-m-d_H-i-s');
        $fileName = "proyecto_{$proyecto->id}_{$fechaHora}.xlsx";

        $folder = public_path('excel_consulta');
        if (!is_dir($folder)) mkdir($folder, 0755, true);
        $path = $folder . '/' . $fileName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return response()->download($path);
    }

    public function productosProyecto(Request $request, Proyecto $proyecto)
    {
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaInventario)) {
            return response()->json([], 200);
        }

        $possibleColumns = [
            'codigo' => 'codigo',
            'descripcion' => 'descripcion',
            'producto' => 'producto',
            'unidad_medida' => 'unidad_medida',
            'stock' => 'stock',
            'id' => 'id',
            'solicitado_por' => 'solicitado_por',
            'solicitante' => 'solicitante',
            'requested_by' => 'requested_by',
            'categoria' => 'categoria',
            'categoria_id' => 'categoria_id',
            'category' => 'category',
            'fecha' => 'fecha',
            'fecha_registro' => 'fecha_registro',
            'created_at' => 'created_at',
            'created' => 'created',
            'registered_at' => 'registered_at',
            'timestamp' => 'timestamp',
            'fecha_creacion' => 'fecha_creacion',
        ];

        $select = [];
        foreach ($possibleColumns as $col) {
            if (Schema::hasColumn($tablaInventario, $col)) {
                $select[] = $col;
            }
        }

        if (empty($select)) return response()->json([], 200);

        // filtros
        $q = $request->query('q', null);
        $solicitadoPor = $request->query('solicitado_por', null);
        $categoria = $request->query('categoria', null);
        $minStock = $request->query('minStock', null);
        $onlyAvailable = filter_var($request->query('onlyAvailable', 'true'), FILTER_VALIDATE_BOOLEAN);
        $sortBy = $request->query('sortBy', 'producto');
        $limit = (int) $request->query('limit', 200);
        $meta = filter_var($request->query('meta', 'false'), FILTER_VALIDATE_BOOLEAN);

        $qb = DB::table($tablaInventario)->select($select);

        // Búsqueda libre
        if ($q) {
            $qClean = trim($q);
            $like = '%' . mb_strtolower($qClean) . '%';

            $qb->where(function ($inner) use ($like, $select) {
                if (in_array('producto', $select)) {
                    $inner->orWhereRaw('LOWER(producto) LIKE ?', [$like]);
                }
                if (in_array('descripcion', $select)) {
                    $inner->orWhereRaw('LOWER(descripcion) LIKE ?', [$like]);
                }
                if (in_array('codigo', $select)) {
                    $inner->orWhereRaw('LOWER(CAST(codigo AS CHAR)) LIKE ?', [$like]);
                }
                if (in_array('solicitado_por', $select)) {
                    $inner->orWhereRaw('LOWER(solicitado_por) LIKE ?', [$like]);
                }
                if (in_array('categoria', $select)) {
                    $inner->orWhereRaw('LOWER(CAST(categoria AS CHAR)) LIKE ?', [$like]);
                }
                if (in_array('requested_by', $select)) {
                    $inner->orWhereRaw('LOWER(requested_by) LIKE ?', [$like]);
                }
                if (in_array('solicitante', $select)) {
                    $inner->orWhereRaw('LOWER(solicitante) LIKE ?', [$like]);
                }
                if (in_array('category', $select)) {
                    $inner->orWhereRaw('LOWER(category) LIKE ?', [$like]);
                }

                if (in_array('fecha', $select)) {
                    $inner->orWhereRaw('LOWER(CAST(fecha AS CHAR)) LIKE ?', [$like]);
                }
                if (in_array('created_at', $select)) {
                    $inner->orWhereRaw('LOWER(CAST(created_at AS CHAR)) LIKE ?', [$like]);
                }
            });
        }

        // filtros específicos (solicitado_por)
        if ($solicitadoPor) {
            $val = '%' . mb_strtolower($solicitadoPor) . '%';
            $qb->where(function ($w) use ($val, $select) {
                if (in_array('solicitado_por', $select)) $w->orWhereRaw('LOWER(solicitado_por) LIKE ?', [$val]);
                if (in_array('requested_by', $select)) $w->orWhereRaw('LOWER(requested_by) LIKE ?', [$val]);
                if (in_array('solicitante', $select)) $w->orWhereRaw('LOWER(solicitante) LIKE ?', [$val]);
            });
        }

        // filtros específicos (categoria)
        if ($categoria) {
            $val = '%' . mb_strtolower($categoria) . '%';
            $qb->where(function ($w) use ($val, $select) {
                if (in_array('categoria', $select)) $w->orWhereRaw('LOWER(CAST(categoria AS CHAR)) LIKE ?', [$val]);
                if (in_array('categoria_id', $select)) $w->orWhereRaw('LOWER(CAST(categoria_id AS CHAR)) LIKE ?', [$val]);
                if (in_array('category', $select)) $w->orWhereRaw('LOWER(category) LIKE ?', [$val]);
            });
        }

        if (!is_null($minStock) && is_numeric($minStock)) {
            $qb->where('stock', '>=', (float)$minStock);
        }

        if ($onlyAvailable) {
            $qb->where(function ($w) {
                $w->whereNull('stock')->orWhere('stock', '>', 0);
            });
        }

        $allowedSort = ['producto', 'codigo', 'categoria'];
        if (!in_array($sortBy, $allowedSort)) $sortBy = 'producto';
        $realSort = in_array($sortBy, $select) ? $sortBy : (in_array('producto', $select) ? 'producto' : $select[0]);

        $qb->orderBy($realSort, 'asc');

        if ($limit > 0 && $limit <= 2000) $qb->limit($limit);
        else $qb->limit(1000);

        $rows = $qb->get();

        $normalized = $rows->map(function ($r) {
            $r = (array) $r;

            // buscar primer campo de fecha disponible en el raw
            $dateCandidates = [
                'fecha', 'fecha_registro', 'created_at', 'created', 'registered_at', 'timestamp', 'fecha_creacion'
            ];

            $foundDate = null;
            foreach ($dateCandidates as $dc) {
                if (array_key_exists($dc, $r) && !is_null($r[$dc]) && $r[$dc] !== '') {
                    $foundDate = $r[$dc];
                    break;
                }
            }

            // formatear a YYYY-MM-DD si es posible
            $fecha = null;
            if ($foundDate !== null) {
                try {
                    $fecha = Carbon::parse($foundDate)->toDateString(); // YYYY-MM-DD
                } catch (\Exception $e) {
                    $fecha = null;
                }
            }

            return [
                'code' => $r['codigo'] ?? $r['id'] ?? null,
                'producto' => $r['producto'] ?? $r['descripcion'] ?? null,
                'descripcion' => $r['descripcion'] ?? $r['producto'] ?? null,
                'unidad_medida' => $r['unidad_medida'] ?? null,
                'stock' => isset($r['stock']) ? (float)$r['stock'] : null,
                'solicitado_por' => $r['solicitado_por'] ?? $r['solicitante'] ?? $r['requested_by'] ?? null,
                'categoria' => $r['categoria'] ?? $r['categoria_id'] ?? $r['category'] ?? null,
                'id' => $r['id'] ?? null,
                // nueva propiedad fecha
                'fecha' => $fecha,
                'raw' => $r,
            ];
        });

        if ($meta) {
            $categorias = $normalized->pluck('categoria')->filter()->unique()->values();
            $solicitantes = $normalized->pluck('solicitado_por')->filter()->unique()->values();
            return response()->json([
                'data' => $normalized,
                'meta' => [
                    'categorias' => $categorias,
                    'solicitantes' => $solicitantes,
                    'count' => $normalized->count(),
                ],
            ], 200);
        }

        return response()->json($normalized, 200);
    }

    public function importarSalidas(Request $request, Proyecto $proyecto)
    {
        $request->validate([
            'archivo_excel' => 'required|file|mimes:xlsx,csv,ods',
        ]);

        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaSalidas)) {
            Log::error("importarSalidas: tabla salidas no existe: {$tablaSalidas}");
            return back()->with('error', 'La tabla de salidas del proyecto no existe.');
        }

        try {
            $path = $request->file('archivo_excel')->getRealPath();
            (new FastExcel)->import($path, function ($line) use ($tablaSalidas) {
                $normalized = [];
                foreach ($line as $k => $v) {
                    $key = strtolower(trim($k));
                    $key = str_replace(' ', '_', $key);
                    $normalized[$key] = $v;
                }

                $n_acta = $normalized['n_acta'] ?? ($normalized['n°_acta'] ?? null);
                $nombre = $normalized['nombre'] ?? null;
                $lugar = $normalized['lugar'] ?? null;
                $distrito = $normalized['distrito'] ?? null;
                $fecha = $normalized['fecha'] ?? null;
                $producto = $normalized['producto'] ?? $normalized['producto_code'] ?? $normalized['producto_label'] ?? null;
                $cantidad = isset($normalized['cantidad']) ? (float)$normalized['cantidad'] : 0;

                if (empty($nombre) || empty($fecha) || $cantidad <= 0) {
                    Log::warning("importarSalidas: fila ignorada por datos insuficientes", ['fila' => $this->toLogSafe($normalized)]);
                    return null;
                }

                $insert = [
                    'n_acta' => $n_acta,
                    'nombre' => $nombre,
                    'lugar' => $lugar,
                    'distrito' => $distrito,
                    'fecha' => $fecha,
                    'cantidad' => $cantidad,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn($tablaSalidas, 'producto_code')) {
                    $insert['producto_code'] = $normalized['producto_code'] ?? $producto;
                } elseif (Schema::hasColumn($tablaSalidas, 'producto')) {
                    $insert['producto'] = $normalized['producto'] ?? $producto;
                } elseif (Schema::hasColumn($tablaSalidas, 'producto_label')) {
                    $insert['producto_label'] = $normalized['producto_label'] ?? $producto;
                }

                DB::table($tablaSalidas)->insert($insert);
                return null;
            });
        } catch (\Throwable $e) {
            Log::error("importarSalidas - excepción: " . $e->getMessage(), ['exception' => $this->toLogSafe($e)]);
            return back()->with('error', 'Error importando archivo: ' . $e->getMessage());
        }

        return back()->with('success', 'Salidas importadas correctamente.');
    }

    public function porProducto(Proyecto $proyecto, $codigo)
    {
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaSalidas)) {
            return response()->json([]);
        }

        $query = DB::table($tablaSalidas);
        $query->where(function ($q) use ($tablaSalidas, $codigo) {
            if (Schema::hasColumn($tablaSalidas, 'producto_code')) {
                $q->orWhere('producto_code', $codigo);
            }
            if (Schema::hasColumn($tablaSalidas, 'producto')) {
                $q->orWhere('producto', $codigo);
            }
            if (Schema::hasColumn($tablaSalidas, 'producto_label')) {
                $q->orWhere('producto_label', 'like', "%{$codigo}%");
            }
        });

        $salidas = $query->orderBy('fecha', 'asc')->get();
        return response()->json($salidas);
    }
    public function ultimoCodigo()
    {
        $ultimo = \App\Models\Salida::orderBy('id', 'desc')->first();
        
        $numero = 1; // Valor por defecto si no hay registros aún

        if ($ultimo && $ultimo->codigo1) {
            // Si existe, incrementa el número
            $numero = intval($ultimo->codigo1) + 1;
        }

        return response()->json(['siguiente' => $numero]);
    }

    private function toLogSafe($value)
    {
        try {
            if ($value instanceof \Throwable) {
                return [
                    'exception_message' => $value->getMessage(),
                    'exception_class' => get_class($value),
                    'exception_trace' => $value->getTraceAsString(),
                ];
            }

            if (is_array($value)) return $value;

            if (is_object($value)) {
                $json = json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR);
                if ($json !== false) {
                    $decoded = json_decode($json, true);
                    return $decoded !== null ? $decoded : ['__class' => get_class($value)];
                }
                return ['__class' => get_class($value)];
            }

            if (is_resource($value)) return (string) $value;
            return $value;
        } catch (\Throwable $ex) {
            return ['__toLogSafe_error' => $ex->getMessage()];
        }
    }

    public function ultimoActa(Proyecto $proyecto)
    {
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        if (!Schema::hasTable($tablaSalidas)) {
            return response()->json(['error' => 'La tabla de salidas no existe'], 404);
        }

        // Buscar el último n_acta que siga el formato AE - NUMERO - AÑO
        $ultimo = DB::table($tablaSalidas)
            ->select('n_acta')
            ->where('n_acta', 'like', 'AE - % - ' . date('Y'))
            ->orderByDesc('id')
            ->first();

        if (!$ultimo) {
            return response()->json([
                'success' => true,
                'ultimo_n_acta' => null,
                'proximo' => 'AE - 1 - ' . date('Y')
            ]);
        }

        // Extraer el número del medio
        preg_match('/AE\s*-\s*(\d+)\s*-\s*\d{4}/', $ultimo->n_acta, $matches);
        $numero = isset($matches[1]) ? (int)$matches[1] : 0;

        $proximo = 'AE - ' . ($numero + 1) . ' - ' . date('Y');

        return response()->json([
            'success' => true,
            'ultimo_n_acta' => $ultimo->n_acta,
            'proximo' => $proximo
        ]);
    }

}
