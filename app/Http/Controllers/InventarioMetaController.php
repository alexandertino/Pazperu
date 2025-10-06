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
            'unidadmedida' => 'unidades_medida',
            'unidad_medida' => 'unidades_medida',
            'unidad-medida' => 'unidades_medida',
            'unidad' => 'unidades_medida',
            'solicitantes' => 'solicitantes',
            'solicitante' => 'solicitantes',
            'personas' => 'personas',
            'persona' => 'personas',
        ];

        return $map[$t] ?? null;
    }

    public function manage()
    {
        $categorias = DB::table('categorias')->select('id', 'nombre')->orderBy('nombre')->get();
        $unidadMedida = DB::table('unidades_medida')->select('id', 'nombre')->orderBy('nombre')->get();
        $solicitantes = DB::table('solicitantes')->select('id', 'nombre')->orderBy('nombre')->get();
        // Traer también lugar y distrito para personas
        $personas = DB::table('personas')->select('id', 'nombre', 'lugar', 'distrito')->orderBy('nombre')->get();

        return Inertia::render('Inventarios/MetaManager', [
            'categorias' => $categorias,
            'UnidadMedida' => $unidadMedida,
            'solicitantes' => $solicitantes,
            'personas' => $personas,
        ]);
    }

    public function index()
    {
        $categorias = DB::table('categorias')->select('id', 'nombre')->orderBy('nombre')->get();
        $unidadMedida = DB::table('unidades_medida')->select('id', 'nombre')->orderBy('nombre')->get();
        $solicitantes = DB::table('solicitantes')->select('id', 'nombre')->orderBy('nombre')->get();
        $personas = DB::table('personas')->select('id', 'nombre', 'lugar', 'distrito')->orderBy('nombre')->get();

        return response()->json([
            'categorias' => $categorias,
            'UnidadMedida' => $unidadMedida,
            'solicitantes' => $solicitantes,
            'personas' => $personas,
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $table = $this->tableForType($type);
        if (!$table) {
            return response()->json(['message' => 'Tipo inválido'], 422);
        }

        // Validaciones por tipo
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

        // default para otras tablas
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

        DB::table($table)->where('id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function insertInitialData()
    {
        $inserted = [
            'categorias' => [],
            'unidades_medida' => [],
            'solicitantes' => [],
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

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Datos iniciales procesados (insertados si no existían).',
                'result' => $inserted,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // opcional: Log::error('insertInitialData error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al insertar datos iniciales.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
