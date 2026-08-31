<?php

namespace App\Http\Controllers;

use App\Models\DailyAdmission;
use App\Models\ForecastRun;
use App\Models\Hospital;
use App\Services\ForecastUpdater;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class MedcastController extends Controller
{
    private const MODEL_ORDER = ['Naive', 'SeasonalNaive', 'SARIMA', 'Prophet', 'HoltWinters'];

    private const WEEKDAY_ORDER = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private function hospital(): Hospital
    {
        return Hospital::query()
            ->where('code', 'NDH')
            ->firstOrFail();
    }

    /**
     * @return array{hospitalName: string, currentDateTime: string}
     */
    private function hospitalContext(Hospital $hospital): array
    {
        return [
            'hospitalName' => $hospital->name,
            'currentDateTime' => now()->timezone($hospital->timezone)->format('F j, Y · g:i A'),
        ];
    }

    private function occupancyStatus(float $rate): string
    {
        return match (true) {
            $rate >= 90 => 'Critical',
            $rate >= 80 => 'High',
            $rate >= 60 => 'Moderate',
            default => 'Low',
        };
    }

    private function trendLabel(float $forecastAvg, float $recentAvg): string
    {
        $diff = $forecastAvg - $recentAvg;

        return match (true) {
            $diff >= 3 => 'Increase',
            $diff >= 1 => 'Slight Increase',
            $diff <= -3 => 'Decrease',
            $diff <= -1 => 'Slight Decrease',
            default => 'Stable',
        };
    }

    private function demandLevelClass(string $level): string
    {
        return match ($level) {
            'High' => 'bg-rose-50 text-rose-700',
            'Moderate' => 'bg-amber-50 text-amber-700',
            'Low' => 'bg-emerald-50 text-emerald-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    private function latestBenchmarkBatchId(Hospital $hospital): ?string
    {
        return $hospital->modelBenchmarks()
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->value('batch_id');
    }

    private function formatModelLabel(?string $name, ?string $order = null): string
    {
        if (! $name) {
            return '—';
        }

        $order = trim((string) $order);

        return $order !== '' ? trim($name.' '.$order) : $name;
    }

    /**
     * @param  array<int, float|int|string>  $values
     */
    private function percentile(array $values, float $percentile): float
    {
        $values = array_values(array_map('floatval', $values));
        sort($values, SORT_NUMERIC);

        if ($values === []) {
            return 0.0;
        }

        $index = ($percentile / 100) * (count($values) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return $values[$lower];
        }

        $weight = $index - $lower;

        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    private function normalCdf(float $value): float
    {
        $sign = $value < 0 ? -1 : 1;
        $x = abs($value) / sqrt(2);
        $t = 1 / (1 + 0.3275911 * $x);
        $erf = 1 - (((((1.061405429 * $t - 1.453152027) * $t) + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);

        return 0.5 * (1 + $sign * $erf);
    }

    /**
     * @return array{level: string, class: string, preparedness: string}
     */
    private function capacityRiskLevel(float $score): array
    {
        return match (true) {
            $score >= 75 => ['level' => 'Critical', 'class' => 'bg-rose-100 text-rose-800', 'preparedness' => 'Emergency surge activation'],
            $score >= 50 => ['level' => 'High', 'class' => 'bg-orange-100 text-orange-800', 'preparedness' => 'Enhanced staffing and overflow readiness'],
            $score >= 25 => ['level' => 'Moderate', 'class' => 'bg-amber-100 text-amber-800', 'preparedness' => 'Standby resources and daily monitoring'],
            default => ['level' => 'Low', 'class' => 'bg-emerald-100 text-emerald-800', 'preparedness' => 'Routine preparedness'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function monthlyOutlook(?ForecastRun $run): array
    {
        $params = $run ? ($run->model_params ?? []) : [];
        $outlook = $params['monthly_outlook'] ?? null;

        if (! is_array($outlook) || empty($outlook['actual'])) {
            $fallback = public_path('figures/monthly-outlook.json');
            if (is_file($fallback)) {
                $decoded = json_decode((string) file_get_contents($fallback), true);
                $outlook = is_array($decoded) ? $decoded : null;
            }
        }

        if (! is_array($outlook)) {
            return [];
        }

        $actual = collect($this->normalizeMonthlySeries($outlook['actual'] ?? null));
        $forecast = collect($this->normalizeMonthlySeries($outlook['forecast'] ?? null));
        if ($actual->isEmpty() || $forecast->isEmpty()) {
            return [];
        }
        $months = $actual->pluck('month')->merge($forecast->pluck('month'))->unique()->values();
        $actualMap = $actual->pluck('value', 'month');
        $forecastMap = $forecast->pluck('value', 'month');
        $lastActualMonth = $actual->last()['month'];

        return [
            'model_order' => $outlook['model_order'] ?? ($run?->model_order),
            'actual_start' => $outlook['actual_start'] ?? $actual->first()['month'],
            'actual_end' => $outlook['actual_end'] ?? $lastActualMonth,
            'forecast_start' => $outlook['forecast_start'] ?? $forecast->first()['month'],
            'forecast_end' => $outlook['forecast_end'] ?? $forecast->last()['month'],
            'categories' => $months->all(),
            'actual' => $months->map(fn ($m) => $actualMap[$m] ?? null)->all(),
            'forecast' => $months->map(function ($m) use ($forecastMap, $lastActualMonth, $actualMap) {
                if (isset($forecastMap[$m])) {
                    return $forecastMap[$m];
                }
                // bridge so the red line meets the last blue point
                if ($m === $lastActualMonth) {
                    return $actualMap[$m] ?? null;
                }

                return null;
            })->all(),
        ];
    }

    /**
     * @return list<array{month: string, value: float}>
     */
    private function normalizeMonthlySeries(mixed $series): array
    {
        if (! is_array($series)) {
            return [];
        }

        $normalized = [];
        foreach ($series as $item) {
            if (! is_array($item) || ! is_string($item['month'] ?? null) || ! is_numeric($item['value'] ?? null)) {
                continue;
            }

            $normalized[] = [
                'month' => $item['month'],
                'value' => (float) $item['value'],
            ];
        }

        return $normalized;
    }

    public function dashboard(): View
    {
        $hospital = $this->hospital();
        $admissions = $hospital->dailyAdmissions()
            ->orderBy('admission_date')
            ->get();

        abort_if($admissions->isEmpty(), 404, 'No admission data found. Run: php artisan db:seed --class=MedcastSeeder');

        $latest = $admissions->last();
        $asOf = $latest->admission_date->copy();
        $last7 = $admissions->where('admission_date', '>=', $asOf->copy()->subDays(6));
        $rangeStart = $admissions->first()->admission_date;
        $rangeEnd = $asOf;

        $forecastRun = $hospital->activeForecastRun();
        $allForecastPoints = $forecastRun
            ? $forecastRun->points()->orderBy('forecast_date')->get()
            : collect();
        $forecastPoints = $allForecastPoints->take(7)->values();

        $threshold = $hospital->activeDemandThreshold();
        $tomorrowPoint = $allForecastPoints->first();
        $tomorrowDemand = ($threshold && $tomorrowPoint)
            ? $threshold->classify((float) $tomorrowPoint->point_forecast)
            : '—';

        $sevenDayAvg = round((float) $last7->avg('total_admissions'), 1);
        $forecastAvg = $forecastPoints->isNotEmpty()
            ? round((float) $forecastPoints->avg('point_forecast'), 1)
            : $sevenDayAvg;

        $pi80Low = $forecastPoints->min('pi80_low');
        $pi80High = $forecastPoints->max('pi80_high');
        $occupancy = (float) ($latest->occupancy_rate ?? 0);

        // Chart: last 30 actual days + forecast horizon (up to 7)
        $history = $admissions->take(-30)->values();
        $categories = [];
        $actual = [];
        $forecast = [];
        $pi80 = [];
        $pi95 = [];

        foreach ($history as $row) {
            $categories[] = $row->admission_date->format('M d');
            $actual[] = $row->total_admissions;
            $forecast[] = null;
            $pi80[] = null;
            $pi95[] = null;
        }

        if ($history->isNotEmpty() && $forecastPoints->isNotEmpty()) {
            $lastIdx = count($categories) - 1;
            $bridge = (float) $history->last()->total_admissions;
            $forecast[$lastIdx] = $bridge;
            $pi80[$lastIdx] = [$bridge, $bridge];
            $pi95[$lastIdx] = [$bridge, $bridge];
        }

        foreach ($forecastPoints as $point) {
            $categories[] = $point->forecast_date->format('M d');
            $actual[] = null;
            $forecast[] = (float) $point->point_forecast;
            $pi80[] = [(float) $point->pi80_low, (float) $point->pi80_high];
            $pi95[] = [(float) $point->pi95_low, (float) $point->pi95_high];
        }

        $totalAdmissions = (int) $admissions->sum('total_admissions');
        $totalRegular = (int) $admissions->sum('regular_admissions');
        $totalEmergency = (int) $admissions->sum('emergency_admissions');
        $totalOther = (int) $admissions->sum('other_admissions');
        $typeBase = max(1, $totalRegular + $totalEmergency + $totalOther);

        return view('medcast.dashboard', array_merge($this->hospitalContext($hospital), [
            'dateRange' => $rangeStart->format('M j').' - '.$rangeEnd->format('M j, Y'),
            'kpis' => [
                'todays_admissions' => $latest->total_admissions,
                'seven_day_average' => $sevenDayAvg,
                'forecast_low' => $pi80Low !== null ? (int) round((float) $pi80Low) : '—',
                'forecast_high' => $pi80High !== null ? (int) round((float) $pi80High) : '—',
                'forecast_interval' => '80% Prediction Interval',
                'next_7_days_trend' => $this->trendLabel($forecastAvg, $sevenDayAvg),
                'bed_occupancy' => (int) round($occupancy),
                'bed_occupancy_status' => $this->occupancyStatus($occupancy),
                'tomorrow_demand' => $tomorrowDemand,
                'primary_model' => $this->formatModelLabel($forecastRun?->model_name, $forecastRun?->model_order),
            ],
            'admissionSummary' => [
                'total_admissions' => $totalAdmissions,
                'average_per_day' => round((float) $admissions->avg('total_admissions'), 1),
                'highest_admission' => (int) $admissions->max('total_admissions'),
                'lowest_admission' => (int) $admissions->min('total_admissions'),
                'emergency_cases' => $totalEmergency,
                'discharges' => (int) $admissions->sum('discharges'),
            ],
            'admissionsByType' => [
                'regular' => (int) round(($totalRegular / $typeBase) * 100),
                'emergency' => (int) round(($totalEmergency / $typeBase) * 100),
                'other' => (int) round(($totalOther / $typeBase) * 100),
                'total' => $totalAdmissions,
            ],
            'recentDailyAdmissions' => $admissions->sortByDesc('admission_date')->take(5)->values()->map(fn (DailyAdmission $row) => [
                'date' => $row->admission_date->format('M j, Y'),
                'regular' => $row->regular_admissions,
                'emergency' => $row->emergency_admissions,
                'total' => $row->total_admissions,
                'discharges' => $row->discharges,
            ])->all(),
            'chartData' => [
                'categories' => $categories,
                'actual' => $actual,
                'forecast' => $forecast,
                'pi80' => $pi80,
                'pi95' => $pi95,
            ],
        ]));
    }

    public function trends(): View
    {
        $hospital = $this->hospital();
        $stats = $hospital->trendStats()->get();

        $weekdayMap = $stats->where('stat_type', 'weekday')->keyBy('stat_key');
        $weekdayCategories = [];
        $weekdayValues = [];
        foreach (self::WEEKDAY_ORDER as $day) {
            $weekdayCategories[] = substr($day, 0, 3);
            $weekdayValues[] = isset($weekdayMap[$day]) ? round((float) $weekdayMap[$day]->value, 1) : null;
        }

        $monthly = $stats->where('stat_type', 'monthly')->sortBy('stat_key')->values();
        $monthlyCategories = [];
        $monthlyValues = [];
        foreach ($monthly as $row) {
            try {
                $monthlyCategories[] = Carbon::createFromFormat('Y-m', $row->stat_key)->format('M Y');
            } catch (\Throwable) {
                $monthlyCategories[] = $row->stat_key;
            }
            $monthlyValues[] = round((float) $row->value, 1);
        }

        $overall = $stats->first(fn ($s) => $s->stat_type === 'overall' && $s->stat_key === 'summary');
        $meta = is_array($overall?->meta) ? $overall->meta : [];

        $peakWeekday = collect(self::WEEKDAY_ORDER)
            ->map(fn ($day) => [
                'day' => $day,
                'value' => isset($weekdayMap[$day]) ? (float) $weekdayMap[$day]->value : null,
            ])
            ->filter(fn ($row) => $row['value'] !== null)
            ->sortByDesc('value')
            ->first();

        $quietWeekday = collect(self::WEEKDAY_ORDER)
            ->map(fn ($day) => [
                'day' => $day,
                'value' => isset($weekdayMap[$day]) ? (float) $weekdayMap[$day]->value : null,
            ])
            ->filter(fn ($row) => $row['value'] !== null)
            ->sortBy('value')
            ->first();

        return view('medcast.trends', array_merge($this->hospitalContext($hospital), [
            'overall' => [
                'mean' => isset($meta['mean']) ? round((float) $meta['mean'], 1) : ($overall ? round((float) $overall->value, 1) : null),
                'min' => isset($meta['min']) ? (int) $meta['min'] : null,
                'max' => isset($meta['max']) ? (int) $meta['max'] : null,
                'std' => isset($meta['std']) ? round((float) $meta['std'], 1) : null,
                'slope_per_month' => isset($meta['slope_per_month']) ? round((float) $meta['slope_per_month'], 3) : null,
                'direction' => $meta['direction'] ?? '—',
                'n_days' => $meta['n_days'] ?? null,
                'start' => isset($meta['start']) ? Carbon::parse($meta['start'])->format('M j, Y') : null,
                'end' => isset($meta['end']) ? Carbon::parse($meta['end'])->format('M j, Y') : null,
            ],
            'peakWeekday' => $peakWeekday,
            'quietWeekday' => $quietWeekday,
            'weekdayChart' => [
                'categories' => $weekdayCategories,
                'values' => $weekdayValues,
            ],
            'monthlyChart' => [
                'categories' => $monthlyCategories,
                'values' => $monthlyValues,
            ],
            'hasData' => $stats->isNotEmpty(),
        ]));
    }

    public function historical(): View
    {
        $hospital = $this->hospital();
        $admissions = $hospital->dailyAdmissions()
            ->orderByDesc('admission_date')
            ->get();

        $rows = $admissions->map(fn (DailyAdmission $row) => [
            'date' => $row->admission_date->format('M d, Y'),
            'regular' => $row->regular_admissions,
            'emergency' => $row->emergency_admissions,
            'total' => $row->total_admissions,
            'discharges' => $row->discharges,
            'occupancy' => (int) round((float) ($row->occupancy_rate ?? 0)),
        ])->all();

        $monthlyGroups = $hospital->dailyAdmissions()
            ->orderBy('admission_date')
            ->get()
            ->groupBy(fn (DailyAdmission $row) => $row->admission_date->format('Y-m'));

        $monthly = [
            'categories' => [],
            'admissions' => [],
            'emergencies' => [],
        ];

        foreach ($monthlyGroups as $month => $group) {
            $monthly['categories'][] = Carbon::createFromFormat('Y-m', $month)->format('M');
            $monthly['admissions'][] = (int) $group->sum('total_admissions');
            $monthly['emergencies'][] = (int) $group->sum('emergency_admissions');
        }

        $from = $admissions->last()?->admission_date;
        $to = $admissions->first()?->admission_date;

        return view('medcast.historical', array_merge($this->hospitalContext($hospital), [
            'dateRange' => ($from && $to)
                ? $from->format('M j').' - '.$to->format('M j, Y')
                : 'No data',
            'stats' => [
                'records' => $admissions->count(),
                'total' => (int) $admissions->sum('total_admissions'),
                'avg' => $admissions->isEmpty() ? 0 : round((float) $admissions->avg('total_admissions'), 1),
                'peak' => (int) ($admissions->max('total_admissions') ?? 0),
            ],
            'rows' => $rows,
            'monthly' => $monthly,
        ]));
    }

    public function uploadAdmissions(Request $request, ForecastUpdater $forecastUpdater): RedirectResponse
    {
        set_time_limit(300);

        $hospital = $this->hospital();

        $request->validate([
            'admissions_file' => ['required', 'file', 'max:10240', 'extensions:csv,txt,xlsx'],
        ]);

        try {
            $uploaded = $request->file('admissions_file');
            $workDir = storage_path('app/medcast/uploads');
            File::ensureDirectoryExists($workDir);

            $extension = strtolower($uploaded->getClientOriginalExtension() ?: 'csv');
            $filename = 'upload_'.now()->format('Ymd_His').'.'.$extension;
            $storedPath = $workDir.DIRECTORY_SEPARATOR.$filename;
            $uploaded->move($workDir, $filename);

            $csvPath = $storedPath;
            if (in_array($extension, ['xlsx', 'xls'], true)) {
                $csvPath = $workDir.'/upload_'.now()->format('Ymd_His').'.csv';
                $convert = Process::timeout(120)->run([
                    'python',
                    base_path('python/convert_admissions_xlsx.py'),
                    '--input', $storedPath,
                    '--output', $csvPath,
                ]);

                if ($convert->failed() || ! File::exists($csvPath)) {
                    return redirect()
                        ->route('historical')
                        ->with('error', 'Could not convert Excel file. Please upload CSV, or install openpyxl for Python.');
                }
            }

            $importExit = Artisan::call('medcast:import-admissions', [
                '--path' => $csvPath,
                '--skip-forecast' => true,
            ]);

            if ($importExit !== 0) {
                return redirect()
                    ->route('historical')
                    ->with('error', 'Import failed. '.trim(Artisan::output()));
            }

            $forecastExit = $forecastUpdater->run();
            if ($forecastExit !== 0) {
                return redirect()
                    ->route('historical')
                    ->with('error', 'Data was saved, but the forecast refresh failed. '.$forecastUpdater->output());
            }

            $count = $hospital->dailyAdmissions()->count();

            return redirect()
                ->route('forecasting')
                ->with('success', "Upload saved. The dataset now contains {$count} days; matching dates were updated, new dates were added, and all forecasts were refreshed.");
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('historical')
                ->with('error', 'Upload failed: '.$e->getMessage());
        }
    }

    public function encode(): View
    {
        $hospital = $this->hospital();
        $latest = $hospital->dailyAdmissions()->orderByDesc('admission_date')->first();
        $defaults = $latest ? [
            'regular_admissions' => $latest->regular_admissions,
            'emergency_admissions' => $latest->emergency_admissions,
            'other_admissions' => $latest->other_admissions,
            'discharges' => $latest->discharges,
            'occupied_beds' => $latest->occupied_beds ?? 0,
        ] : [
            'regular_admissions' => 0,
            'emergency_admissions' => 0,
            'other_admissions' => 0,
            'discharges' => 0,
            'occupied_beds' => 0,
        ];

        return view('medcast.encode', array_merge($this->hospitalContext($hospital), [
            'hospital' => $hospital,
            'latest' => $latest,
            'defaults' => array_merge([
                'admission_date' => now()->timezone($hospital->timezone)->toDateString(),
            ], $defaults),
        ]));
    }

    public function storeAdmission(Request $request, ForecastUpdater $forecastUpdater): RedirectResponse
    {
        set_time_limit(300);

        $hospital = $this->hospital();

        $data = $request->validate([
            'admission_date' => ['required', 'date'],
            'regular_admissions' => ['required', 'integer', 'min:0'],
            'emergency_admissions' => ['required', 'integer', 'min:0'],
            'other_admissions' => ['required', 'integer', 'min:0'],
            'discharges' => ['required', 'integer', 'min:0'],
            'occupied_beds' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $occupied = (int) $data['occupied_beds'];
        $occupancy = $hospital->total_beds > 0
            ? round(($occupied / $hospital->total_beds) * 100, 2)
            : null;

        $values = [
            'regular_admissions' => $data['regular_admissions'],
            'emergency_admissions' => $data['emergency_admissions'],
            'other_admissions' => $data['other_admissions'],
            'total_admissions' => $data['regular_admissions'] + $data['emergency_admissions'] + $data['other_admissions'],
            'discharges' => $data['discharges'],
            'occupied_beds' => $occupied,
            'occupancy_rate' => $occupancy,
            'notes' => $data['notes'] ?? 'Encoded via MEDCAST daily form',
        ];

        $existing = $hospital->dailyAdmissions()
            ->whereDate('admission_date', $data['admission_date'])
            ->first();

        if ($existing) {
            $existing->update($values);
        } else {
            $hospital->dailyAdmissions()->create([
                'admission_date' => $data['admission_date'],
                ...$values,
            ]);
        }

        $exit = $forecastUpdater->run();
        if ($exit !== 0) {
            return redirect()
                ->route('encode')
                ->with('error', 'Daily record was saved, but the forecast refresh failed. '.$forecastUpdater->output());
        }

        return redirect()
            ->route('forecasting')
            ->with('success', 'Daily record saved and all forecasts were updated automatically.');
    }

    public function forecasting(Request $request): View
    {
        $hospital = $this->hospital();
        $run = $hospital->activeForecastRun();
        abort_if(! $run, 404, 'No active forecast run. Run: php artisan db:seed --class=MedcastSeeder');

        $horizonDays = (int) $request->query('horizon', 7);
        if (! in_array($horizonDays, [1, 7, 30], true)) {
            $horizonDays = 7;
        }

        $allPoints = $run->points()->orderBy('forecast_date')->get();
        $points = $allPoints->take($horizonDays)->values();
        $threshold = $hospital->activeDemandThreshold();

        $recentAvg = (float) $hospital->dailyAdmissions()
            ->orderByDesc('admission_date')
            ->limit(7)
            ->get()
            ->avg('total_admissions');

        $horizon = $points->map(function ($p) use ($threshold) {
            $forecast = (float) $p->point_forecast;
            $level = $threshold ? $threshold->classify($forecast) : '—';

            return [
                'date' => $p->forecast_date->format('D, M d'),
                'category' => $p->forecast_date->format('M d'),
                'forecast' => round($forecast, 1),
                'low80' => round((float) $p->pi80_low, 1),
                'high80' => round((float) $p->pi80_high, 1),
                'low95' => round((float) $p->pi95_low, 1),
                'high95' => round((float) $p->pi95_high, 1),
                'demand_level' => $level,
                'demand_class' => $this->demandLevelClass($level),
            ];
        })->all();

        $forecastAvg = $points->isNotEmpty()
            ? round((float) $points->avg('point_forecast'), 1)
            : 0.0;

        $batchId = $this->latestBenchmarkBatchId($hospital);
        $benchmarks = $batchId
            ? $hospital->modelBenchmarks()
                ->where('batch_id', $batchId)
                ->where('horizon_days', $horizonDays)
                ->get()
            : collect();

        $bestByHorizon = $batchId
            ? $hospital->modelBenchmarks()
                ->where('batch_id', $batchId)
                ->where('is_best_for_horizon', true)
                ->get()
                ->keyBy('horizon_days')
            : collect();

        $comparison = collect(self::MODEL_ORDER)->map(function (string $model) use ($benchmarks) {
            $row = $benchmarks->firstWhere('model_name', $model);

            return [
                'model' => $model,
                'mae' => $row ? round((float) $row->mae, 2) : null,
                'rmse' => $row ? round((float) $row->rmse, 2) : null,
                'mase' => $row ? round((float) $row->mase, 3) : null,
                'is_best' => (bool) ($row?->is_best_for_horizon),
            ];
        })->filter(fn ($row) => $row['mae'] !== null)->values()->all();

        $bestModels = [
            1 => $bestByHorizon->get(1)?->model_name,
            7 => $bestByHorizon->get(7)?->model_name,
            30 => $bestByHorizon->get(30)?->model_name,
        ];

        return view('medcast.forecasting', array_merge($this->hospitalContext($hospital), [
            'selectedHorizon' => $horizonDays,
            'model' => [
                'name' => $this->formatModelLabel($run->model_name, $run->model_order),
                'trained_on' => ($run->train_start_date?->format('M Y') ?? '—')
                    .' – '.($run->train_end_date?->format('M Y') ?? '—'),
                'horizon' => $horizonDays.' day'.($horizonDays === 1 ? '' : 's'),
                'last_run' => $run->run_at->timezone($hospital->timezone)->format('M j, Y · g:i A'),
            ],
            'horizon' => $horizon,
            'summary' => [
                'range' => $points->isNotEmpty()
                    ? ((int) round((float) $points->min('pi80_low'))).' – '.((int) round((float) $points->max('pi80_high')))
                    : '—',
                'mean' => $forecastAvg,
                'trend' => $this->trendLabel($forecastAvg, $recentAvg),
                'confidence' => '80% PI',
            ],
            'bestModels' => $bestModels,
            'comparison' => $comparison,
            'monthlyOutlook' => $this->monthlyOutlook($run),
            'resultsFigureUrl' => file_exists(public_path('figures/sarima-12month-forecast.png'))
                ? asset('figures/sarima-12month-forecast.png')
                : null,
        ]));
    }

    public function runForecast(): RedirectResponse
    {
        set_time_limit(300);

        try {
            $exitCode = Artisan::call('medcast:run-forecast');
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                return redirect()
                    ->route('forecasting')
                    ->with('error', 'Forecast failed. '.$output);
            }

            $hospital = $this->hospital();
            $run = $hospital->activeForecastRun();
            $mape = $run?->evaluations()->latest('evaluated_at')->value('mape');

            $message = 'Multi-model forecast completed successfully.';
            if ($run) {
                $message .= ' Primary model: '.$this->formatModelLabel($run->model_name, $run->model_order).'.';
            }
            if ($mape !== null) {
                $message .= ' Holdout MAPE: '.$mape.'%.';
            }

            return redirect()
                ->route('forecasting')
                ->with('success', $message);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('forecasting')
                ->with('error', 'Forecast failed: '.$e->getMessage());
        }
    }

    public function performance(): View
    {
        $hospital = $this->hospital();
        $batchId = $this->latestBenchmarkBatchId($hospital);
        $benchmarks = $batchId
            ? $hospital->modelBenchmarks()->where('batch_id', $batchId)->get()
            : collect();

        $horizons = [1, 7, 30];
        $matrix = [];
        foreach (self::MODEL_ORDER as $model) {
            $cells = [];
            foreach ($horizons as $h) {
                $row = $benchmarks->first(fn ($b) => $b->model_name === $model && (int) $b->horizon_days === $h);
                $cells[$h] = $row ? [
                    'mae' => round((float) $row->mae, 2),
                    'rmse' => round((float) $row->rmse, 2),
                    'mase' => round((float) $row->mase, 3),
                    'coverage80' => $row->coverage_80 !== null ? round((float) $row->coverage_80, 1) : null,
                    'coverage95' => $row->coverage_95 !== null ? round((float) $row->coverage_95, 1) : null,
                    'f1' => $row->f1_score !== null ? round((float) $row->f1_score, 1) : null,
                    'robustness' => $row->robustness_score !== null ? round((float) $row->robustness_score, 1) : null,
                    'is_best' => (bool) $row->is_best_for_horizon,
                ] : null;
            }
            $matrix[] = [
                'model' => $model,
                'horizons' => $cells,
            ];
        }

        $bestByHorizon = [];
        foreach ($horizons as $h) {
            $best = $benchmarks->first(fn ($b) => (int) $b->horizon_days === $h && $b->is_best_for_horizon);
            $bestByHorizon[$h] = $best ? [
                'model' => $best->model_name,
                'mae' => round((float) $best->mae, 2),
                'rmse' => round((float) $best->rmse, 2),
                'mase' => round((float) $best->mase, 3),
                'coverage80' => $best->coverage_80 !== null ? round((float) $best->coverage_80, 1) : null,
                'coverage95' => $best->coverage_95 !== null ? round((float) $best->coverage_95, 1) : null,
                'width80' => $best->avg_width_80 !== null ? round((float) $best->avg_width_80, 2) : null,
                'width95' => $best->avg_width_95 !== null ? round((float) $best->avg_width_95, 2) : null,
                'relativeWidth80' => $best->relative_width_80 !== null ? round((float) $best->relative_width_80, 1) : null,
                'relativeWidth95' => $best->relative_width_95 !== null ? round((float) $best->relative_width_95, 1) : null,
                'highDemandMae' => $best->high_demand_mae !== null ? round((float) $best->high_demand_mae, 2) : null,
                'highDemandDays' => (int) $best->high_demand_days,
                'sensitivity' => $best->sensitivity !== null ? round((float) $best->sensitivity, 1) : null,
                'specificity' => $best->specificity !== null ? round((float) $best->specificity, 1) : null,
                'precision' => $best->precision !== null ? round((float) $best->precision, 1) : null,
                'f1' => $best->f1_score !== null ? round((float) $best->f1_score, 1) : null,
                'falseAlertRate' => $best->false_alert_rate !== null ? round((float) $best->false_alert_rate, 1) : null,
                'missedEventRate' => $best->missed_event_rate !== null ? round((float) $best->missed_event_rate, 1) : null,
                'rollingMaeMean' => $best->rolling_mae_mean !== null ? round((float) $best->rolling_mae_mean, 2) : null,
                'rollingMaeStd' => $best->rolling_mae_std !== null ? round((float) $best->rolling_mae_std, 2) : null,
                'robustness' => $best->robustness_score !== null ? round((float) $best->robustness_score, 1) : null,
                'diagnostics' => is_array($best->diagnostics) ? $best->diagnostics : [],
            ] : null;
        }

        $horizon7 = $benchmarks->where('horizon_days', 7)->keyBy('model_name');
        $maseChart = [
            'categories' => [],
            'mase' => [],
        ];
        foreach (self::MODEL_ORDER as $model) {
            if (! isset($horizon7[$model])) {
                continue;
            }
            $maseChart['categories'][] = $model;
            $maseChart['mase'][] = round((float) $horizon7[$model]->mase, 3);
        }

        $evals = $hospital->modelEvaluations()
            ->orderByDesc('period_start')
            ->get();
        $latest = $evals->first();

        $recent = $hospital->dailyAdmissions()->orderByDesc('admission_date')->limit(16)->get()->sortBy('admission_date')->values();
        $residuals = [];
        $residualLabels = [];
        if ($recent->count() >= 8) {
            $slice = $recent->take(-8)->values();
            foreach ($slice as $i => $row) {
                $baseline = $recent->slice(max(0, $recent->count() - 16 + $i), 7)->avg('total_admissions') ?? $row->total_admissions;
                $residuals[] = round($row->total_admissions - (float) $baseline, 1);
                $residualLabels[] = 'D'.($i + 1);
            }
        }

        $primary = $hospital->activeForecastRun();
        $primaryModelName = $primary ? $primary->model_name : 'SARIMA';
        $primaryMetrics = $benchmarks->first(fn ($b) => $b->model_name === $primaryModelName && (int) $b->horizon_days === 7);
        $params = is_array($primary?->model_params) ? $primary->model_params : [];
        $datasetStart = $params['dataset_coverage_start'] ?? $primary?->train_start_date?->toDateString();
        $datasetEnd = $params['dataset_coverage_end'] ?? $primary?->train_end_date?->toDateString();

        $sarimaRun = $batchId
            ? $hospital->forecastRuns()->where('batch_id', $batchId)->where('model_name', 'SARIMA')->latest('id')->first()
            : $hospital->forecastRuns()->where('model_name', 'SARIMA')->latest('id')->first();
        $sarimaSelection = is_array($sarimaRun?->model_params)
            ? ($sarimaRun->model_params['sarima_order_selection'] ?? null)
            : null;
        $sarimaSelected = is_array($sarimaSelection) ? ($sarimaSelection['selected'] ?? null) : null;
        $sarimaCandidates = is_array($sarimaSelection) ? ($sarimaSelection['candidates'] ?? []) : [];

        return view('medcast.performance', array_merge($this->hospitalContext($hospital), [
            'metrics' => [
                'mae' => $primaryMetrics ? round((float) $primaryMetrics->mae, 2) : ($latest ? (float) $latest->mae : 0),
                'rmse' => $primaryMetrics ? round((float) $primaryMetrics->rmse, 2) : ($latest ? (float) $latest->rmse : 0),
                'mase' => $primaryMetrics ? round((float) $primaryMetrics->mase, 3) : null,
                'mape' => $latest ? (float) $latest->mape : null,
                'r2' => $latest ? (float) $latest->r_squared : null,
                'coverage80' => $primaryMetrics?->coverage_80 !== null ? round((float) $primaryMetrics->coverage_80, 1) : ($latest ? (float) $latest->coverage_80 : null),
                'coverage95' => $primaryMetrics?->coverage_95 !== null ? round((float) $primaryMetrics->coverage_95, 1) : ($latest ? (float) $latest->coverage_95 : null),
                'primary_model' => $this->formatModelLabel($primary?->model_name, $primary?->model_order),
            ],
            'datasetInfo' => [
                'version' => $params['dataset_version'] ?? ($primary && $primary->batch_id ? 'MEDCAST-'.substr($primary->batch_id, 0, 12) : 'Legacy dataset'),
                'coverage' => $datasetStart && $datasetEnd
                    ? Carbon::parse($datasetStart)->format('M j, Y').' – '.Carbon::parse($datasetEnd)->format('M j, Y')
                    : 'Not available',
                'records' => (int) ($params['dataset_records'] ?? $hospital->dailyAdmissions()->count()),
                'holdout_days' => (int) ($params['holdout_days'] ?? 30),
                'training_records' => (int) ($params['training_records'] ?? 0),
                'testing_records' => (int) ($params['testing_records'] ?? ($params['holdout_days'] ?? 30)),
                'training_percent' => (float) ($params['training_percent'] ?? 80),
                'testing_percent' => (float) ($params['testing_percent'] ?? 20),
                'generated_at' => $primary?->run_at?->timezone($hospital->timezone)->format('M j, Y · g:i A') ?? '—',
                'batch_id' => $primary ? $primary->batch_id : '—',
            ],
            'matrix' => $matrix,
            'bestByHorizon' => $bestByHorizon,
            'sensitivityAnalysis' => $bestByHorizon[7]['diagnostics']['sensitivity_analysis'] ?? [],
            'hasBenchmarks' => $benchmarks->isNotEmpty(),
            'sarimaSelected' => $sarimaSelected,
            'sarimaCandidates' => $sarimaCandidates,
            'sarimaCriterion' => is_array($sarimaSelection) ? ($sarimaSelection['selection_criterion'] ?? null) : null,
            'residualChart' => [
                'categories' => $residualLabels,
                'residuals' => $residuals,
            ],
            'maseChart' => $maseChart,
            'backtests' => $evals->map(fn ($e) => [
                'period' => $e->period_label ?: $e->period_start->format('M Y'),
                'mae' => (float) $e->mae,
                'rmse' => (float) $e->rmse,
                'mape' => (float) $e->mape,
                'status' => $e->status ?? 'Fair',
            ])->all(),
        ]));
    }

    public function capacityRisk(Request $request): View
    {
        $hospital = $this->hospital();
        $horizon = (int) $request->query('horizon', 7);
        if (! in_array($horizon, [1, 7, 30], true)) {
            $horizon = 7;
        }

        $capacityMode = strtolower((string) $request->query('capacity_mode', 'moderate'));
        if (! in_array($capacityMode, ['constrained', 'moderate', 'expanded', 'custom'], true)) {
            $capacityMode = 'moderate';
        }

        $penaltyMode = strtolower((string) $request->query('penalty', 'balanced'));
        $penalties = [
            'balanced' => [
                'label' => 'Balanced',
                'multiplier' => 1.0,
                'description' => 'Equal emphasis on overload prevention and resource use.',
            ],
            'overload-sensitive' => [
                'label' => 'Overload-sensitive',
                'multiplier' => 1.15,
                'description' => 'Escalates earlier when missed overload could be costly.',
            ],
            'resource-conservative' => [
                'label' => 'Resource-conservative',
                'multiplier' => 0.85,
                'description' => 'Requires stronger evidence before committing extra capacity.',
            ],
        ];
        if (! isset($penalties[$penaltyMode])) {
            $penaltyMode = 'balanced';
        }

        $history = $hospital->dailyAdmissions()
            ->orderBy('admission_date')
            ->pluck('total_admissions')
            ->all();
        abort_if($history === [], 404, 'No admission history is available for capacity scenarios.');

        $capacityPresets = [
            'constrained' => max(1, (int) round($this->percentile($history, 50))),
            'moderate' => max(1, (int) round($this->percentile($history, 66))),
            'expanded' => max(1, (int) round($this->percentile($history, 90))),
        ];
        $customCapacity = max(1, min(9999, (int) $request->query('custom_capacity', $capacityPresets['moderate'])));
        $scenarioCapacity = $capacityMode === 'custom'
            ? $customCapacity
            : $capacityPresets[$capacityMode];

        $batchId = $this->latestBenchmarkBatchId($hospital);
        $bestBenchmark = $batchId
            ? $hospital->modelBenchmarks()
                ->where('batch_id', $batchId)
                ->where('horizon_days', $horizon)
                ->where('is_best_for_horizon', true)
                ->first()
            : null;

        $run = null;
        if ($bestBenchmark) {
            $run = $hospital->forecastRuns()
                ->where('batch_id', $batchId)
                ->where('model_name', $bestBenchmark->model_name)
                ->where('status', 'completed')
                ->latest('run_at')
                ->first();
        }
        $run ??= $hospital->activeForecastRun();
        abort_if(! $run, 404, 'No completed forecast is available for capacity scenarios.');

        $points = $run->points()->orderBy('forecast_date')->limit($horizon)->get();
        abort_if($points->isEmpty(), 404, 'The selected forecast has no forecast points.');

        $daily = $points->map(function ($point) use ($scenarioCapacity, $penalties, $penaltyMode) {
            $forecast = (float) $point->point_forecast;
            $low80 = (float) ($point->pi80_low ?? $forecast);
            $high80 = (float) ($point->pi80_high ?? $forecast);
            $sigma = max(0.0, ($high80 - $low80) / (2 * 1.2816));
            $probability = $sigma > 0.0001
                ? 1 - $this->normalCdf(($scenarioCapacity - $forecast) / $sigma)
                : ($forecast > $scenarioCapacity ? 1.0 : 0.0);
            $probability = max(0.0, min(1.0, $probability));
            $pressure = $forecast / max(1, $scenarioCapacity);
            $baseScore = ($probability * 65) + (min(1.5, $pressure) / 1.5 * 35);
            $riskScore = min(100, $baseScore * $penalties[$penaltyMode]['multiplier']);
            $risk = $this->capacityRiskLevel($riskScore);

            return [
                'date' => $point->forecast_date->format('D, M j'),
                'category' => $point->forecast_date->format('M j'),
                'forecast' => round($forecast, 1),
                'interval' => round($low80, 1).' – '.round($high80, 1),
                'pressure' => round($pressure, 2),
                'overload' => round(max(0, $forecast - $scenarioCapacity), 1),
                'probability' => round($probability * 100, 1),
                'risk' => $risk['level'],
                'risk_class' => $risk['class'],
            ];
        })->values();

        $averageForecast = (float) $daily->avg('forecast');
        $pressureRatio = $averageForecast / max(1, $scenarioCapacity);
        $probabilityAny = 1 - $daily->reduce(
            fn (float $carry, array $day) => $carry * (1 - ($day['probability'] / 100)),
            1.0
        );
        $baseRiskScore = ($probabilityAny * 65) + (min(1.5, $pressureRatio) / 1.5 * 35);
        $riskScore = min(100, $baseRiskScore * $penalties[$penaltyMode]['multiplier']);
        $risk = $this->capacityRiskLevel($riskScore);
        $params = is_array($run->model_params) ? $run->model_params : [];

        return view('medcast.capacity-risk', array_merge($this->hospitalContext($hospital), [
            'controls' => [
                'horizon' => $horizon,
                'capacity_mode' => $capacityMode,
                'custom_capacity' => $customCapacity,
                'penalty' => $penaltyMode,
            ],
            'capacityPresets' => $capacityPresets,
            'penalties' => $penalties,
            'scenario' => [
                'model' => $this->formatModelLabel($run->model_name, $run->model_order),
                'forecasted_admissions' => round($averageForecast, 1),
                'capacity' => $scenarioCapacity,
                'pressure_ratio' => round($pressureRatio, 2),
                'expected_overload' => round((float) $daily->sum('overload'), 1),
                'probability' => round($probabilityAny * 100, 1),
                'risk_score' => round($riskScore, 1),
                'risk_level' => $risk['level'],
                'risk_class' => $risk['class'],
                'preparedness' => $risk['preparedness'],
                'penalty_description' => $penalties[$penaltyMode]['description'],
            ],
            'dailyScenarios' => $daily->all(),
            'chartData' => [
                'categories' => $daily->pluck('category')->all(),
                'forecast' => $daily->pluck('forecast')->all(),
                'capacity' => array_fill(0, $daily->count(), $scenarioCapacity),
                'probability' => $daily->pluck('probability')->all(),
            ],
            'datasetInfo' => [
                'version' => $params['dataset_version'] ?? ($run->batch_id ? 'MEDCAST-'.substr($run->batch_id, 0, 12) : 'Legacy dataset'),
                'coverage' => ($params['dataset_coverage_start'] ?? $run->train_start_date?->toDateString())
                    .' – '.($params['dataset_coverage_end'] ?? $run->train_end_date?->toDateString()),
            ],
        ]));
    }

    public function decisionSupport(): View
    {
        $hospital = $this->hospital();
        $latest = $hospital->dailyAdmissions()->orderByDesc('admission_date')->first();
        $run = $hospital->activeForecastRun();
        $allPoints = $run ? $run->points()->orderBy('forecast_date')->get() : collect();
        $points = $allPoints->take(7)->values();
        $threshold = $hospital->activeDemandThreshold();

        $occupied = (int) ($latest ? ($latest->occupied_beds ?? 0) : 0);
        $occupancy = (float) ($latest ? ($latest->occupancy_rate ?? 0) : 0);
        $available = max(0, $hospital->total_beds - $occupied);

        $classifiedDays = $points->map(function ($p) use ($threshold) {
            $forecast = (float) $p->point_forecast;
            $level = $threshold ? $threshold->classify($forecast) : '—';

            return [
                'date' => $p->forecast_date->format('D, M j'),
                'forecast' => round($forecast, 1),
                'low80' => round((float) $p->pi80_low, 1),
                'high80' => round((float) $p->pi80_high, 1),
                'demand_level' => $level,
                'demand_class' => $this->demandLevelClass($level),
            ];
        })->all();

        $highDays = collect($classifiedDays)->where('demand_level', 'High')->values();
        $moderateDays = collect($classifiedDays)->where('demand_level', 'Moderate')->count();
        $highCount = $highDays->count();

        $peakForecast = $points->max('point_forecast');
        $peakDate = $points->sortByDesc('point_forecast')->first();
        $avgDischarge = (float) $hospital->dailyAdmissions()->orderByDesc('admission_date')->limit(7)->get()->avg('discharges');
        $projectedPeak = min(100, round($occupancy + max(0, ((float) $peakForecast - $avgDischarge) / max(1, $hospital->total_beds) * 100 * 3), 0));

        $emergencyShare = 0;
        $recent = $hospital->dailyAdmissions()->orderByDesc('admission_date')->limit(7)->get();
        if ($recent->sum('total_admissions') > 0) {
            $emergencyShare = round(($recent->sum('emergency_admissions') / $recent->sum('total_admissions')) * 100);
        }

        $alerts = [];
        if ($highCount >= 3) {
            $alerts[] = [
                'level' => 'watch',
                'title' => 'Multiple high-demand days ahead',
                'detail' => sprintf(
                    '%d of the next 7 forecast days are classified High (>%s admissions/day). Peak around %s admissions on %s.',
                    $highCount,
                    $threshold ? (int) $threshold->moderate_max : '—',
                    (int) round((float) ($peakForecast ?? 0)),
                    $peakDate?->forecast_date->format('M j') ?? 'upcoming days'
                ),
                'action' => 'Increase staffing and open surge capacity for high-demand days',
            ];
        } elseif ($highCount >= 1) {
            $alerts[] = [
                'level' => 'watch',
                'title' => 'High demand day(s) expected',
                'detail' => sprintf(
                    '%d high-demand day(s): %s.',
                    $highCount,
                    $highDays->pluck('date')->implode(', ')
                ),
                'action' => 'Schedule float nurse coverage near high-demand dates',
            ];
        } elseif ($moderateDays >= 4) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Mostly moderate demand ahead',
                'detail' => sprintf('%d of the next 7 days are Moderate demand.', $moderateDays),
                'action' => 'Maintain current staffing with light contingency',
            ];
        } else {
            $alerts[] = [
                'level' => 'ok',
                'title' => 'Demand within manageable range',
                'detail' => 'Next 7 forecast days are mostly Low or Moderate under active thresholds.',
                'action' => 'Continue routine staffing and bed management',
            ];
        }

        $alerts[] = [
            'level' => $occupancy >= 85 ? 'watch' : 'info',
            'title' => 'Bed occupancy '.strtolower($this->occupancyStatus($occupancy)),
            'detail' => sprintf(
                'Current occupancy %s%%. Projected occupancy may reach ~%s%% if discharges stay near %.0f/day.',
                (int) round($occupancy),
                (int) $projectedPeak,
                $avgDischarge
            ),
            'action' => $occupancy >= 80 ? 'Prioritize early discharge planning' : 'Continue routine bed management',
        ];

        $alerts[] = [
            'level' => $emergencyShare >= 35 ? 'watch' : 'ok',
            'title' => $emergencyShare >= 35 ? 'Emergency share elevated' : 'Emergency share within normal range',
            'detail' => sprintf('Emergency cases are ~%s%% of recent admissions.', $emergencyShare),
            'action' => $emergencyShare >= 35 ? 'Review ER surge readiness' : 'Continue routine ER triage',
        ];

        return view('medcast.decision-support', array_merge($this->hospitalContext($hospital), [
            'alerts' => $alerts,
            'threshold' => $threshold ? [
                'low_max' => (float) $threshold->low_max,
                'moderate_max' => (float) $threshold->moderate_max,
                'high_min' => (float) $threshold->high_min,
                'method' => $threshold->method ?? 'percentile',
                'rules' => [
                    'Low: ≤ '.(int) $threshold->low_max.' admissions/day',
                    'Moderate: > '.(int) $threshold->low_max.' and ≤ '.(int) $threshold->moderate_max.' admissions/day',
                    'High: > '.(int) $threshold->moderate_max.' admissions/day',
                ],
            ] : null,
            'classifiedDays' => $classifiedDays,
            'demandSummary' => [
                'high' => $highCount,
                'moderate' => $moderateDays,
                'low' => collect($classifiedDays)->where('demand_level', 'Low')->count(),
            ],
            'recommendations' => [
                [
                    'area' => 'Staffing',
                    'priority' => $highCount >= 2 ? 'High' : ($highCount >= 1 ? 'Medium' : 'Low'),
                    'text' => $highCount >= 1
                        ? 'Add float nurse coverage on High demand days ('.$highDays->pluck('date')->implode(', ').').'
                        : 'Maintain current staffing pattern for the next 7 days.',
                ],
                [
                    'area' => 'Beds',
                    'priority' => $projectedPeak >= 85 || $highCount >= 3 ? 'High' : 'Medium',
                    'text' => sprintf('Keep %d–%d surge beds on standby through the forecast horizon.', 4, 6),
                ],
                [
                    'area' => 'Supplies',
                    'priority' => $highCount >= 3 ? 'Medium' : 'Low',
                    'text' => $highCount >= 3
                        ? 'Pre-stage admission kits ahead of clustered high-demand days.'
                        : 'Maintain standard admission kit stock; no bulk reorder needed.',
                ],
                [
                    'area' => 'ER',
                    'priority' => $emergencyShare >= 35 ? 'High' : 'Medium',
                    'text' => 'Monitor ER-to-ward transfers against the admission forecast.',
                ],
            ],
            'capacity' => [
                'total_beds' => $hospital->total_beds,
                'occupied' => $occupied,
                'available' => $available,
                'occupancy' => (int) round($occupancy),
                'projected_peak' => (int) $projectedPeak,
            ],
        ]));
    }

    public function about(): View
    {
        $hospital = $this->hospital();
        $run = $hospital->activeForecastRun();

        return view('medcast.about', array_merge($this->hospitalContext($hospital), [
            'system' => [
                'name' => 'MEDCAST',
                'full_name' => 'Patient Admission Forecasting and Decision-Support System',
                'hospital' => $hospital->name,
                'version' => '1.2.0',
                'model' => $this->formatModelLabel($run ? $run->model_name : 'SARIMA', $run?->model_order),
                'models' => 'Naive, SeasonalNaive, SARIMA, Prophet, HoltWinters',
                'horizons' => '1-day, 7-day, and 30-day',
                'capacity' => Hospital::MEAN_OPERATIONAL_BEDS.' mean operational beds ('.Hospital::BASE_BEDS.' base beds plus overflow capacity)',
                'purpose' => 'Help hospital administrators anticipate daily patient admissions, compare forecasting models with interval and high-demand diagnostics, test capacity-risk scenarios, and support operational decisions with probabilistic forecasts and demand-level alerts.',
            ],
        ]));
    }
}
