@extends('layouts.medcast')

@section('title', 'Trends')
@section('page-title', 'Admission Trends')
@section('page-subtitle', 'Weekday and monthly patterns from historical admissions (SOP Q1)')

@section('content')
    @if (! $hasData)
        <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            No trend statistics yet. Run <code class="font-mono">php artisan medcast:run-forecast</code> to compute weekday, monthly, and overall summaries.
        </section>
    @endif

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Overall Mean</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $overall['mean'] ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">admissions / day</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Trend Direction</p>
            <p class="mt-2 text-2xl font-bold capitalize text-violet-600">{{ $overall['direction'] }}</p>
            <p class="mt-1 text-xs text-slate-400">
                @if ($overall['slope_per_month'] !== null)
                    {{ $overall['slope_per_month'] >= 0 ? '+' : '' }}{{ $overall['slope_per_month'] }}/month
                @else
                    slope unavailable
                @endif
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Busiest Weekday</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $peakWeekday['day'] ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">
                {{ isset($peakWeekday['value']) ? round($peakWeekday['value'], 1).' avg admissions' : 'no data' }}
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Quietest Weekday</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $quietWeekday['day'] ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">
                {{ isset($quietWeekday['value']) ? round($quietWeekday['value'], 1).' avg admissions' : 'no data' }}
            </p>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Min / Max Day</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $overall['min'] ?? '—' }} – {{ $overall['max'] ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">observed daily range</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Std. Deviation</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $overall['std'] ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">day-to-day variability</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Sample Size</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $overall['n_days'] ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">days analyzed</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Coverage Window</p>
            <p class="mt-2 text-lg font-bold text-slate-900">{{ $overall['start'] ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-400">to {{ $overall['end'] ?? '—' }}</p>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-1 text-lg font-semibold text-slate-900">Average Admissions by Weekday</h2>
            <p class="mb-4 text-sm text-slate-500">Which days of the week are typically busiest?</p>
            <div id="weekdayChart" class="min-h-[320px]"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-1 text-lg font-semibold text-slate-900">Monthly Average Admissions</h2>
            <p class="mb-4 text-sm text-slate-500">How do admissions change across months?</p>
            <div id="monthlyChart" class="min-h-[320px]"></div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    const weekdayChart = @json($weekdayChart);
    const monthlyChart = @json($monthlyChart);

    new ApexCharts(document.querySelector('#weekdayChart'), {
        chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Avg admissions', data: weekdayChart.values }],
        colors: ['#2563eb'],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: weekdayChart.categories, labels: { style: { colors: '#64748b' } } },
        yaxis: { min: 0, title: { text: 'Admissions / day' }, labels: { style: { colors: '#64748b' } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        tooltip: { y: { formatter: (val) => val == null ? '—' : val + ' avg' } }
    }).render();

    new ApexCharts(document.querySelector('#monthlyChart'), {
        chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'inherit', zoom: { enabled: false } },
        series: [{ name: 'Monthly avg', data: monthlyChart.values }],
        colors: ['#0f766e'],
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
        dataLabels: { enabled: false },
        xaxis: {
            categories: monthlyChart.categories,
            labels: { rotate: -45, style: { colors: '#64748b', fontSize: '11px' }, hideOverlappingLabels: true }
        },
        yaxis: { min: 0, title: { text: 'Admissions / day' }, labels: { style: { colors: '#64748b' } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        tooltip: { y: { formatter: (val) => val == null ? '—' : val + ' avg' } }
    }).render();
</script>
@endpush
