<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\ActivityLog;


class ProyectoEasyController extends Controller
{
    public function create(Request $request, Proyecto $proyecto)
    {
        // normalización incoming
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

            // <-- nuevas claves que aceptamos desde el botón
            'numero_prefijo' => $incoming['numero_prefijo'] ?? $incoming['numeroPrefijo'] ?? null,
            'pieza_text' => $incoming['pieza_text'] ?? $incoming['piezaText'] ?? $incoming['pieza'] ?? null,
        ];

        // si actividad viene y contrato no, usar actividad como contrato
        if (!empty($prefill['actividad']) && empty($prefill['contrato'])) {
            $prefill['contrato'] = $prefill['actividad'];
        }

        // NO copiar la descripción larga a numero_descripcion_pieza.
        // Usar n_acta solo si parece un número corto; en otro caso dejar null para que frontend solicite next_numero.
        if (empty($prefill['numero_descripcion_pieza'])) {
            if (!empty($prefill['n_acta']) && preg_match('/^\d+$/', (string)$prefill['n_acta'])) {
                $prefill['numero_descripcion_pieza'] = (string)$prefill['n_acta'];
            } else {
                $prefill['numero_descripcion_pieza'] = null;
            }
        }

        // (Opcional) si quieres usar n_acta numérica como prefijo por defecto cuando no hay nada:
        // if (empty($prefill['numero_descripcion_pieza']) && !empty($prefill['n_acta']) && preg_match('/^\d+$/', (string)$prefill['n_acta'])) {
        //     $prefill['numero_descripcion_pieza'] = trim((string)$prefill['n_acta']) . '-';
        // }

