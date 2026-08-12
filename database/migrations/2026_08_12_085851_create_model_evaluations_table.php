<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('forecast_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period_label')->nullable()->comment('e.g. Apr 2025');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('mae', 8, 3)->nullable();
            $table->decimal('rmse', 8, 3)->nullable();
            $table->decimal('mape', 8, 3)->nullable()->comment('Percentage');
            $table->decimal('r_squared', 8, 4)->nullable();
            $table->decimal('coverage_80', 5, 2)->nullable()->comment('Percentage');
            $table->decimal('coverage_95', 5, 2)->nullable()->comment('Percentage');
            $table->string('status', 32)->nullable()->comment('Good|Fair|Poor');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_evaluations');
    }
};
