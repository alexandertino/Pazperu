<?php

use App\Http\Controllers\CrearController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\ReporteController;
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

//tres botones del inicio
Route::get('/CrearProyecto', [CrearController::class, 'index'])->name('page.one');
Route::get('/Proyecto', [ProyectoController::class, 'index'])->name('page.two');
Route::get('/reportes', [ReporteController::class, 'index'])->name('page.three');

//ruta ver
Route::get('/proyectos/inventario-salidas', function () {
    return Inertia::render('VerInventarioSalidas'); // Asegúrate que esté en /resources/js/Pages/
})->name('proyectos.inventario_salidas');

//ruta ver
Route::get('/proyectos/inventario-nuevo', function () {
    return Inertia::render('AgregarInventario'); 
})->name('proyectos.AgregarInventario');

require __DIR__.'/auth.php';