        // ===== Obtener preferencias desde easy_preferences si existen (no sobreescribir) =====
        try {
            $prefRow = DB::table('easy_preferences')
                ->where('proyecto_id', $proyecto->id)
                ->whereNotNull('prefs')
                ->orderBy('id', 'desc')
                ->first();

            if ($prefRow && $prefRow->prefs) {
                $prefs = json_decode($prefRow->prefs, true);

                if (!empty($prefs['cuenta_general']) && empty($prefill['cuenta_general'])) {
                    $prefill['cuenta_general'] = $prefs['cuenta_general'];
                }
                if (!empty($prefs['tipo_cambio']) && empty($prefill['tipo_cambio'])) {
                    $prefill['tipo_cambio'] = $prefs['tipo_cambio'];
                }
                if (!empty($prefs['moneda_gestion']) && empty($prefill['moneda_gestion'])) {
                    $prefill['moneda_gestion'] = $prefs['moneda_gestion'];
                }

                // exponemos ultimo_numero_full (pero no lo copiamos automáticamente en numero_descripcion_pieza)
                if (!empty($prefs['ultimo_numero'])) {
                    $prefill['ultimo_numero_full'] = $prefs['ultimo_numero'];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('easy.create: easy_preferences not available or error: ' . $e->getMessage());
        }

        // Normalizar keys (minúscula + PascalCase para frontend)
        $outPrefill = [];
        foreach ($prefill as $k => $v) {
            if ($v !== null && $v !== '') {
                $outPrefill[$k] = $v;
                $outPrefill[ucfirst($k)] = $v;
            }
        }

        // Log final: qué vamos a enviar al view
        Log::info('easy.create prefill (to view):', ['proyecto_id' => $proyecto->id, 'prefill' => $outPrefill]);

        return inertia('proyectos/easycreate', [
            'proyecto' => $proyecto,
            'prefill' => $outPrefill
        ]);
    }

    public function lastPrefill(Proyecto $proyecto)
    {
        try {
            $tableSuffix = strtolower(str_replace(' ', '_', $proyecto->nombre));
            $tableEasy = 'easy_proyecto_' . $tableSuffix;

            // obtener el último número usado
            $last = DB::table($tableEasy)
                ->orderByDesc('id')
                ->value('numero_descripcion_pieza');

            $nextNum = 1;
            if ($last && preg_match('/^(\d+)/', $last, $m)) {
                $nextNum = intval($m[1]) + 1;
            }

            return response()->json([
                'ok' => true,
                'next_numero' => $nextNum
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
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


    public function store(Request $request, Proyecto $proyecto)
{
    try {
        // Validación
        $validated = $request->validate([
            'Cuenta_general' => 'required|string|max:255',
            'gasto_moneda_local' => 'nullable|numeric|min:0',
            'ingreso_moneda_local' => 'nullable|numeric|min:0',
            'moneda_facturacion' => 'required|string|max:10',
            'debito_moneda_gestion' => 'nullable|numeric|min:0',
            'credito_moneda_gestion' => 'nullable|numeric|min:0',
            'moneda_gestion' => 'required|string|max:10',
            'numero_descripcion_pieza' => 'required|string|max:255',
            'codigo_presupuestario' => 'nullable|string|max:255',
            'naturaleza_presupuesto' => 'nullable|string|max:255',
            'contrato' => 'nullable|string|max:255',
            'bailleur_fondos' => 'nullable|string|max:255',
            'tipo_cambio' => 'nullable|numeric',
            'fecha' => 'nullable|date',
        ]);

        // Nombre dinámico de la tabla
        $tableSuffix = strtolower(str_replace(' ', '_', $proyecto->nombre));
        $tableEasy = 'easy_proyecto_' . $tableSuffix;

        // --- Asegurar que la tabla exista y que tenga la columna tipo_cambio ---
        // Si la tabla no existe, la creamos con la estructura mínima (incluyendo tipo_cambio)
        if (! Schema::hasTable($tableEasy)) {
            Schema::create($tableEasy, function (Blueprint $t) {
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
                // columna tipo_cambio
                $t->decimal('tipo_cambio', 18, 6)->nullable();
                $t->timestamps();
            });
        } else {
            // Si la tabla existe pero no tiene la columna tipo_cambio, la agregamos
            if (! Schema::hasColumn($tableEasy, 'tipo_cambio')) {
                Schema::table($tableEasy, function (Blueprint $t) use ($tableEasy) {
                    $t->decimal('tipo_cambio', 18, 6)->nullable()->after('bailleur_fondos');
                });
            }
        }

        // --- Insertar registro dentro de transacción ---
        DB::beginTransaction();
        try {
            $insertData = [
                'Cuenta_general' => $validated['Cuenta_general'],
                'gasto_moneda_local' => $validated['gasto_moneda_local'] ?? 0,
                'ingreso_moneda_local' => $validated['ingreso_moneda_local'] ?? 0,
                'moneda_facturacion' => $validated['moneda_facturacion'],
                'debito_moneda_gestion' => $validated['debito_moneda_gestion'] ?? 0,
                'credito_moneda_gestion' => $validated['credito_moneda_gestion'] ?? 0,
                'moneda_gestion' => $validated['moneda_gestion'],
                'numero_descripcion_pieza' => $validated['numero_descripcion_pieza'],
                'codigo_presupuestario' => $validated['codigo_presupuestario'] ?? null,
                'naturaleza_presupuesto' => $validated['naturaleza_presupuesto'] ?? null,
                'contrato' => $validated['contrato'] ?? null,
                'bailleur_fondos' => $validated['bailleur_fondos'] ?? null,
                // incluir tipo_cambio solo si la columna existe (verificación extra por seguridad)
                'tipo_cambio' => Schema::hasColumn($tableEasy, 'tipo_cambio') ? ($validated['tipo_cambio'] ?? null) : null,
                'fecha' => $validated['fecha'] ?? now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table($tableEasy)->insert($insertData);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // --- Actualizar easy_preferences si llegaron claves relevantes (misma lógica que antes) ---
        try {
            $threshold = intval(env('EASY_TC_THRESHOLD', 3));
            $userId = Auth::id();
            $proyectoId = $proyecto->id;

            $explicitKeys = [
                'cuenta_general' => $validated['Cuenta_general'] ?? null,
                'tipo_cambio' => array_key_exists('tipo_cambio', $validated) ? $validated['tipo_cambio'] : null,
                'moneda_gestion' => $validated['moneda_gestion'] ?? null,
                'moneda_facturacion' => $validated['moneda_facturacion'] ?? null,
                'debito_moneda_gestion' => array_key_exists('debito_moneda_gestion', $validated) ? $validated['debito_moneda_gestion'] : null,
                'credito_moneda_gestion' => array_key_exists('credito_moneda_gestion', $validated) ? $validated['credito_moneda_gestion'] : null,
                'gasto_moneda_local' => array_key_exists('gasto_moneda_local', $validated) ? $validated['gasto_moneda_local'] : null,
                'ingreso_moneda_local' => array_key_exists('ingreso_moneda_local', $validated) ? $validated['ingreso_moneda_local'] : null,
                'numero_descripcion_pieza' => $validated['numero_descripcion_pieza'] ?? null,
                'codigo_presupuestario' => $validated['codigo_presupuestario'] ?? null,
                'naturaleza_presupuesto' => $validated['naturaleza_presupuesto'] ?? null,
                'contrato' => $validated['contrato'] ?? null,
                'bailleur_fondos' => $validated['bailleur_fondos'] ?? null,
            ];

            // obtener fila preferente user -> global
            $prefRow = DB::table('easy_preferences')
                ->where('proyecto_id', $proyectoId)
                ->where('user_id', $userId)
                ->whereNull('clave')
                ->orderBy('id', 'desc')
                ->first();

            if (! $prefRow) {
                $prefRow = DB::table('easy_preferences')
                    ->where('proyecto_id', $proyectoId)
                    ->whereNull('user_id')
                    ->whereNull('clave')
                    ->orderBy('id', 'desc')
                    ->first();
            }

            $prefsToSave = [];
            foreach ($explicitKeys as $k => $v) {
                if ($v !== null && $v !== '') {
                    $prefsToSave[$k] = $v;
                }
            }

            if (!empty($prefsToSave)) {
                if ($prefRow) {
                    $old = $prefRow->prefs ? json_decode($prefRow->prefs, true) : [];
                    $merged = array_merge($old, $prefsToSave);

                    if (isset($prefsToSave['tipo_cambio'])) {
                        $newRate = floatval($prefsToSave['tipo_cambio']);
                        $existingRate = isset($old['tipo_cambio']) ? floatval($old['tipo_cambio']) : null;

                        if ($existingRate === null) {
                            $merged['tipo_cambio'] = $newRate;
                            $merged['tipo_cambio_usage'] = [
                                'count' => 0,
                                'last_value' => $newRate,
                                'updated_at' => now()->toDateTimeString()
                            ];
                        } elseif (abs($existingRate - $newRate) > 0.000001) {
                            $usage = $old['tipo_cambio_usage'] ?? ['count' => 0, 'last_value' => $existingRate, 'updated_at' => null];

                            if (!isset($usage['last_value']) || abs(floatval($usage['last_value']) - $newRate) > 0.000001) {
                                $usage['count'] = intval(($usage['count'] ?? 0)) + 1;
                                $usage['last_value'] = $newRate;
                                $usage['updated_at'] = now()->toDateTimeString();
                            } else {
                                $usage['count'] = intval(($usage['count'] ?? 0)) + 1;
                                $usage['updated_at'] = now()->toDateTimeString();
                            }

                            if ($usage['count'] >= $threshold) {
                                $merged['tipo_cambio'] = $newRate;
                                $usage['count'] = 0;
                                $usage['last_value'] = $newRate;
                                $usage['updated_at'] = now()->toDateTimeString();
                            }

                            $merged['tipo_cambio_usage'] = $usage;
                        } else {
                            $usage = $old['tipo_cambio_usage'] ?? ['count' => 0, 'last_value' => $existingRate, 'updated_at' => now()->toDateTimeString()];
                            $usage['updated_at'] = now()->toDateTimeString();
                            $merged['tipo_cambio_usage'] = $usage;
                        }
                    }

                    DB::table('easy_preferences')->where('id', $prefRow->id)->update([
                        'prefs' => json_encode($merged),
                        'updated_at' => now(),
                    ]);

                    ActivityLog::create([
                        'user_id' => $userId,
                        'action' => 'update',
                        'model' => 'easy_preferences',
                        'model_id' => $prefRow->id,
                        'changes' => ['prefs' => $merged],
                    ]);
                } else {
                    if (isset($prefsToSave['tipo_cambio'])) {
                        $rate = floatval($prefsToSave['tipo_cambio']);
                        $prefsToSave['tipo_cambio_usage'] = [
                            'count' => 0,
                            'last_value' => $rate,
                            'updated_at' => now()->toDateTimeString()
                        ];
                    }

                    $insertId = DB::table('easy_preferences')->insertGetId([
                        'user_id' => $userId,
                        'proyecto_id' => $proyectoId,
                        'clave' => null,
                        'prefs' => json_encode($prefsToSave),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    ActivityLog::create([
                        'user_id' => $userId,
                        'action' => 'create',
                        'model' => 'easy_preferences',
                        'model_id' => $insertId,
                        'changes' => ['prefs' => $prefsToSave],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('easy.store: error updating easy_preferences - ' . $e->getMessage());
        }

        return response()->json([
            'ok' => true,
            'message' => 'Registro EASY creado correctamente en ' . $tableEasy,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'ok' => false,
            'message' => 'Error al guardar EASY: ' . $e->getMessage(),
        ], 500);
    }
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

        // Normaliza claves entrantes a snake_case para aceptar camelCase / PascalCase desde frontend
        $payload = collect($request->all())
            ->mapWithKeys(function ($value, $key) {
                return [Str::snake($key) => $value];
            })
            ->toArray();

        Log::debug('updateEasy payload normalized', [
            'table' => $table,
            'id' => $id,
            'payload' => $payload,
        ]);

        // Validación: NOTA — aquí NO está 'descripcion' porque la tabla NO la contiene
        $validated = validator($payload, [
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
        ])->validate();

        // Filtra sólo columnas existentes en la tabla para evitar errores
        $columns = Schema::getColumnListing($table);
        $allowed = array_intersect_key($validated, array_flip($columns));

        // Si no hay campos coincidentes, actualiza solo updated_at (opcional)
        if (empty($allowed)) {
            $allowed['updated_at'] = now();
        } else {
            $allowed['updated_at'] = now();
        }

        try {
            $updatedRows = DB::table($table)->where('id', $id)->update($allowed);
            Log::info('updateEasy success', [
                'table' => $table,
                'id' => $id,
                'payload' => $allowed,
                'rows' => $updatedRows,
            ]);
        } catch (\Throwable $e) {
            Log::error("updateEasy DB error en {$table}: " . $e->getMessage(), [
                'table' => $table,
                'id' => $id,
                'payload' => $allowed
            ]);
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
