<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_general_id')->constrained('cuentas_generales')->onDelete('cascade');
            $table->integer('numero')->nullable();
            $table->date('fecha_operacion');
            $table->string('medio_pago', 30)->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('deudor', 14, 2)->default(0);
            $table->decimal('acreedor', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2)->default(0);
            $table->foreignId('subcuenta_id')->nullable()->constrained('subcuentas')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('movimientos');
    }
};
