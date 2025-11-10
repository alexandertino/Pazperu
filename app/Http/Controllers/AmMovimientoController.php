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
        $data = $request->validate([
            'n_acta'         => 'nullable|string|max:100',
            'prefix'         => 'nullable|in:c,b,C,B',
            'fecha'          => 'nullable|date',
            'descripcion'    => 'nullable|string|max:255',
            'presupuestario' => 'nullable|string|max:255',
            'actividad'      => 'nullable|string|max:255',
            'ingresos'       => 'nullable|numeric',
            'egresos'        => 'nullable|numeric',
            'inventario'     => 'nullable',
            'cantidad'       => 'nullable|integer|min:1',
            'meta'           => 'nullable',
        ]);

        // Normalizar inventario flag
        $inventarioFlag = isset($data['inventario']) ? filter_var($data['inventario'], FILTER_VALIDATE_BOOLEAN) : false;
        $cantidad = isset($data['cantidad']) ? (int)$data['cantidad'] : 1;
        $metaRaw = $data['meta'] ?? null;

        // Determinar prefijo (c|b) y n_acta inicial
        $nActaInput  = isset($data['n_acta']) ? trim($data['n_acta']) : '';
        $prefixInput = isset($data['prefix']) ? strtolower($data['prefix']) : null;

        if ($nActaInput !== '') {
            $first = strtolower(substr($nActaInput, 0, 1));
        } elseif ($prefixInput) {
            $first = $prefixInput;
        } else {
            $first = 'c';
        }
        if (! in_array($first, ['c', 'b'])) $first = 'c';

        // Sanitizar nombre de proyecto -> sufijo de tabla (slug seguro)
        $base = \Illuminate\Support\Str::slug($proyecto->nombre, '_');
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower($base));

        $tablaCaja    = 'am_caja_proyecto_' . $base;
        $tablaBanco   = 'am_banco_proyecto_' . $base;
        $tablaDestino = $first === 'c' ? $tablaCaja : $tablaBanco;

        // Asegurar existencia de tabla dinámica
        $this->ensureTableExists($tablaDestino);

        // Preparar valores
        $ingresos = isset($data['ingresos']) ? (float)$data['ingresos'] : 0.0;
        $egresos  = isset($data['egresos']) ? (float)$data['egresos'] : 0.0;

        $presupuestarioVal = (isset($data['presupuestario']) && trim((string)$data['presupuestario']) !== '')
            ? $data['presupuestario']
            : '----------';

        $actividadVal = (isset($data['actividad']) && trim((string)$data['actividad']) !== '')
            ? $data['actividad']
            : '----------';

        $fechaVal = isset($data['fecha']) && $data['fecha']
            ? \Carbon\Carbon::parse($data['fecha'])->format('Y-m-d')
            : \Carbon\Carbon::now()->format('Y-m-d');

        // Payload full para la tabla AM
        $allData = [
            'n_acta'         => $nActaInput,
            'fecha'          => $fechaVal,
            'descripcion'    => $data['descripcion'] ?? null,
            'presupuestario' => $presupuestarioVal,
            'actividad'      => $actividadVal,
            'ingresos'       => $ingresos,
            'egresos'        => $egresos,
            'saldo'          => 0, // se calculará
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        // Filtrar solo columnas existentes
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing($tablaDestino);
        $insertData = array_intersect_key($allData, array_flip($columns));

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Bloquear y obtener último registro para evitar race conditions
            $last = \Illuminate\Support\Facades\DB::table($tablaDestino)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $previousSaldo = $last ? (float)$last->saldo : 0.0;
            $lastNActa     = $last ? $last->n_acta : null;

            // Generar n_acta si no viene y si la columna existe
            $calculatedNActa = null;
            if (trim($nActaInput) === '' && in_array('n_acta', $columns)) {
                $calculatedNActa = $this->generateNextNActa($lastNActa, strtoupper($first));
                $insertData['n_acta'] = $calculatedNActa;
            } elseif (trim($nActaInput) !== '' && in_array('n_acta', $columns)) {
                $insertData['n_acta'] = $nActaInput;
            }

            // Calcular saldo si la columna existe
            $saldoNuevo = $previousSaldo + $ingresos - $egresos;
            if (in_array('saldo', $columns)) {
                $insertData['saldo'] = $saldoNuevo;
            }

            // Insertar movimiento AM (tabla dinámica)
            $amId = \Illuminate\Support\Facades\DB::table($tablaDestino)->insertGetId($insertData);

            // Determine which numero value to send to inventario: prefer lo guardado en insertData
            $numeroToSend = $insertData['n_acta'] ?? $calculatedNActa ?? null;

            \App\Models\ActivityLog::create([
                'user_id'  => \Illuminate\Support\Facades\Auth::id(),
                'action'   => 'create',
                'model'    => $tablaDestino,
                'model_id' => $amId,
                'changes'  => ['new' => $insertData],
            ]);

            \Illuminate\Support\Facades\DB::commit();

            // Respuesta JSON para peticiones AJAX
            if ($request->expectsJson()) {
                $payload = [
                    'ok' => true,
                    'message' => "Registro guardado en {$tablaDestino}",
                    'tabla' => $tablaDestino,
                    'previous_saldo' => $previousSaldo,
                    'saldo_saved' => $saldoNuevo,
                    'n_acta_saved' => $numeroToSend,
                    'am_id' => $amId,
                    // si hay inventario, incluimos redirect (con numero)
                ];

                if ($inventarioFlag) {
                    $params = http_build_query([
                        'am_table'    => $tablaDestino,
                        'am_row_id'   => $amId,
                        'descripcion' => $insertData['descripcion'] ?? '',
                        'numero'      => $numeroToSend,
                    ]);
                    $payload['redirect'] = route('proyectos.inventarios.create', $proyecto->id) . '?' . $params;
                }

                return response()->json($payload, 201);
            }

            // Redirección HTTP normal (form submit)
            if ($inventarioFlag) {
                $params = http_build_query([
                    'am_table'    => $tablaDestino,
                    'am_row_id'   => $amId,
                    'descripcion' => $insertData['descripcion'] ?? '',
                    'numero'      => $numeroToSend,
                ]);
                return redirect()->to(route('proyectos.inventarios.create', $proyecto->id) . '?' . $params);
            }

            return redirect()->back()->with('success', "Registro guardado en {$tablaDestino}");
        } catch (\Illuminate\Database\QueryException $qe) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('QueryException AM guardar: ' . $qe->getMessage(), [
                'tabla' => $tablaDestino,
                'payload' => $insertData,
            ]);

            $response = [
                'ok' => false,
                'message' => 'Database error during insert.',
                'error' => $qe->getMessage(),
            ];

            if (config('app.debug')) {
                if (method_exists($qe, 'getSql')) $response['sql'] = $qe->getSql();
                if (method_exists($qe, 'getBindings')) $response['bindings'] = $qe->getBindings();
                $response['payload'] = $insertData;
            }

            return response()->json($response, 500);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Exception AM guardar: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

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

            $table->timestamps();

            $table->index('n_acta');
        });
    }

    protected function recalcularSaldosDesdeTabla(string $tabla, int $startId){
        if (! Schema::hasTable($tabla)) return;

        DB::beginTransaction();
        try {
            $prev = DB::table($tabla)
                ->where('id', '<', $startId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $saldo = $prev ? (float) $prev->saldo : 0.0;

            $rows = DB::table($tabla)
                ->where('id', '>=', $startId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $r) {
                $ing = isset($r->ingresos) ? (float)$r->ingresos : 0.0;
                $eg  = isset($r->egresos) ? (float)$r->egresos : 0.0;

                $saldo = round($saldo + $ing - $eg, 2);

                DB::table($tabla)->where('id', $r->id)->update([
                    'saldo' => $saldo,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function recalcularTodoTabla(string $tabla){
        if (! Schema::hasTable($tabla)) return;

        $first = DB::table($tabla)->orderBy('id')->first();
        if (! $first) return; // tabla vacía

        $this->recalcularSaldosDesdeTabla($tabla, $first->id);
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
            if (Schema::hasColumn($tablaDestino, 'saldo')) {
                $this->recalcularSaldosDesdeTabla($tablaDestino, $id);
            }
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

    public function updateCaja(Proyecto $proyecto, Request $request, $id){
    $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
    $tabla = 'am_caja_proyecto_' . $base;

    if (! Schema::hasTable($tabla) || ! DB::table($tabla)->where('id', $id)->exists()) {
        return redirect()->back()->with('error', 'Registro de caja no encontrado.');
    }

    $data = $request->validate([
        'n_acta'         => 'nullable|string|max:50',
        'fecha'          => 'nullable|date',
        'descripcion'    => 'nullable|string|max:255',
        'presupuestario' => 'nullable|string|max:100',
        'actividad'      => 'nullable|string|max:10',
        'ingresos'       => 'nullable|numeric',
        'egresos'        => 'nullable|numeric',
    ]);

    // Normalizar numeric -> decimal
    $ingresos = isset($data['ingresos']) ? (float)$data['ingresos'] : null;
    $egresos  = isset($data['egresos'])  ? (float)$data['egresos']  : null;

    $update = [
        'n_acta'         => $data['n_acta'] ?? null,
        'fecha'          => $data['fecha'] ?? null,
        'descripcion'    => $data['descripcion'] ?? null,
        'presupuestario' => $data['presupuestario'] ?? null,
        'actividad'      => $data['actividad'] ?? null,
        // solo incluir ingresos/egresos si existen en la request
        'updated_at'     => now(),
    ];

    // incluir columnas si la tabla las tiene
    $columns = Schema::getColumnListing($tabla);
    if (in_array('ingresos', $columns) && $ingresos !== null) $update['ingresos'] = $ingresos;
    if (in_array('egresos', $columns) && $egresos !== null) $update['egresos'] = $egresos;

    try {
        DB::beginTransaction();

        DB::table($tabla)->where('id', $id)->update($update);

        // Recalcular saldos desde este id (asegúrate de tener el método recalcularSaldosDesdeTabla en el controlador)
        if (in_array('saldo', $columns)) {
            $this->recalcularSaldosDesdeTabla($tabla, (int)$id);
        }

        DB::commit();

        return redirect()->route('proyectos.amcaja.edit', [$proyecto->id, $id])
            ->with('success', 'Registro actualizado correctamente.');
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('Error updateCaja: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error al actualizar la caja.');
    }
}


    public function destroyCaja(Proyecto $proyecto, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = 'am_caja_proyecto_' . $base;

        if (!Schema::hasTable($tabla)) {
            return response()->json(['message' => 'Tabla de caja no existe.'], 404);
        }

        try {
            DB::beginTransaction();
            DB::table($tabla)->where('id', $id)->delete();

            $next = DB::table($tabla)->where('id', '>', $id)->orderBy('id')->first();
            if ($next) {
                $this->recalcularSaldosDesdeTabla($tabla, $next->id);
            }

            DB::commit();
            return response()->json(['message' => 'Registro eliminado correctamente.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error destroyCaja: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar registro.'], 500);
        }
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

    // Validación
    $data = $request->validate([
        'descripcion'    => 'nullable|string|max:255',
        'presupuestario' => 'nullable|string|max:255',
        'actividad'      => 'nullable|string|max:255',
        'fecha'          => 'required|date',
        'ingresos'       => 'nullable|numeric|min:0',
        'egresos'        => 'nullable|numeric|min:0',
    ]);

    // Normalizar valores numéricos (evitar nulls)
    $data['ingresos'] = isset($data['ingresos']) ? number_format((float)$data['ingresos'], 2, '.', '') : '0.00';
    $data['egresos']  = isset($data['egresos'])  ? number_format((float)$data['egresos'], 2, '.', '') : '0.00';

    // Filtramos solo columnas existentes por seguridad
    $columns = Schema::getColumnListing($tabla);
    $allowed = array_intersect_key($data, array_flip($columns));
    $allowed['updated_at'] = now();

    try {
        DB::beginTransaction();

        DB::table($tabla)->where('id', $id)->update($allowed);

        // Recalcular saldos desde este id (si tu función espera la tabla y el id)
        if (method_exists($this, 'recalcularSaldosDesdeTabla')) {
            $this->recalcularSaldosDesdeTabla($tabla, $id);
        }

        DB::commit();

        return redirect()->route('proyectos.ambanco.edit', [$proyecto->id, $id])
            ->with('success', 'Banco actualizado correctamente.');
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('Error updateBanco: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return redirect()->back()->with('error', 'Error al actualizar el banco: ' . $e->getMessage());
    }
    }


    public function destroyBanco(Proyecto $proyecto, $id)
    {
        $base = (string) Str::of($proyecto->nombre)->lower()->replace(' ', '_');
        $tabla = 'am_banco_proyecto_' . $base;

        if (!Schema::hasTable($tabla)) {
            return response()->json(['message' => 'Tabla de banco no existe.'], 404);
        }

        try {
            DB::beginTransaction();

            // Borrar registro
            DB::table($tabla)->where('id', $id)->delete();

            // Recalcular saldos si existe siguiente registro
            $next = DB::table($tabla)->where('id', '>', $id)->orderBy('id')->first();
            if ($next) {
                $this->recalcularSaldosDesdeTabla($tabla, $next->id);
            }

            DB::commit();

            return response()->json(['message' => 'Registro de Banco eliminado correctamente.'], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error destroyBanco: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar registro de Banco.'], 500);
        }
    }

    public function recalcular(Proyecto $proyecto, Request $request){
        $tabla = $request->input('tabla'); // am_caja_proyecto_xxx o am_banco_proyecto_xxx
        if (! $tabla || ! Schema::hasTable($tabla)) {
            return response()->json(['ok' => false, 'message' => 'Tabla inválida'], 422);
        }

        try {
            $this->recalcularTodoTabla($tabla);
            return response()->json(['ok' => true, 'message' => 'Recalculado correctamente.']);
        } catch (\Throwable $e) {
            Log::error('Error recalcular: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al recalcular'], 500);
        }
    }

}
