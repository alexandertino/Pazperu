<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Str;

class InventarioMetaController extends Controller
{
    protected function tableForType(string $type)
    {
        $t = Str::lower($type);

        $map = [
            'categorias' => 'categorias',
            'categoria' => 'categorias',

            'unidadmedida' => 'unidades_medida',
            'unidad_medida' => 'unidades_medida',
            'unidad-medida' => 'unidades_medida',
            'unidad' => 'unidades_medida',
            'unidadmedidas' => 'unidades_medida',

            'solicitantes' => 'solicitantes',
            'solicitante' => 'solicitantes',

            'personas' => 'personas',
            'persona' => 'personas',

            'proyectos' => 'proyectos',
            'proyecto' => 'proyectos',

            'usuarios' => 'users', // nuevo mapeo
            'usuario' => 'users',
        ];

        return $map[$t] ?? null;
    }

    public function manage()
    {
        $categorias = DB::table('categorias')->select('id', 'nombre')->orderBy('nombre')->get();
        $unidadMedida = DB::table('unidades_medida')->select('id', 'nombre')->orderBy('nombre')->get();
        $solicitantes = DB::table('solicitantes')->select('id', 'nombre')->orderBy('nombre')->get();
        $personas = DB::table('personas')->select('id', 'nombre', 'lugar', 'distrito')->orderBy('nombre')->get();

        $proyectos = DB::table('proyectos')
            ->select('id', 'nombre', 'estado', 'descripcion', 'fecha_inicio', 'fecha_fin')
            ->orderBy('nombre')
            ->get();

        // Usuarios: sólo exponer id, name, email y role (no password ni email_verified_at)
        $usuarios = DB::table('users')
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventarios/MetaManager', [
            'categorias' => $categorias,
            'UnidadMedida' => $unidadMedida,
            'solicitantes' => $solicitantes,
            'personas' => $personas,
            'proyectos' => $proyectos,
            'usuarios' => $usuarios, // enviar usuarios al frontend
        ]);
    }

    public function index()
    {
        $categorias = DB::table('categorias')->select('id', 'nombre')->orderBy('nombre')->get();
        $unidadMedida = DB::table('unidades_medida')->select('id', 'nombre')->orderBy('nombre')->get();
        $solicitantes = DB::table('solicitantes')->select('id', 'nombre')->orderBy('nombre')->get();
        $personas = DB::table('personas')->select('id', 'nombre', 'lugar', 'distrito')->orderBy('nombre')->get();

        $proyectos = DB::table('proyectos')
            ->select('id', 'nombre', 'estado', 'descripcion', 'fecha_inicio', 'fecha_fin')
            ->orderBy('nombre')
            ->get();

        $usuarios = DB::table('users')
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categorias' => $categorias,
            'UnidadMedida' => $unidadMedida,
            'solicitantes' => $solicitantes,
            'personas' => $personas,
            'proyectos' => $proyectos,
            'usuarios' => $usuarios, // incluido en el JSON
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $table = $this->tableForType($type);
        if (!$table) {
            return response()->json(['message' => 'Tipo inválido'], 422);
        }

        // No permitimos crear usuarios desde aquí (gestión de usuarios por separado)
        if ($table === 'users') {
            return response()->json(['message' => 'Creación de usuarios no está permitida desde este endpoint'], 403);
        }

        // Personas
        if ($table === 'personas') {
            $request->validate([
                'nombre' => ['required', 'string', 'max:255'],
                'lugar' => ['nullable', 'string', 'max:255'],
                'distrito' => ['nullable', 'string', 'max:255'],
            ]);

            $id = DB::table('personas')->insertGetId([
                'nombre' => $request->input('nombre'),
                'lugar' => $request->input('lugar'),
                'distrito' => $request->input('distrito'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('personas')->where('id', $id)->first();

            return response()->json([
                'id' => $id,
                'persona' => $row
            ], 201);
        }

        // Proyectos
        if ($table === 'proyectos') {
            $request->validate([
                'nombre' => ['required', 'string', 'max:255'],
                'estado' => ['nullable', 'string', 'max:50'],
                'descripcion' => ['nullable', 'string'],
                'fecha_inicio' => ['nullable', 'date'],
                'fecha_fin' => ['nullable', 'date'],
            ]);

            $id = DB::table('proyectos')->insertGetId([
                'nombre' => $request->input('nombre'),
                'estado' => $request->input('estado') ?? null,
                'descripcion' => $request->input('descripcion') ?? null,
                'fecha_inicio' => $request->input('fecha_inicio') ?? null,
                'fecha_fin' => $request->input('fecha_fin') ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('proyectos')->where('id', $id)->first();

            return response()->json([
                'id' => $id,
                'proyecto' => $row
            ], 201);
        }

        // default: solo nombre para las otras tablas
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $id = DB::table($table)->insertGetId([
            'nombre' => $request->input('nombre'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table($table)->where('id', $id)->first();

        return response()->json([
            'id' => $id,
            'item' => $row
        ], 201);
    }

    public function update(Request $request, $type, $id)
{
    $table = $this->tableForType($type);
    if (!$table) return response()->json(['message' => 'Tipo inválido'], 422);

    $exists = DB::table($table)->where('id', $id)->exists();
    if (!$exists) return response()->json(['message' => 'No encontrado'], 404);

    if ($table === 'personas') {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'lugar' => ['nullable', 'string', 'max:255'],
            'distrito' => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('personas')->where('id', $id)->update([
            'nombre' => $request->input('nombre'),
            'lugar' => $request->input('lugar'),
            'distrito' => $request->input('distrito'),
            'updated_at' => now(),
        ]);

        $row = DB::table('personas')->where('id', $id)->first();
        return response()->json(['id' => $id, 'persona' => $row]);
    }

    if ($table === 'proyectos') {
        // Validación: nombre ya NO es required (acceptamos que no venga al editar)
        $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
        ]);

        // Construimos el array de update dinámicamente para no sobrescribir campos no enviados
        $update = ['updated_at' => now()];

        if ($request->has('nombre')) {
            // Si llega nombre (aunque vacío), lo usamos; la validación earlier lo permite o rechazará.
            $update['nombre'] = $request->input('nombre');
        }

        if ($request->has('estado')) {
            $update['estado'] = $request->input('estado') ?: null;
        }

        if ($request->has('descripcion')) {
            $update['descripcion'] = $request->input('descripcion') ?: null;
        }

        if ($request->has('fecha_inicio')) {
            $update['fecha_inicio'] = $request->input('fecha_inicio') ?: null;
        }

        if ($request->has('fecha_fin')) {
            $update['fecha_fin'] = $request->input('fecha_fin') ?: null;
        }

        DB::table('proyectos')->where('id', $id)->update($update);

        $row = DB::table('proyectos')->where('id', $id)->first();
        return response()->json(['id' => $id, 'proyecto' => $row]);
    }

    if ($table === 'users') {
        // solo permitimos actualizar el role desde este endpoint
        $request->validate([
            'role' => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('users')->where('id', $id)->update([
            'role' => $request->input('role'),
            'updated_at' => now(),
        ]);

        $row = DB::table('users')->select('id', 'name', 'email', 'role')->where('id', $id)->first();
        return response()->json(['id' => $id, 'usuario' => $row]);
    }

    // default para otras tablas (nombre)
    $request->validate([
        'nombre' => ['required', 'string', 'max:255'],
    ]);

    DB::table($table)->where('id', $id)->update([
        'nombre' => $request->input('nombre'),
        'updated_at' => now(),
    ]);

    $row = DB::table($table)->where('id', $id)->first();
    return response()->json(['id' => $id, 'item' => $row]);
    }


    public function destroy($type, $id)
    {
        $table = $this->tableForType($type);
        if (!$table) return response()->json(['message' => 'Tipo inválido'], 422);

        $exists = DB::table($table)->where('id', $id)->exists();
        if (!$exists) return response()->json(['message' => 'No encontrado'], 404);

        // Si quieres prevenir borrado de usuarios desde aquí, puedes bloquearlo:
        if ($table === 'users') {
            // return response()->json(['message' => 'No permitido eliminar usuarios desde este endpoint'], 403);
            // Si permites borrar usuarios coméntalo o implementa autorización.
        }

        DB::table($table)->where('id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function insertInitialData()
    {
        $inserted = [
            'categorias' => [],
            'unidades_medida' => [],
            'solicitantes' => [],
            'proyectos' => [],
        ];

        DB::beginTransaction();
        try {
            // --- Categorías ---
            $categorias = [
                'Gastos de comunicación y visibilidad institucional',
                'Materiales e insumos de saneamiento',
                'Materiales e insumos para actividades del proyecto',
                'Materiales operativos del personal',
                'Útiles y papelería',
                'Envases, Empaques y Embalajes',
                'Materiales de Difusión o promoción',
            ];

            foreach ($categorias as $nombre) {
                $nombreTrim = trim($nombre);
                $existing = DB::table('categorias')->where('nombre', $nombreTrim)->first();
                if ($existing) {
                    $inserted['categorias'][] = ['id' => $existing->id, 'nombre' => $existing->nombre, 'skipped' => true];
                    continue;
                }

                $id = DB::table('categorias')->insertGetId([
                    'nombre' => $nombreTrim,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('categorias')->where('id', $id)->first();
                $inserted['categorias'][] = ['id' => $row->id, 'nombre' => $row->nombre, 'skipped' => false];
            }

            // --- Unidades de medida ---
            $unidades = [
                'Centímetro',
                'Docena',
                'Gramo',
                'Kilogramo',
                'Litro',
                'Metro',
                'Metro cuadrado',
                'Metro cúbico',
                'Mililitro',
                'Milímetro',
                'Par',
                'Pieza',
                'Tonelada',
                'Unidad',
            ];

            foreach ($unidades as $nombre) {
                $nombreTrim = trim($nombre);
                $existing = DB::table('unidades_medida')->where('nombre', $nombreTrim)->first();
                if ($existing) {
                    $inserted['unidades_medida'][] = ['id' => $existing->id, 'nombre' => $existing->nombre, 'skipped' => true];
                    continue;
                }

                $id = DB::table('unidades_medida')->insertGetId([
                    'nombre' => $nombreTrim,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('unidades_medida')->where('id', $id)->first();
                $inserted['unidades_medida'][] = ['id' => $row->id, 'nombre' => $row->nombre, 'skipped' => false];
            }

            // --- Solicitantes ---
            $solicitantes = [
                'Dominguez Checa, Maribel Janeth',
                'Luna Espinoza, Jesús Enrique',
                'Antonio Solis, Sadith Luz',
                'Godoy Alejo, Gianella Mireya',
                'Lucinayda Criollo Aquino',
                'Doria Herrera, Luis Edgardo',
                'Bravo Palacios, Rufino',
                'Ferrer Hilario, Maxwell',
                'Johon Patricio Albornoz Cristobal',
                'Laurencio Simon, Aider Luis',
                'Evangelista Anchante, Luis Diego',
                'Justo Vigilio, Bernardo',
                'Vela Cárdenas, Mosclis Lucély',
                'Alva Soto, Emmanuel Jesús',
                'Cotrina Fabian, Michael Noder',
                'Vásquez Cornelio, Iván Alexander',
            ];

            foreach ($solicitantes as $nombre) {
                $nombreTrim = trim($nombre);
                $existing = DB::table('solicitantes')->where('nombre', $nombreTrim)->first();
                if ($existing) {
                    $inserted['solicitantes'][] = ['id' => $existing->id, 'nombre' => $existing->nombre, 'skipped' => true];
                    continue;
                }

                $id = DB::table('solicitantes')->insertGetId([
                    'nombre' => $nombreTrim,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('solicitantes')->where('id', $id)->first();
                $inserted['solicitantes'][] = ['id' => $row->id, 'nombre' => $row->nombre, 'skipped' => false];
            }

            // --- Proyectos (ejemplos iniciales) ---
            $proyectos = [
                [
                    'nombre' => 'Proyecto Piloto A',
                    'estado' => 'activo',
                    'descripcion' => 'Piloto en zona norte para evaluación de procesos',
                    'fecha_inicio' => now()->toDateString(),
                    'fecha_fin' => now()->addMonths(3)->toDateString(),
                ],
                [
                    'nombre' => 'Campaña de Difusión 2025',
                    'estado' => 'pendiente',
                    'descripcion' => 'Materiales y actividades de difusión para el próximo semestre',
                    'fecha_inicio' => null,
                    'fecha_fin' => null,
                ],
            ];

            foreach ($proyectos as $p) {
                $nombreTrim = trim($p['nombre']);
                $existing = DB::table('proyectos')->where('nombre', $nombreTrim)->first();
                if ($existing) {
                    $inserted['proyectos'][] = ['id' => $existing->id, 'nombre' => $existing->nombre, 'skipped' => true];
                    continue;
                }

                $id = DB::table('proyectos')->insertGetId([
                    'nombre' => $nombreTrim,
                    'estado' => $p['estado'] ?? null,
                    'descripcion' => $p['descripcion'] ?? null,
                    'fecha_inicio' => $p['fecha_inicio'] ?? null,
                    'fecha_fin' => $p['fecha_fin'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('proyectos')->where('id', $id)->first();
                $inserted['proyectos'][] = ['id' => $row->id, 'nombre' => $row->nombre, 'skipped' => false];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Datos iniciales procesados (insertados si no existían).',
                'result' => $inserted,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al insertar datos iniciales.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
