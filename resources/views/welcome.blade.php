<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Malabar Club — Staff &amp; Member Portals</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Outfit', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="h-full flex flex-col justify-between bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <!-- Header -->
    <header class="border-b border-slate-800/80 bg-slate-950/60 backdrop-blur-md sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 flex items-center justify-center font-black text-slate-950 text-xl shadow-lg shadow-amber-500/20">
                    {{ config('club.short_name', 'MC') }}
                </div>
                <div>
                    <div class="font-extrabold text-lg tracking-tight text-white flex items-center gap-2">
                        <span>{{ config('club.name', 'Malabar Club') }}</span>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold uppercase tracking-wider">{{ config('club.location', 'Vadakkencherry') }}</span>
                    </div>
                    <p class="text-xs text-slate-400">Private Members' Club Management Platform</p>
                </div>
            </div>

            <nav class="flex items-center gap-3">
                @if (auth('member')->check())
                    <a href="{{ route('member.dashboard') }}" class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition shadow-sm flex items-center gap-1.5">
                        <span>💳</span>
                        <span>{{ auth('member')->user()->name }} (Portal)</span>
                    </a>
                @else
                    <a href="{{ route('member.login') }}" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-amber-400 border border-amber-500/30 transition flex items-center gap-1.5">
                        <span>📱</span>
                        <span>Member Login</span>
                    </a>
                @endif

                @auth('web')
                    <a href="{{ route('pos') }}" class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-500 text-slate-950 hover:bg-amber-400 transition shadow-sm">
                        ⚡ Counter POS
                    </a>
                    @if (auth()->user()?->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))
                        <a href="{{ url('/admin') }}" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">
                            🏛️ Club Admin
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition cursor-pointer">
                        Staff Sign In →
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl w-full text-center space-y-10">
            <!-- Badge & Hero Header -->
            <div class="space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    <span>Server-Authoritative Operational Systems Active</span>
                </div>
                <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-white leading-tight">
                    Malabar Club Portals &amp; Operations
                </h1>
                <p class="text-base text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    Integrated systems for members and club staff. Digital membership card, real-time prepaid wallet, visual room rack, high-throughput counter POS, and administrative ledger.
                </p>
            </div>

            <!-- Portal Gateway Cards (4 cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 text-left">
                <!-- 1. Member Portal & PWA -->
                <a href="{{ auth('member')->check() ? route('member.dashboard') : route('member.login') }}" class="p-6 rounded-3xl bg-slate-900/90 border border-amber-500/40 hover:border-amber-400 hover:bg-slate-900/60 transition group flex flex-col justify-between shadow-xl relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-20 h-20 bg-amber-500/10 rounded-full blur-xl pointer-events-none"></div>
                    <div class="space-y-3 relative z-10">
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-2xl flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
                                💳
                            </div>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-semibold">PWA App</span>
                        </div>
                        <h3 class="text-lg font-bold text-white group-hover:text-amber-400 transition-colors">Member Portal</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Digital QR membership card, live prepaid balance, court bookings, room stays, itemized bills, and sports plans.
                        </p>
                    </div>
                    <div class="pt-5 flex items-center text-xs font-bold text-amber-400 group-hover:translate-x-1 transition-transform relative z-10">
                        <span>Access Portal</span>
                        <span class="ml-1">→</span>
                    </div>
                </a>

                <!-- 2. Counter POS -->
                <a href="{{ route('pos') }}" class="p-6 rounded-3xl bg-slate-900/90 border border-slate-800 hover:border-amber-500/60 hover:bg-slate-900/60 transition group flex flex-col justify-between shadow-xl">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-2xl flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
                                ⚡
                            </div>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-semibold">Staff Only</span>
                        </div>
                        <h3 class="text-lg font-bold text-white group-hover:text-amber-400 transition-colors">Counter POS Station</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Rapid keyboard billing, member card scan, wallet prepaid deductions, and 80mm ESC/POS thermal printing.
                        </p>
                    </div>
                    <div class="pt-5 flex items-center text-xs font-bold text-amber-400 group-hover:translate-x-1 transition-transform">
                        <span>Launch Station</span>
                        <span class="ml-1">→</span>
                    </div>
                </a>

                <!-- 3. Front Desk Room Rack -->
                <a href="{{ url('/admin/room-rack') }}" class="p-6 rounded-3xl bg-slate-900/90 border border-slate-800 hover:border-emerald-500/60 hover:bg-slate-900/60 transition group flex flex-col justify-between shadow-xl">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-2xl flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
                                🏨
                            </div>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-semibold">Reception</span>
                        </div>
                        <h3 class="text-lg font-bold text-white group-hover:text-emerald-400 transition-colors">Visual Room Rack</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Front-desk accommodation grid (Rooms 101–105), housekeeping cleanliness tracking, and guest check-in/out.
                        </p>
                    </div>
                    <div class="pt-5 flex items-center text-xs font-bold text-emerald-400 group-hover:translate-x-1 transition-transform">
                        <span>View Room Rack</span>
                        <span class="ml-1">→</span>
                    </div>
                </a>

                <!-- 4. Club Administration -->
                <a href="{{ url('/admin') }}" class="p-6 rounded-3xl bg-slate-900/90 border border-slate-800 hover:border-sky-500/60 hover:bg-slate-900/60 transition group flex flex-col justify-between shadow-xl">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/30 text-sky-400 text-2xl flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
                                🏛️
                            </div>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-semibold">Admin</span>
                        </div>
                        <h3 class="text-lg font-bold text-white group-hover:text-sky-400 transition-colors">Club Administration</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Membership roster, GST tariffs, inventory control, food contractor settlements, and double-entry General Ledger.
                        </p>
                    </div>
                    <div class="pt-5 flex items-center text-xs font-bold text-sky-400 group-hover:translate-x-1 transition-transform">
                        <span>Enter Admin Panel</span>
                        <span class="ml-1">→</span>
                    </div>
                </a>
            </div>

            <!-- Architectural Safeguards Bar -->
            <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 text-xs text-slate-400 flex flex-wrap items-center justify-around gap-4 font-mono text-[11px]">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Strict No-Credit Policy (Non-Bypassable)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>Immutable General Ledger &amp; Triggers</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                    <span>Indian GST Statutory Compliance</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                    <span>Tally Prime XML Outbox Bridge</span>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950/60 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span>© {{ date('Y') }} {{ config('club.name', 'Malabar Club') }}. {{ config('club.address', 'Vadakkencherry, Palakkad District, Kerala') }}. All rights reserved.</span>
            <span class="font-mono text-[11px]">System Status: Online ({{ config('club.timezone', 'Asia/Kolkata') }})</span>
        </div>
    </footer>
</body>
</html>