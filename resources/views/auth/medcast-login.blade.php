<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — MEDCAST</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Source Serif 4"', 'Georgia', 'serif'],
                        sans: ['"DM Sans"', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        nnhs: {
                            green: '#0f5c3a',
                            forest: '#0a3d28',
                            gold: '#c9a227',
                            cream: '#f4f7f2',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-nnhs-cream font-sans text-slate-800 antialiased">
    <div class="relative min-h-screen overflow-hidden">
        {{-- Atmosphere --}}
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_rgba(15,92,58,0.18),_transparent_50%),radial-gradient(ellipse_at_bottom_right,_rgba(201,162,39,0.16),_transparent_45%)]"></div>
        <div class="pointer-events-none absolute -left-24 top-16 h-72 w-72 rounded-full bg-nnhs-green/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-16 bottom-10 h-80 w-80 rounded-full bg-nnhs-gold/20 blur-3xl"></div>

        <div class="relative mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid items-stretch gap-8 lg:grid-cols-2 lg:gap-10">
                {{-- Brand panel --}}
                <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-nnhs-forest via-nnhs-green to-[#147a4d] p-8 text-white shadow-xl sm:p-10">
                    <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, #fff 0.8px, transparent 0.9px); background-size: 18px 18px;"></div>
                    <div class="relative flex h-full flex-col">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-xl font-bold ring-1 ring-white/30 backdrop-blur">
                                N
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-nnhs-gold">Norala, South Cotabato</p>
                                <h1 class="font-display text-2xl font-bold leading-tight sm:text-3xl">Norala National High School</h1>
                            </div>
                        </div>

                        <div class="mt-10">
                            <p class="inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-nnhs-gold ring-1 ring-white/20">Research Prototype</p>
                            <h2 class="mt-4 font-display text-3xl font-bold leading-tight sm:text-4xl">MEDCAST</h2>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-white/85 sm:text-base">
                                Patient Admission Forecasting and Decision-Support System for Norala District Hospital.
                            </p>
                        </div>

                        <ul class="mt-8 space-y-3 text-sm text-white/90">
                            <li class="flex items-start gap-3">
                                <span class="mt-1 inline-block h-2 w-2 rounded-full bg-nnhs-gold"></span>
                                Historical admission trend analysis
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-1 inline-block h-2 w-2 rounded-full bg-nnhs-gold"></span>
                                Multi-model forecasting (SARIMA, Prophet, and more)
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-1 inline-block h-2 w-2 rounded-full bg-nnhs-gold"></span>
                                Demand-level decision support for hospital planning
                            </li>
                        </ul>

                        <div class="mt-auto pt-10 text-xs text-white/70">
                            Developed as a student research prototype of Norala National High School.
                        </div>
                    </div>
                </section>

                {{-- Login form --}}
                <section class="flex items-center rounded-3xl border border-slate-200/80 bg-white/90 p-8 shadow-xl backdrop-blur sm:p-10">
                    <div class="w-full">
                        <div class="mb-8">
                            <p class="text-sm font-semibold uppercase tracking-wider text-nnhs-green">Staff Access</p>
                            <h2 class="mt-2 font-display text-3xl font-bold text-slate-900">Sign in</h2>
                            <p class="mt-2 text-sm text-slate-500">Enter your credentials to open the MEDCAST dashboard.</p>
                        </div>

                        @if (session('status'))
                            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                            @csrf

                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="email"
                                    placeholder="admin@norala.ph"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-nnhs-green focus:ring-4 focus:ring-nnhs-green/15"
                                >
                            </div>

                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                                    @if (Route::has('password.request'))
                                        <a href="{{ route('password.request') }}" class="text-xs font-medium text-nnhs-green hover:underline">Forgot password?</a>
                                    @endif
                                </div>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-nnhs-green focus:ring-4 focus:ring-nnhs-green/15"
                                >
                            </div>

                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-nnhs-green focus:ring-nnhs-green" {{ old('remember') ? 'checked' : '' }}>
                                Remember me
                            </label>

                            <button
                                type="submit"
                                data-test="login-button"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-nnhs-green px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-nnhs-forest focus:outline-none focus:ring-4 focus:ring-nnhs-green/25"
                            >
                                Log in to MEDCAST
                            </button>
                        </form>

                        <div class="mt-8 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">
                            <p class="font-semibold text-slate-700">Demo accounts</p>
                            <p class="mt-1">admin@norala.ph / password</p>
                            <p>staff@norala.ph / password</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</body>
</html>
