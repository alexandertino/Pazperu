<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;


class AmMovimientoController extends Controller
{
    public function create(Proyecto $proyecto)
    {
        return inertia('proyectos/amcajacreate', [
            'proyecto' => $proyecto
        ]);
    }

    public function store(Proyecto $proyecto, Request $request)
    {
        // Validación
        $data = $request->validate([
            'n_acta'         => 'nullable|string|max:100',
            'prefix'         => 'nullable|in:c,b,C,B',
            'fecha'          => 'nullable|date',
            'descripcion'    => 'nullable|string|max:50',
            'presupuestario' => 'nullable|string|max:255',
            'actividad'      => 'nullable|string|max:255',
            'ingresos'       => 'nullable|numeric',
            'egresos'        => 'nullable|numeric',
            'accion'         => 'nullable|string|in:transf,sueldo,gb,ch,ingreso',
        ]);

        // Determinar prefijo / tipo (c | b)
        $nActaInput  = isset($data['n_acta']) ? trim($data['n_acta']) : '';
        $prefixInput = isset($data['prefix']) ? strtolower($data['prefix']) : null;

        if ($nActaInput !== '') {
            $first = strtolower(substr($nActaInput, 0, 1));
        } elseif ($prefixInput) {
            $first = $prefixInput;
        } else {
            $first = 'c';
        }

        if (! in_array($first, ['c', 'b'])) {
            $first = 'c';
        }

        // Sanitizar nombre de proyecto para formar sufijo de tabla (solo a-z0-9_)
        $base = (string) \Illuminate\Support\Str::of($proyecto->nombre)
            ->lower()
            ->replace(' ', '_');
        $base = preg_replace('/[^a-z0-9_]/', '', $base);

        $tablaCaja  = 'am_caja_proyecto_' . $base;
        $tablaBanco = 'am_banco_proyecto_' . $base;
        $tablaDestino = $first === 'c' ? $tablaCaja : $tablaBanco;

        // Asegurar existencia de la tabla (tu método)
        // Debe crear la tabla si no existe
        $this->ensureTableExists($tablaDestino);

        // Obtener último registro (si hay)
        $last = DB::table($tablaDestino)->orderBy('id', 'desc')->first();
        $previousSaldo = $last->saldo ?? 0;
        $lastNActa     = $last->n_acta ?? null;

        // Generar n_acta si no viene
        if ($nActaInput === '') {
            // Asumo que tienes generateNextNActa($lastNActa, $prefixUpper)
            $nActaInput = $this->generateNextNActa($lastNActa, strtoupper($first));
        }

        // Cálculos
        $ingresos = isset($data['ingresos']) ? (float) $data['ingresos'] : 0.0;
        $egresos  = isset($data['egresos']) ? (float) $data['egresos'] : 0.0;
        $saldoNuevo = $previousSaldo + $ingresos - $egresos;

        $presupuestarioVal = (isset($data['presupuestario']) && trim((string)$data['presupuestario']) !== '')
            ? $data['presupuestario']
            : '----------';

        $actividadVal = (isset($data['actividad']) && trim((string)$data['actividad']) !== '')
            ? $data['actividad']
            : '----------';

        $fechaVal = isset($data['fecha']) && $data['fecha']
            ? \Carbon\Carbon::parse($data['fecha'])->format('Y-m-d')
            : \Carbon\Carbon::now()->format('Y-m-d');

        // Construir payload "completo" que querríamos insertar
        $allData = [
            'n_acta'         => $nActaInput,
            'fecha'          => $fechaVal,
            'descripcion'    => $data['descripcion'] ?? null,
            'presupuestario' => $presupuestarioVal,
            'actividad'      => $actividadVal,
            'ingresos'       => $ingresos,
            'egresos'        => $egresos,
            'saldo'          => $saldoNuevo,
            'accion'         => $data['accion'] ?? null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        // Filtrar solo columnas que existen realmente en la tabla destino
        $columns = Schema::getColumnListing($tablaDestino);
        $insertData = array_intersect_key($allData, array_flip($columns));

        // Log para debug (opcional)
        Log::debug('AM guardar - tabla destino: ' . $tablaDestino, [
            'columns' => $columns,
            'payload_full' => $allData,
            'payload_insert' => $insertData,
        ]);

        try {
            DB::beginTransaction();
            DB::table($tablaDestino)->insert($insertData);
            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => "Registro guardado en {$tablaDestino}",
                'table' => $tablaDestino,
                'previous_saldo' => $previousSaldo,
                'saldo_saved' => $saldoNuevo,
                'n_acta_saved' => $nActaInput,
            ], 201);
        } catch (\Illuminate\Database\QueryException $qe) {
            DB::rollBack();

            Log::error('QueryException AM guardar: ' . $qe->getMessage(), [
                'sql' => $qe->getSql(),
                'bindings' => $qe->getBindings(),
                'tabla' => $tablaDestino,
                'payload' => $insertData,
                'columns' => $columns,
            ]);

            // En dev está bien retornar sql/bindings; en producción, devolver mensaje genérico
            $response = [
                'ok' => false,
                'message' => 'Database error during insert.',
                'error' => $qe->getMessage(),
                'columns' => $columns,
            ];

            if (config('app.debug')) {
                $response['sql'] = $qe->getSql();
                $response['bindings'] = $qe->getBindings();
                $response['payload'] = $insertData;
            }

            return response()->json($response, 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Exception AM guardar: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'ok' => false,
                'message' => 'Error al guardar el registro.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    protected function ensureTableExists(string $tableName)
    {
        if (Schema::hasTable($tableName)) return;

        Schema::create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('n_acta', 100)->nullable();
            $table->date('fecha')->nullable();
            $table->string('descripcion', 50)->nullable();
            $table->string('presupuestario', 255)->nullable();
            $table->string('actividad', 255)->nullable();
            $table->decimal('ingresos', 16, 2)->nullable()->default(0);
            $table->decimal('egresos', 16, 2)->nullable()->default(0);
            $table->decimal('saldo', 16, 2)->nullable()->default(0);
            $table->string('accion', 50)->nullable();
            $table->timestamps();

            $table->index('n_acta');
        });
    }

