<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_runs', function (Blueprint $table) {
            $table->string('batch_id', 64)->nullable()->after('hospital_id')->index();
            $table->boolean('is_primary')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('forecast_runs', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'is_primary']);
        });
    }
};
