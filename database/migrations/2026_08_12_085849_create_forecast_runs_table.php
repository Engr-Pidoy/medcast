<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('model_name')->default('SARIMA');
            $table->string('model_order')->nullable()->comment('e.g. (1,1,1)(1,1,1)7');
            $table->json('model_params')->nullable()->comment('p,d,q,P,D,Q,m and extras');
            $table->unsignedTinyInteger('horizon_days')->default(7);
            $table->date('train_start_date')->nullable();
            $table->date('train_end_date')->nullable();
            $table->timestamp('run_at');
            $table->string('status', 32)->default('completed'); // pending|running|completed|failed
            $table->boolean('is_active')->default(true)->comment('Latest published run for dashboards');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'is_active']);
            $table->index('run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_runs');
    }
};
