<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\SalidaController;
use App\Http\Controllers\CrearController;
use App\Http\Controllers\InventarioSalidaController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\ProyectoExportController;
use App\Http\Controllers\SalidaPdfController;
use App\Http\Controllers\SalidaDebugController;
use App\Http\Controllers\AmMovimientoController;
use App\Http\Controllers\ProyectoEasyController;
use App\Http\Controllers\ProyectoContabilidadExportController;
use App\Http\Controllers\VinculacionController;
use App\Http\Controllers\InventarioMetaController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\SolicitanteController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Fondos\SubcuentaController;
use App\Http\Controllers\Cuentas\CuentaGeneralController;
use App\Http\Controllers\Movimientos\MovimientoController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Rutas protegidas (web + sesión)
Route::middleware('auth')->group(function () {

    // routes/web.php
Route::get('proyectos/{proyecto}/inventario-salidas', [InventarioSalidaController::class, 'show'])
    ->name('proyectos.inventario_salidas')
    ->defaults('component', 'ProyectosMovimientos/Show/ProyectoMovimientosShow');
    
    Route::prefix('proyectos/{proyecto}')->name('proyectos.')->controller(SalidaController::class)->group(function () {
        // Listado (index)
        Route::get('/salidas', 'index')->name('salidas');
        // dentro del mismo grupo
        Route::get('productos', [SalidaController::class, 'productosProyecto'])->name('productos');

        // Crear formulario
        Route::get('/salidas/create', 'create')->name('salidas.create');

        // Guardar (soporta single item o { items: [...] } para bulk)
        Route::post('/salidas', 'store')->name('salidas.store');

        // Editar / actualizar
        Route::get('/salidas/{id}/edit', 'edit')->name('salidas.edit');
        Route::put('/salidas/{id}', 'update')->name('salidas.update');
        Route::patch('/salidas/{id}', 'update'); // opcional, si usas patch

        // Eliminar
        Route::delete('/salidas/{id}', 'destroy')->name('salidas.destroy');

        // Buscar producto (usado en frontend: /buscar-producto/{codigo})
        Route::get('/salidas/buscar-producto/{codigo}', 'buscarProducto')->name('salidas.buscarProducto');

        // Buscar / filtrar salidas por producto
        Route::get('/salidas/por-producto/{codigo}', 'porProducto')->name('salidas.porProducto');

        // Importar / Exportar
        Route::post('/salidas/importar', 'importarSalidas')->name('salidas.importar');
        Route::get('/salidas/exportar', 'exportarProyecto')->name('salidas.exportar');
    });


    Route::get('/proyectos/{proyecto}/salidas/producto/{codigo}/pdf', [SalidaPdfController::class, 'exportPdf'])
        ->name('salidas.producto.pdf');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // routes/api.php o web.php (según tu estructura)
    Route::patch('/proyectos/{proyectoId}/salidas/aceptar-multiple', [SalidaController::class, 'aceptarMultiple']);

    // Proyectos (lista y crear)
    Route::get('/proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');
    Route::get('/CrearProyecto', [CrearController::class, 'index'])->name('page.one');
    Route::post('/crear', [ProyectoController::class, 'store'])->name('proyectos.store');

    // Export global de proyecto
    Route::get('/proyecto/{proyecto}/exportar', [ProyectoExportController::class, 'exportarProyecto'])
        ->name('proyecto.exportar');
    Route::get('/proyectos/{proyecto}/salidas/producto/{codigo}/debug', [SalidaDebugController::class, 'porProductoDebug']);

    // Grupo de rutas que pertenecen a un proyecto específico
    Route::prefix('proyectos/{proyecto}')->name('proyectos.')->group(function () {


        // Inventarios
        Route::get('inventarios', [InventarioController::class, 'index'])->name('inventarios.index');
        Route::get('inventarios/create', [InventarioController::class, 'create'])->name('inventarios.create');
        Route::post('inventarios', [InventarioController::class, 'store'])->name('inventarios.store');
        Route::get('inventarios/verificar-codigo/{codigo}', [InventarioController::class, 'verificarCodigo'])
            ->where('codigo', '.*')->name('inventarios.verificar_codigo');
        Route::get('inventarios/{id}/edit', [InventarioController::class, 'edit'])->name('inventarios.edit');
        Route::put('inventarios/{id}', [InventarioController::class, 'update'])->name('inventarios.update');
        Route::delete('inventarios/{id}', [InventarioController::class, 'destroy'])->name('inventarios.destroy');


        // Salidas
        Route::get('salidas', [SalidaController::class, 'index'])->name('salidas.index');
        Route::get('salidas/create', [SalidaController::class, 'create'])->name('salidas.create');
        Route::post('salidas', [SalidaController::class, 'store'])->name('salidas.store');
        Route::get('salidas/{id}/edit', [SalidaController::class, 'edit'])->name('salidas.edit');
        Route::put('salidas/{id}', [SalidaController::class, 'update'])->name('salidas.update');
        Route::delete('salidas/{id}', [SalidaController::class, 'destroy'])->name('salidas.destroy');



        Route::get('am/create', [AmMovimientoController::class, 'create'])
            ->name('proyectos.am.create');

        Route::post('/am', [AmMovimientoController::class, 'store'])
            ->name('proyectos.am.store');


        // EASY (mostrar form + guardar)
        Route::get('/easy/create', [ProyectoEasyController::class, 'create'])
            ->name('proyectos.easy.create');


        Route::get('salidas/producto/{codigo}', [SalidaController::class, 'porProducto'])
            ->where('codigo', '.*')->name('salidas.por_producto');

        Route::get('buscar-producto/{codigo}', [SalidaController::class, 'buscarProducto'])
            ->where('codigo', '.*')->name('salidas.buscar_producto');

        // Import / Export del proyecto

        Route::get('exportar', [ProyectoExportController::class, 'exportarProyecto'])->name('exportar');
        Route::get('exportar-opcional', [ProyectoExportController::class, 'exportarProyectoOpcional'])->name('exportar.opcional');

        Route::get('inventarios/exportar', [InventarioController::class, 'exportarInventario'])->name('inventarios.exportar');
        Route::post('inventarios/importar', [InventarioController::class, 'importarInventario'])->name('inventarios.importar');

        Route::post('salidas/importar', [SalidaController::class, 'importarSalidas'])->name('salidas.importar');
    });

    Route::middleware(['auth'])->group(function () {
        Route::match(['get', 'post'], '/proyectos/{proyecto}/easy/preferences', [ProyectoEasyController::class, 'preferences']);
    });

    Route::middleware(['auth'])->group(function () {
        Route::get('/proyectos/{proyecto}/easy/last-prefill', [ProyectoEasyController::class, 'lastPrefill'])
            ->name('proyectos.easy.last_prefill');
    });


    Route::middleware(['auth'])->group(function () {
        // Caja
        Route::get('proyectos/{proyecto}/amcaja/{id}/edit', [AmMovimientoController::class, 'editCaja'])
            ->name('proyectos.amcaja.edit');
        Route::put('proyectos/{proyecto}/amcaja/{id}', [AmMovimientoController::class, 'updateCaja'])
            ->name('proyectos.amcaja.update');
        Route::delete('proyectos/{proyecto}/amcaja/{id}', [AmMovimientoController::class, 'destroyCaja'])
            ->name('proyectos.amcaja.destroy');

        // Banco
        Route::get('proyectos/{proyecto}/ambanco/{id}/edit', [AmMovimientoController::class, 'editBanco'])
            ->name('proyectos.ambanco.edit');
        Route::put('proyectos/{proyecto}/ambanco/{id}', [AmMovimientoController::class, 'updateBanco'])
            ->name('proyectos.ambanco.update');
        Route::delete('proyectos/{proyecto}/ambanco/{id}', [AmMovimientoController::class, 'destroyBanco'])
            ->name('proyectos.ambanco.destroy');
    });

    Route::get('proyectos/{proyecto}/easy/{id}/edit', [ProyectoEasyController::class, 'editEasy'])
        ->name('proyectos.easy.edit');

    Route::put('proyectos/{proyecto}/easy/{id}', [ProyectoEasyController::class, 'updateEasy'])
        ->name('proyectos.easy.update');

    Route::get('/proyectos/{proyecto}/inventario/recibir', [InventarioController::class, 'recibir']);

    Route::delete('proyectos/{proyecto}/easy/{id}', [ProyectoEasyController::class, 'destroyEasy'])
        ->name('proyectos.easy.destroy');

    Route::get('/proyectos/{proyecto}/exchange-rate', [ExchangeRateController::class, 'getForMonth']);
    Route::post('/proyectos/{proyecto}/exchange-rate', [ExchangeRateController::class, 'upsert']);

    // Si quieres endpoint global (sin proyecto) para crear global:
    Route::post('/exchange-rate', [ExchangeRateController::class, 'upsert']);

    // POST para guardar (ruta canonical)
    Route::post('/proyectos/{proyecto}/am', [AmMovimientoController::class, 'store'])
        ->name('proyectos.am.store');

    Route::get('proyectos/{proyecto}/am/{id}/edit', [AmMovimientoController::class, 'edit'])
        ->name('proyectos.am.edit');

    Route::put('proyectos/{proyecto}/am/{id}', [AmMovimientoController::class, 'update'])
        ->name('proyectos.am.update');

    Route::post('proyectos/{proyecto}/easy/store', [ProyectoEasyController::class, 'store'])
        ->name('proyectos.easy.store');

    // routes/web.php
    Route::middleware(['auth'])->group(function () {
        // Endpoint JSON para obtener logs por modelo + id (solo admin en controlador)
        Route::get('/activity-logs/model/{model}/{id}', [\App\Http\Controllers\ActivityLogController::class, 'forModel'])
            ->name('activity_logs.forModel');
    });

    Route::get('/proyectos/{proyecto}/inventario/meta', [InventarioController::class, 'meta']);

    Route::get('/proyectos/{proyecto}/am/datos', [AmMovimientoController::class, 'datos'])
        ->name('api.proyectos.am.datos');

    Route::get('/proyectos/{proyecto}/am/meta', [AmMovimientoController::class, 'meta'])
        ->name('api.proyectos.am.meta');

    Route::middleware('api')->group(function () {
        Route::delete('/proyectos/{proyecto}/amcaja/{id}', [AmMovimientoController::class, 'destroyCaja']);
    });

    // Eliminar registro de CAJA
    Route::delete('/proyectos/{proyecto}/amcaja/{id}', [AmMovimientoController::class, 'destroyCaja'])
        ->name('proyectos.amcaja.destroy');

    // (si usas también banco)
    Route::delete('/proyectos/{proyecto}/ambanco/{id}', [AmMovimientoController::class, 'destroyBanco'])
        ->name('proyectos.ambanco.destroy');

    Route::get('/proyectos/{proyecto}/ultimo-acta', [\App\Http\Controllers\SalidaController::class, 'ultimoActa']);

    Route::middleware(['auth'])->group(function () {
        // Recalcular saldos (POST)
        Route::post('/proyectos/{proyecto}/am/recalcular', [AmMovimientoController::class, 'recalcular'])
            ->name('proyectos.am.recalcular');
    });
    // batch: devuelve vinculaciones para varios am_row_id
    Route::get('/proyectos/{proyecto}/vinculaciones/batch', [VinculacionController::class, 'batch'])
        ->name('proyectos.vinculaciones.batch');

    // one: devuelve vinculaciones para un solo am_row_id
    Route::get('/proyectos/{proyecto}/vinculaciones/one', [VinculacionController::class, 'one'])
        ->name('proyectos.vinculaciones.one');

    Route::middleware(['auth'])->group(function () {
        // Vista de gestión (Inertia) - global
        Route::get('/inventario/meta/manage', [InventarioMetaController::class, 'manage'])
            ->name('inventario.meta.manage');

        // Endpoints API globales para CRUD
        Route::get('/inventario/meta', [InventarioMetaController::class, 'index']);
        Route::post('/inventario/meta', [InventarioMetaController::class, 'store']);
        Route::put('/inventario/meta/{type}/{id}', [InventarioMetaController::class, 'update']);
        Route::delete('/inventario/meta/{type}/{id}', [InventarioMetaController::class, 'destroy']);
    });

    Route::get('/salidas/ultimo-codigo', [SalidaController::class, 'ultimoCodigo']);

    // API (recomendado)
    Route::post('/inventario/meta/insert-initial', [InventarioMetaController::class, 'insertInitialData']);

    Route::get(
        '/proyectos/{proyecto}/exportar-contabilidad-multiples',
        [ProyectoContabilidadExportController::class, 'exportarMesMultiples']
    )->name('proyectos.exportar.contabilidad_multiples');

    // opcional: alias si el frontend ya usa "/guardar"
    Route::post('/proyectos/{proyecto}/am/guardar', [AmMovimientoController::class, 'store']);
    // Rutas globales
    Route::get('inventarios/pdf', [InventarioController::class, 'generarPDF'])->name('inventarios.pdf');
    Route::get('/reportes', [ActivityLogController::class, 'index'])->name('reportes.index');
    Route::get('/personas', [PersonaController::class, 'index'])->name('personas.index');
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::get('/unidades-medida', [UnidadMedidaController::class, 'index'])->name('unidades_medida.index');
    Route::get('/solicitantes', [SolicitanteController::class, 'index'])->name('solicitantes.index');
});


