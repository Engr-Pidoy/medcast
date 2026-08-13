<?php

namespace App\Http\Controllers;

use App\Models\DailyAdmission;
use App\Models\Hospital;
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

    public function uploadAdmissions(Request $request): RedirectResponse
    {
        set_time_limit(300);

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
                '--fresh' => true,
                '--skip-forecast' => true,
            ]);

            if ($importExit !== 0) {
                return redirect()
                    ->route('historical')
                    ->with('error', 'Import failed. '.trim(Artisan::output()));
            }

            $forecastExit = Artisan::call('medcast:run-forecast');
            if ($forecastExit !== 0) {
                return redirect()
                    ->route('historical')
                    ->with('error', 'Data imported, but SARIMA forecast failed. '.trim(Artisan::output()));
            }

            $count = DailyAdmission::query()->count();

            return redirect()
                ->route('forecasting')
                ->with('success', "Uploaded and imported {$count} days. SARIMA forecast updated automatically.");
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

        return view('medcast.encode', array_merge($this->hospitalContext($hospital), [
            'hospital' => $hospital,
            'latest' => $latest,
            'defaults' => [
                'admission_date' => now()->timezone($hospital->timezone)->toDateString(),
                'regular_admissions' => $latest?->regular_admissions ?? 0,
                'emergency_admissions' => $latest?->emergency_admissions ?? 0,
                'other_admissions' => $latest?->other_admissions ?? 0,
                'discharges' => $latest?->discharges ?? 0,
                'occupied_beds' => $latest?->occupied_beds ?? 0,
            ],
        ]));
    }

    public function storeAdmission(Request $request): RedirectResponse
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
            'run_forecast' => ['nullable', 'boolean'],
        ]);

        $occupied = (int) $data['occupied_beds'];
        $occupancy = $hospital->total_beds > 0
            ? round(($occupied / $hospital->total_beds) * 100, 2)
            : null;

        DailyAdmission::query()->updateOrCreate(
            [
                'hospital_id' => $hospital->id,
                'admission_date' => $data['admission_date'],
            ],
            [
                'regular_admissions' => $data['regular_admissions'],
                'emergency_admissions' => $data['emergency_admissions'],
                'other_admissions' => $data['other_admissions'],
                'total_admissions' => $data['regular_admissions'] + $data['emergency_admissions'] + $data['other_admissions'],
                'discharges' => $data['discharges'],
                'occupied_beds' => $occupied,
                'occupancy_rate' => $occupancy,
                'notes' => $data['notes'] ?? 'Encoded via MEDCAST daily form',
            ]
        );

        if ($request->boolean('run_forecast')) {
            $exit = Artisan::call('medcast:run-forecast');
            if ($exit !== 0) {
                return redirect()
                    ->route('encode')
                    ->with('error', 'Record saved, but forecast failed. '.trim(Artisan::output()));
            }

            return redirect()
                ->route('forecasting')
                ->with('success', 'Daily record saved and SARIMA forecast updated.');
        }

        return redirect()
            ->route('encode')
            ->with('success', 'Daily admission record saved for '.$data['admission_date'].'.');
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
        $primaryMetrics = $benchmarks->first(fn ($b) => $b->model_name === ($primary?->model_name ?? 'SARIMA') && (int) $b->horizon_days === 7);

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
                'coverage80' => $latest ? (float) $latest->coverage_80 : null,
                'coverage95' => $latest ? (float) $latest->coverage_95 : null,
                'primary_model' => $this->formatModelLabel($primary?->model_name, $primary?->model_order),
            ],
            'matrix' => $matrix,
            'bestByHorizon' => $bestByHorizon,
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

    public function decisionSupport(): View
    {
        $hospital = $this->hospital();
        $latest = $hospital->dailyAdmissions()->orderByDesc('admission_date')->first();
        $run = $hospital->activeForecastRun();
        $allPoints = $run ? $run->points()->orderBy('forecast_date')->get() : collect();
        $points = $allPoints->take(7)->values();
        $threshold = $hospital->activeDemandThreshold();

        $occupied = (int) ($latest?->occupied_beds ?? 0);
        $occupancy = (float) ($latest?->occupancy_rate ?? 0);
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
                'version' => '1.1.0',
                'model' => $this->formatModelLabel($run?->model_name ?? 'SARIMA', $run?->model_order),
                'models' => 'Naive, SeasonalNaive, SARIMA, Prophet, HoltWinters',
                'horizons' => '1-day, 7-day, and 30-day',
                'purpose' => 'Help hospital administrators anticipate daily patient admissions, compare forecasting models across short and medium horizons, plan staffing and bed capacity, and support operational decisions with probabilistic forecasts and demand-level alerts.',
            ],
        ]));
    }
}
