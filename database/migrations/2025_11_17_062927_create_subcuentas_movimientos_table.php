<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('subcuentas_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcuenta_id')->constrained('subcuentas')->onDelete('cascade');
            $table->date('fecha')->nullable();
            $table->string('descripcion')->nullable();
            $table->decimal('deudor', 14, 2)->default(0);
            $table->decimal('acreedor', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('subcuentas_movimientos');
    }
};