    public function meta(Proyecto $proyecto, Request $request)
    {
        $prefix = strtolower($request->get('prefix', 'c'));

        if (! in_array($prefix, ['c', 'b'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Prefijo inválido. Solo se acepta C o B.'
            ], 422);
        }

        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = $prefix === 'c'
            ? 'am_caja_proyecto_' . $base
            : 'am_banco_proyecto_' . $base;

        if (! Schema::hasTable($tabla)) {
            return response()->json([
                'ok' => true,
                'next_n_acta' => strtoupper($prefix) . '-001',
                'last_n_acta' => null,
                'previous_saldo' => 0,
                'tabla' => $tabla,
            ]);
        }

        $last = DB::table($tabla)->orderBy('id', 'desc')->first();

        $lastActa = $last->n_acta ?? null;
        $nextActa = null;

        if ($lastActa) {
            // Extraer número del acta: ej C-001 → 001
            if (preg_match('/^([A-Za-z])[- ]?(\d+)$/', $lastActa, $m)) {
                $num = intval($m[2]) + 1;
                $nextActa = strtoupper($m[1]) . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        } else {
            $nextActa = strtoupper($prefix) . '-001';
        }

        return response()->json([
            'ok' => true,
            'next_n_acta' => $nextActa,
            'last_n_acta' => $lastActa,
            'previous_saldo' => $last->saldo ?? 0,
            'tabla' => $tabla,
        ]);
    }

    protected function generateNextNActa($lastNActa, $prefixUpper)
    {
        if (! $lastNActa) {
            return "{$prefixUpper}-001";
        }

        if (preg_match('/(\d+)$/', $lastNActa, $m)) {
            $num = $m[1];
            $len = strlen($num);
            $nextNum = str_pad(((int)$num) + 1, $len, '0', STR_PAD_LEFT);
            return preg_replace('/\d+$/', $nextNum, $lastNActa);
        }

        return "{$prefixUpper}-001";
    }


    public function datos(Proyecto $proyecto)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tablaCaja = 'am_caja_proyecto_' . $base;
        $tablaBanco = 'am_banco_proyecto_' . $base;

        $caja = [];
        $banco = [];

        if (Schema::hasTable($tablaCaja)) {
            $caja = DB::table($tablaCaja)->orderBy('id')->get()->toArray();
        }

        if (Schema::hasTable($tablaBanco)) {
            $banco = DB::table($tablaBanco)->orderBy('id')->get()->toArray();
        }

        return response()->json([
            'ok' => true,
            'caja' => $caja,
            'banco' => $banco,
        ]);
    }

    public function edit(Proyecto $proyecto, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');

        // Buscar en ambas tablas (caja o banco) la fila por id
        $tablaCaja  = 'am_caja_proyecto_' . $base;
        $tablaBanco = 'am_banco_proyecto_' . $base;

        $record = null;
        $tabla  = null;

        if (Schema::hasTable($tablaCaja)) {
            $r = DB::table($tablaCaja)->where('id', $id)->first();
            if ($r) {
                $record = $r;
                $tabla = $tablaCaja;
            }
        }

        if (! $record && Schema::hasTable($tablaBanco)) {
            $r = DB::table($tablaBanco)->where('id', $id)->first();
            if ($r) {
                $record = $r;
                $tabla = $tablaBanco;
            }
        }

        if (! $record) {
            return redirect()->back()->with('error', 'Registro no encontrado.');
        }

        return inertia('proyectos/amcajaedit', [
            'proyecto' => $proyecto,
            'acta'     => $record,
            'tabla'    => $tabla,
        ]);
    }

