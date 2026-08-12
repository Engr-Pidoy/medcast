@extends('layouts.medcast')

@section('title', 'Forecasting')
@section('page-title', 'Forecasting')
@section('page-subtitle', $selectedHorizon.'-day primary-model admission forecast with prediction intervals')

@section('content')
    <section class="flex flex-wrap items-center gap-2">
        @foreach ([1 => '1-day', 7 => '7-day', 30 => '30-day'] as $days => $label)
            <a href="{{ route('forecasting', ['horizon' => $days]) }}"
               class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ (int) $selectedHorizon === (int) $days ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
        <div class="ml-auto flex flex-wrap gap-2 text-xs">
            @foreach ([1 => '1d', 7 => '7d', 30 => '30d'] as $days => $label)
                @if (! empty($bestModels[$days]))
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">
                        Best {{ $label }}: {{ $bestModels[$days] }}
                    </span>
                @endif
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Primary Model</p>
            <p class="mt-2 text-lg font-bold text-slate-900">{{ $model['name'] }}</p>
            <p class="mt-1 text-xs text-slate-400">trained on {{ $model['trained_on'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Forecast Range</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $summary['range'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $summary['confidence'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Mean Forecast</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $summary['mean'] }}</p>
            <p class="mt-1 text-xs text-slate-400">patients/day · {{ $model['horizon'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Trend</p>
            <p class="mt-2 text-2xl font-bold text-violet-600">{{ $summary['trend'] }}</p>
            <p class="mt-1 text-xs text-slate-400">last run {{ $model['last_run'] }}</p>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-10">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-7">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-900">Next {{ $selectedHorizon }}-Day Forecast</h2>
                <div class="flex flex-wrap gap-3 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 border-t-2 border-dashed border-red-500"></span> Forecast</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-4 rounded-sm bg-red-400/40"></span> 80% PI</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-4 rounded-sm bg-red-300/25"></span> 95% PI</span>
                </div>
            </div>
            <div id="forecastChart" class="min-h-[340px]"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Run Settings</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                <li class="flex justify-between py-3"><span class="text-slate-500">View horizon</span><span class="font-semibold">{{ $model['horizon'] }}</span></li>
                <li class="flex justify-between py-3"><span class="text-slate-500">Seasonality</span><span class="font-semibold">Weekly (7)</span></li>
                <li class="flex justify-between py-3"><span class="text-slate-500">Interval</span><span class="font-semibold">80% / 95%</span></li>
                <li class="flex justify-between py-3"><span class="text-slate-500">Update</span><span class="font-semibold">Daily 6:00 AM</span></li>
            </ul>
            <form id="run-forecast-form" method="POST" action="{{ route('forecasting.run') }}" class="mt-4">
                @csrf
                <button id="run-forecast-btn" type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                    <svg id="run-forecast-icon" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span id="run-forecast-label">Run Forecast</span>
                </button>
                <p class="mt-2 text-center text-xs text-slate-400">May take 10–60 seconds. Page will refresh when done.</p>
            </form>
        </div>
    </section>

    @if (count($comparison))
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-1 text-lg font-semibold text-slate-900">Model Comparison · {{ $selectedHorizon }}-day horizon</h2>
            <p class="mb-4 text-sm text-slate-500">Holdout MAE / RMSE / MASE for the selected forecast horizon. Best model highlighted.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Model</th>
                            <th class="px-4 py-3 text-right">MAE</th>
                            <th class="px-4 py-3 text-right">RMSE</th>
                            <th class="px-4 py-3 text-right">MASE</th>
                            <th class="px-4 py-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($comparison as $row)
                            <tr class="{{ $row['is_best'] ? 'bg-emerald-50/70' : 'hover:bg-slate-50/80' }}">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row['model'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['mae'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['rmse'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['mase'] }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($row['is_best'])
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Best</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Forecast Table</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-right">Point Forecast</th>
                        <th class="px-4 py-3 text-right">Demand Level</th>
                        <th class="px-4 py-3 text-right">80% PI Low</th>
                        <th class="px-4 py-3 text-right">80% PI High</th>
                        <th class="px-4 py-3 text-right">95% PI Low</th>
                        <th class="px-4 py-3 text-right">95% PI High</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($horizon as $row)
                        <tr class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $row['date'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $row['forecast'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row['demand_class'] }}">{{ $row['demand_level'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $row['low80'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $row['high80'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $row['low95'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $row['high95'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    const form = document.getElementById('run-forecast-form');
    const btn = document.getElementById('run-forecast-btn');
    const label = document.getElementById('run-forecast-label');
    if (form && btn && label) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            label.textContent = 'Running models...';
        });
    }

    const horizon = @json($horizon);
    new ApexCharts(document.querySelector('#forecastChart'), {
        chart: { type: 'line', height: 360, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: '95% PI', type: 'rangeArea', data: horizon.map(r => ({ x: r.category, y: [r.low95, r.high95] })) },
            { name: '80% PI', type: 'rangeArea', data: horizon.map(r => ({ x: r.category, y: [r.low80, r.high80] })) },
            { name: 'Forecast', type: 'line', data: horizon.map(r => ({ x: r.category, y: r.forecast })) }
        ],
        colors: ['#fecaca', '#f87171', '#ef4444'],
        stroke: { curve: 'smooth', width: [0, 0, 2.5], dashArray: [0, 0, 6] },
        fill: { opacity: [0.35, 0.45, 1] },
        dataLabels: { enabled: false },
        xaxis: { type: 'category', labels: { style: { colors: '#64748b' } } },
        yaxis: { min: 0, title: { text: 'Admissions' }, labels: { style: { colors: '#64748b' } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        legend: { show: false },
        tooltip: { shared: true, y: { formatter: (val) => Array.isArray(val) ? val[0] + ' – ' + val[1] : val } }
    }).render();
</script>
@endpush
