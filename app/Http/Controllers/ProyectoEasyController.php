<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\ActivityLog;


class ProyectoEasyController extends Controller
{
    public function create(Request $request, Proyecto $proyecto)
    {
        // normalización incoming (tuya)
        $incoming = $request->all();

        $prefill = [
            'cuenta_general' => $incoming['Cuenta_general'] ?? $incoming['cuenta_general'] ?? null,
            'gasto_moneda_local' => $incoming['gasto_moneda_local'] ?? null,
            'ingreso_moneda_local' => $incoming['ingreso_moneda_local'] ?? null,
            'descripcion' => $incoming['descripcion'] ?? $incoming['Descripcion'] ?? null,
            'numero_descripcion_pieza' => $incoming['numero_descripcion_pieza'] ?? null,
            'codigo_presupuestario' => $incoming['codigo_presupuestario'] ?? null,
            'naturaleza_presupuesto' => $incoming['naturaleza_presupuesto'] ?? null,
            'contrato' => $incoming['contrato'] ?? null,
            'bailleur_fondos' => $incoming['bailleur_fondos'] ?? null,
            'tipo_cambio' => $incoming['tipo_cambio'] ?? null,
            'moneda_gestion' => $incoming['moneda_gestion'] ?? null,
            'fecha' => $incoming['fecha'] ?? null,
            'n_acta' => $incoming['n_acta'] ?? null,
            'actividad' => $incoming['actividad'] ?? null,
        ];

        if (!empty($prefill['actividad']) && empty($prefill['contrato'])) {
            $prefill['contrato'] = $prefill['actividad'];
        }

        if (empty($prefill['numero_descripcion_pieza'])) {
            $prefill['numero_descripcion_pieza'] = $prefill['descripcion'] ?? $prefill['n_acta'] ?? null;
        }

        // ===== Obtener preferencias desde easy_preferences si existen =====
        try {
            $prefRow = DB::table('easy_preferences')
                ->where('proyecto_id', $proyecto->id)
                ->whereNotNull('prefs')
                ->orderBy('id', 'desc')
                ->first();

            if ($prefRow && $prefRow->prefs) {
                $prefs = json_decode($prefRow->prefs, true);
                // solo tomar claves si vienen
                if (!empty($prefs['cuenta_general']) && empty($prefill['cuenta_general'])) {
                    $prefill['cuenta_general'] = $prefs['cuenta_general'];
                }
                if (!empty($prefs['tipo_cambio']) && empty($prefill['tipo_cambio'])) {
                    $prefill['tipo_cambio'] = $prefs['tipo_cambio'];
                }
                if (!empty($prefs['moneda_gestion']) && empty($prefill['moneda_gestion'])) {
                    $prefill['moneda_gestion'] = $prefs['moneda_gestion'];
                }
                if (!empty($prefs['ultimo_numero']) && empty($prefill['numero_descripcion_pieza'])) {
                    // permitir que frontend reciba el último formato completo
                    $prefill['numero_descripcion_pieza'] = $prefs['ultimo_numero'];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('easy.create: easy_preferences not available or error: ' . $e->getMessage());
        }

        // Normalizar keys para compatibilidad
        $outPrefill = [];
        foreach ($prefill as $k => $v) {
            if ($v !== null) {
                $outPrefill[$k] = $v;
                $outPrefill[ucfirst($k)] = $v;
            }
        }

        Log::info('easy.create prefill (to view):', $outPrefill);

        return inertia('proyectos/easycreate', [
            'proyecto' => $proyecto,
            'prefill' => $outPrefill
        ]);
    }

    public function lastPrefill(Proyecto $proyecto, Request $request)
    {
        $table = $this->makeTableName($proyecto, $request->query('nombre'));

        if (! Schema::hasTable($table)) {
            return response()->json([
                'ok' => true,
                'next_numero' => 1,
                'last_cuenta' => null,
                'suggested_numero_full' => '1-Descripcion'
            ]);
        }

        $incomingDescription = $request->query('descripcion') ?? null;
        $incomingDescription = is_string($incomingDescription) ? trim($incomingDescription) : null;

        // último registro
        $last = DB::table($table)->orderByDesc('id')->first();

        $lastCuenta = $last->cuenta_general ?? null;
        $lastNumero = $last->numero_descripcion_pieza ?? null;

        // extraer prefijo numérico si existe (soporta guion, underscore, punto)
        $lastNum = null;
        $lastRest = null;
        if ($lastNumero && preg_match('/^\s*(\d+)\s*[-._]?\s*(.*)$/u', $lastNumero, $m)) {
            $lastNum = intval($m[1]);
            $lastRest = trim($m[2] ?? '');
        }

        // helper para limpiar la descripción (espacios -> underscore, colapsar guiones múltiples)
        $sanitize = function ($str) {
            $s = trim($str);
            $s = preg_replace('/\s+/', '_', $s);           // espacios a _
            $s = preg_replace('/_+/', '_', $s);            // colapsar _
            $s = preg_replace("/[\r\n]+/", '', $s);        // quitar saltos
            return $s;
        };

        if ($incomingDescription) {
            $next = ($lastNum !== null) ? $lastNum + 1 : 1;
            $descClean = $sanitize($incomingDescription);
            $suggested = $next . '-' . $descClean;
            return response()->json([
                'ok' => true,
                'next_numero' => $next,
                'last_cuenta' => $lastCuenta,
                'suggested_numero_full' => $suggested
            ]);
        }

        if ($lastNumero) {
            if ($lastNum !== null) {
                $next = $lastNum + 1;
                $rest = $lastRest ?: 'Descripcion';
                $suggested = $next . '-' . $rest;
                return response()->json([
                    'ok' => true,
                    'next_numero' => $next,
                    'last_cuenta' => $lastCuenta,
                    'suggested_numero_full' => $suggested
                ]);
            } else {
                // si el último no tenía prefijo numérico, proponemos v2 o iniciar en 1
                return response()->json([
                    'ok' => true,
                    'next_numero' => 1,
                    'last_cuenta' => $lastCuenta,
                    'suggested_numero_full' => ($lastNumero ? ($lastNumero . ' (v2)') : '1-Descripcion')
                ]);
            }
        }

        // no hay nada aún
        return response()->json([
            'ok' => true,
            'next_numero' => 1,
            'last_cuenta' => $lastCuenta,
            'suggested_numero_full' => '1-Descripcion'
        ]);
    }

    protected function makeTableName(Proyecto $proyecto, ?string $nombre = null): string
    {
        $base = $nombre ?? $proyecto->nombre ?? 'default';
        $sufijo = strtolower(str_replace(' ', '_', (string) $base));
        $sufijo = preg_replace('/[^a-z0-9_]/', '', $sufijo);
        if ($sufijo === '') {
            $sufijo = 'default';
        }
        return 'easy_proyecto_' . $sufijo;
    }

    protected function ensureTableExists(string $table)
    {
        if (Schema::hasTable($table)) {
            // Si la tabla existe pero no tiene columna tipo_cambio, la agregamos
            if (! Schema::hasColumn($table, 'tipo_cambio')) {
                Schema::table($table, function (\Illuminate\Database\Schema\Blueprint $t) use ($table) {
                    $t->decimal('tipo_cambio', 18, 6)->nullable()->after('bailleur_fondos');
                });
            }
            return;
        }

        Schema::create($table, function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('n_acta')->nullable();
            $t->date('fecha')->nullable();
            $t->string('presupuestario', 255)->nullable();
            $t->string('actividad', 255)->nullable();
            $t->text('descripcion')->nullable();
            $t->decimal('ingresos', 15, 2)->default(0);
            $t->decimal('egresos', 15, 2)->default(0);
            $t->decimal('saldo', 15, 2)->default(0);
            $t->string('cuenta_general')->nullable();
            $t->decimal('gasto_moneda_local', 15, 2)->default(0);
            $t->decimal('ingreso_moneda_local', 15, 2)->default(0);
            $t->string('moneda_facturacion', 10)->nullable();
            $t->decimal('debito_moneda_gestion', 15, 2)->default(0);
            $t->decimal('credito_moneda_gestion', 15, 2)->default(0);
            $t->string('moneda_gestion', 10)->nullable();
            $t->string('numero_descripcion_pieza', 255)->nullable();
            $t->string('codigo_presupuestario', 255)->nullable();
            $t->string('naturaleza_presupuesto', 50)->nullable();
            $t->string('contrato', 255)->nullable();
            $t->string('bailleur_fondos', 50)->nullable();

            // NUEVO: tipo_cambio guardado en cada registro
            $t->decimal('tipo_cambio', 18, 6)->nullable();

            $t->timestamps();
        });

        Log::info("Tabla EASY creada: {$table}");
    }


    public function store(Request $request, \App\Models\Proyecto $proyecto)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'Cuenta_general' => 'required|string|max:255',
            'gasto_moneda_local' => 'nullable|numeric|min:0',
            'ingreso_moneda_local' => 'nullable|numeric|min:0',
            'moneda_facturacion' => 'required|string|max:10',
            'debito_moneda_gestion' => 'nullable|numeric|min:0',
            'credito_moneda_gestion' => 'nullable|numeric|min:0',
            'moneda_gestion' => 'required|string|max:10',
            'numero_descripcion_pieza' => 'required|string|max:255', // puede venir "398-Desc" o solo "Desc"
            'tipo_cambio' => 'nullable|numeric',
            'fecha' => 'nullable|date'
        ]);

        // construir nombre de tabla
        $tableSuffix = strtolower(str_replace(' ', '_', $validated['nombre']));
        $tableSuffix = preg_replace('/[^a-z0-9_]/', '', $tableSuffix);
        if ($tableSuffix === '') $tableSuffix = 'default';
        $tableEasy = 'easy_proyecto_' . $tableSuffix;

        // asegurar existencia/columnas
        $this->ensureTableExists($tableEasy);

        // normalizador simple para la parte textual de la descripción
        $sanitizeDesc = function ($str) {
            $s = trim((string)$str);
            $s = preg_replace('/\s+/', '_', $s);    // espacios -> _
            $s = preg_replace('/_+/', '_', $s);     // colapsar underscores múltiples
            $s = preg_replace("/[\r\n]+/", '', $s); // quitar saltos de línea
            return $s;
        };

        // decidir fecha a usar (si no viene, hoy)
        try {
            $dt = isset($validated['fecha']) ? Carbon::parse($validated['fecha']) : Carbon::now();
        } catch (\Throwable $e) {
            $dt = Carbon::now();
        }
        $fechaParaGuardar = $dt->format('Y-m-d');

        // insertar dentro de transacción para evitar duplicados en el número secuencial
        $newId = null;

        try {
            DB::transaction(function () use (&$newId, $tableEasy, $validated, $sanitizeDesc, $fechaParaGuardar, $proyecto, $dt) {
                // obtener último registro bloqueando (si no hay ninguno, $last será null)
                $last = DB::table($tableEasy)->lockForUpdate()->orderByDesc('id')->first();
                $lastNumero = $last ? ($last->numero_descripcion_pieza ?? null) : null;

                $lastNum = null;
                $lastRest = null;
                if ($lastNumero && preg_match('/^\s*(\d+)\s*[-._]?\s*(.*)$/u', $lastNumero, $m)) {
                    $lastNum = intval($m[1]);
                    $lastRest = trim($m[2] ?? '');
                }

                $incoming = trim((string)$validated['numero_descripcion_pieza']);
                $finalNumeroFull = null;

                // si el incoming ya trae prefijo numérico (ej "398-....") lo respetamos (sanitizamos la parte textual)
                if (preg_match('/^\s*(\d+)\s*[-._]?\s*(.*)$/u', $incoming, $mi)) {
                    $num = intval($mi[1]);
                    $rest = trim($mi[2] ?? '');
                    $restSan = $rest ? $sanitizeDesc($rest) : ($lastRest ?: 'Descripcion');
                    $finalNumeroFull = $num . '-' . $restSan;
                } else {
                    // incoming no tiene número; calculamos siguiente
                    $next = ($lastNum !== null) ? ($lastNum + 1) : 1;
                    $restSan = $incoming ? $sanitizeDesc($incoming) : ($lastRest ?: 'Descripcion');
                    $finalNumeroFull = $next . '-' . $restSan;
                }

                // determinar tipo_cambio a usar: primero intentar obtener de exchange_rates (modelo exchange_rates)
                $tipoCambioToUse = null;
                try {
                    if (class_exists(\App\Models\exchange_rates::class)) {
                        $rateRow = \App\Models\exchange_rates::currentForProject($proyecto->id, (int)$dt->year, (int)$dt->month)
                            ?? \App\Models\exchange_rates::fallbackGlobal((int)$dt->year, (int)$dt->month);

                        if ($rateRow && isset($rateRow->rate)) {
                            $tipoCambioToUse = $rateRow->rate;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::debug('Error buscando exchange rate: ' . $e->getMessage());
                }

                // si no se obtuvo, tomar la que envió el cliente (si existe)
                if ($tipoCambioToUse === null && request()->filled('tipo_cambio')) {
                    $tipoCambioToUse = request()->input('tipo_cambio');
                }

                // construir el array a insertar
                $insert = [
                    'cuenta_general' => $validated['Cuenta_general'],
                    'gasto_moneda_local' => $validated['gasto_moneda_local'] ?? 0,
                    'ingreso_moneda_local' => $validated['ingreso_moneda_local'] ?? 0,
                    'moneda_facturacion' => $validated['moneda_facturacion'],
                    'debito_moneda_gestion' => $validated['debito_moneda_gestion'] ?? 0,
                    'credito_moneda_gestion' => $validated['credito_moneda_gestion'] ?? 0,
                    'moneda_gestion' => $validated['moneda_gestion'],
                    'numero_descripcion_pieza' => $finalNumeroFull,
                    'codigo_presupuestario' => request()->input('codigo_presupuestario'),
                    'naturaleza_presupuesto' => request()->input('naturaleza_presupuesto'),
                    'contrato' => request()->input('contrato'),
                    'bailleur_fondos' => request()->input('bailleur_fondos'),
                    'fecha' => $fechaParaGuardar,
                    'tipo_cambio' => $tipoCambioToUse,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // insertar
                $newId = DB::table($tableEasy)->insertGetId($insert);

                // actualizar/crear easy_preferences con último número y otros prefs relevantes
                $this->ensurePreferencesTableExists();
                $userId = Auth::id();
                $proyectoId = $proyecto->id;

                $prefsToSave = [
                    'cuenta_general' => $validated['Cuenta_general'],
                    'tipo_cambio' => $tipoCambioToUse !== null ? $tipoCambioToUse : (request()->input('tipo_cambio') ?? null),
                    'moneda_gestion' => $validated['moneda_gestion'],
                    'ultimo_numero' => $finalNumeroFull,
                    'ultimo_fecha' => $fechaParaGuardar
                ];
                $prefsToSave = array_filter($prefsToSave, function ($v) {
                    return $v !== null && $v !== '';
                });

                $existing = DB::table('easy_preferences')
                    ->where('user_id', $userId)
                    ->where('proyecto_id', $proyectoId)
                    ->whereNull('clave')
                    ->first();

                if ($existing) {
                    $old = $existing->prefs ? json_decode($existing->prefs, true) : [];
                    $merged = array_merge($old, $prefsToSave);
                    DB::table('easy_preferences')->where('id', $existing->id)->update([
                        'prefs' => json_encode($merged),
                        'updated_at' => now()
                    ]);
                } else {
                    DB::table('easy_preferences')->insert([
                        'user_id' => $userId,
                        'proyecto_id' => $proyectoId,
                        'clave' => null,
                        'prefs' => json_encode($prefsToSave),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }); // fin transaction

        } catch (\Throwable $e) {
            Log::error('Error guardando registro EASY: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error guardando registro EASY: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'Registro EASY guardado correctamente.',
            'id' => $newId,
            'table' => $tableEasy,
        ], 201);
    }

    protected function ensurePreferencesTableExists()
    {
        if (Schema::hasTable('easy_preferences')) {
            return;
        }

        Schema::create('easy_preferences', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('user_id')->nullable()->index(); // null = pref global del proyecto
            $t->unsignedBigInteger('proyecto_id')->nullable()->index();
            $t->string('clave')->nullable()->index(); // opcional: clave para distintos sets de prefs
            $t->json('prefs')->nullable(); // JSON con las preferencias
            $t->timestamps();
        });
    }

    public function preferences(Proyecto $proyecto, Request $request)
    {
        $this->ensurePreferencesTableExists();

        $userId = Auth::id(); // si quieres que también acepte guardar globales, podrías permitir user_id=null mediante permisos
        $proyectoId = $proyecto->id;

        // Si es GET: devolver preferencias (preferencia del user > pref global del proyecto > vacío)
        if ($request->isMethod('get')) {
            $row = DB::table('easy_preferences')
                ->where('user_id', $userId)
                ->where('proyecto_id', $proyectoId)
                ->first();

            if (! $row) {
                // buscar pref global del proyecto (user_id NULL)
                $row = DB::table('easy_preferences')
                    ->whereNull('user_id')
                    ->where('proyecto_id', $proyectoId)
                    ->first();
            }

            $prefs = $row && $row->prefs ? json_decode($row->prefs, true) : [];

            return response()->json([
                'ok' => true,
                'prefs' => $prefs,
                'source' => $row ? ($row->user_id ? 'user' : 'project_global') : 'none',
            ]);
        }

        // Si es POST/PUT: validar y guardar/actualizar
        $validated = $request->validate([
            'cuenta_general' => 'nullable|string|max:255',
            'tipo_cambio' => 'nullable|numeric',
            'moneda_gestion' => 'nullable|string|max:10',
            'moneda_facturacion' => 'nullable|string|max:10',
            'debito_moneda_gestion' => 'nullable|numeric',
            'credito_moneda_gestion' => 'nullable|numeric',
            'gasto_moneda_local' => 'nullable|numeric',
            'ingreso_moneda_local' => 'nullable|numeric',
            'numero_descripcion_pieza' => 'nullable|string|max:255',
            'codigo_presupuestario' => 'nullable|string|max:255',
            'naturaleza_presupuesto' => 'nullable|string|max:255',
            'contrato' => 'nullable|string|max:255',
            'bailleur_fondos' => 'nullable|string|max:255',
            'prefs' => 'nullable|array', // cualquier clave-valor extra
            'clave' => 'nullable|string|max:100', // opcional para identificar el set
            // si quieres permitir guardar "global" (user_id null), añade lógica de permisos aquí
        ]);

        // Construir prefs finales: tomar sólo valores no nulos y mergear con 'prefs' array
        $explicitKeys = [
            'cuenta_general',
            'tipo_cambio',
            'moneda_gestion',
            'moneda_facturacion',
            'debito_moneda_gestion',
            'credito_moneda_gestion',
            'gasto_moneda_local',
            'ingreso_moneda_local',
            'numero_descripcion_pieza',
            'codigo_presupuestario',
            'naturaleza_presupuesto',
            'contrato',
            'bailleur_fondos'
        ];

        $prefsToSave = [];

        foreach ($explicitKeys as $k) {
            if (array_key_exists($k, $validated) && $validated[$k] !== null) {
                $prefsToSave[$k] = $validated[$k];
            }
        }

        // mergear el array 'prefs' que venga en el body (si existe)
        if (! empty($validated['prefs']) && is_array($validated['prefs'])) {
            foreach ($validated['prefs'] as $k => $v) {
                // solo guardar claves válidas (o todas si lo prefieres)
                $prefsToSave[$k] = $v;
            }
        }

        $clave = $validated['clave'] ?? null;

        // comprobar si ya existe fila (por user + proyecto + clave)
        $existingQuery = DB::table('easy_preferences')
            ->where('user_id', $userId)
            ->where('proyecto_id', $proyectoId);

        if ($clave !== null) {
            $existingQuery->where('clave', $clave);
        } else {
            $existingQuery->whereNull('clave');
        }

        $existing = $existingQuery->first();

        if ($existing) {
            // merge con prefs existentes (no sobrescribir keys no presentes)
            $old = $existing->prefs ? json_decode($existing->prefs, true) : [];
            $merged = array_merge($old, $prefsToSave);

            DB::table('easy_preferences')
                ->where('id', $existing->id)
                ->update([
                    'prefs' => json_encode($merged),
                    'updated_at' => now(),
                ]);

            $savedRowId = $existing->id;
            $resultPrefs = $merged;
        } else {
            $insertId = DB::table('easy_preferences')->insertGetId([
                'user_id' => $userId,
                'proyecto_id' => $proyectoId,
                'clave' => $clave,
                'prefs' => json_encode($prefsToSave),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $savedRowId = $insertId;
            $resultPrefs = $prefsToSave;
        }

        // Activity log
        ActivityLog::create([
            'user_id' => $userId,
            'action' => $existing ? 'update' : 'create',
            'model' => 'easy_preferences',
            'model_id' => $savedRowId,
            'changes' => ['prefs' => $resultPrefs],
        ]);

        return response()->json([
            'ok' => true,
            'prefs' => $resultPrefs,
            'id' => $savedRowId,
        ]);
    }

    // mostrar
    public function editEasy(Proyecto $proyecto, $id)
    {
        $table = $this->makeTableName($proyecto);

        if (! Schema::hasTable($table)) {
            Log::warning("editEasy: tabla no existe: {$table}");
            abort(404, "Tabla EASY no encontrada: {$table}");
        }

        try {
            $acta = DB::table($table)->where('id', $id)->first();
        } catch (\Throwable $e) {
            Log::error("editEasy DB error al leer {$table}: " . $e->getMessage(), ['table' => $table, 'id' => $id]);
            abort(500, "Error DB leyendo registro: " . $e->getMessage());
        }

        if (! $acta) {
            abort(404, "Registro no encontrado en {$table}");
        }

        return inertia('proyectos/easyedit', [
            'proyecto' => $proyecto,
            'acta' => $acta,
            'table' => $table,
        ]);
    }

    // actualizar
    public function updateEasy(Request $request, Proyecto $proyecto, $id)
    {
        $table = $this->makeTableName($proyecto);

        if (! Schema::hasTable($table)) {
            return redirect()->back()->with('error', "Tabla EASY no encontrada: {$table}");
        }

        $data = $request->validate([
            'descripcion' => 'nullable|string',
            'fecha' => 'nullable|date',
            'gasto_moneda_local' => 'nullable|numeric',
            'ingreso_moneda_local' => 'nullable|numeric',
            'debito_moneda_gestion' => 'nullable|numeric',
            'credito_moneda_gestion' => 'nullable|numeric',
            'numero_descripcion_pieza' => 'nullable|string|max:255',
            'codigo_presupuestario' => 'nullable|string|max:255',
            'naturaleza_presupuesto' => 'nullable|string|max:255',
            'contrato' => 'nullable|string|max:255',
            'bailleur_fondos' => 'nullable|string|max:255',
            'tipo_cambio' => 'nullable|numeric',
            'n_acta' => 'nullable|string|max:100',
            'cuenta_general' => 'nullable|string|max:255',
        ]);

        // sólo columnas existentes — evita SQL errors por columnas ausentes
        $columns = Schema::getColumnListing($table);
        $allowed = array_intersect_key($data, array_flip($columns));
        $allowed['updated_at'] = now();

        try {
            DB::table($table)->where('id', $id)->update($allowed);
        } catch (\Throwable $e) {
            Log::error("updateEasy DB error en {$table}: " . $e->getMessage(), ['table' => $table, 'id' => $id, 'payload' => $allowed]);
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }

        return redirect()->route('proyectos.easy.edit', [$proyecto->id, $id])
            ->with('success', 'Registro EASY actualizado correctamente.');
    }

    // eliminar
    public function destroyEasy(Proyecto $proyecto, $id)
    {
        $table = $this->makeTableName($proyecto);

        if (! Schema::hasTable($table)) {
            return redirect()->back()->with('error', "Tabla EASY no encontrada: {$table}");
        }

        try {
            DB::table($table)->where('id', $id)->delete();
        } catch (\Throwable $e) {
            Log::error("destroyEasy DB error en {$table}: " . $e->getMessage(), ['table' => $table, 'id' => $id]);
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Registro eliminado.');
    }
}
