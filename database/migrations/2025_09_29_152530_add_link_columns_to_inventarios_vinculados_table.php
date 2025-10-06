<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventarios_vinculados', function (Blueprint $table) {
            $table->id();

            // referencias
            $table->unsignedBigInteger('proyecto_id')->index();
            $table->string('am_table', 120)->index();    // tabla AM (ej. am_caja_proyecto_foo)
            $table->unsignedBigInteger('am_row_id')->nullable()->index();

            // referencia al inventario (tabla dinámica del inventario)
            $table->string('inventario_table', 120)->nullable()->index();
            $table->unsignedBigInteger('inventario_row_id')->nullable()->index();

            // datos auxiliares
            $table->string('codigo')->nullable(); // opcional si quieres guardar codigo del inventario
            $table->string('descripcion', 1000)->nullable();
            $table->date('fecha')->nullable();
            $table->integer('cantidad')->default(0);
            $table->json('meta')->nullable();

            // estado y auditoría
            $table->string('status', 50)->default('draft')->index(); // draft | linked | confirmed | etc.
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->timestamps();

            // foreign key opcional si tienes tabla proyectos
            if (Schema::hasTable('proyectos')) {
                $table->foreign('proyecto_id')->references('id')->on('proyectos')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios_vinculados');
    }
};
