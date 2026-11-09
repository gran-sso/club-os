<!-- ======================================================================= -->
<!-- MALABAR CLUB MOBILE & TABLET NATIVE UX ENHANCEMENTS                      -->
<!-- ======================================================================= -->

<div class="print:hidden no-print" x-data="{
    online: navigator.onLine,
    showNetworkToast: false,
    networkToastMsg: '',
    networkToastType: 'offline',

    // Add to Home Screen (A2HS) Prompt State
    isMobile: /iPhone|iPad|iPod|Android/i.test(navigator.userAgent),
    isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
    pwaDismissed: localStorage.getItem('malabar_admin_pwa_dismissed') === '1',
    deferredPrompt: null,
    isIos: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
    showIosInstructions: false,

    init() {
        window.addEventListener('offline', () => {
            this.online = false;
            this.networkToastMsg = '⚠️ Offline Mode — Reconnecting to club server...';
            this.networkToastType = 'offline';
            this.showNetworkToast = true;
        });

        window.addEventListener('online', () => {
            this.online = true;
            this.networkToastMsg = '✓ Reconnected to Malabar Club Server';
            this.networkToastType = 'online';
            this.showNetworkToast = true;
            setTimeout(() => this.showNetworkToast = false, 3500);
        });

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
        });
    },

    promptInstall() {
        if (this.deferredPrompt) {
            this.deferredPrompt.prompt();
            this.deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    this.dismissPwaBanner();
                }
                this.deferredPrompt = null;
            });
        } else if (this.isIos) {
            this.showIosInstructions = !this.showIosInstructions;
        }
    },

    dismissPwaBanner() {
        this.pwaDismissed = true;
        localStorage.setItem('malabar_admin_pwa_dismissed', '1');
    }
}" class="relative">

    <!-- 1. Live Network Connectivity Toast -->
    <div x-show="showNetworkToast"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-y-12 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="-translate-y-12 opacity-0"
        style="display: none;"
        class="fixed top-4 inset-x-4 mx-auto z-50 max-w-sm px-4 py-2.5 rounded-full text-xs font-bold text-center shadow-2xl flex items-center justify-center gap-2"
        :class="networkToastType === 'offline' ? 'bg-rose-600 text-white shadow-rose-950/50' : 'bg-emerald-600 text-white shadow-emerald-950/50'">
        <span x-text="networkToastMsg"></span>
    </div>

    <!-- 2. Mobile Install / Add to Home Screen Banner (Mobile Only, not installed yet) -->
    <div x-show="isMobile && !isStandalone && !pwaDismissed"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-10 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        style="display: none;"
        class="fixed bottom-20 inset-x-3 z-40 max-w-md mx-auto bg-slate-900/95 text-white border border-slate-700/80 rounded-2xl p-3.5 shadow-2xl backdrop-blur-md lg:hidden">
        
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-white">Install Malabar Admin</h4>
                    <p class="text-[10px] text-slate-300">Run as full-screen app on this device</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                    @click="promptInstall()"
                    class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-[11px] rounded-lg transition-transform active:scale-95 shadow-sm">
                    Install
                </button>
                <button type="button"
                    @click="dismissPwaBanner()"
                    class="p-1 text-slate-400 hover:text-white rounded-md text-xs">
                    ✕
                </button>
            </div>
        </div>

        <!-- iOS Safari Instructions -->
        <div x-show="showIosInstructions" x-collapse class="mt-3 pt-3 border-t border-slate-800 text-[11px] text-slate-300 space-y-1">
            <p class="font-semibold text-amber-400">To install on iPhone / iPad:</p>
            <ol class="list-decimal list-inside space-y-1 text-slate-300">
                <li>Tap the <strong class="text-white">Share</strong> button <span class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 font-mono text-[10px]">⎋</span> at the bottom of Safari.</li>
                <li>Scroll down and tap <strong class="text-white">'Add to Home Screen'</strong> <span class="px-1 py-0.5 rounded bg-slate-800 border border-slate-700 font-mono text-[10px]">⊞</span>.</li>
                <li>Tap <strong class="text-white">'Add'</strong> in the top right corner.</li>
            </ol>
        </div>
    </div>

    <!-- 3. Native Mobile Bottom Navigation Dock (Shown on screens < 1024px) -->
    <nav aria-label="Mobile Navigation Dock"
        class="fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-md border-t border-slate-800/90 text-slate-400 lg:hidden shadow-2xl transition-all select-none"
        style="padding-bottom: env(safe-area-inset-bottom, 0px);">
        
        <div class="grid grid-cols-5 h-16 items-center px-1">
            
            <!-- 1. Executive Dashboard -->
            <a href="/admin"
                class="flex flex-col items-center justify-center py-1 transition-colors group {{ request()->is('admin') && !request()->is('admin/*') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                <div class="relative p-1 rounded-xl transition-all group-active:scale-90 {{ request()->is('admin') && !request()->is('admin/*') ? 'bg-amber-500/10 text-amber-400' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <span class="text-[10px] tracking-tight mt-0.5">Dashboard</span>
            </a>

            <!-- 2. Visual Room Rack -->
            <a href="/admin/room-rack"
                class="flex flex-col items-center justify-center py-1 transition-colors group {{ request()->is('admin/room-rack*') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                <div class="relative p-1 rounded-xl transition-all group-active:scale-90 {{ request()->is('admin/room-rack*') ? 'bg-amber-500/10 text-amber-400' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <span class="text-[10px] tracking-tight mt-0.5">Rooms</span>
            </a>

            <!-- 3. Badminton Court Schedule -->
            <a href="/admin/court-schedule"
                class="flex flex-col items-center justify-center py-1 transition-colors group {{ request()->is('admin/court-schedule*') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                <div class="relative p-1 rounded-xl transition-all group-active:scale-90 {{ request()->is('admin/court-schedule*') ? 'bg-amber-500/10 text-amber-400' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-[10px] tracking-tight mt-0.5">Badminton</span>
            </a>

            <!-- 4. Counter POS -->
            <a href="/pos"
                class="flex flex-col items-center justify-center py-1 transition-colors group {{ request()->is('pos*') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                <div class="relative p-1 rounded-xl transition-all group-active:scale-90 {{ request()->is('pos*') ? 'bg-amber-500/10 text-amber-400' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-[10px] tracking-tight mt-0.5">POS</span>
            </a>

            <!-- 5. Filament Navigation Menu Drawer -->
            <button type="button"
                @click="$store.sidebar ? ($store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()) : null"
                class="flex flex-col items-center justify-center py-1 transition-colors group text-slate-400 hover:text-slate-200">
                <div class="relative p-1 rounded-xl transition-all group-active:scale-90">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </div>
                <span class="text-[10px] tracking-tight mt-0.5">Menu</span>
            </button>
        </div>
    </nav>
</div>

