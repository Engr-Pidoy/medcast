<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_trend_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('stat_type'); // weekday|monthly|overall
            $table->string('stat_key'); // Mon|2023-01|overall
            $table->decimal('value', 12, 4);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'stat_type', 'stat_key'], 'admission_trend_stats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_trend_stats');
    }
};
