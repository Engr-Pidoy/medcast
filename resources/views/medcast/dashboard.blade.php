@extends('layouts.medcast')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Overview of patient admissions and latest forecast')

@section('header-actions')
    <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <input type="text" value="{{ $dateRange }}" readonly
               class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-10 pr-4 text-sm text-slate-700 shadow-sm sm:w-56">
    </div>
    <button type="button" onclick="window.location.reload()"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Refresh Data
    </button>
@endsection

@section('content')
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Today's Admissions</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $kpis['todays_admissions'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">patients admitted today</p>
                </div>
                <div class="rounded-lg bg-blue-50 p-2.5 text-blue-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">7-Day Average</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $kpis['seven_day_average'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">patients/day</p>
                </div>
                <div class="rounded-lg bg-emerald-50 p-2.5 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Tomorrow Demand</p>
                    @php
                        $demandClass = match ($kpis['tomorrow_demand'] ?? '—') {
                            'High' => 'text-rose-600',
                            'Moderate' => 'text-amber-600',
                            'Low' => 'text-emerald-600',
                            default => 'text-slate-900',
                        };
                    @endphp
                    <p class="mt-2 text-2xl font-bold {{ $demandClass }}">{{ $kpis['tomorrow_demand'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">via {{ $kpis['primary_model'] }}</p>
                </div>
                <div class="rounded-lg bg-teal-50 p-2.5 text-teal-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Forecast Next 7 Days</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $kpis['forecast_low'] }} – {{ $kpis['forecast_high'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $kpis['forecast_interval'] }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 p-2.5 text-amber-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Next 7 Days Trend</p>
                    <p class="mt-2 text-2xl font-bold text-violet-600">{{ $kpis['next_7_days_trend'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">vs. recent average</p>
                </div>
                <div class="rounded-lg bg-violet-50 p-2.5 text-violet-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Bed Occupancy</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $kpis['bed_occupancy'] }}%</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $kpis['bed_occupancy_status'] }}</p>
                </div>
                <div class="rounded-lg bg-rose-50 p-2.5 text-rose-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-10">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-7">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-900">Daily Patient Admissions</h2>
                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 bg-[#1e3a8a]"></span> Actual</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 border-t-2 border-dashed border-red-500"></span> Forecast</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-4 rounded-sm bg-red-400/40"></span> 80% PI</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-4 rounded-sm bg-red-300/25"></span> 95% PI</span>
                </div>
            </div>
            <div id="admissionsChart" class="min-h-[340px]"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Admission Summary</h2>
            <ul class="divide-y divide-slate-100">
                <li class="flex items-center justify-between py-3"><span class="text-sm text-slate-500">Total Admissions</span><span class="text-sm font-semibold text-slate-900">{{ number_format($admissionSummary['total_admissions']) }}</span></li>
                <li class="flex items-center justify-between py-3"><span class="text-sm text-slate-500">Average per Day</span><span class="text-sm font-semibold text-slate-900">{{ $admissionSummary['average_per_day'] }}</span></li>
                <li class="flex items-center justify-between py-3"><span class="text-sm text-slate-500">Highest Admission</span><span class="text-sm font-semibold text-slate-900">{{ $admissionSummary['highest_admission'] }}</span></li>
                <li class="flex items-center justify-between py-3"><span class="text-sm text-slate-500">Lowest Admission</span><span class="text-sm font-semibold text-slate-900">{{ $admissionSummary['lowest_admission'] }}</span></li>
                <li class="flex items-center justify-between py-3"><span class="text-sm text-slate-500">Emergency Cases</span><span class="text-sm font-semibold text-slate-900">{{ number_format($admissionSummary['emergency_cases']) }}</span></li>
                <li class="flex items-center justify-between py-3"><span class="text-sm text-slate-500">Discharges</span><span class="text-sm font-semibold text-slate-900">{{ number_format($admissionSummary['discharges']) }}</span></li>
            </ul>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-10">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
            <h2 class="mb-2 text-lg font-semibold text-slate-900">Admissions by Type</h2>
            <div id="typeDonutChart" class="min-h-[300px]"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-7">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Recent Daily Admissions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3 text-right">Regular Admissions</th>
                            <th class="px-4 py-3 text-right">Emergency Cases</th>
                            <th class="px-4 py-3 text-right">Total Admissions</th>
                            <th class="px-4 py-3 text-right">Discharges</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentDailyAdmissions as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $row['date'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-600">{{ $row['regular'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-600">{{ $row['emergency'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900">{{ $row['total'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-600">{{ $row['discharges'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    const chartData = @json($chartData);
    const admissionsByType = @json($admissionsByType);

    new ApexCharts(document.querySelector('#admissionsChart'), {
        chart: { type: 'line', height: 360, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit' },
        series: [
            { name: '95% PI', type: 'rangeArea', data: chartData.categories.map((cat, i) => ({ x: cat, y: chartData.pi95[i] })).filter(p => p.y !== null) },
            { name: '80% PI', type: 'rangeArea', data: chartData.categories.map((cat, i) => ({ x: cat, y: chartData.pi80[i] })).filter(p => p.y !== null) },
            { name: 'Actual Admissions', type: 'line', data: chartData.categories.map((cat, i) => ({ x: cat, y: chartData.actual[i] })) },
            { name: 'Forecast', type: 'line', data: chartData.categories.map((cat, i) => ({ x: cat, y: chartData.forecast[i] })) }
        ],
        colors: ['#fecaca', '#f87171', '#1e3a8a', '#ef4444'],
        stroke: { curve: 'smooth', width: [0, 0, 2.5, 2.5], dashArray: [0, 0, 0, 6] },
        fill: { opacity: [0.35, 0.45, 1, 1] },
        dataLabels: { enabled: false },
        xaxis: { type: 'category', labels: { rotate: -45, style: { colors: '#64748b', fontSize: '11px' }, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { title: { text: 'Admissions', style: { color: '#64748b', fontSize: '12px' } }, min: 0, labels: { style: { colors: '#64748b', fontSize: '11px' } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        legend: { show: false },
        tooltip: { shared: true, intersect: false, y: { formatter: (val) => Array.isArray(val) ? val[0] + ' – ' + val[1] : (val ?? '—') } }
    }).render();

    new ApexCharts(document.querySelector('#typeDonutChart'), {
        chart: { type: 'donut', height: 300, fontFamily: 'inherit' },
        series: [admissionsByType.regular, admissionsByType.emergency, admissionsByType.other],
        labels: ['Regular Admissions', 'Emergency Cases', 'Other'],
        colors: ['#2563eb', '#f43f5e', '#94a3b8'],
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        total: { show: true, label: 'Total', formatter: () => admissionsByType.total }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', fontSize: '12px' },
        tooltip: { y: { formatter: (val) => val + '%' } }
    }).render();
</script>
@endpush
