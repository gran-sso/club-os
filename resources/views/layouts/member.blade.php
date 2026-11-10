<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Member Portal — Malabar Club' }}</title>

    <!-- PWA & Mobile Meta Tags -->
    <meta name="theme-color" content="#020617">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Malabar Club">
    <meta name="application-name" content="Malabar Club">
    <meta name="mobile-web-app-capable" content="yes">

    <link rel="manifest" href="/manifest-member.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">
    <link rel="icon" type="image/svg+xml" href="/icons/icon-192.svg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-slate-950 text-slate-100 flex flex-col font-sans"
    x-data="{
        deferredPrompt: null,
        showInstallBanner: false,
        isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
        isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
        online: navigator.onLine,
        init() {
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                if (!this.isStandalone) {
                    this.showInstallBanner = true;
                }
            });
            window.addEventListener('appinstalled', () => {
                this.showInstallBanner = false;
                this.deferredPrompt = null;
            });
            window.addEventListener('online', () => this.online = true);
            window.addEventListener('offline', () => this.online = false);
        },
        installApp() {
            if (this.deferredPrompt) {
                this.deferredPrompt.prompt();
                this.deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        this.showInstallBanner = false;
                    }
                    this.deferredPrompt = null;
                });
            }
        }
    }">

    <!-- Offline Alert Strip -->
    <div x-cloak x-show="!online" class="bg-rose-900/90 text-rose-100 text-xs font-semibold py-2 px-4 text-center border-b border-rose-700/50 sticky top-0 z-50 print:hidden no-print">
        ⚠️ You are currently offline. Displayed balances reflect the latest synchronized audit snapshot.
    </div>

    <!-- Executive Top Header -->
    <header class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800/80 sticky top-0 z-40 print:hidden no-print">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <!-- Brand Crest -->
            <a href="{{ route('member.dashboard') }}" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 p-0.5 shadow-md shadow-amber-500/10 group-hover:scale-105 transition-transform shrink-0">
                    <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center font-serif text-amber-400 font-extrabold text-sm tracking-wider">
                        {{ config('club.short_name', 'MC') }}
                    </div>
                </div>
                <div>
                    <h1 class="text-sm font-extrabold tracking-tight text-white group-hover:text-amber-400 transition-colors">
                        {{ config('club.name', 'Malabar Club') }}
                    </h1>
                    <p class="text-[10px] text-slate-400 font-medium tracking-wide uppercase">
                        Member Portal &bull; {{ config('club.location', 'Vadakkencherry') }}
                    </p>
                </div>
            </a>

            <!-- Member Action & Logout -->
            @if(auth('member')->check())
                @php
                    $currentMember = auth('member')->user();
                    $headerUnreadNotices = app(\App\Domain\Communication\Services\NoticeService::class)->getUnreadCount($currentMember);
                @endphp
                <div class="flex items-center gap-2.5">
                    <!-- Notifications Bell Quick Link -->
                    <a href="{{ route('member.dashboard', ['tab' => 'notices']) }}"
                        class="relative p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/70 transition shadow-2xs group"
                        title="Club Notices & Alerts">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @if($headerUnreadNotices > 0)
                            <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-extrabold text-white ring-2 ring-slate-900 animate-pulse">
                                {{ $headerUnreadNotices > 9 ? '9+' : $headerUnreadNotices }}
                            </span>
                        @endif
                    </a>

                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-bold text-white leading-tight truncate max-w-[150px]">
                            {{ $currentMember->name }}
                        </div>
                        <div class="text-[10px] font-mono font-bold text-amber-400">
                            {{ $currentMember->membership_number }}
                        </div>
                    </div>

                    <form method="POST" action="{{ route('member.logout') }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-rose-950/40 text-slate-300 hover:text-rose-400 border border-slate-700/70 hover:border-rose-800 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                            title="Sign Out of Portal">
                            <svg width="14" height="14" class="w-3.5 h-3.5" style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span class="hidden sm:inline">Sign Out</span>
                        </button>
                    </form>
                </div>
            @else
                <a href="{{ route('member.login') }}" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-xs transition cursor-pointer">
                    Member Login
                </a>
            @endif
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {{ $slot }}
    </main>

    <!-- PWA Install Banner Prompt -->
    <div x-cloak x-show="showInstallBanner && !isStandalone"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-8"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-8"
        class="fixed bottom-4 inset-x-4 max-w-md mx-auto z-50 bg-gradient-to-r from-slate-900 to-slate-950 border border-amber-500/40 rounded-2xl p-4 shadow-2xl shadow-amber-500/10 flex items-center justify-between gap-3 print:hidden no-print">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 font-bold text-lg shrink-0">
                📲
            </div>
            <div>
                <h4 class="text-xs font-bold text-white tracking-tight">Install Malabar Club App</h4>
                <p class="text-[11px] text-slate-400 leading-tight">Instant access to digital card &amp; wallet on home screen.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button @click="showInstallBanner = false" type="button" class="text-slate-400 hover:text-slate-200 text-xs px-2 py-1.5 cursor-pointer">Later</button>
            <button @click="installApp()" type="button" class="px-3.5 py-1.5 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-xs cursor-pointer shrink-0">
                Install
            </button>
        </div>
    </div>

    <!-- Executive Footer -->
    <footer class="mt-auto border-t border-slate-900 py-6 text-center text-xs text-slate-500 print:hidden no-print">
        <div class="max-w-4xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px]">
            <span>&copy; {{ date('Y') }} {{ config('club.name', 'Malabar Club') }}, {{ config('club.location', 'Vadakkencherry, Kerala') }}. All rights reserved.</span>
            <span class="text-slate-600 font-mono">Server-Authoritative Ledger System</span>
        </div>
    </footer>

    @livewireScripts
</body>
</html>

