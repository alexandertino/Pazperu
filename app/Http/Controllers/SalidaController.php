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

class SalidaController extends Controller
{
    public function index(Proyecto $proyecto)
    {
        // Generar nombre de tabla de salidas de forma consistente
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        // Verificar que exista la tabla antes de consultarla
        if (!Schema::hasTable($tablaSalidas)) {
            abort(404, 'La tabla de salidas para este proyecto no existe.');
        }

        // Obtener salidas ordenadas por fecha ascendente
        $salidas = DB::table($tablaSalidas)
            ->orderBy('fecha', 'asc')
            ->get();

        // Retornar vista con datos
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
        $request->validate([
            'n_acta' => 'required|string|max:50',
            'nombre' => 'required|string|max:100',
            'lugar' => 'required|string|max:100',
            'distrito' => 'required|string|max:100',
            'fecha' => 'required|date',
            'producto' => 'required|string|max:100',
            'cantidad' => 'required|numeric|min:1'
        ]);

        // 🔹 Tabla salidas dinámica por proyecto
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        if (!Schema::hasTable($tablaSalidas)) {
            return response()->json(['error' => 'La tabla de salidas no existe'], 404);
        }

        // 🔹 Verificamos persona en la tabla global "personas"
        $persona = DB::table('personas')
            ->where('nombre', $request->nombre)
            ->first();

        if (!$persona) {
            // Si no existe, la insertamos
            DB::table('personas')->insert([
                'nombre'     => $request->nombre,
                'lugar'      => $request->lugar,
                'distrito'   => $request->distrito,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Si ya existe, opcionalmente actualizamos lugar y distrito
            DB::table('personas')
                ->where('id', $persona->id)
                ->update([
                    'lugar'      => $request->lugar,
                    'distrito'   => $request->distrito,
                    'updated_at' => now(),
                ]);
        }

        $id = DB::table($tablaSalidas)->insertGetId([
            'n_acta'     => $request->n_acta,
            'nombre'     => $request->nombre,
            'lugar'      => $request->lugar,
            'distrito'   => $request->distrito,
            'fecha'      => $request->fecha,
            'producto'   => $request->producto,
            'cantidad'   => $request->cantidad,
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        // Log de creación
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create',
            'model' => $tablaSalidas,
            'model_id' => $id,
            'changes' => ['new' => $request->all()],
        ]);


        // 🔹 Actualizar inventario
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        $producto = DB::table($tablaInventario)
            ->where('codigo', $request->producto)
            ->first();

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado en inventario'], 404);
        }

        $nuevoStock = max(0, $producto->stock - $request->cantidad);
        $nuevasSalidas = ($producto->salidas ?? 0) + $request->cantidad;

        DB::table($tablaInventario)
            ->where('codigo', $request->producto)
            ->update([
                'stock'      => $nuevoStock,
                'salidas'    => $nuevasSalidas,
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'salida' => [
                'n_acta'   => $request->n_acta,
                'nombre'   => $request->nombre,
                'lugar'    => $request->lugar,
                'distrito' => $request->distrito,
                'fecha'    => $request->fecha,
                'producto' => $request->producto,
                'cantidad' => $request->cantidad
            ],
            'nuevoStock' => $nuevoStock
        ]);
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
        $request->validate([
            'n_acta'   => 'required|string|max:50',
            'nombre'   => 'required|string|max:100',
            'lugar'    => 'required|string|max:100',
            'distrito' => 'required|string|max:100',
            'fecha'    => 'required|date',
            'producto' => 'required|string|max:100',
            'cantidad' => 'required|numeric|min:1',
        ]);

        $proyecto = Proyecto::findOrFail($proyectoId);
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        // 🔹 Guardamos datos antiguos (antes de actualizar)
        $oldData = DB::table($tablaSalidas)->where('id', $id)->first();
        if (!$oldData) {
            return response()->json(['error' => 'Salida no encontrada'], 404);
        }

        // 🔹 Actualizar la salida
        DB::table($tablaSalidas)
            ->where('id', $id)
            ->update([
                'n_acta'     => $request->n_acta,
                'nombre'     => $request->nombre,
                'lugar'      => $request->lugar,
                'distrito'   => $request->distrito,
                'fecha'      => $request->fecha,
                'producto'   => $request->producto,
                'cantidad'   => $request->cantidad,
                'updated_at' => now(),
            ]);

        // 🔹 Guardamos datos nuevos (después de actualizar)
        $newData = DB::table($tablaSalidas)->where('id', $id)->first();

        // 🔹 Ajustar inventario
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $producto = DB::table($tablaInventario)->where('codigo', $request->producto)->first();

        if ($producto) {
            // Devolver stock anterior (sumamos la cantidad original)
            $stockRestaurado = $producto->stock + $oldData->cantidad;

            // Luego restar la nueva cantidad
            $nuevoStock = max(0, $stockRestaurado - $request->cantidad);

            // Ajustar total de salidas (quitamos la original y sumamos la nueva)
            $nuevasSalidas = max(0, ($producto->salidas - $oldData->cantidad) + $request->cantidad);

            DB::table($tablaInventario)
                ->where('codigo', $request->producto)
                ->update([
                    'stock'      => $nuevoStock,
                    'salidas'    => $nuevasSalidas,
                    'updated_at' => now()
                ]);
        }

        // 🔹 Log de actualización
        ActivityLog::create([
            'user_id'  => Auth::id(),
            'action'   => 'update',
            'model'    => $tablaSalidas,
            'model_id' => $id,
            'changes'  => [
                'old' => $oldData,
                'new' => $newData,
            ],
        ]);

        return redirect()->route('proyectos.salidas', $proyectoId)
            ->with('success', 'Salida actualizada correctamente.');
    }

    public function destroy($proyectoId, $id)
    {

        $proyecto = Proyecto::findOrFail($proyectoId);

        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        // Buscar salida
        $salida = DB::table($tablaSalidas)->where('id', $id)->first();
        // salida ya la tienes aquí
        DB::table($tablaSalidas)->where('id', $id)->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'model' => $tablaSalidas,
            'model_id' => $id,
            'changes' => ['deleted' => $salida],
        ]);

