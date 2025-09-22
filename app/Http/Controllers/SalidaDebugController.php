<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Proyecto;
use Throwable;

class SalidaDebugController extends Controller
{
    public function porProductoDebug(Proyecto $proyecto, $codigo)
    {
        try {
            // 1) Generar nombre de tabla (mejorado: slug para evitar acentos/espacios raros)
            $tablaSalidas = 'salidas_proyecto_' . Str::slug($proyecto->nombre, '_');
            $tablaSalidas = Str::of($tablaSalidas)->lower()->__toString();

            // 2) Intentar detectar tablas con ese prefijo (varias estrategias)
            $foundTables = [];
            try {
                // método MySQL / MariaDB rápido
                $like = 'salidas_proyecto_%';
                $foundTables = DB::select("SHOW TABLES LIKE ?", [$like]);
            } catch (Throwable $e1) {
                try {
                    // fallback: information_schema (funciona en MySQL/Postgres con ajustes)
                    $dbName = DB::getDatabaseName();
                    $foundTables = DB::select(
                        "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name LIKE ?",
                        [$dbName, 'salidas_proyecto_%']
                    );
                } catch (Throwable $e2) {
                    // si falla, dejamos foundTables vacío
                    $foundTables = [];
                }
            }

            // normalize found table names (array of strings)
            $tables = array_map(function($row){
                if (is_object($row)) {
                    // objeto con una sola propiedad (SHOW TABLES devuelve clave variable)
                    $vals = array_values((array)$row);
                    return $vals[0] ?? null;
                }
                if (is_array($row)) {
                    $vals = array_values($row);
                    return $vals[0] ?? null;
                }
                return null;
            }, $foundTables);
            $tables = array_values(array_filter($tables));

            // 3) Check exact table existence using Schema
            $tablaExiste = Schema::hasTable($tablaSalidas);

            // 4) Listar columnas si existe alguna tabla detectada
            $columns = [];
            if ($tablaExiste) {
                $columns = Schema::getColumnListing($tablaSalidas);
            } else {
                // si no existe exactamente, intenta tomar la primera tabla encontrada
                if (!empty($tables)) {
                    $first = $tables[0];
                    $columns = Schema::getColumnListing($first);
                }
            }

            // 5) Columnas candidatas para buscar producto (mira y agrega variantes si las usas)
            $candidatas = ['producto_code','producto','producto_label','producto_name','code','codigo','productoId'];

            // 6) Construir query de debug (no ejecutamos todavía)
            $query = DB::table($tablaExiste ? $tablaSalidas : ($tables[0] ?? ''));
            // agregar condiciones según columnas existentes
            $query->where(function($q) use ($tablaExiste, $tablaSalidas, $candidatas, $codigo) {
                foreach ($candidatas as $col) {
                    if (Schema::hasColumn($tablaSalidas, $col)) {
                        $q->orWhere($col, $col === 'producto_label' ? 'like' : '=', $col === 'producto_label' ? "%{$codigo}%" : $codigo);
                    }
                }
            });

            // 7) Obtener SQL y bindings
            $sql = $query->toSql();
            $bindings = $query->getBindings();

            // 8) Ejecutar consulta (si tabla válida)
            $salidas = [];
            $count = 0;
            $firstRow = null;
            if ($tablaExiste || !empty($tables)) {
                $salidasCollect = $query->orderBy('fecha', 'asc')->get();
                $salidas = $salidasCollect->toArray();
                $count = $salidasCollect->count();
                $firstRow = $salidasCollect->first();
            }

            return response()->json([
                'ok' => true,
                'tabla_generada' => $tablaSalidas,
                'tabla_existe' => $tablaExiste,
                'tablas_encontradas_prefijo' => $tables,
                'columnas_detectadas' => $columns,
                'columnas_candidatas' => $candidatas,
                'sql_debug' => $sql,
                'bindings' => $bindings,
                'count' => $count,
                'first_row' => $firstRow,
                'salidas_preview' => array_slice($salidas, 0, 10),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
