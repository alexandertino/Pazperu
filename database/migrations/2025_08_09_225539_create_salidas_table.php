<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('salidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('acta')->nullable();
            $table->string('nombre');
            $table->string('lugar')->nullable();
            $table->string('distrito')->nullable();
            $table->date('fecha');
            $table->string('producto');
            $table->integer('cantidad')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};