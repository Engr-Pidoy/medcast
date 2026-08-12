<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->decimal('point_forecast', 8, 2);
            $table->decimal('pi80_low', 8, 2)->nullable();
            $table->decimal('pi80_high', 8, 2)->nullable();
            $table->decimal('pi95_low', 8, 2)->nullable();
            $table->decimal('pi95_high', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['forecast_run_id', 'forecast_date']);
            $table->index('forecast_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_points');
    }
};
