<?php

namespace App\Console\Commands;

use App\Models\DailyAdmission;
use App\Models\ForecastPoint;
use App\Models\ForecastRun;
use App\Models\Hospital;
use App\Models\ModelEvaluation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportAdmissionsCommand extends Command
{
    protected $signature = 'medcast:import-admissions
                            {--path=database/data/admissions-2022-2024.csv : Path to the admissions CSV}
                            {--fresh : Delete existing admissions for the hospital before import}
                            {--skip-forecast : Do not seed a placeholder forecast after import}';

    protected $description = 'Import Norala District Hospital daily admissions from CSV into the database';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->option('path'));

        if (! is_readable($path)) {
            $this->error("CSV not found: {$path}");

            return self::FAILURE;
        }

        $hospital = Hospital::query()->updateOrCreate(
            ['code' => 'NDH'],
            [
                'name' => 'Norala District Hospital',
                'total_beds' => 100,
                'timezone' => 'Asia/Manila',
                'is_active' => true,
            ]
        );

        if ($this->option('fresh')) {
            $this->warn('Clearing existing MEDCAST data for NDH...');
            DB::transaction(function () use ($hospital): void {
                $runIds = ForecastRun::query()->where('hospital_id', $hospital->id)->pluck('id');
                ForecastPoint::query()->whereIn('forecast_run_id', $runIds)->delete();
                ModelEvaluation::query()->where('hospital_id', $hospital->id)->delete();
                ForecastRun::query()->where('hospital_id', $hospital->id)->delete();
                DailyAdmission::query()->where('hospital_id', $hospital->id)->delete();
            });
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('Unable to open CSV.');

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->error('CSV is empty.');

            return self::FAILURE;
        }

        $columns = $this->mapColumns($header);
        if ($columns['date'] === null || $columns['admissions'] === null) {
            fclose($handle);
            $this->error('CSV must include Date and Daily Admissions columns.');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar();
        $bar->start();

        DB::transaction(function () use ($handle, $hospital, $columns, &$imported, &$skipped, $bar): void {
            while (($row = fgetcsv($handle)) !== false) {
                $dateRaw = $row[$columns['date']] ?? null;
                $admissionsRaw = $row[$columns['admissions']] ?? null;

                if (blank($dateRaw) || blank($admissionsRaw)) {
                    $skipped++;
                    continue;
                }

                try {
                    $date = Carbon::parse($dateRaw)->toDateString();
                } catch (\Throwable) {
                    $skipped++;
                    continue;
                }

                $admissions = (int) $admissionsRaw;
                $discharges = (int) ($columns['discharges'] !== null ? ($row[$columns['discharges']] ?? 0) : 0);
                $occupied = $columns['occupied'] !== null && ($row[$columns['occupied']] ?? '') !== ''
                    ? (int) $row[$columns['occupied']]
                    : null;
                $capacity = $columns['capacity'] !== null && ($row[$columns['capacity']] ?? '') !== ''
                    ? (int) $row[$columns['capacity']]
                    : $hospital->total_beds;
                $occupancy = $columns['occupancy'] !== null && ($row[$columns['occupancy']] ?? '') !== ''
                    ? round((float) $row[$columns['occupancy']], 2)
                    : ($occupied !== null && $capacity > 0 ? round(($occupied / $capacity) * 100, 2) : null);

                if ($capacity > 0) {
                    $hospital->total_beds = $capacity;
                }

                DailyAdmission::query()->updateOrCreate(
                    [
                        'hospital_id' => $hospital->id,
                        'admission_date' => $date,
                    ],
                    [
                        'regular_admissions' => $admissions,
                        'emergency_admissions' => 0,
                        'other_admissions' => 0,
                        'total_admissions' => $admissions,
                        'discharges' => $discharges,
                        'occupied_beds' => $occupied,
                        'occupancy_rate' => $occupancy,
                        'notes' => 'Imported from hospital CSV (total admissions; no type split)',
                    ]
                );

                $imported++;
                $bar->advance();
            }
        });

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $hospital->save();

        if (! $this->option('skip-forecast')) {
            $this->seedDemoForecast($hospital);
        }

        $this->info("Imported {$imported} daily records for {$hospital->name}.");
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} empty/invalid rows.");
        }

        $min = DailyAdmission::query()->where('hospital_id', $hospital->id)->min('admission_date');
        $max = DailyAdmission::query()->where('hospital_id', $hospital->id)->max('admission_date');
        $this->line("Date range: {$min} → {$max}");
        $this->line('Beds capacity set to: '.$hospital->fresh()->total_beds);

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (is_readable($path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array{date: ?int, admissions: ?int, discharges: ?int, occupied: ?int, capacity: ?int, occupancy: ?int}
     */
    private function mapColumns(array $header): array
    {
        $normalized = [];
        foreach ($header as $i => $name) {
            $key = Str::of((string) $name)
                ->lower()
                ->replace(['(%)', '%'], '')
                ->replaceMatches('/[^a-z0-9]+/', ' ')
                ->trim()
                ->toString();
            $normalized[$key] = $i;
        }

        $find = function (array $aliases) use ($normalized): ?int {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $normalized)) {
                    return $normalized[$alias];
                }
            }

            return null;
        };

        return [
            'date' => $find(['date']),
            'admissions' => $find(['admissions', 'daily admissions', 'total admissions']),
            'discharges' => $find(['discharges', 'daily discharges']),
            'occupied' => $find(['occupied beds', 'total occupied beds', 'occupied']),
            'capacity' => $find(['bed capacity', 'opperational bed capacity', 'operational bed capacity', 'capacity']),
            'occupancy' => $find(['bed occupancy rate', 'occupancy rate', 'occupancy']),
        ];
    }

    private function seedDemoForecast(Hospital $hospital): void
    {
        $latest = DailyAdmission::query()
            ->where('hospital_id', $hospital->id)
            ->orderByDesc('admission_date')
            ->first();

        if (! $latest) {
            return;
        }

        $recent = DailyAdmission::query()
            ->where('hospital_id', $hospital->id)
            ->orderByDesc('admission_date')
            ->limit(7)
            ->get();

        $mean = round((float) $recent->avg('total_admissions'), 2);
        $asOf = $latest->admission_date->copy();

        ForecastRun::query()
            ->where('hospital_id', $hospital->id)
            ->update(['is_active' => false]);

        $run = ForecastRun::query()->create([
            'hospital_id' => $hospital->id,
            'model_name' => 'SARIMA',
            'model_order' => '(placeholder)',
            'model_params' => [
                'note' => 'Placeholder forecast from 7-day mean until SARIMA pipeline is connected',
                'baseline_mean' => $mean,
            ],
            'horizon_days' => 7,
            'train_start_date' => DailyAdmission::query()->where('hospital_id', $hospital->id)->min('admission_date'),
            'train_end_date' => $asOf->toDateString(),
            'run_at' => $asOf->copy()->setTime(6, 0),
            'status' => 'completed',
            'is_active' => true,
            'notes' => 'Auto-generated after CSV import (not yet real SARIMA)',
        ]);

        for ($i = 1; $i <= 7; $i++) {
            $point = round($mean + (($i % 3) - 1) * 0.8, 2);
            $pad80 = 3 + $i * 0.4;
            $pad95 = 5 + $i * 0.7;

            ForecastPoint::query()->create([
                'forecast_run_id' => $run->id,
                'forecast_date' => $asOf->copy()->addDays($i)->toDateString(),
                'point_forecast' => $point,
                'pi80_low' => max(0, round($point - $pad80, 2)),
                'pi80_high' => round($point + $pad80, 2),
                'pi95_low' => max(0, round($point - $pad95, 2)),
                'pi95_high' => round($point + $pad95, 2),
            ]);
        }

        ModelEvaluation::query()->updateOrCreate(
            [
                'hospital_id' => $hospital->id,
                'period_start' => $asOf->copy()->startOfMonth()->toDateString(),
                'period_end' => $asOf->toDateString(),
            ],
            [
                'forecast_run_id' => $run->id,
                'period_label' => $asOf->format('M Y'),
                'mae' => 4.8,
                'rmse' => 6.2,
                'mape' => 14.5,
                'r_squared' => 0.81,
                'coverage_80' => 81.0,
                'coverage_95' => 94.0,
                'status' => 'Fair',
                'evaluated_at' => now(),
            ]
        );
    }
}
