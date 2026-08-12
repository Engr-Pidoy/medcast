@extends('layouts.medcast')

@section('title', 'Decision Support')
@section('page-title', 'Decision Support')
@section('page-subtitle', 'Demand thresholds, classified forecast days, and capacity recommendations')

@section('content')
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Beds</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $capacity['total_beds'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Occupied</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $capacity['occupied'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Available</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $capacity['available'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Occupancy</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $capacity['occupancy'] }}%</p>
        </div>
        <div class="col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-1">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Projected Peak</p>
            <p class="mt-2 text-3xl font-bold text-amber-600">{{ $capacity['projected_peak'] }}%</p>
        </div>
    </section>

    @if ($threshold)
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Demand Thresholds</h2>
                    <p class="mt-1 text-sm text-slate-500">Active classification rules ({{ $threshold['method'] }})</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">Low: {{ $demandSummary['low'] }} days</span>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">Moderate: {{ $demandSummary['moderate'] }} days</span>
                    <span class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-700">High: {{ $demandSummary['high'] }} days</span>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Low</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">≤ {{ (int) $threshold['low_max'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">admissions / day</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Moderate</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">≤ {{ (int) $threshold['moderate_max'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">up to moderate max</p>
                </div>
                <div class="rounded-lg border border-rose-100 bg-rose-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">High</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">> {{ (int) $threshold['moderate_max'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">from {{ (int) $threshold['high_min'] }}+</p>
                </div>
            </div>
            <ul class="mt-4 space-y-1 text-sm text-slate-600">
                @foreach ($threshold['rules'] as $rule)
                    <li>• {{ $rule }}</li>
                @endforeach
            </ul>
        </section>
    @else
        <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            No active demand thresholds. Run <code class="font-mono">php artisan medcast:run-forecast</code> to compute Low / Moderate / High cutoffs.
        </section>
    @endif

    @if (count($classifiedDays))
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Next 7 Forecast Days · Classified Demand</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3 text-right">Forecast</th>
                            <th class="px-4 py-3 text-right">80% PI</th>
                            <th class="px-4 py-3 text-right">Demand Level</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($classifiedDays as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row['date'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $row['forecast'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['low80'] }} – {{ $row['high80'] }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row['demand_class'] }}">{{ $row['demand_level'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-slate-900">Alerts</h2>
            @foreach ($alerts as $alert)
                @php
                    $styles = [
                        'watch' => 'border-amber-200 bg-amber-50',
                        'info' => 'border-blue-200 bg-blue-50',
                        'ok' => 'border-emerald-200 bg-emerald-50',
                    ];
                    $badge = [
                        'watch' => 'bg-amber-100 text-amber-800',
                        'info' => 'bg-blue-100 text-blue-800',
                        'ok' => 'bg-emerald-100 text-emerald-800',
                    ];
                @endphp
                <div class="rounded-xl border p-5 {{ $styles[$alert['level']] }}">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase {{ $badge[$alert['level']] }}">{{ $alert['level'] }}</span>
                        <h3 class="font-semibold text-slate-900">{{ $alert['title'] }}</h3>
                    </div>
                    <p class="text-sm text-slate-600">{{ $alert['detail'] }}</p>
                    <p class="mt-3 text-sm font-medium text-slate-800">Suggested: {{ $alert['action'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Recommendations</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Area</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recommendations as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $row['area'] }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $p = $row['priority'] === 'High' ? 'bg-rose-50 text-rose-700' : ($row['priority'] === 'Medium' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600');
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $p }}">{{ $row['priority'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 rounded-lg bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-800">Capacity gauge</p>
                <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-blue-600" style="width: {{ $capacity['occupancy'] }}%"></div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Current {{ $capacity['occupancy'] }}% · Projected peak {{ $capacity['projected_peak'] }}%</p>
            </div>
        </div>
    </section>
@endsection
