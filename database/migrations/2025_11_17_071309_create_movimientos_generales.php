<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('movimientos_generales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas_generales')->onDelete('cascade');

            $table->date('fecha');
            $table->string('medio_pago')->nullable();
            $table->string('descripcion')->nullable();

            $table->decimal('deudor', 12, 2)->default(0);
            $table->decimal('acreedor', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('movimientos_generales');
    }

};
