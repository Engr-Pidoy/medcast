<?php

namespace App\Console\Commands;

use App\Models\AdmissionTrendStat;
use App\Models\DailyAdmission;
use App\Models\DemandThreshold;
use App\Models\ForecastPoint;
use App\Models\ForecastRun;
use App\Models\Hospital;
use App\Models\ModelBenchmark;
use App\Models\ModelEvaluation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class RunForecastCommand extends Command
{
    protected $signature = 'medcast:run-forecast
                            {--hospital=NDH : Hospital code}
                            {--holdout= : Optional holdout days; defaults to a 20% chronological test split}
                            {--python=python : Python executable}';

    protected $description = 'Run full MEDCAST benchmark (5 models, 1/7/30 horizons) and save forecasts';

    public function handle(): int
    {
        $hospital = Hospital::query()->where('code', $this->option('hospital'))->first();
        if (! $hospital) {
            $this->error('Hospital not found. Import admissions first.');

            return self::FAILURE;
        }

        $count = DailyAdmission::query()->where('hospital_id', $hospital->id)->count();
        if ($count < 60) {
            $this->error("Need more history (have {$count} days).");

            return self::FAILURE;
        }

        $workDir = storage_path('app/medcast');
        File::ensureDirectoryExists($workDir);
        $inputCsv = $workDir.'/admissions_export.csv';
        $outputJson = $workDir.'/benchmark_result.json';
        $script = base_path('python/forecast_benchmark.py');

        $this->info("Exporting {$count} days...");
        $this->exportAdmissionsCsv($hospital, $inputCsv);

        $this->info('Running multi-model benchmark (Naive, SeasonalNaive, SARIMA, Prophet, HoltWinters)...');
        $arguments = [
            $this->option('python'),
            $script,
            '--input', $inputCsv,
            '--output', $outputJson,
        ];
        if (filled($this->option('holdout'))) {
            $arguments[] = '--holdout';
            $arguments[] = (string) $this->option('holdout');
        }

        $result = Process::timeout(900)->run($arguments);

        if ($result->failed()) {
            $this->error('Benchmark script failed.');
            $this->line($result->errorOutput() ?: $result->output());

            return self::FAILURE;
        }

        if ($result->output()) {
            $this->line(trim($result->output()));
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(File::get($outputJson), true, 512, JSON_THROW_ON_ERROR);
        $this->persist($hospital, $payload);

        $this->info('Saved trends, thresholds, benchmarks, and 30-day forecasts for all models.');
        $this->line('Primary model: '.($payload['primary_model'] ?? 'SARIMA'));

        return self::SUCCESS;
    }

    private function exportAdmissionsCsv(Hospital $hospital, string $path): void
    {
        $rows = DailyAdmission::query()
            ->where('hospital_id', $hospital->id)
            ->orderBy('admission_date')
            ->get(['admission_date', 'total_admissions']);

        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new RuntimeException("Cannot write {$path}");
        }

        fputcsv($fh, ['date', 'admissions']);
        foreach ($rows as $row) {
            fputcsv($fh, [$row->admission_date->toDateString(), $row->total_admissions]);
        }
        fclose($fh);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persist(Hospital $hospital, array $payload): void
    {
        DB::transaction(function () use ($hospital, $payload): void {
            $batchId = $payload['batch_id'] ?? uniqid('batch_', true);

            ForecastRun::query()->where('hospital_id', $hospital->id)->update([
                'is_active' => false,
                'is_primary' => false,
            ]);
            DemandThreshold::query()->where('hospital_id', $hospital->id)->update(['is_active' => false]);
            AdmissionTrendStat::query()->where('hospital_id', $hospital->id)->delete();
            ModelBenchmark::query()->where('hospital_id', $hospital->id)->where('batch_id', $batchId)->delete();

            // Trends
            foreach (($payload['trends']['weekday'] ?? []) as $key => $value) {
                AdmissionTrendStat::query()->create([
                    'hospital_id' => $hospital->id,
                    'stat_type' => 'weekday',
                    'stat_key' => $key,
                    'value' => $value,
                ]);
            }
            foreach (($payload['trends']['monthly'] ?? []) as $key => $value) {
                AdmissionTrendStat::query()->create([
                    'hospital_id' => $hospital->id,
                    'stat_type' => 'monthly',
                    'stat_key' => $key,
                    'value' => $value,
                ]);
            }
            if (! empty($payload['trends']['overall'])) {
                AdmissionTrendStat::query()->create([
                    'hospital_id' => $hospital->id,
                    'stat_type' => 'overall',
                    'stat_key' => 'summary',
                    'value' => $payload['trends']['overall']['mean'] ?? 0,
                    'meta' => $payload['trends']['overall'],
                ]);
            }

            // Thresholds
            $thr = $payload['thresholds'] ?? null;
            if (is_array($thr)) {
                DemandThreshold::query()->create([
                    'hospital_id' => $hospital->id,
                    'low_max' => $thr['low_max'],
                    'moderate_max' => $thr['moderate_max'],
                    'high_min' => $thr['high_min'],
                    'method' => $thr['method'] ?? 'percentile',
                    'meta' => $thr['meta'] ?? null,
                    'is_active' => true,
                ]);
            }

            // Benchmarks
            foreach ($payload['benchmarks'] ?? [] as $row) {
                ModelBenchmark::query()->create([
                    'hospital_id' => $hospital->id,
                    'batch_id' => $batchId,
                    'model_name' => $row['model_name'],
                    'horizon_days' => $row['horizon_days'],
                    'mae' => $row['mae'],
                    'rmse' => $row['rmse'],
                    'mase' => $row['mase'],
                    'coverage_80' => $row['coverage_80'] ?? null,
                    'coverage_95' => $row['coverage_95'] ?? null,
                    'avg_width_80' => $row['avg_width_80'] ?? null,
                    'avg_width_95' => $row['avg_width_95'] ?? null,
                    'relative_width_80' => $row['relative_width_80'] ?? null,
                    'relative_width_95' => $row['relative_width_95'] ?? null,
                    'high_demand_mae' => $row['high_demand_mae'] ?? null,
                    'high_demand_days' => $row['high_demand_days'] ?? 0,
                    'sensitivity' => $row['sensitivity'] ?? null,
                    'specificity' => $row['specificity'] ?? null,
                    'precision' => $row['precision'] ?? null,
                    'f1_score' => $row['f1_score'] ?? null,
                    'false_alert_rate' => $row['false_alert_rate'] ?? null,
                    'missed_event_rate' => $row['missed_event_rate'] ?? null,
                    'rolling_mae_mean' => $row['rolling_mae_mean'] ?? null,
                    'rolling_mae_std' => $row['rolling_mae_std'] ?? null,
                    'robustness_score' => $row['robustness_score'] ?? null,
                    'diagnostics' => [
                        'high_demand_threshold' => $row['high_demand_threshold'] ?? null,
                        'confusion_matrix' => $row['confusion_matrix'] ?? null,
                        'sensitivity_analysis' => $row['sensitivity_analysis'] ?? null,
                    ],
                    'is_best_for_horizon' => (bool) ($row['is_best_for_horizon'] ?? false),
                    'evaluated_at' => now(),
                ]);
            }

            $primary = $payload['primary_model'] ?? 'SARIMA';

            $sarimaSelection = $payload['sarima_order_selection'] ?? null;
            $selectedSarimaOrder = $payload['selected_sarima_order'] ?? '(1,1,1)(1,1,1)7';

            foreach ($payload['forecasts'] ?? [] as $modelName => $points) {
                $isPrimary = $modelName === $primary;
                $run = ForecastRun::query()->create([
                    'hospital_id' => $hospital->id,
                    'batch_id' => $batchId,
                    'model_name' => $modelName,
                    'model_order' => $modelName === 'SARIMA' ? $selectedSarimaOrder : null,
                    'model_params' => [
                        'batch_id' => $batchId,
                        'dataset_version' => $payload['dataset_version'] ?? null,
                        'dataset_records' => $payload['dataset_records'] ?? null,
                        'dataset_coverage_start' => $payload['dataset_coverage_start'] ?? null,
                        'dataset_coverage_end' => $payload['dataset_coverage_end'] ?? null,
                        'holdout_days' => $payload['holdout_days'] ?? null,
                        'training_records' => $payload['training_records'] ?? null,
                        'testing_records' => $payload['testing_records'] ?? null,
                        'training_percent' => $payload['training_percent'] ?? null,
                        'testing_percent' => $payload['testing_percent'] ?? null,
                        'split_method' => $payload['split_method'] ?? null,
                        'prophet_backend' => $payload['prophet_backend'] ?? null,
                        'best_model_by_horizon' => $payload['best_model_by_horizon'] ?? null,
                        'sarima_order_selection' => $modelName === 'SARIMA' ? $sarimaSelection : null,
                        'monthly_outlook' => $modelName === 'SARIMA' ? ($payload['monthly_outlook'] ?? null) : null,
                    ],
                    'horizon_days' => 30,
                    'train_start_date' => $payload['train_start_date'] ?? null,
                    'train_end_date' => $payload['train_end_date'] ?? null,
                    'run_at' => now(),
                    'status' => 'completed',
                    'is_active' => true,
                    'is_primary' => $isPrimary,
                    'notes' => 'Generated by medcast:run-forecast multi-model benchmark',
                ]);

                foreach ($points as $point) {
                    ForecastPoint::query()->create([
                        'forecast_run_id' => $run->id,
                        'forecast_date' => $point['forecast_date'],
                        'point_forecast' => $point['point_forecast'],
                        'pi80_low' => $point['pi80_low'] ?? null,
                        'pi80_high' => $point['pi80_high'] ?? null,
                        'pi95_low' => $point['pi95_low'] ?? null,
                        'pi95_high' => $point['pi95_high'] ?? null,
                    ]);
                }

                // Keep ModelEvaluation rows for primary model horizons
                if ($isPrimary) {
                    ModelEvaluation::query()->where('hospital_id', $hospital->id)->delete();
                    $end = $payload['train_end_date'] ?? now()->toDateString();
                    foreach ([1, 7, 30] as $h) {
                        $b = collect($payload['benchmarks'] ?? [])->first(
                            fn ($x) => $x['model_name'] === $modelName && (int) $x['horizon_days'] === $h
                        );
                        if (! $b) {
                            continue;
                        }
                        ModelEvaluation::query()->create([
                            'hospital_id' => $hospital->id,
                            'forecast_run_id' => $run->id,
                            'period_label' => "Holdout {$h}-day ({$modelName})",
                            'period_start' => Carbon::parse($end)->subDays(max(0, $h - 1))->toDateString(),
                            'period_end' => $end,
                            'mae' => $b['mae'],
                            'rmse' => $b['rmse'],
                            'mape' => null,
                            'r_squared' => null,
                            'coverage_80' => null,
                            'coverage_95' => null,
                            'status' => ((float) $b['mase'] <= 1.0 ? 'Good' : (((float) $b['mase'] <= 1.25) ? 'Fair' : 'Poor')),
                            'evaluated_at' => now(),
                        ]);
                    }
                }
            }
        });
    }
}