/* Cuentas (Inertia pages) */
Route::get('/cuentas', [CuentaGeneralController::class, 'index'])->name('cuentas.index');
Route::get('/cuentas/create', [CuentaGeneralController::class, 'create'])->name('cuentas.create');
Route::post('/cuentas', [CuentaGeneralController::class, 'store'])->name('cuentas.store');
Route::get('/cuentas/{id}', [CuentaGeneralController::class, 'show'])->name('cuentas.show');
Route::get('/cuentas/{id}/edit', [CuentaGeneralController::class, 'edit'])->name('cuentas.edit');
Route::put('/cuentas/{id}', [CuentaGeneralController::class, 'update'])->name('cuentas.update');
Route::delete('/cuentas/{id}', [CuentaGeneralController::class, 'destroy'])->name('cuentas.destroy');

/* Fondos page (Inertia) */
Route::get('/cuentas/{id}/fondos', function ($id) {
    $cuenta = \App\Models\CuentaGeneral::findOrFail($id);
    $fondos = \App\Models\Subcuenta::where('cuenta_id', $id)->with('movimientos')->get();
    return Inertia::render('Fondos/Index', ['cuenta' => $cuenta, 'fondos' => $fondos]);
})->name('cuentas.fondos');

//apis:
// MOVIMIENTOS GENERALES
Route::get('/movimientos', [MovimientoController::class, 'index']);
Route::get('/movimientos/create', [MovimientoController::class, 'create'])->name('movimientos.create');
Route::get('/movimientos/{id}/edit', [MovimientoController::class, 'edit'])->name('movimientos.edit');

