<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcuentas', function (Blueprint $table) {
            $table->id();

            $table->foreignId("cuenta_id")
                ->constrained("cuentas_generales")
                ->onDelete("cascade");

            $table->string("nombre");
            $table->decimal("saldo_inicial", 12, 2)->default(0);
            $table->text("descripcion")->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcuentas');
    }
};
