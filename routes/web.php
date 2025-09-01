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
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UserController;
use App\Models\Persona;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use SebastianBergmann\CodeCoverage\Report\Xml\Report;

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


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

//boton ruta de crear Proyecto
Route::get('/CrearProyecto', [CrearController::class, 'index'])->name('page.one');
//ruta que crea el proyecto
Route::post('/crear',[ProyectoController::class, 'store'])->name('proyectos.store');


//boton ruta ver los proyecto
Route::get('/proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');
//ruta para ver los datos del proyecto escogido 
Route::get('/proyectos/{proyecto}/inventario-salidas', [InventarioSalidaController::class, 'show'])
    ->name('proyectos.inventario_salidas');


//boton ruta para ver los reportes
Route::get('/reportes', [ReporteController::class, 'index'])->name('page.three');


// Inventarios
Route::get('/proyectos/{proyecto}/inventarios', [InventarioController::class, 'index'])->name('proyectos.inventarios');
Route::get('/proyectos/{proyecto}/inventarios/create', [InventarioController::class, 'create'])->name('proyectos.inventarios.create');
Route::post('/proyectos/{proyecto}/inventarios', [InventarioController::class, 'store'])->name('proyectos.inventarios.store');
Route::get('/proyectos/{proyecto}/inventarios/verificar-codigo/{codigo}', [InventarioController::class, 'verificarCodigo']);

// Nuevas rutas para editar y eliminar
Route::get('/proyectos/{proyecto}/inventarios/{id}/edit', [InventarioController::class, 'edit'])->name('proyectos.inventarios.edit');
Route::put('/proyectos/{proyecto}/inventarios/{id}', [InventarioController::class, 'update'])->name('proyectos.inventarios.update');
Route::delete('/proyectos/{proyecto}/inventarios/{id}', [InventarioController::class, 'destroy'])->name('proyectos.inventarios.destroy');

//consultas:
Route::get('/proyectos/{proyecto}/salidas/producto/{codigo}', 
    [SalidaController::class, 'porProducto']
)->where('codigo', '.*');


//salidas
Route::get('/proyectos/{proyecto}/salidas', [SalidaController::class, 'index'])->name('proyectos.salidas');
Route::get('/proyectos/{proyecto}/salidas/create', [SalidaController::class, 'create'])->name('proyectos.salidas.create');
Route::post('/proyectos/{proyecto}/salidas', [SalidaController::class, 'store'])->name('proyectos.salidas.store');

Route::get('/proyectos/{proyecto}/salidas/{id}/edit', [SalidaController::class, 'edit'])->name('proyectos.salidas.edit');
Route::put('/proyectos/{proyecto}/salidas/{id}', [SalidaController::class, 'update'])->name('proyectos.salidas.update');
Route::delete('/proyectos/{proyecto}/salidas/{id}', [SalidaController::class, 'destroy'])->name('proyectos.salidas.destroy');


//ruta para el exel
Route::get('inventario/{proyecto}/exportar', [InventarioController::class, 'exportarInventario']);
Route::post('inventario/{proyecto}/importar', [InventarioController::class, 'importarInventario']);

//otra ruta mas exel profecional:
Route::get('/proyecto/{proyecto}/exportar', [ProyectoExportController::class, 'exportarProyecto'])
     ->name('proyecto.exportar');

//otra ruta mas exel usuariosw:
Route::get('/proyecto/{proyecto}/egpi0013', [ProyectoExportController::class, 'exportarProyectoOpcional'])
     ->name('proyecto.exportar');

//buscar producto para las salidas:
Route::get('/proyectos/{proyecto}/buscar-producto/{codigo}', [SalidaController::class, 'buscarProducto'])
    ->where('codigo', '.*');

//ruta para el exel
Route::post('salidas/{proyecto}/importar', [SalidaController::class, 'importarSalidas']);

//ruta para exportar el pdf
Route::get('/inventarios/pdf', [InventarioController::class, 'generarPDF'])->name('inventarios.pdf');
Route::get('/reportes', function () {
    return Inertia::render('Logs/Index');
})->name('reportes.index');

Route::get('/reportes', [ActivityLogController::class, 'index'])
    ->name('reportes.index');

Route::get('/personas', [PersonaController::class, 'index']);

require __DIR__.'/auth.php';