Route::post('/movimientos', [MovimientoController::class, 'store']);

Route::get('/movimientos/{id}', [MovimientoController::class, 'show']);
Route::put('/movimientos/{id}', [MovimientoController::class, 'update']); // ✔ FALTABA
Route::delete('/movimientos/{id}', [MovimientoController::class, 'destroy']);

// Fondos API
// FONDOS (Subcuentas)
Route::get("/fondos",        [SubcuentaController::class, "index"])->name("fondos.index");
Route::get("/fondos/create", [SubcuentaController::class, "create"])->name("fondos.create");
Route::post("/fondos",       [SubcuentaController::class, "store"])->name("fondos.store");
Route::get("/fondos/{id}/edit", [SubcuentaController::class, "edit"])->name("fondos.edit");
Route::put("/fondos/{id}",      [SubcuentaController::class, "update"])->name("fondos.update");
Route::delete("/fondos/{id}",   [SubcuentaController::class, "destroy"])->name("fondos.destroy");

Route::get('/fondos/{id}/detalle', [SubcuentaController::class, 'show'])
    ->name('fondos.detalle');


// API para actualizar cuenta (saldo inicial...)
Route::put('/cuentas/{id}', [CuentaGeneralController::class, 'update']); // ya definido en web controller
// Rutas para cuentas
Route::resource('cuentas', CuentaGeneralController::class);

Route::resource('cuentas', CuentaGeneralController::class);

// Ruta para recalcular (debe ir ANTES del resource)
Route::post('/cuentas/{cuenta}/recalcular', [CuentaGeneralController::class, 'recalcular'])
    ->name('cuentas.recalcular')
    ->where('cuenta', '[0-9]+');

// Ruta para recalcular todo
Route::post('/cuentas/recalcular-todo', [CuentaGeneralController::class, 'recalcularTodo'])
    ->name('cuentas.recalcular.todo');

Route::post(
    '/subcuentas/recalcular-saldos',
    [SubcuentaController::class, 'recalcularSaldos']
)->name('subcuentas.recalcular');

require __DIR__ . '/auth.php';
