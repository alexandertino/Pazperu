<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proyecto_id')->nullable()->index();
            $table->smallInteger('year')->index();
            $table->tinyInteger('month')->index(); // 1..12
            $table->string('from_currency', 10)->default('PEN');
            $table->string('to_currency', 10)->default('EUR');
            $table->decimal('rate', 18, 6);
            $table->string('source')->nullable(); // p.ej. 'manual' o nombre API
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['proyecto_id', 'year', 'month', 'from_currency', 'to_currency'], 'uq_exchange_monthly');
        });
    }

    public function down()
    {
        Schema::dropIfExists('exchange_rates');
    }
};
