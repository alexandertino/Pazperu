<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Proyecto;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class ProyectoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'estado' => 'required|in:En Proceso,Pausado,Terminado',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'required|date',
        ]);

        // Guardar el proyecto
        $proyecto = Proyecto::create([
            'nombre' => $request->nombre,
            'estado' => $request->estado,
            'descripcion' => $request->descripcion,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->estado === 'Terminado' ? now() : null,
        ]);

        // Normalizar el nombre para usar en tablas
        $tableSuffix = strtolower(str_replace(' ', '_', $request->nombre));

        // Inventario
        $tableInventario = 'inventario_proyecto_' . $tableSuffix;
        if (!Schema::hasTable($tableInventario)) {
            Schema::create($tableInventario, function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->unique();
                $table->date('fecha');
                $table->string('descripcion');
                $table->string("categoria");
                $table->string('unidad_medida');
                $table->integer('entradas')->default(0);
                $table->integer('salidas')->default(0);
                $table->integer('stock')->default(0);
                $table->decimal('precio', 10, 2)->default(0);
                $table->string('solicitado_por')->nullable();
                $table->text('comentario')->nullable();
                $table->timestamps();
            });
        }

        // Salidas
        $tableSalidas = 'salidas_proyecto_' . $tableSuffix;
        if (!Schema::hasTable($tableSalidas)) {
            Schema::create($tableSalidas, function (Blueprint $table) {
                $table->id();
                $table->string('n_acta')->index();
                $table->unsignedBigInteger('persona_id')->nullable()->index();
                $table->string('nombre')->nullable();
                $table->string('lugar')->nullable();
                $table->string('distrito')->nullable();
                $table->date('fecha')->nullable();
                $table->string('producto')->nullable()->index();
                $table->string('producto_code', 120)->nullable()->index();
                $table->string('producto_label', 255)->nullable();
                $table->string('um', 50)->nullable();
                $table->decimal('cantidad', 20, 4)->default(0);
                $table->string('estado', 50)->default('pendiente')->index();
                $table->timestamps();
                $table->index(['producto_code', 'fecha']);
            });
        }

        $tableCaja = 'am_caja_proyecto_' . $tableSuffix;
        if (!Schema::hasTable($tableCaja)) {
            Schema::create($tableCaja, function (Blueprint $table) {
                $table->id();
                $table->string('n_acta')->nullable()->index();
                $table->date('fecha');
                $table->string('descripcion', 64);
                $table->string('presupuestario')->nullable();
                $table->string('actividad')->nullable();
                $table->decimal('ingresos', 15, 2)->default(0);
                $table->decimal('egresos', 15, 2)->default(0);
                $table->decimal('saldo', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // 2. AM-banco
        $tableBanco = 'am_banco_proyecto_' . $tableSuffix;
        if (!Schema::hasTable($tableBanco)) {
            Schema::create($tableBanco, function (Blueprint $table) {
                $table->id();
                $table->string('n_acta')->nullable()->index();
                $table->date('fecha');
                $table->string('descripcion');
                $table->string('presupuestario')->nullable();
                $table->string('actividad')->nullable();
                $table->decimal('ingresos', 15, 2)->default(0);
                $table->decimal('egresos', 15, 2)->default(0);
                $table->decimal('saldo', 15, 2)->default(0);
                $table->string('accion')->nullable();
                $table->timestamps();
            });
        }

        // 3. Easy
        $tableEasy = 'easy_proyecto_' . $tableSuffix;
        if (!Schema::hasTable($tableEasy)) {
            Schema::create($tableEasy, function (Blueprint $table) {
                $table->id();
                $table->string('n_acta')->nullable()->index();
                $table->string('Cuenta_general')->nullable();
                $table->decimal('gasto_moneda_local', 15, 2)->default(0);
                $table->decimal('ingreso_moneda_local', 15, 2)->default(0);
                $table->string('moneda_facturacion')->nullable();
                $table->decimal('debito_moneda_gestion', 15, 2)->default(0);
                $table->decimal('credito_moneda_gestion', 15, 2)->default(0);
                $table->string('moneda_gestion')->nullable();
                $table->string('numero_descripcion_pieza')->nullable();
                $table->string('codigo_presupuestario')->nullable();
                $table->string('naturaleza_presupuesto')->nullable();
                $table->string('contrato')->nullable();
                $table->string('bailleur_fondos')->nullable();
                $table->date('fecha');
                $table->timestamps();
            });
        }

        return redirect()->back()->with('success', 'Proyecto y tablas creados correctamente.');
    }

    public function index()
    {
        $proyectos = Proyecto::all();

        return inertia('Proyecto', [
            'proyectos' => $proyectos
        ]);
    }
}
