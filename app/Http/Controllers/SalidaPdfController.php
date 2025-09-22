<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Proyecto;

class SalidaPdfController extends Controller
{
    public function exportPdf(Request $request, Proyecto $proyecto, $codigo)
    {
        // nombre dinámico de la tabla (usa slug para evitar caracteres raros)
        $tablaSalidas = 'salidas_proyecto_' . Str::slug($proyecto->nombre, '_');
        $tablaSalidas = Str::of($tablaSalidas)->lower()->__toString();

        if (! Schema::hasTable($tablaSalidas)) {
            abort(404, "Tabla $tablaSalidas no encontrada");
        }

        // columnas candidatas a mostrar
        $candidatas = [
            'N° Acta' => 'n_acta',
            'Nombre'  => 'nombre',
            'Lugar'   => 'lugar',
            'Distrito'=> 'distrito',
            'Fecha'   => 'fecha',
            'Cantidad'=> 'cantidad',
        ];

        $mostrar = [];
        foreach ($candidatas as $label => $col) {
            if (Schema::hasColumn($tablaSalidas, $col)) {
                $mostrar[] = ['label' => $label, 'col' => $col];
            }
        }

        // detectar columna cantidad alternativa
        $cantidadCol = null;
        if (Schema::hasColumn($tablaSalidas, 'cantidad')) {
            $cantidadCol = 'cantidad';
        } else {
            foreach (['qty','cant','cantidad_salida'] as $alt) {
                if (Schema::hasColumn($tablaSalidas, $alt)) { $cantidadCol = $alt; break; }
            }
            if ($cantidadCol && ! collect($mostrar)->pluck('col')->contains($cantidadCol)) {
                $mostrar[] = ['label' => 'Cantidad', 'col' => $cantidadCol];
            }
        }

        // consulta (misma lógica que usas en porProducto)
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

        // total cantidad si aplica
        $total = null;
        if ($cantidadCol) {
            $total = $salidas->sum(function ($r) use ($cantidadCol) {
                return floatval($r->{$cantidadCol} ?? 0);
            });
        }

        // obtener nombre del producto (prioridad: query param -> tabla productos -> primer fila)
        $productoNombre = $request->query('producto_nombre') ?? null;
        if (!$productoNombre && Schema::hasTable('productos')) {
            $p = DB::table('productos')->where('codigo', $codigo)->first();
            $productoNombre = $p->nombre ?? null;
        }
        if (!$productoNombre && $salidas->first()) {
            foreach (['producto_label','producto','producto_name','name'] as $col) {
                if (isset($salidas->first()->{$col})) {
                    $productoNombre = $salidas->first()->{$col};
                    break;
                }
            }
        }

        // otros datos opcionales desde query params
        $productoDescripcion = $request->query('producto_descripcion', null);
        $productoStock = $request->query('producto_stock', null);

        $data = [
            'salidas' => $salidas,
            'mostrar' => $mostrar,
            'productoNombre' => $productoNombre,
            'productoCodigo' => $codigo,
            'productoDescripcion' => $productoDescripcion,
            'productoStock' => $productoStock,
            'total' => $total,
            'proyecto' => $proyecto,
            'fecha_generado' => Carbon::now()->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView('salidas.pdf', $data)->setPaper('a4', 'portrait');

        $filename = 'salidas_' . ($codigo) . '_' . Carbon::now()->format('Ymd_His') . '.pdf';

        return $pdf->stream($filename);
    }
}
