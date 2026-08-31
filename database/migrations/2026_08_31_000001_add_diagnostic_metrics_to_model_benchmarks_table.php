<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_benchmarks', function (Blueprint $table) {
            $table->decimal('coverage_80', 5, 2)->nullable()->after('mase');
            $table->decimal('coverage_95', 5, 2)->nullable()->after('coverage_80');
            $table->decimal('avg_width_80', 10, 4)->nullable()->after('coverage_95');
            $table->decimal('avg_width_95', 10, 4)->nullable()->after('avg_width_80');
            $table->decimal('relative_width_80', 8, 2)->nullable()->after('avg_width_95');
            $table->decimal('relative_width_95', 8, 2)->nullable()->after('relative_width_80');
            $table->decimal('high_demand_mae', 10, 4)->nullable()->after('relative_width_95');
            $table->unsignedSmallInteger('high_demand_days')->default(0)->after('high_demand_mae');
            $table->decimal('sensitivity', 5, 2)->nullable()->after('high_demand_days');
            $table->decimal('specificity', 5, 2)->nullable()->after('sensitivity');
            $table->decimal('precision', 5, 2)->nullable()->after('specificity');
            $table->decimal('f1_score', 5, 2)->nullable()->after('precision');
            $table->decimal('false_alert_rate', 5, 2)->nullable()->after('f1_score');
            $table->decimal('missed_event_rate', 5, 2)->nullable()->after('false_alert_rate');
            $table->decimal('rolling_mae_mean', 10, 4)->nullable()->after('missed_event_rate');
            $table->decimal('rolling_mae_std', 10, 4)->nullable()->after('rolling_mae_mean');
            $table->decimal('robustness_score', 5, 2)->nullable()->after('rolling_mae_std');
            $table->json('diagnostics')->nullable()->after('robustness_score');
        });
    }

    public function down(): void
    {
        Schema::table('model_benchmarks', function (Blueprint $table) {
            $table->dropColumn([
                'coverage_80',
                'coverage_95',
                'avg_width_80',
                'avg_width_95',
                'relative_width_80',
                'relative_width_95',
                'high_demand_mae',
                'high_demand_days',
                'sensitivity',
                'specificity',
                'precision',
                'f1_score',
                'false_alert_rate',
                'missed_event_rate',
                'rolling_mae_mean',
                'rolling_mae_std',
                'robustness_score',
                'diagnostics',
            ]);
        });
    }
};