    public function update(Proyecto $proyecto, Request $request, $id)
    {
        // Validar sólo descripcion
        $data = $request->validate([
            'descripcion' => 'nullable|string|max:50',
        ]);

        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tablaCaja  = 'am_caja_proyecto_' . $base;
        $tablaBanco = 'am_banco_proyecto_' . $base;

        // Determinar en qué tabla está el registro
        $tablaDestino = null;
        if (Schema::hasTable($tablaCaja) && DB::table($tablaCaja)->where('id', $id)->exists()) {
            $tablaDestino = $tablaCaja;
        } elseif (Schema::hasTable($tablaBanco) && DB::table($tablaBanco)->where('id', $id)->exists()) {
            $tablaDestino = $tablaBanco;
        } else {
            // Si la petición vino de Inertia, mejor redirect back con flash
            return redirect()->back()->with('error', 'Registro no encontrado.');
        }

        $update = [
            'descripcion' => $data['descripcion'] ?? null,
            'updated_at' => now(),
        ];

        try {
            DB::beginTransaction();
            DB::table($tablaDestino)->where('id', $id)->update($update);
            DB::commit();

            // Redirigir atrás para que Inertia actualice la página (no respondas con JSON plano)
            return redirect()->back()->with('success', 'Descripción actualizada correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updating AM descripcion: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Error al actualizar la descripción: ' . $e->getMessage());
        }
    }

    public function editCaja(Proyecto $proyecto, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = 'am_caja_proyecto_' . $base;

        if (! Schema::hasTable($tabla)) {
            abort(404, 'Tabla de caja no existe.');
        }

        $record = DB::table($tabla)->where('id', $id)->first();

        if (! $record) {
            abort(404, 'Registro no encontrado en caja.');
        }

        return inertia('proyectos/amcajaedit', [
            'proyecto' => $proyecto,
            'acta'     => $record,
            'tabla'    => $tabla,
        ]);
    }

    public function updateCaja(Proyecto $proyecto, Request $request, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = 'am_caja_proyecto_' . $base;

        if (! Schema::hasTable($tabla) || ! DB::table($tabla)->where('id', $id)->exists()) {
            return redirect()->back()->with('error', 'Registro de caja no encontrado.');
        }

        $data = $request->validate([
            'descripcion' => 'nullable|string|max:50',
        ]);

        try {
            DB::table($tabla)->where('id', $id)->update([
                'descripcion' => $data['descripcion'] ?? null,
                'updated_at' => now(),
            ]);

            return redirect()->route('proyectos.amcaja.edit', [$proyecto->id, $id])
                ->with('success', 'Descripción actualizada correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error updateCaja: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar la caja.');
        }
    }

    public function destroyCaja(Proyecto $proyecto, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = 'am_caja_proyecto_' . $base;

        if (! Schema::hasTable($tabla)) {
            return redirect()->back()->with('error', 'Tabla de caja no existe.');
        }

        DB::table($tabla)->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Registro de caja eliminado correctamente.');
    }


    public function editBanco(Proyecto $proyecto, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = 'am_banco_proyecto_' . $base;

        if (! Schema::hasTable($tabla)) {
            abort(404, 'Tabla de banco no existe.');
        }

        $record = DB::table($tabla)->where('id', $id)->first();

        if (! $record) {
            abort(404, 'Registro no encontrado en banco.');
        }

        return inertia('proyectos/ambancoedit', [
            'proyecto' => $proyecto,
            'acta'     => $record,
            'tabla'    => $tabla,
        ]);
    }

    public function updateBanco(Proyecto $proyecto, Request $request, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = 'am_banco_proyecto_' . $base;

        if (! Schema::hasTable($tabla) || ! DB::table($tabla)->where('id', $id)->exists()) {
            return redirect()->back()->with('error', 'Registro de banco no encontrado.');
        }

        $data = $request->validate([
            'descripcion'    => 'nullable|string|max:255',
            'presupuestario' => 'nullable|string|max:255',
            'actividad'      => 'nullable|string|max:255',
            'accion'         => 'nullable|string|max:255',
        ]);

        // Filtrar solo columnas existentes para evitar errores si alguna columna no existe
        $columns = Schema::getColumnListing($tabla);
        $allowed = array_intersect_key($data, array_flip($columns));
        $allowed['updated_at'] = now();

        try {
            DB::table($tabla)->where('id', $id)->update($allowed);

            return redirect()->route('proyectos.ambanco.edit', [$proyecto->id, $id])
                ->with('success', 'Banco actualizado correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error updateBanco: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar el banco.');
        }
    }

    public function destroyBanco(Proyecto $proyecto, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = 'am_banco_proyecto_' . $base;

        if (! Schema::hasTable($tabla)) {
            return redirect()->back()->with('error', 'Tabla de banco no existe.');
        }

        DB::table($tabla)->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Registro de banco eliminado correctamente.');
    }
}
