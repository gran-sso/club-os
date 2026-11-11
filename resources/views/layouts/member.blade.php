<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950 print:h-auto print:bg-white print:text-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512.png">
    <link rel="icon" type="image/svg+xml" href="/icons/icon-192.svg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $isAuthenticatedMember = auth('member')->check();
@endphp
<body id="member-app-shell"
    class="{{ $isAuthenticatedMember ? 'h-dvh overflow-hidden md:h-auto md:min-h-full md:overflow-visible' : 'min-h-dvh' }} bg-slate-950 text-slate-100 flex flex-col font-sans print:h-auto print:overflow-visible print:bg-white print:text-slate-900"
    x-data="{
        deferredPrompt: null,
        supportsWebInstall: typeof Navigator !== 'undefined' && typeof Navigator.prototype.install === 'function',
        canInstall: false,
        showInstallBanner: false,
        installing: false,
        isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
        online: navigator.onLine,
        dismissKey: 'malabar_member_pwa_dismissed_at',
        isBannerDismissedRecently() {
            try {
                const raw = localStorage.getItem(this.dismissKey);
                if (!raw) return false;
                return (Date.now() - Number(raw)) < 7 * 24 * 60 * 60 * 1000;
            } catch (_) {
                return false;
            }
        },
        init() {
            if (!this.isStandalone && this.supportsWebInstall) {
                this.canInstall = true;
                this.showInstallBanner = !this.isBannerDismissedRecently();
            }
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                if (!this.isStandalone) {
                    this.canInstall = true;
                    if (!this.isBannerDismissedRecently()) {
                        this.showInstallBanner = true;
                    }
                }
            });
            window.addEventListener('appinstalled', () => {
                this.showInstallBanner = false;
                this.canInstall = false;
                this.deferredPrompt = null;
            });
            window.addEventListener('online', () => this.online = true);
            window.addEventListener('offline', () => this.online = false);
        },
        dismissInstallBanner() {
            this.showInstallBanner = false;
            try {
                localStorage.setItem(this.dismissKey, String(Date.now()));
            } catch (_) {}
        },
        async installApp() {
            if (this.installing) return;
            this.installing = true;
            try {
                if (this.supportsWebInstall) {
                    await navigator.install();
                    this.showInstallBanner = false;
                    this.canInstall = false;
                } else if (this.deferredPrompt) {
                    const promptEvent = this.deferredPrompt;
                    this.deferredPrompt = null;
                    await promptEvent.prompt();
                    const { outcome } = await promptEvent.userChoice;
                    if (outcome === 'accepted') {
                        this.showInstallBanner = false;
                        this.canInstall = false;
                    } else if (!this.deferredPrompt) {
                        this.canInstall = false;
                        this.showInstallBanner = false;
                    }
                }
            } catch (error) {
                if (error && error.name !== 'AbortError') {
                    console.warn('Member Portal installation prompt aborted or failed:', error);
                }
            } finally {
                this.installing = false;
            }
        }
    }">

    <!-- Offline Alert Strip (Non-overlapping top flex row) -->
    <div x-cloak x-show="!online" role="status" aria-live="polite"
        class="shrink-0 bg-rose-900/95 text-rose-100 text-[11px] font-semibold py-1.5 px-4 text-center border-b border-rose-700/60 print:hidden no-print">
        ⚠️ Offline mode &bull; Balances show the last synchronized server snapshot.
    </div>

    <!-- Compact Native App Header -->
    <header class="shrink-0 bg-slate-900/90 backdrop-blur-md border-b border-slate-800/80 md:sticky md:top-0 z-40 print:hidden no-print"
        style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="max-w-4xl mx-auto px-3.5 sm:px-6 h-13 sm:h-15 flex items-center justify-between gap-2">
            <!-- Brand Crest -->
            <a href="{{ route('member.dashboard') }}" class="flex items-center gap-2.5 sm:gap-3 group min-w-0">
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 p-0.5 shadow-md shadow-amber-500/10 group-hover:scale-105 transition-transform shrink-0">
                    <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center font-serif text-amber-400 font-extrabold text-xs sm:text-sm tracking-wider">
                        {{ config('club.short_name', 'MC') }}
                    </div>
                </div>
                <div class="min-w-0">
                    <h1 class="text-sm font-extrabold tracking-tight text-white group-hover:text-amber-400 transition-colors leading-tight truncate">
                        {{ config('club.name', 'Malabar Club') }}
                    </h1>
                    <p class="text-[10px] text-slate-400 font-medium tracking-wide uppercase truncate">
                        Member Portal <span class="hidden sm:inline">&bull; {{ config('club.location', 'Vadakkencherry') }}</span>
                    </p>
                </div>
            </a>

            <!-- Member Action & Logout -->
            @if($isAuthenticatedMember)
                @php
                    $currentMember = auth('member')->user();
                    $headerUnreadNotices = app(\App\Domain\Communication\Services\NoticeService::class)->getUnreadCount($currentMember);
                @endphp
                <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
                    <!-- Notifications Bell Quick Link -->
                    <a href="{{ route('member.dashboard', ['tab' => 'notices']) }}"
                        aria-label="Club Notices & Alerts"
                        class="relative min-w-11 min-h-11 sm:min-w-9 sm:min-h-9 rounded-xl bg-slate-800/90 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700/70 transition shadow-2xs inline-flex items-center justify-center active:scale-95"
                        title="Club Notices & Alerts">
                        <svg aria-hidden="true" class="w-4.5 h-4.5 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @if($headerUnreadNotices > 0)
                            <span class="absolute top-1 right-1 sm:-top-1 sm:-right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-extrabold text-white ring-2 ring-slate-900">
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

                    <!-- Desktop Sign Out (Mobile Sign Out is in the More bottom sheet) -->
                    <form method="POST" action="{{ route('member.logout') }}" class="hidden md:inline">
                        @csrf
                        <button type="submit"
                            class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-rose-950/40 text-slate-300 hover:text-rose-400 border border-slate-700/70 hover:border-rose-800 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                            title="Sign Out of Portal">
                            <svg aria-hidden="true" width="14" height="14" class="w-3.5 h-3.5" style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span>Sign Out</span>
                        </button>
                    </form>
                </div>
            @else
                <a href="{{ route('member.login') }}" class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-xs transition cursor-pointer">
                    Member Login
                </a>
            @endif
        </div>
    </header>

    <!-- Scrollable App Viewport Container (Mobile locks inside dvh above bottom dock; Desktop flows naturally) -->
    <main id="member-main"
        class="{{ $isAuthenticatedMember ? 'flex-1 min-h-0 overflow-y-auto overscroll-y-contain md:overflow-visible max-w-4xl w-full mx-auto px-3.5 sm:px-6 pt-3 pb-[calc(5.75rem+env(safe-area-inset-bottom,0px))] md:py-8' : 'flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8' }} print:p-0 print:max-w-none print:h-auto print:overflow-visible print:bg-white print:text-slate-900">
        {{ $slot }}
    </main>

    <!-- PWA Install Banner Prompt (Clears bottom navigation dock on mobile) -->
    <div x-cloak x-show="showInstallBanner && canInstall && !isStandalone"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-8"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-8"
        class="fixed {{ $isAuthenticatedMember ? 'bottom-[calc(4.75rem+env(safe-area-inset-bottom,0px))] md:bottom-4' : 'bottom-4' }} inset-x-3.5 sm:inset-x-4 max-w-md mx-auto z-50 bg-slate-900/95 backdrop-blur-md border border-amber-500/40 rounded-2xl p-3.5 shadow-2xl shadow-amber-500/10 flex items-center justify-between gap-3 print:hidden no-print">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 font-bold text-lg shrink-0" aria-hidden="true">
                📲
            </div>
            <div class="min-w-0">
                <h4 class="text-xs font-bold text-white tracking-tight truncate">Install Malabar Club App</h4>
                <p class="text-[11px] text-slate-400 leading-tight">Instant access to digital card &amp; wallet.</p>
            </div>
        </div>
        <div class="flex items-center gap-1.5 shrink-0">
            <button @click="dismissInstallBanner()" type="button" class="text-slate-400 hover:text-slate-200 text-xs px-2.5 py-2 rounded-lg cursor-pointer">Later</button>
            <button @click="installApp()" :aria-disabled="installing ? 'true' : null" type="button" class="px-3.5 py-2 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-xs cursor-pointer shrink-0 active:scale-95 transition-transform">
                Install
            </button>
        </div>
    </div>

    <!-- Desktop Executive Footer (Hidden on authenticated mobile viewports; surfaced in More sheet) -->
    <footer class="{{ $isAuthenticatedMember ? 'hidden md:block ' : '' }}mt-auto border-t border-slate-900 py-6 text-center text-xs text-slate-500 print:hidden no-print">
        <div class="max-w-4xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px]">
            <span>&copy; {{ date('Y') }} {{ config('club.name', 'Malabar Club') }}, {{ config('club.location', 'Vadakkencherry, Kerala') }}. All rights reserved.</span>
            <span class="text-slate-600 font-mono">Server-Authoritative Ledger System</span>
        </div>
    </footer>

    @livewireScripts

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((err) => {
                    console.warn('Member Portal PWA service worker registration skipped:', err);
                });
            });
        }
    </script>
</body>
</html>