        if (!$salida) {
            return response()->json(['error' => 'Salida no encontrada'], 404);
        }

        // Buscar producto en inventario usando el mismo campo (codigo)
        $producto = DB::table($tablaInventario)->where('codigo', $salida->producto)->first();

        if ($producto) {
            DB::table($tablaInventario)
                ->where('id', $producto->id)
                ->update([
                    'stock'   => $producto->stock + $salida->cantidad,
                    'salidas' => max(0, $producto->salidas - $salida->cantidad),
                    'updated_at' => now()
                ]);
        }

        // Eliminar la salida
        DB::table($tablaSalidas)->where('id', $id)->delete();

        return response()->json(['success' => 'Salida eliminada y stock restaurado']);
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

    //Patrte de exel:
    public function exportarProyecto($proyecto)
    {
        $tablaInventario = 'inventario_proyecto_' . Str::of($proyecto)->lower()->replace(' ', '_');
        $tablaSalidas    = 'salidas_proyecto_' . Str::of($proyecto)->lower()->replace(' ', '_');

        $inventarios = DB::table($tablaInventario)
            ->select('codigo', 'fecha', 'descripcion', 'um', 'categoria', 'entradas', 'salidas', 'stock')
            ->get()
            ->toArray();

        $salidas = DB::table($tablaSalidas)
            ->select('n_acta', 'nombre', 'lugar', 'distrito', 'fecha', 'producto', 'cantidad')
            ->get()
            ->toArray();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 🔹 Título del proyecto
        $sheet->mergeCells('A1:H1'); // Combina celdas para el título
        $sheet->setCellValue('A1', strtoupper("Proyecto: $proyecto"));
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 🔹 Inventario (empieza en fila 3, columna A)
        $sheet->setCellValue('A3', 'Inventario');
        $sheet->getStyle('A3')->getFont()->setBold(true);

        // Encabezados inventario
        $headersInventario = ['Codigo', 'Fecha', 'Descripcion', 'UM', 'Categoria', 'Entradas', 'Salidas', 'Stock'];
        $sheet->fromArray($headersInventario, null, 'A4');
        $sheet->getStyle('A4:H4')->getFont()->setBold(true);

        // Datos inventario
        $sheet->fromArray($inventarios, null, 'A5');

        // 🔹 Salidas (empieza en fila 3, columna J → al lado de inventario)
        $sheet->setCellValue('J3', 'Salidas');
        $sheet->getStyle('J3')->getFont()->setBold(true);

        $headersSalidas = ['N° Acta', 'Nombre', 'Lugar', 'Distrito', 'Fecha', 'Producto', 'Cantidad'];
        $sheet->fromArray($headersSalidas, null, 'J4');
        $sheet->getStyle('J4:P4')->getFont()->setBold(true);

        $sheet->fromArray($salidas, null, 'J5');

        // Ajustar ancho automático
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Nombre con fecha/hora
        $fechaHora = Carbon::now()->format('Y-m-d_H-i-s');
        $nombreArchivo = "proyecto_{$proyecto}_{$fechaHora}.xlsx";

        // Guardar en carpeta public/excel_consulta
        $carpeta = public_path('excel_consulta');
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }
        $rutaArchivo = $carpeta . '/' . $nombreArchivo;

        $writer = new Xlsx($spreadsheet);
        $writer->save($rutaArchivo);

        return response()->download($rutaArchivo);
    }

    // Importar salidas desde Excel
    public function importarSalidas(Request $request, $proyecto)
    {
        $request->validate([
            'archivo_excel' => 'required|file|mimes:xlsx,csv,ods',
        ]);

        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto)->lower()->replace(' ', '_');

        (new FastExcel)->import($request->file('archivo_excel'), function ($line) use ($tablaSalidas) {
            DB::table($tablaSalidas)->insert([
                'n_acta'   => $line['n_acta'],
                'nombre'   => $line['nombre'],
                'lugar'    => $line['lugar'],
                'distrito' => $line['distrito'],
                'fecha'    => $line['fecha'],
                'producto' => $line['producto'],
                'cantidad' => $line['cantidad'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Salidas importadas correctamente.');
    }

    public function porProducto(Proyecto $proyecto, $codigo)
    {
        // Nombre dinámico de la tabla (ej: salidas_proyecto_hola)
        $tablaSalidas = 'salidas_proyecto_' . Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        // Si la tabla no existe, devolver vacío
        if (!Schema::hasTable($tablaSalidas)) {
            return response()->json([]);
        }

        // Buscar las salidas por el campo correcto "producto"
        $salidas = DB::table($tablaSalidas)
            ->where('producto', $codigo)
            ->orderBy('fecha', 'asc')
            ->get();

        return response()->json($salidas);
    }
}
