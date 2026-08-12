@extends('layouts.medcast')

@section('title', 'Encode Daily Data')
@section('page-title', 'Encode Daily Data')
@section('page-subtitle', 'Enter daily admission totals for Norala District Hospital')

@section('header-actions')
@endsection

@section('content')
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
            <h2 class="mb-1 text-lg font-semibold text-slate-900">Daily Admission Entry</h2>
            <p class="mb-5 text-sm text-slate-500">Aggregated daily counts only — no patient names needed.</p>

            <form method="POST" action="{{ route('encode.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Date</label>
                    <input type="date" name="admission_date" value="{{ old('admission_date', $defaults['admission_date']) }}" required
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    @error('admission_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Regular Admissions</label>
                        <input type="number" min="0" name="regular_admissions" value="{{ old('regular_admissions', $defaults['regular_admissions']) }}" required
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        @error('regular_admissions') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Emergency Cases</label>
                        <input type="number" min="0" name="emergency_admissions" value="{{ old('emergency_admissions', $defaults['emergency_admissions']) }}" required
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        @error('emergency_admissions') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Other</label>
                        <input type="number" min="0" name="other_admissions" value="{{ old('other_admissions', $defaults['other_admissions']) }}" required
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        @error('other_admissions') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Discharges</label>
                        <input type="number" min="0" name="discharges" value="{{ old('discharges', $defaults['discharges']) }}" required
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        @error('discharges') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Occupied Beds</label>
                        <input type="number" min="0" name="occupied_beds" value="{{ old('occupied_beds', $defaults['occupied_beds']) }}" required
                               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        @error('occupied_beds') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Notes (optional)</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">{{ old('notes') }}</textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="run_forecast" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('run_forecast') ? 'checked' : '' }}>
                    Run SARIMA forecast after saving
                </label>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        Save Daily Record
                    </button>
                    <a href="{{ route('historical') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        View Historical
                    </a>
                </div>
            </form>
        </div>

        <div class="space-y-4 xl:col-span-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-900">Hospital capacity</h3>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $hospital->total_beds }}</p>
                <p class="text-sm text-slate-500">total operational beds</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-semibold text-slate-900">Latest saved record</h3>
                @if ($latest)
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li class="flex justify-between"><span>Date</span><span class="font-medium text-slate-900">{{ $latest->admission_date->format('M j, Y') }}</span></li>
                        <li class="flex justify-between"><span>Regular</span><span class="font-medium text-slate-900">{{ $latest->regular_admissions }}</span></li>
                        <li class="flex justify-between"><span>Emergency</span><span class="font-medium text-slate-900">{{ $latest->emergency_admissions }}</span></li>
                        <li class="flex justify-between"><span>Total</span><span class="font-medium text-slate-900">{{ $latest->total_admissions }}</span></li>
                        <li class="flex justify-between"><span>Discharges</span><span class="font-medium text-slate-900">{{ $latest->discharges }}</span></li>
                        <li class="flex justify-between"><span>Occupancy</span><span class="font-medium text-slate-900">{{ $latest->occupancy_rate }}%</span></li>
                    </ul>
                @else
                    <p class="text-sm text-slate-500">No records yet.</p>
                @endif
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                <h3 class="font-semibold text-amber-900">How staff use this</h3>
                <ol class="mt-2 list-decimal space-y-1 pl-4 text-sm text-amber-900/80">
                    <li>Encode today’s totals here, or</li>
                    <li>Upload CSV/Excel in Historical Data</li>
                    <li>Run Forecast (manual or auto 6:00 AM)</li>
                </ol>
            </div>
        </div>
    </section>
@endsection
