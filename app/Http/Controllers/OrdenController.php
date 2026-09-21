<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;


class OrdenController extends Controller
{
    // 📋 Vista principal (tú defines las tablas manualmente)
    public function index()
    {
        return Inertia::render('Orden/Index', [
            'tablas' => [
                'am_caja_proyecto_ally_mikushun_2',
                'am_banco_proyecto_ally_mikushun_2',
                'am_caja_proyecto_conserva_aves',
                'am_banco_proyecto_conserva_aves',
                'am_caja_proyecto_feed_good',
                'am_banco_proyecto_feed_good',
                'am_caja_proyecto_fondo_flamenco',
                'am_banco_proyecto_fondo_flamenco',
            ]
        ]);
    }

    // 📊 Cargar datos de tabla
    public function cargar($tabla)
    {
        if (!Schema::hasTable($tabla)) {
            return response()->json(['error' => 'Tabla inválida'], 422);
        }

        $data = DB::table($tabla)->orderBy('id')->get();
        return response()->json($data);
    }

    // 🔼 Subir
    public function subir($tabla, $id)
    {
        return $this->mover($tabla, $id, 'up');
    }

    // 🔽 Bajar
    public function bajar($tabla, $id)
    {
        return $this->mover($tabla, $id, 'down');
    }

    private function mover($tabla, $id, $direccion)
    {
        if (!Schema::hasTable($tabla)) {
            return response()->json(['error' => 'Tabla inválida'], 422);
        }

        DB::beginTransaction();

        try {
            $actual = DB::table($tabla)->where('id', $id)->first();

            if (!$actual) {
                return response()->json(['error' => 'No existe'], 404);
            }

            if ($direccion === 'up') {
                $otro = DB::table($tabla)
                    ->where('id', '<', $id)
                    ->orderBy('id', 'desc')
                    ->first();
            } else {
                $otro = DB::table($tabla)
                    ->where('id', '>', $id)
                    ->orderBy('id', 'asc')
                    ->first();
            }

            if (!$otro) {
                return response()->json(['ok' => false, 'message' => 'No hay más registros']);
            }

            // 🔥 ID temporal (max + 1) para evitar conflictos
            $tempId = DB::table($tabla)->max('id') + 1;

            DB::table($tabla)->where('id', $actual->id)->update(['id' => $tempId]);
            DB::table($tabla)->where('id', $otro->id)->update(['id' => $actual->id]);
            DB::table($tabla)->where('id', $tempId)->update(['id' => $otro->id]);

            DB::commit();

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🆕 Mover a posición específica
public function moverAPosicion($tabla, $id, Request $request)
{
    $nuevaPosicion = $request->nueva_posicion;
    
    if (!Schema::hasTable($tabla)) {
        return response()->json(['error' => 'Tabla inválida'], 422);
    }
    
    DB::beginTransaction();
    
    try {
        // Obtener todos los IDs en orden actual
        $registros = DB::table($tabla)->orderBy('id')->get();
        $idsActuales = $registros->pluck('id')->toArray();
        
        // Encontrar la posición actual del registro
        $posicionActual = array_search($id, $idsActuales);
        if ($posicionActual === false) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }
        
        // Si la posición es la misma, no hacer nada
        if ($posicionActual + 1 == $nuevaPosicion) {
            return response()->json(['ok' => true, 'message' => 'Ya está en esa posición']);
        }
        
        // Reordenar el array de IDs
        $idsReordenados = $idsActuales;
        $elemento = array_splice($idsReordenados, $posicionActual, 1);
        array_splice($idsReordenados, $nuevaPosicion - 1, 0, $elemento);
        
        // Actualizar IDs temporalmente para evitar conflictos
        $maxId = DB::table($tabla)->max('id');
        $tempBase = $maxId + 1000; // Usar un número grande para evitar conflictos
        
        // Primero, asignar IDs temporales únicos basados en nueva posición
        foreach ($idsReordenados as $idx => $nuevoId) {
            $tempId = $tempBase + $idx;
            DB::table($tabla)->where('id', $nuevoId)->update(['id' => $tempId]);
        }
        
        // Luego, reasignar los IDs secuencialmente según el nuevo orden
        $nuevoIdSecuencial = 1;
        $registrosTemporales = DB::table($tabla)->orderBy('id')->get();
        
        foreach ($registrosTemporales as $registro) {
            DB::table($tabla)->where('id', $registro->id)->update(['id' => $nuevoIdSecuencial]);
            $nuevoIdSecuencial++;
        }
        
        DB::commit();
        
        return response()->json(['ok' => true]);
        
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    // 🆕 Agregar registro en posición específica por mes/año
    public function agregar($tabla, Request $request)
    {
        if (!Schema::hasTable($tabla)) {
            return response()->json(['error' => 'Tabla inválida'], 422);
        }

        $request->validate([
            'fecha'          => 'required|date',
            'posicion'       => 'required|integer|min:1',
            'descripcion'    => 'nullable|string|max:255',
            'n_acta'         => 'nullable|string|max:100',
            'presupuestario' => 'nullable|string|max:255',
            'actividad'      => 'nullable|string|max:255',
            'ingresos'       => 'nullable|numeric|min:0',
            'egresos'        => 'nullable|numeric|min:0',
        ]);

        $fecha   = $request->fecha;
        $mes     = (int) date('m', strtotime($fecha));
        $anio    = (int) date('Y', strtotime($fecha));
        $posicion = (int) $request->posicion;

        DB::beginTransaction();

        try {
            // Obtener registros del mes/año seleccionado ordenados por id
            $registrosMes = DB::table($tabla)
                ->whereMonth('fecha', $mes)
                ->whereYear('fecha', $anio)
                ->orderBy('id')
                ->get();

            // Determinar el target_id donde insertar
            if ($registrosMes->isEmpty()) {
                // No hay registros en ese mes, insertar después del último registro anterior
                $anterior = DB::table($tabla)
                    ->where(function ($q) use ($anio, $mes) {
                        $q->whereYear('fecha', '<', $anio)
                          ->orWhere(function ($q2) use ($anio, $mes) {
                              $q2->whereYear('fecha', $anio)
                                 ->whereMonth('fecha', '<', $mes);
                          });
                    })
                    ->orderBy('id', 'desc')
                    ->first();

                $targetId = $anterior ? $anterior->id + 1 : 1;
            } else {
                // Hay registros en ese mes
                if ($posicion <= $registrosMes->count()) {
                    // Insertar en la posición indicada (desplazando)
                    $targetId = (int) $registrosMes->values()[$posicion - 1]->id;
                } else {
                    // Insertar al final del mes
                    $ultimoMes = $registrosMes->last();
                    $targetId = (int) $ultimoMes->id + 1;
                }
            }

            // Desplazar todos los IDs >= targetId hacia arriba en 1
            // Iterar en orden DESCENDENTE para evitar conflictos de PK
            $registrosDesplazar = DB::table($tabla)
                ->where('id', '>=', $targetId)
                ->orderBy('id', 'desc')
                ->get();

            foreach ($registrosDesplazar as $reg) {
                $oldId = (int) $reg->id;
                $newId = $oldId + 1;

                DB::table($tabla)->where('id', $oldId)->update(['id' => $newId]);

                // Actualizar referencias en inventarios_vinculados
                if (Schema::hasTable('inventarios_vinculados')) {
                    DB::table('inventarios_vinculados')
                        ->where('am_table', $tabla)
                        ->where('am_row_id', $oldId)
                        ->update(['am_row_id' => $newId]);
                }
            }

            // Insertar el nuevo registro con el targetId
            $ingresos = (float) ($request->ingresos ?? 0);
            $egresos  = (float) ($request->egresos ?? 0);

            // Calcular saldo basado en el registro anterior
            $prevRecord = DB::table($tabla)
                ->where('id', '<', $targetId)
                ->orderBy('id', 'desc')
                ->first();
            $saldoAnterior = $prevRecord ? (float) $prevRecord->saldo : 0;
            $saldo = $saldoAnterior + $ingresos - $egresos;

            $columns = Schema::getColumnListing($tabla);

            $insertData = [
                'id'         => $targetId,
                'fecha'      => $fecha,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('descripcion', $columns))    $insertData['descripcion']    = $request->descripcion ?? '';
            if (in_array('n_acta', $columns))          $insertData['n_acta']         = $request->n_acta ?? '';
            if (in_array('presupuestario', $columns))  $insertData['presupuestario'] = $request->presupuestario ?? '----------';
            if (in_array('actividad', $columns))       $insertData['actividad']      = $request->actividad ?? '----------';
            if (in_array('ingresos', $columns))        $insertData['ingresos']       = $ingresos;
            if (in_array('egresos', $columns))         $insertData['egresos']        = $egresos;
            if (in_array('saldo', $columns))           $insertData['saldo']          = $saldo;

            DB::table($tabla)->insert($insertData);

            // Recalcular saldos desde targetId en adelante
            if (in_array('saldo', $columns)) {
                $this->recalcularSaldosDesde($tabla, $targetId);
            }

            DB::commit();

            return response()->json(['ok' => true, 'message' => 'Registro agregado en posición ' . $posicion]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Recalcular saldos acumulados desde un ID en adelante
    private function recalcularSaldosDesde($tabla, $startId)
    {
        $prev = DB::table($tabla)
            ->where('id', '<', $startId)
            ->orderBy('id', 'desc')
            ->first();

        $saldo = $prev ? (float) $prev->saldo : 0;

        $rows = DB::table($tabla)
            ->where('id', '>=', $startId)
            ->orderBy('id')
            ->get();

        foreach ($rows as $r) {
            $ing = isset($r->ingresos) ? (float) $r->ingresos : 0;
            $eg  = isset($r->egresos)  ? (float) $r->egresos  : 0;
            $saldo = round($saldo + $ing - $eg, 2);

            DB::table($tabla)->where('id', $r->id)->update([
                'saldo'      => $saldo,
                'updated_at' => now(),
            ]);
        }
    }

}