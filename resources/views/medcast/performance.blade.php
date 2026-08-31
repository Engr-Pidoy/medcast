@extends('layouts.medcast')

@section('title', 'Model Performance')
@section('page-title', 'Model Performance')
@section('page-subtitle', 'Multi-model benchmark comparison across 1 / 7 / 30-day horizons')

@section('content')
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Primary Model</p>
            <p class="mt-2 text-lg font-bold text-slate-900">{{ $metrics['primary_model'] }}</p>
            <p class="mt-1 text-xs text-slate-400">active forecast run</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">MAE (7d)</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $metrics['mae'] }}</p>
            <p class="mt-1 text-xs text-slate-400">mean absolute error</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">RMSE (7d)</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $metrics['rmse'] }}</p>
            <p class="mt-1 text-xs text-slate-400">root mean squared error</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">MASE (7d)</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $metrics['mase'] ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">vs seasonal naive</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">80% Coverage</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $metrics['coverage80'] !== null ? $metrics['coverage80'].'%' : '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">PI hit rate</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">95% Coverage</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $metrics['coverage95'] !== null ? $metrics['coverage95'].'%' : '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">PI hit rate</p>
        </div>
    </section>

    <section class="rounded-xl border border-blue-200 bg-blue-50/60 p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Evaluation transparency</p>
                <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $datasetInfo['version'] }}</h2>
                <p class="mt-1 text-sm text-slate-600">Coverage: {{ $datasetInfo['coverage'] }} · {{ number_format($datasetInfo['records']) }} daily observations</p>
                <p class="mt-1 text-sm font-medium text-blue-800">Chronological split: {{ $datasetInfo['training_percent'] }}% training ({{ number_format($datasetInfo['training_records']) }}) / {{ $datasetInfo['testing_percent'] }}% testing ({{ number_format($datasetInfo['testing_records']) }})</p>
            </div>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm lg:text-right">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Holdout</dt>
                    <dd class="mt-1 font-semibold text-slate-700">{{ $datasetInfo['holdout_days'] }} days (20%)</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Evaluated</dt>
                    <dd class="mt-1 font-semibold text-slate-700">{{ $datasetInfo['generated_at'] }}</dd>
                </div>
            </dl>
        </div>
    </section>

    @if ($hasBenchmarks)
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ([1 => '1-day', 7 => '7-day', 30 => '30-day'] as $h => $label)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Best · {{ $label }}</p>
                    @if ($bestByHorizon[$h] ?? null)
                        <p class="mt-2 text-xl font-bold text-emerald-700">{{ $bestByHorizon[$h]['model'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            MAE {{ $bestByHorizon[$h]['mae'] }} · RMSE {{ $bestByHorizon[$h]['rmse'] }} · MASE {{ $bestByHorizon[$h]['mase'] }}
                        </p>
                    @else
                        <p class="mt-2 text-lg font-semibold text-slate-400">—</p>
                    @endif
                </div>
            @endforeach
        </section>

        @if (!empty($sarimaCandidates))
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 text-lg font-semibold text-slate-900">SARIMA Order Selection (AIC / BIC)</h2>
                <p class="mb-2 text-sm text-slate-500">
                    {{ $sarimaCriterion ?? 'Candidate SARIMA orders compared using AIC (primary) and BIC.' }}
                </p>
                @if (!empty($sarimaSelected))
                    <p class="mb-4 text-sm text-slate-700">
                        Selected order:
                        <span class="font-semibold text-emerald-700">{{ $sarimaSelected['model_order'] ?? '—' }}</span>
                        · AIC {{ $sarimaSelected['aic'] ?? '—' }}
                        · BIC {{ $sarimaSelected['bic'] ?? '—' }}
                    </p>
                @endif
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">Order</th>
                                <th class="px-4 py-3 text-right">AIC</th>
                                <th class="px-4 py-3 text-right">BIC</th>
                                <th class="px-4 py-3 text-right">HQIC</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sarimaCandidates as $cand)
                                @php
                                    $isSelected = ($sarimaSelected['model_order'] ?? null) === ($cand['model_order'] ?? null);
                                @endphp
                                <tr class="{{ $isSelected ? 'bg-emerald-50' : 'hover:bg-slate-50/80' }}">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium {{ $isSelected ? 'text-emerald-800' : 'text-slate-800' }}">
                                        {{ $cand['model_order'] ?? '—' }}
                                        @if ($isSelected)
                                            <span class="ml-2 text-xs font-semibold uppercase text-emerald-600">selected</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right {{ $isSelected ? 'font-semibold text-emerald-800' : 'text-slate-600' }}">{{ $cand['aic'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right {{ $isSelected ? 'font-semibold text-emerald-800' : 'text-slate-600' }}">{{ $cand['bic'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right {{ $isSelected ? 'font-semibold text-emerald-800' : 'text-slate-600' }}">{{ $cand['hqic'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-1 text-lg font-semibold text-slate-900">Benchmark Matrix</h2>
            <p class="mb-4 text-sm text-slate-500">MAE / RMSE / MASE for Naive, SeasonalNaive, SARIMA, Prophet, and HoltWinters. Best model per horizon highlighted.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3" rowspan="2">Model</th>
                            @foreach ([1, 7, 30] as $h)
                                <th class="px-4 py-3 text-center" colspan="3">{{ $h }}-day</th>
                            @endforeach
                        </tr>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            @foreach ([1, 7, 30] as $h)
                                <th class="px-3 py-2 text-right">MAE</th>
                                <th class="px-3 py-2 text-right">RMSE</th>
                                <th class="px-3 py-2 text-right">MASE</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($matrix as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $row['model'] }}</td>
                                @foreach ([1, 7, 30] as $h)
                                    @php $cell = $row['horizons'][$h] ?? null; @endphp
                                    @if ($cell)
                                        <td class="px-3 py-3 text-right {{ $cell['is_best'] ? 'bg-emerald-50 font-semibold text-emerald-800' : 'text-slate-600' }}">{{ $cell['mae'] }}</td>
                                        <td class="px-3 py-3 text-right {{ $cell['is_best'] ? 'bg-emerald-50 font-semibold text-emerald-800' : 'text-slate-600' }}">{{ $cell['rmse'] }}</td>
                                        <td class="px-3 py-3 text-right {{ $cell['is_best'] ? 'bg-emerald-50 font-semibold text-emerald-800' : 'text-slate-600' }}">{{ $cell['mase'] }}</td>
                                    @else
                                        <td class="px-3 py-3 text-right text-slate-300">—</td>
                                        <td class="px-3 py-3 text-right text-slate-300">—</td>
                                        <td class="px-3 py-3 text-right text-slate-300">—</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Prediction-Interval Quality by Horizon</h2>
            <p class="mt-1 text-sm text-slate-500">Coverage shows how often actual admissions fell inside the interval. Relative width is normalized against mean actual demand; robustness measures rolling error stability within the holdout without model refitting.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Horizon / best model</th>
                            <th class="px-4 py-3 text-right">80% coverage</th>
                            <th class="px-4 py-3 text-right">95% coverage</th>
                            <th class="px-4 py-3 text-right">Avg 80% width</th>
                            <th class="px-4 py-3 text-right">Relative 80%</th>
                            <th class="px-4 py-3 text-right">Avg 95% width</th>
                            <th class="px-4 py-3 text-right">Relative 95%</th>
                            <th class="px-4 py-3 text-right">Rolling MAE</th>
                            <th class="px-4 py-3 text-right">Robustness</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ([1, 7, 30] as $h)
                            @php $row = $bestByHorizon[$h] ?? null; @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $h }}-day · {{ $row['model'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['coverage80'] !== null ? $row['coverage80'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['coverage95'] !== null ? $row['coverage95'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['width80'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['relativeWidth80'] !== null ? $row['relativeWidth80'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['width95'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['relativeWidth95'] !== null ? $row['relativeWidth95'].'%' : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-600">{{ $row && $row['rollingMaeMean'] !== null ? $row['rollingMaeMean'].' ± '.$row['rollingMaeStd'] : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['robustness'] !== null ? $row['robustness'].'/100' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">High-Demand Detection Performance</h2>
            <p class="mt-1 text-sm text-slate-500">High demand is defined from the training-set 66th percentile. Rates use forecasted versus actual high-demand days.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Horizon</th>
                            <th class="px-4 py-3 text-right">High-day MAE</th>
                            <th class="px-4 py-3 text-right">Sensitivity</th>
                            <th class="px-4 py-3 text-right">Specificity</th>
                            <th class="px-4 py-3 text-right">Precision</th>
                            <th class="px-4 py-3 text-right">F1-score</th>
                            <th class="px-4 py-3 text-right">False alerts</th>
                            <th class="px-4 py-3 text-right">Missed events</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ([1, 7, 30] as $h)
                            @php $row = $bestByHorizon[$h] ?? null; @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $h }}-day</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['highDemandMae'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['sensitivity'] !== null ? $row['sensitivity'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['specificity'] !== null ? $row['specificity'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['precision'] !== null ? $row['precision'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-700">{{ $row && $row['f1'] !== null ? $row['f1'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['falseAlertRate'] !== null ? $row['falseAlertRate'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row && $row['missedEventRate'] !== null ? $row['missedEventRate'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if (!empty($sensitivityAnalysis))
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Sensitivity Analyses · 7-day Best Model</h2>
                <p class="mt-1 text-sm text-slate-500">How conclusions change under capacity, threshold, penalty, and outlier assumptions.</p>
                <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-4">
                        <h3 class="font-semibold text-slate-800">Capacity sensitivity</h3>
                        <div class="mt-3 space-y-2 text-sm">
                            @foreach (($sensitivityAnalysis['capacity'] ?? []) as $name => $item)
                                <div class="flex items-center justify-between gap-3"><span class="uppercase text-slate-500">{{ $name }} · {{ $item['capacity'] }}/day</span><span class="font-medium text-slate-700">{{ $item['forecast_overload'] }} overload</span></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-4">
                        <h3 class="font-semibold text-slate-800">Threshold sensitivity</h3>
                        <div class="mt-3 space-y-2 text-sm">
                            @foreach (($sensitivityAnalysis['threshold'] ?? []) as $name => $item)
                                <div class="flex items-center justify-between gap-3"><span class="uppercase text-slate-500">{{ $name }} · cutoff {{ $item['threshold'] }}</span><span class="font-medium text-slate-700">F1 {{ $item['f1_score'] !== null ? $item['f1_score'].'%' : '—' }}</span></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-4">
                        <h3 class="font-semibold text-slate-800">Penalty sensitivity</h3>
                        <div class="mt-3 space-y-2 text-sm">
                            @foreach (($sensitivityAnalysis['penalty'] ?? []) as $name => $item)
                                <div class="flex items-center justify-between gap-3"><span class="capitalize text-slate-500">{{ str_replace('_', ' ', $name) }}</span><span class="font-medium text-slate-700">{{ $item['weighted_error_rate'] }}% weighted error</span></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-4">
                        <h3 class="font-semibold text-slate-800">Outlier sensitivity</h3>
                        @php $outlier = $sensitivityAnalysis['outlier'] ?? []; @endphp
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-400">P99 cap</dt><dd class="font-medium text-slate-700">{{ $outlier['winsorization_cap_p99'] ?? '—' }}</dd></div>
                            <div><dt class="text-slate-400">MAE change</dt><dd class="font-medium text-slate-700">{{ isset($outlier['mae_change_percent']) ? $outlier['mae_change_percent'].'%' : '—' }}</dd></div>
                        </dl>
                    </div>
                </div>
            </section>
        @endif
    @else
        <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            No model benchmarks yet. Run <code class="font-mono">php artisan medcast:run-forecast</code> to populate the comparison matrix.
        </section>
    @endif

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">MASE by Model (7-day)</h2>
            <div id="maseChart" class="min-h-[300px]"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Weekly Residuals</h2>
            <div id="residualChart" class="min-h-[300px]"></div>
        </div>
    </section>

    @if (count($backtests))
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Backtest Results</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Period</th>
                            <th class="px-4 py-3 text-right">MAE</th>
                            <th class="px-4 py-3 text-right">RMSE</th>
                            <th class="px-4 py-3 text-right">MAPE</th>
                            <th class="px-4 py-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($backtests as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row['period'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['mae'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['rmse'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['mape'] }}%</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row['status'] === 'Good' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection

@push('scripts')
<script>
    const mase = @json($maseChart);
    const residual = @json($residualChart);

    new ApexCharts(document.querySelector('#maseChart'), {
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'MASE', data: mase.mase }],
        colors: ['#2563eb'],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: mase.categories, labels: { style: { colors: '#64748b' } } },
        yaxis: { min: 0, labels: { style: { colors: '#64748b' } }, title: { text: 'MASE' } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        tooltip: { y: { formatter: (val) => val == null ? '—' : val } }
    }).render();

    new ApexCharts(document.querySelector('#residualChart'), {
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Residual', data: residual.residuals }],
        colors: ['#64748b'],
        plotOptions: { bar: { borderRadius: 4, colors: { ranges: [{ from: -100, to: 0, color: '#f87171' }, { from: 0, to: 100, color: '#34d399' }] } } },
        dataLabels: { enabled: false },
        xaxis: { categories: residual.categories, labels: { style: { colors: '#64748b' } } },
        yaxis: { labels: { style: { colors: '#64748b' } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 }
    }).render();
</script>
@endpush
