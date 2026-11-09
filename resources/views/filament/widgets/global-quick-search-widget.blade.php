<x-filament-widgets::widget>
    <div
        x-data="{
            query: '',
            focusGlobalSearch(preset = '') {
                const globalInput = document.querySelector('.fi-global-search input') || document.querySelector('input[type=\'search\']');
                if (globalInput) {
                    if (preset) {
                        globalInput.value = preset;
                        globalInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    globalInput.focus();
                    globalInput.click();
                }
            },
            handleInput(e) {
                const val = e.target.value;
                const globalInput = document.querySelector('.fi-global-search input') || document.querySelector('input[type=\'search\']');
                if (globalInput) {
                    globalInput.value = val;
                    globalInput.dispatchEvent(new Event('input', { bubbles: true }));
                    globalInput.focus();
                }
            }
        }"
        class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-amber-950 text-white border border-amber-500/20 rounded-2xl p-6 shadow-lg mb-2"
    >
        <!-- Background Ambient Glow -->
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-4">
            <!-- Header Title & Shortcut Badge -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/30 text-amber-400 flex items-center justify-center font-bold text-xl shadow-inner">
                        🔍
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white tracking-tight flex items-center gap-2">
                            <span>Club-Wide Global Search &amp; Command Hub</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                20 Sections Active
                            </span>
                        </h2>
                        <p class="text-xs text-slate-300">
                            Search members, RFID cards, invoices, room reservations, badminton slots, inventory, vouchers, and reports instantly.
                        </p>
                    </div>
                </div>

                <!-- Keyboard Shortcut Helper -->
                <div class="flex items-center gap-1.5 self-start sm:self-auto text-xs text-slate-400 bg-slate-800/80 border border-slate-700/80 px-2.5 py-1.5 rounded-lg shadow-xs">
                    <span class="text-[11px]">Shortcut:</span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-900 text-amber-300 font-mono text-[10px] border border-slate-700 shadow-xs">⌘K</kbd>
                    <span class="text-slate-500">or</span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-900 text-amber-300 font-mono text-[10px] border border-slate-700 shadow-xs">Ctrl+K</kbd>
                </div>
            </div>

            <!-- Interactive Search Input Bar -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input
                    type="text"
                    x-model="query"
                    x-on:focus="focusGlobalSearch(query)"
                    x-on:input="handleInput($event)"
                    placeholder="Search across all 18 sections: type member name, phone, bill #, room 101, badminton, or 'reports'..."
                    class="w-full pl-11 pr-28 py-3.5 bg-slate-950/70 hover:bg-slate-950/90 focus:bg-slate-950 text-white placeholder-slate-400 text-sm rounded-xl border border-slate-700 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all outline-hidden shadow-inner"
                />
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <button
                        type="button"
                        x-on:click="focusGlobalSearch(query)"
                        class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-lg transition-colors shadow-xs flex items-center gap-1.5"
                    >
                        <span>Search</span>
                        <span>&rarr;</span>
                    </button>
                </div>
            </div>

            <!-- Quick-Jump Action Buttons & Coverage Pills -->
            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-800/80">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mr-1">Quick Jump:</span>

                @unless(auth()->user()?->hasRole('admin'))
                <a
                    href="{{ route('pos') }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/20 transition-colors"
                >
                    <span>⚡</span>
                    <span>Counter POS</span>
                </a>
                @endunless

                <a
                    href="{{ url('/admin/room-rack') }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-500/10 hover:bg-blue-500/20 text-blue-300 border border-blue-500/20 transition-colors"
                >
                    <span>🏨</span>
                    <span>Room Rack</span>
                </a>

                <a
                    href="{{ url('/admin/court-schedule') }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/20 transition-colors"
                >
                    <span>🏸</span>
                    <span>Court Schedule</span>
                </a>

                <a
                    href="{{ url('/admin/financial-reports') }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/20 transition-colors"
                >
                    <span>📊</span>
                    <span>Executive Reports</span>
                </a>

                <a
                    href="{{ url('/admin/bank-accounts') }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/20 transition-colors"
                >
                    <span>🏦</span>
                    <span>Bank Passbook</span>
                </a>

                <a
                    href="{{ url('/admin/system-settings') }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-700/50 hover:bg-slate-700 text-slate-300 border border-slate-600 transition-colors"
                >
                    <span>⚙️</span>
                    <span>Club Settings</span>
                </a>

                @if(auth()->user()?->hasRole('admin'))
                    <a
                        href="{{ url('/admin/users') }}"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/20 transition-colors"
                    >
                        <span>🛡️</span>
                        <span>Staff Access</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-filament-widgets::widget>

