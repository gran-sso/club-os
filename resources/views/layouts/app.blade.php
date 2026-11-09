<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100 text-slate-800">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Counter POS' }} — Malabar Club</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#d97706">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Malabar Club">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="h-full flex flex-col bg-slate-100 text-slate-800 antialiased selection:bg-amber-500 selection:text-white">
    <!-- Top Bar -->
    <header class="border-b border-slate-200/90 bg-white/95 backdrop-blur-md sticky top-0 z-40 shadow-xs print:hidden no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 flex items-center justify-center font-bold text-slate-950 shadow-md shadow-amber-500/20 group-hover:scale-105 transition-transform">
                        {{ config('club.short_name', 'MC') }}
                    </div>
                    <div>
                        <div class="font-bold text-lg tracking-tight text-slate-900 flex items-center gap-2">
                            <span>{{ config('club.name', 'Malabar Club') }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-medium">{{ config('club.location', 'Vadakkencherry') }}</span>
                        </div>
                        <p class="text-xs text-slate-500">Staff Counter & Terminal Management</p>
                    </div>
                </a>
            </div>

            <!-- Nav Links -->
            <nav class="flex items-center space-x-2.5">
                <a href="{{ route('pos') }}" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('pos') ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                    ⚡ Counter POS
                </a>
                @if (auth('web')->user()?->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))
                    <a href="{{ url('/admin/room-rack') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all hidden md:inline-flex items-center gap-1.5">
                        🏨 Room Rack
                    </a>
                    <a href="{{ url('/admin/court-schedule') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all hidden lg:inline-flex items-center gap-1.5">
                        🏸 Court Schedule
                    </a>
                    @if (auth('web')->user()?->hasAnyRole(['admin', 'manager', 'accountant', 'auditor', 'Administrator', 'Manager', 'Accountant', 'Auditor']))
                        <a href="{{ url('/admin') }}" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all flex items-center gap-1.5">
                            🏛️ Club Admin
                        </a>
                    @endif
                @endif

                @auth('web')
                    <div class="h-5 w-px bg-slate-200 mx-1"></div>
                    <div class="flex items-center space-x-3 pl-2">
                        <div class="text-right hidden sm:block">
                            <div class="text-xs font-semibold text-slate-900">{{ auth('web')->user()->name }}</div>
                            <div class="text-[10px] text-amber-700 font-medium capitalize">{{ auth('web')->user()->roles?->first()?->name ?? 'Staff' }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Sign Out">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @endauth
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col">
        {{ $slot }}
    </main>

    @livewireScripts

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch((err) => {
                    console.warn('PWA service worker registration skipped:', err);
                });
            });
        }
    </script>
</body>
</html>
