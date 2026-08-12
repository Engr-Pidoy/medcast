<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('batch_id', 64)->index();
            $table->string('model_name');
            $table->unsignedSmallInteger('horizon_days');
            $table->decimal('mae', 10, 4)->nullable();
            $table->decimal('rmse', 10, 4)->nullable();
            $table->decimal('mase', 10, 4)->nullable();
            $table->boolean('is_best_for_horizon')->default(false);
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'batch_id', 'model_name', 'horizon_days'], 'model_benchmarks_unique');
            $table->index(['hospital_id', 'horizon_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_benchmarks');
    }
};
