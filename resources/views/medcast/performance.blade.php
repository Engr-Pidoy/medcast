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
