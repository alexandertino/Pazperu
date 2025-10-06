<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\InventarioVinculado;

class VinculacionController extends Controller
{
    protected function fetchInventarioRow(?string $tabla, $rowId)
    {
        if (! $tabla || ! $rowId) return null;
        // seguridad: permitir sólo tablas que empiecen con inventario_proyecto_ para evitar consultas arbitrarias
        if (! preg_match('/^inventario_proyecto_[a-z0-9_]+$/', $tabla)) return null;

        try {
            $row = DB::table($tabla)->where('id', $rowId)->first();
            if (! $row) return null;

            // normalizar/seleccionar solo campos relevantes (ajusta según tu esquema)
            return [
                'id' => $row->id ?? null,
                'codigo' => $row->codigo ?? null,
                'descripcion' => $row->descripcion ?? null,
                'categoria' => $row->categoria ?? null,
                'unidad_medida' => $row->unidad_medida ?? null,
                'entradas' => $row->entradas ?? null,
                'salidas' => $row->salidas ?? null,
                'stock' => $row->stock ?? null,
                'precio' => $row->precio ?? null,
                'fecha' => isset($row->fecha) ? (string)$row->fecha : (isset($row->created_at) ? (string)$row->created_at : null),
            ];
        } catch (\Throwable $e) {
            // si algo falla, devolvemos null (no rompemos la respuesta)
            return null;
        }
    }

    /**
     * Batch: devuelve vinculaciones para una lista de am_row_id.
     */
    public function batch(Request $request, $proyectoId)
    {
        $amTable = $request->query('am_table');
        $rows = $request->query('rows', []);

        if (! $amTable || empty($rows)) {
            return response()->json(['vinculaciones' => (object)[]]);
        }

        $rows = array_values(array_filter($rows, fn($r) => $r !== null && $r !== ''));

        $v = InventarioVinculado::where('proyecto_id', $proyectoId)
            ->where('am_table', $amTable)
            ->whereIn('am_row_id', $rows)
            ->get()
            ->groupBy('am_row_id');

        $out = [];
        foreach ($rows as $r) {
            $key = (string)$r;
            $list = isset($v[$r]) ? $v[$r]->values()->toArray() : [];

            // enriquecer cada vinculación con datos inventario si existen
            $enriched = array_map(function($item) {
                $item['inventario'] = null;
                if (!empty($item['inventario_table']) && !empty($item['inventario_row_id'])) {
                    $item['inventario'] = $this->fetchInventarioRow($item['inventario_table'], $item['inventario_row_id']);
                }
                return $item;
            }, $list);

            $out[$key] = $enriched;
        }

        return response()->json(['vinculaciones' => $out]);
    }

    /**
     * One: devuelve vinculaciones para un solo am_row_id, enriquecidas.
     */
    public function one(Request $request, $proyectoId)
    {
        $amTable = $request->query('am_table');
        $amRowId = $request->query('am_row_id');

        if (! $amTable || ! $amRowId) {
            return response()->json(['vinculaciones' => []]);
        }

        $v = InventarioVinculado::where('proyecto_id', $proyectoId)
            ->where('am_table', $amTable)
            ->where('am_row_id', $amRowId)
            ->get()
            ->map(function($item) {
                $arr = $item->toArray();
                $arr['inventario'] = null;
                if (!empty($arr['inventario_table']) && !empty($arr['inventario_row_id'])) {
                    $arr['inventario'] = $this->fetchInventarioRow($arr['inventario_table'], $arr['inventario_row_id']);
                }
                return $arr;
            });

        return response()->json(['vinculaciones' => $v->values()->toArray()]);
    }
}
