<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->date('admission_date');
            $table->unsignedSmallInteger('regular_admissions')->default(0);
            $table->unsignedSmallInteger('emergency_admissions')->default(0);
            $table->unsignedSmallInteger('other_admissions')->default(0);
            $table->unsignedSmallInteger('total_admissions');
            $table->unsignedSmallInteger('discharges')->default(0);
            $table->unsignedSmallInteger('occupied_beds')->nullable();
            $table->decimal('occupancy_rate', 5, 2)->nullable()->comment('Percentage 0-100');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'admission_date']);
            $table->index('admission_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_admissions');
    }
};
