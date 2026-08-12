@extends('layouts.medcast')

@section('title', 'About')
@section('page-title', 'About')
@section('page-subtitle', 'System information for MEDCAST')

@section('header-actions')
@endsection

@section('content')
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
            <div class="mb-6 flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-blue-600 text-xl font-bold text-white">M</div>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $system['name'] }}</h2>
                    <p class="text-sm text-slate-500">{{ $system['full_name'] }}</p>
                </div>
            </div>
            <p class="text-sm leading-relaxed text-slate-600">{{ $system['purpose'] }}</p>

            <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Hospital</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $system['hospital'] }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Primary Model</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $system['model'] }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Compared Models</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $system['models'] }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Forecast Horizons</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $system['horizons'] }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Version</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $system['version'] }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Auto Forecast</p>
                    <p class="mt-1 font-semibold text-slate-900">Daily 6:00 AM (Asia/Manila)</p>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-900">Prototype workflow</h3>
                <ol class="mt-3 list-decimal space-y-2 pl-4 text-sm text-slate-600">
                    <li>Login as hospital staff/admin</li>
                    <li>Encode daily totals or upload CSV/Excel</li>
                    <li>Run multi-model forecast (button or 6:00 AM schedule)</li>
                    <li>Review Trends, Forecasting, Performance, and Decision Support</li>
                </ol>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-900">Demo accounts</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <li><span class="font-medium text-slate-900">admin@norala.ph</span> / password</li>
                    <li><span class="font-medium text-slate-900">staff@norala.ph</span> / password</li>
                </ul>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-900">Disclaimer</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Forecasts are decision-support aids, not clinical diagnoses. Always combine model output with local operational judgment.
                </p>
            </div>
        </div>
    </section>
@endsection
