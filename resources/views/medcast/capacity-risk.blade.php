@extends('layouts.medcast')

@section('title', 'Capacity-Risk Scenarios')
@section('page-title', 'Capacity-Risk Scenarios')
@section('page-subtitle', 'Test admission-capacity assumptions against probabilistic forecasts')

@section('content')
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-5">
            <h2 class="text-lg font-semibold text-slate-900">Scenario Controls</h2>
            <p class="mt-1 text-sm text-slate-500">Capacity is measured as admissions that can be handled per day. Changing controls does not alter stored forecasts.</p>
        </div>

        <form method="GET" action="{{ route('capacity-risk') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Forecast horizon</span>
                <select name="horizon" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    @foreach ([1, 7, 30] as $days)
                        <option value="{{ $days }}" @selected($controls['horizon'] === $days)>{{ $days }} day{{ $days === 1 ? '' : 's' }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Capacity assumption</span>
                <select id="capacity-mode" name="capacity_mode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="constrained" @selected($controls['capacity_mode'] === 'constrained')>Constrained · {{ $capacityPresets['constrained'] }}/day</option>
                    <option value="moderate" @selected($controls['capacity_mode'] === 'moderate')>Moderate · {{ $capacityPresets['moderate'] }}/day</option>
                    <option value="expanded" @selected($controls['capacity_mode'] === 'expanded')>Expanded · {{ $capacityPresets['expanded'] }}/day</option>
                    <option value="custom" @selected($controls['capacity_mode'] === 'custom')>Custom</option>
                </select>
            </label>

            <label id="custom-capacity-wrap" class="{{ $controls['capacity_mode'] === 'custom' ? 'block' : 'hidden' }}">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Custom admissions/day</span>
                <input type="number" name="custom_capacity" min="1" max="9999" value="{{ $controls['custom_capacity'] }}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            </label>

            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Penalty setting</span>
                <select name="penalty" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    @foreach ($penalties as $key => $penalty)
                        <option value="{{ $key }}" @selected($controls['penalty'] === $key)>{{ $penalty['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end lg:col-span-4">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    Calculate scenario
                </button>
            </div>
        </form>
        <p class="mt-4 text-xs text-slate-400">Preset capacities use historical admission percentiles: constrained=P50, moderate=P66, and expanded=P90.</p>
    </section>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4 xl:grid-cols-8">
        <div class="col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Selected model</p>
            <p class="mt-2 text-lg font-bold text-slate-900">{{ $scenario['model'] }}</p>
            <p class="mt-1 text-xs text-slate-400">best available for {{ $controls['horizon'] }}-day horizon</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Forecast/day</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $scenario['forecasted_admissions'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Capacity/day</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $scenario['capacity'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pressure ratio</p>
            <p class="mt-2 text-2xl font-bold {{ $scenario['pressure_ratio'] > 1 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $scenario['pressure_ratio'] }}×</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Expected overload</p>
            <p class="mt-2 text-2xl font-bold text-orange-600">{{ $scenario['expected_overload'] }}</p>
            <p class="mt-1 text-xs text-slate-400">cumulative admissions</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Exceedance probability</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $scenario['probability'] }}%</p>
            <p class="mt-1 text-xs text-slate-400">at least once</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Capacity risk</p>
            <span class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-bold {{ $scenario['risk_class'] }}">{{ $scenario['risk_level'] }}</span>
            <p class="mt-1 text-xs text-slate-400">score {{ $scenario['risk_score'] }}/100</p>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
            <h2 class="text-lg font-semibold text-slate-900">Forecast vs Scenario Capacity</h2>
            <p class="mt-1 text-sm text-slate-500">Daily admissions forecast and assumed handling capacity.</p>
            <div id="capacityChart" class="mt-4 min-h-[320px]"></div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Recommended preparedness</p>
            <h2 class="mt-2 text-xl font-bold text-slate-900">{{ $scenario['preparedness'] }}</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $scenario['penalty_description'] }}</p>
            <dl class="mt-5 space-y-3 border-t border-blue-100 pt-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Dataset version</dt>
                    <dd class="mt-1 font-medium text-slate-700">{{ $datasetInfo['version'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Coverage period</dt>
                    <dd class="mt-1 font-medium text-slate-700">{{ $datasetInfo['coverage'] }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Daily Capacity Risk</h2>
        <p class="mt-1 text-sm text-slate-500">Exceedance probabilities are estimated from each day’s 80% prediction interval; the horizon-level probability is an independence approximation.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-right">Forecast</th>
                        <th class="px-4 py-3 text-right">80% PI</th>
                        <th class="px-4 py-3 text-right">Pressure</th>
                        <th class="px-4 py-3 text-right">Overload</th>
                        <th class="px-4 py-3 text-right">P(exceed)</th>
                        <th class="px-4 py-3 text-right">Risk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($dailyScenarios as $day)
                        <tr class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $day['date'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $day['forecast'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-slate-600">{{ $day['interval'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $day['pressure'] }}×</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $day['overload'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $day['probability'] }}%</td>
                            <td class="px-4 py-3 text-right"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $day['risk_class'] }}">{{ $day['risk'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
        <p class="font-semibold">Planning interpretation</p>
        <p class="mt-1 leading-relaxed">This scenario tool supports planning; it does not prescribe staffing or bed assignments. Review results with current occupancy, staff availability, clinical acuity, and local surge protocols.</p>
    </section>
@endsection

@push('scripts')
<script>
    const capacityMode = document.getElementById('capacity-mode');
    const customCapacityWrap = document.getElementById('custom-capacity-wrap');
    capacityMode?.addEventListener('change', () => {
        customCapacityWrap?.classList.toggle('hidden', capacityMode.value !== 'custom');
        customCapacityWrap?.classList.toggle('block', capacityMode.value === 'custom');
    });

    const capacityChart = @json($chartData);
    new ApexCharts(document.querySelector('#capacityChart'), {
        chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: 'Forecast admissions', type: 'column', data: capacityChart.forecast },
            { name: 'Scenario capacity', type: 'line', data: capacityChart.capacity }
        ],
        colors: ['#2563eb', '#e11d48'],
        stroke: { width: [0, 3], curve: 'smooth', dashArray: [0, 5] },
        plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: capacityChart.categories, labels: { style: { colors: '#64748b' } } },
        yaxis: { min: 0, title: { text: 'Admissions per day' }, labels: { style: { colors: '#64748b' } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        legend: { position: 'top' }
    }).render();
</script>
@endpush
