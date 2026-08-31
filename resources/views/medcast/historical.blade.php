@extends('layouts.medcast')

@section('title', 'Historical Data')
@section('page-title', 'Historical Data')
@section('page-subtitle', 'Past patient admissions for Norala District Hospital')

@section('header-actions')
    <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <input type="text" value="{{ $dateRange }}" readonly class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-10 pr-4 text-sm sm:w-56">
    </div>
@endsection

@section('content')
    <section class="rounded-xl border border-blue-100 bg-blue-50/70 p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Upload Admissions CSV / Excel</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Upload the hospital file → merge records by date → automatically refresh all forecasts.
                </p>
                <p class="mt-2 text-xs text-slate-500">
                    Expected columns: Date, Daily Admissions, Daily Discharges, Total Occupied Beds, Operational Bed Capacity, Bed Occupancy Rate (%)
                </p>
                <p class="mt-1 text-xs font-medium text-blue-700">
                    Existing dates are updated; new dates are added. Rows not included in the new file are preserved.
                </p>
            </div>
            <form id="upload-admissions-form" method="POST" action="{{ route('historical.upload') }}" enctype="multipart/form-data" class="flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto">
                @csrf
                <input type="file" name="admissions_file" accept=".csv,.xlsx,.txt" required
                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 sm:w-64">
                <button id="upload-admissions-btn" type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                    <span id="upload-admissions-label">Upload &amp; Run Forecast</span>
                </button>
            </form>
        </div>
        @error('admissions_file')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </section>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Records</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['records'] }}</p>
            <p class="mt-1 text-xs text-slate-400">daily rows in range</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Admissions</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($stats['total']) }}</p>
            <p class="mt-1 text-xs text-slate-400">selected period</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Daily Average</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['avg'] }}</p>
            <p class="mt-1 text-xs text-slate-400">patients/day</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Peak Day</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['peak'] }}</p>
            <p class="mt-1 text-xs text-slate-400">highest admissions</p>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-10">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-6">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Monthly Admissions Trend</h2>
            <div id="monthlyChart" class="min-h-[300px]"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-4">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Data Notes</h2>
            <ul class="space-y-3 text-sm text-slate-600">
                <li class="rounded-lg bg-slate-50 p-3">Daily counts include regular and emergency ward admissions.</li>
                <li class="rounded-lg bg-slate-50 p-3">Discharges are same-day or later discharges linked to the census date.</li>
                <li class="rounded-lg bg-slate-50 p-3">Occupancy uses midnight bed census against 120 total beds.</li>
            </ul>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Daily Admission Records</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-right">Regular</th>
                        <th class="px-4 py-3 text-right">Emergency</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Discharges</th>
                        <th class="px-4 py-3 text-right">Occupancy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $row['date'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $row['regular'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $row['emergency'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $row['total'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $row['discharges'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $row['occupancy'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    const uploadForm = document.getElementById('upload-admissions-form');
    const uploadBtn = document.getElementById('upload-admissions-btn');
    const uploadLabel = document.getElementById('upload-admissions-label');
    if (uploadForm && uploadBtn && uploadLabel) {
        uploadForm.addEventListener('submit', function () {
            uploadBtn.disabled = true;
            uploadLabel.textContent = 'Uploading & forecasting...';
        });
    }

    const monthly = @json($monthly);
    new ApexCharts(document.querySelector('#monthlyChart'), {
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: 'Total Admissions', data: monthly.admissions },
            { name: 'Emergency Cases', data: monthly.emergencies }
        ],
        colors: ['#2563eb', '#f43f5e'],
        plotOptions: { bar: { columnWidth: '45%', borderRadius: 4 } },
        dataLabels: { enabled: false },
        xaxis: { categories: monthly.categories, labels: { style: { colors: '#64748b' } } },
        yaxis: { labels: { style: { colors: '#64748b' } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        legend: { position: 'top' }
    }).render();
</script>
@endpush
