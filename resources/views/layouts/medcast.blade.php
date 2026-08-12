<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MEDCAST') — MEDCAST</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        medcast: {
                            navy: '#0f172a',
                            slate: '#1e293b',
                            accent: '#2563eb',
                            soft: '#f1f5f9',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar-link.active {
            background-color: rgba(37, 99, 235, 0.2);
            color: #93c5fd;
            border-right: 3px solid #3b82f6;
        }
        @media (max-width: 1023px) {
            .sidebar-open { transform: translateX(0) !important; }
        }
    </style>
    @stack('styles')
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    @php
        $hospitalName = $hospitalName ?? 'Norala District Hospital';
        $currentDateTime = $currentDateTime ?? now()->format('F j, Y · g:i A');
        $nav = request()->route()?->getName();
    @endphp

    <div class="flex min-h-screen">
        <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden" onclick="toggleSidebar()"></div>

        <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-300 transition-transform duration-200 lg:static lg:translate-x-0">
            <div class="flex h-16 items-center gap-3 border-b border-slate-700/60 px-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white">M</div>
                <div>
                    <p class="text-lg font-bold tracking-wide text-white">MEDCAST</p>
                    <p class="text-[10px] uppercase tracking-wider text-slate-500">Forecasting System</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-5">
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ $nav === 'dashboard' ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                    Dashboard
                </a>
                <a href="{{ route('encode') }}" class="sidebar-link {{ $nav === 'encode' || $nav === 'encode.store' ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Encode Daily Data
                </a>
                <a href="{{ route('historical') }}" class="sidebar-link {{ $nav === 'historical' ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Historical Data
                </a>
                <a href="{{ route('trends') }}" class="sidebar-link {{ $nav === 'trends' ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                    Trends
                </a>
                <a href="{{ route('forecasting') }}" class="sidebar-link {{ $nav === 'forecasting' || $nav === 'forecasting.run' ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    Forecasting
                </a>
                <a href="{{ route('performance') }}" class="sidebar-link {{ $nav === 'performance' ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Model Performance
                </a>
                <a href="{{ route('decision-support') }}" class="sidebar-link {{ $nav === 'decision-support' ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Decision Support
                </a>
                <a href="{{ route('about') }}" class="sidebar-link {{ $nav === 'about' ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    About
                </a>
            </nav>

            <div class="border-t border-slate-700/60 px-5 py-4">
                <p class="text-sm font-semibold text-white">{{ $hospitalName }}</p>
                <p class="mt-1 text-xs text-slate-400" id="sidebar-datetime">{{ $currentDateTime }}</p>
                @auth
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-xs text-slate-300">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[10px] uppercase tracking-wide text-slate-500">{{ auth()->user()->role ?? 'staff' }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md px-2 py-1 text-xs text-slate-400 hover:bg-slate-800 hover:text-white">Logout</button>
                        </form>
                    </div>
                @endauth
            </div>
        </aside>
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                    <div class="flex items-start gap-3">
                        <button type="button" onclick="toggleSidebar()" class="mt-1 rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Open menu">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900">@yield('page-title')</h1>
                            <p class="mt-0.5 text-sm text-slate-500">@yield('page-subtitle')</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        @hasSection('header-actions')
                            @yield('header-actions')
                        @else
                            <button type="button" onclick="window.location.reload()"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Refresh Data
                            </button>
                        @endif
                    </div>
                </div>
            </header>

            <main class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
                @if (session('success'))
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {{ session('error') }}
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebar').classList.toggle('sidebar-open');
            document.getElementById('sidebar-overlay').classList.toggle('hidden');
        }
        (function updateClock() {
            const el = document.getElementById('sidebar-datetime');
            if (el) {
                el.textContent = new Date().toLocaleString('en-US', {
                    month: 'long', day: 'numeric', year: 'numeric',
                    hour: 'numeric', minute: '2-digit', hour12: true
                }).replace(',', ' ·');
            }
            setTimeout(updateClock, 30000);
        })();
    </script>
    @stack('scripts')
</body>
</html>
