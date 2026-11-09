<x-filament-panels::page>
    <div class="space-y-6">

        <!-- ========================================================================= -->
        <!-- 1. CLUBHOUSE EXECUTIVE HEADER & SEARCH BAR (LIGHT THEME)                  -->
        <!-- ========================================================================= -->
        <div class="p-5 sm:p-6 bg-white border border-slate-200/90 rounded-2xl shadow-xs print:hidden no-print">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                
                <!-- Left: Title Hierarchy & System Badge -->
                <div class="flex items-start sm:items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center font-black text-slate-950 text-xl shadow-xs shrink-0 mt-0.5 sm:mt-0">
                        <svg class="w-6 h-6 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">Malabar Club Operations Help &amp; Manual</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200 shrink-0">
                                Official Standard SOP
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Standard operating procedures, keyboard shortcuts, accounting standards, and operational guidelines.
                        </p>
                    </div>
                </div>

                <!-- Right: Action Buttons Group -->
                <div class="flex items-center gap-2.5 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                    <button
                        type="button"
                        onclick="window.print()"
                        class="h-10 px-4 inline-flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-xs transition-all active:scale-98 cursor-pointer shrink-0"
                    >
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print Desk Reference</span>
                    </button>
                </div>
            </div>

            <!-- Integrated Live Search Bar -->
            <div class="mt-5 pt-4 border-t border-slate-100">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-amber-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce.200ms="searchQuery"
                        placeholder="Search operations help: type 'shortcuts', 'F8', 'recharge', 'accommodation deck', 'settlement', 'kseb', 'zero credit'..."
                        class="w-full pl-10 pr-10 py-2.5 bg-slate-50 hover:bg-white focus:bg-white text-slate-900 placeholder:text-slate-400 text-xs sm:text-sm rounded-xl border border-slate-300 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all outline-hidden shadow-2xs font-medium"
                    />
                    @if($searchQuery)
                        <button
                            type="button"
                            wire:click="clearSearch"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 text-xs cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    @endif
                </div>

                <!-- Quick Topic Suggestions -->
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-1">Quick Lookups:</span>
                    
                    <button wire:click="jumpTo('cashier', 'shortcuts')" type="button"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                        ⚡ F2–F10 Shortcuts
                    </button>
                    <button wire:click="jumpTo('cashier', 'recharge')" type="button"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                        💳 Wallet Top-Up
                    </button>
                    <button wire:click="jumpTo('reception', 'accommodation deck')" type="button"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                        🏨 Accommodation Deck
                    </button>
                    <button wire:click="jumpTo('finance', 'coa')" type="button"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                        ⚖️ 5-Digit COA &amp; Expense Heads
                    </button>
                    <button wire:click="jumpTo('finance', 'settlement')" type="button"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                        🍲 Food Contractor
                    </button>
                    <button wire:click="jumpTo('bylaws', 'zero credit')" type="button"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                        🛡️ Zero-Credit Mandate
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 2. SEARCH RESULTS VIEW (SHOWN WHEN SEARCH QUERY IS PRESENT)                -->
        <!-- ========================================================================= -->
        @if(trim($searchQuery) !== '')
            <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4 print:hidden no-print">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-bold text-slate-900">Search Results for:</span>
                        <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 font-bold text-xs">
                            "{{ $searchQuery }}"
                        </span>
                        <span class="text-xs text-slate-500">({{ count($this->filteredArticles) }} matches found)</span>
                    </div>

                    <button
                        type="button"
                        wire:click="clearSearch"
                        class="text-xs font-bold text-amber-700 hover:text-amber-800 transition cursor-pointer"
                    >
                        Clear Search &amp; Return to Tabs &rarr;
                    </button>
                </div>

                @if(count($this->filteredArticles) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($this->filteredArticles as $article)
                            <div class="p-4 bg-slate-50 hover:bg-amber-50/40 border border-slate-200 hover:border-amber-300 rounded-xl transition-all space-y-2 flex flex-col justify-between">
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-200/70 text-slate-800">
                                            {{ $article['tab_label'] }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="jumpTo('{{ $article['tab'] }}')"
                                            class="text-[11px] font-bold text-amber-700 hover:text-amber-800 cursor-pointer flex items-center gap-1"
                                        >
                                            <span>Open Section</span>
                                            <span>&rarr;</span>
                                        </button>
                                    </div>
                                    <h3 class="font-bold text-slate-900 text-sm">
                                        {{ $article['title'] }}
                                    </h3>
                                    <p class="text-xs text-slate-600 leading-relaxed">
                                        {{ $article['summary'] }}
                                    </p>
                                </div>

                                <div class="pt-2 border-t border-slate-200/60 flex flex-wrap gap-1">
                                    @foreach($article['tags'] as $tag)
                                        <span class="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-[10px] font-mono text-slate-600">
                                            #{{ $tag }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-10 text-center space-y-3">
                        <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xl font-bold">
                            🔍
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm">No exact matches found</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">
                            No operational articles matched "{{ $searchQuery }}". Try searching for keywords like "shortcuts", "f8", "prepaid", "deck", "kseb", "settlement", or "rules".
                        </p>
                        <button
                            type="button"
                            wire:click="clearSearch"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-xs transition cursor-pointer"
                        >
                            Reset &amp; View All Sections
                        </button>
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- 3. ROLE-TAILORED SEGMENTED TAB NAVIGATION                                 -->
        <!-- ========================================================================= -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-2 shadow-xs print:hidden no-print overflow-x-auto">
            <div class="flex items-center gap-1.5 min-w-max">
                <button
                    type="button"
                    wire:click="setTab('cashier')"
                    class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'cashier' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Counter &amp; POS</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('reception')"
                    class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'reception' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span>Front Desk &amp; Facilities</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('finance')"
                    class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'finance' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                    <span>Finance &amp; Accounts</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('inventory')"
                    class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'inventory' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span>Procurement &amp; Stores</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('executive')"
                    class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'executive' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span>Executive &amp; Admin</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('bylaws')"
                    class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'bylaws' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Club Bylaws</span>
                </button>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 1: COUNTER CASHIER & POS OPERATIONS GUIDE                             -->
        <!-- ========================================================================= -->
        @if($activeTab === 'cashier')
            <div class="space-y-6">
                <!-- Shift Lifecycle Card -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            ⏱️
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. Cashier Shift Lifecycle (Daily Opening &amp; Closing)</h2>
                            <p class="text-xs text-slate-500">Every counter transaction is audited under an active, verified cashier shift.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                        <div class="p-4 bg-slate-50 border border-slate-200/90 rounded-xl space-y-1.5 flex flex-col justify-between">
                            <div>
                                <span class="font-bold text-amber-700 font-mono text-[11px] uppercase tracking-wider">Step 01</span>
                                <h3 class="font-bold text-slate-900 text-sm mt-0.5">Open Shift</h3>
                                <p class="text-slate-600 leading-relaxed mt-1">
                                    Enter your physical <strong>Opening Cash Float</strong> (e.g. ₹2,000.00). Press <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-300 font-mono text-[10px] shadow-2xs font-bold text-slate-800">↵ Enter</kbd> to unlock the billing workspace.
                                </p>
                            </div>
                            <div class="pt-2 text-[11px] font-semibold text-slate-500 border-t border-slate-200/60 mt-2">
                                Drawer status: Locked &rarr; Active
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200/90 rounded-xl space-y-1.5 flex flex-col justify-between">
                            <div>
                                <span class="font-bold text-blue-700 font-mono text-[11px] uppercase tracking-wider">Step 02</span>
                                <h3 class="font-bold text-slate-900 text-sm mt-0.5">High-Speed Billing</h3>
                                <p class="text-slate-600 leading-relaxed mt-1">
                                    Use keyboard shortcuts to scan items, select serving sizes, and tender bills via <strong>Prepaid Card (F8)</strong>, <strong>Cash (F9)</strong>, or <strong>Digital UPI (F10)</strong>.
                                </p>
                            </div>
                            <div class="pt-2 text-[11px] font-semibold text-slate-500 border-t border-slate-200/60 mt-2">
                                Zero-credit policy strictly enforced
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200/90 rounded-xl space-y-1.5 flex flex-col justify-between">
                            <div>
                                <span class="font-bold text-emerald-700 font-mono text-[11px] uppercase tracking-wider">Step 03</span>
                                <h3 class="font-bold text-slate-900 text-sm mt-0.5">Monitor Drawer</h3>
                                <p class="text-slate-600 leading-relaxed mt-1">
                                    The docked bottom status bar computes real-time <strong>Expected Cash</strong>: <span class="font-mono font-semibold text-slate-900">Float + Cash Sales + Cash Recharges</span>. Prepaid redemptions do not affect drawer cash.
                                </p>
                            </div>
                            <div class="pt-2 text-[11px] font-semibold text-slate-500 border-t border-slate-200/60 mt-2">
                                Real-time ledger synchronization
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200/90 rounded-xl space-y-1.5 flex flex-col justify-between">
                            <div>
                                <span class="font-bold text-rose-700 font-mono text-[11px] uppercase tracking-wider">Step 04</span>
                                <h3 class="font-bold text-slate-900 text-sm mt-0.5">Count &amp; Close Shift</h3>
                                <p class="text-slate-600 leading-relaxed mt-1">
                                    Click <strong>Close Shift</strong> in the bottom bar. Physically count and enter your <strong>Closing Cash</strong>. Any drawer discrepancy is automatically audited for management review.
                                </p>
                            </div>
                            <div class="pt-2 text-[11px] font-semibold text-slate-500 border-t border-slate-200/60 mt-2">
                                Variance journal auto-logged
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Expected Cash Formula Banner -->
                    <div class="p-4 bg-amber-50/60 border border-amber-200/80 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="flex items-start gap-2.5">
                            <span class="text-amber-700 font-bold text-base mt-0.5">🧮</span>
                            <div>
                                <h4 class="font-bold text-amber-950">Statutory Cash Drawer Formula</h4>
                                <p class="text-amber-900 mt-0.5">
                                    <strong class="font-mono text-slate-900">Expected Till Cash</strong> = <span class="font-mono">Opening Float + Drawer Cash Sales + Cash Wallet Recharges</span>.
                                </p>
                            </div>
                        </div>
                        <span class="text-[11px] text-amber-800 bg-white/80 border border-amber-300 px-3 py-1.5 rounded-lg shrink-0 font-medium">
                            Prepaid redemptions (F8) and UPI (F10) do not add drawer cash.
                        </span>
                    </div>
                </div>

                <!-- Keyboard Shortcut Matrix Card -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                                ⌨️
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-slate-900">2. High-Speed Counter Keyboard Shortcuts</h2>
                                <p class="text-xs text-slate-500">Operate counter billing entirely through physical keyboard hotkeys.</p>
                            </div>
                        </div>
                        <span class="text-[11px] font-mono font-bold text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg">
                            Standard Non-Conflicting Function Keys
                        </span>
                    </div>

                    <!-- Keyboard Layout Organized by Function -->
                    <div class="space-y-4">
                        
                        <!-- Group A: Search & Cart Navigation -->
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Item Lookup &amp; Cart Navigation</span>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-white border border-slate-300 font-mono font-black text-slate-900 text-xs shadow-xs inline-flex items-center justify-center shrink-0">F2</kbd>
                                    <div>
                                        <h4 class="font-bold text-slate-900">Find Member / Tap Card</h4>
                                        <p class="text-slate-600 text-[11px] mt-0.5">Focuses member search. Tap RFID smartcard on reader, or type name/phone.</p>
                                    </div>
                                </div>

                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-white border border-slate-300 font-mono font-black text-slate-900 text-xs shadow-xs inline-flex items-center justify-center shrink-0">F3</kbd>
                                    <div>
                                        <h4 class="font-bold text-slate-900">Item Search / Barcode</h4>
                                        <p class="text-slate-600 text-[11px] mt-0.5">Focuses item search. Scan barcode with handheld scanner or type name.</p>
                                    </div>
                                </div>

                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-white border border-slate-300 font-mono font-black text-slate-900 text-xs shadow-xs inline-flex items-center justify-center shrink-0">F4</kbd>
                                    <div>
                                        <h4 class="font-bold text-slate-900">Cart &amp; Quantity Edit</h4>
                                        <p class="text-slate-600 text-[11px] mt-0.5">Jumps focus to cart table. Use arrow keys <kbd class="px-1 py-0.5 rounded bg-white border border-slate-300 font-mono text-[9px]">↑</kbd> <kbd class="px-1 py-0.5 rounded bg-white border border-slate-300 font-mono text-[9px]">↓</kbd> to change quantity.</p>
                                    </div>
                                </div>

                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-white border border-slate-300 font-mono font-black text-slate-900 text-xs shadow-xs inline-flex items-center justify-center shrink-0">1, 2, 3</kbd>
                                    <div>
                                        <h4 class="font-bold text-slate-900">Peg Size Selector</h4>
                                        <p class="text-slate-600 text-[11px] mt-0.5">Numeric keycaps: <strong>1: 30ml Small</strong>, <strong>2: 60ml Large</strong>, <strong>3: Full Bottle (750ml)</strong>.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group B: Payment & Tender -->
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Tender &amp; Payment (Zero-Credit Enforced)</span>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                                <div class="p-3.5 bg-amber-50/50 border border-amber-300/80 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-amber-500 border border-amber-600 font-mono font-black text-slate-950 text-xs shadow-xs inline-flex items-center justify-center shrink-0">F8</kbd>
                                    <div>
                                        <h4 class="font-bold text-amber-950">Prepaid Wallet Tender</h4>
                                        <p class="text-amber-900 text-[11px] mt-0.5">Debits customer's verified prepaid wallet balance. Enforces atomic database lock and strict zero-credit policy.</p>
                                    </div>
                                </div>

                                <div class="p-3.5 bg-emerald-50/50 border border-emerald-300/80 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-emerald-600 border border-emerald-700 font-mono font-black text-white text-xs shadow-xs inline-flex items-center justify-center shrink-0">F9</kbd>
                                    <div>
                                        <h4 class="font-bold text-emerald-950">Cash Payment Tender</h4>
                                        <p class="text-emerald-900 text-[11px] mt-0.5">Tenders invoice via physical drawer cash. Automatically computes customer change and adds cash to till float.</p>
                                    </div>
                                </div>

                                <div class="p-3.5 bg-blue-50/50 border border-blue-300/80 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-blue-600 border border-blue-700 font-mono font-black text-white text-xs shadow-xs inline-flex items-center justify-center shrink-0">F10</kbd>
                                    <div>
                                        <h4 class="font-bold text-blue-950">Digital UPI / EDC Machine</h4>
                                        <p class="text-blue-900 text-[11px] mt-0.5">Records electronic digital collection via card EDC terminal or dynamic clubhouse UPI QR code.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group C: Dialogs & Navigation -->
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Dialogs &amp; Confirmation</span>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-white border border-slate-300 font-mono font-black text-slate-900 text-xs shadow-xs inline-flex items-center justify-center shrink-0">Esc</kbd>
                                    <div>
                                        <h4 class="font-bold text-slate-900">Dismiss Dialog / Cancel Modal</h4>
                                        <p class="text-slate-600 text-[11px] mt-0.5">Instantly closes any active pop-up, top-up modal, error alert, or receipt preview without submitting.</p>
                                    </div>
                                </div>

                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                                    <kbd class="min-w-[38px] h-8 px-2 rounded-lg bg-white border border-slate-300 font-mono font-black text-slate-900 text-xs shadow-xs inline-flex items-center justify-center shrink-0">↵ Enter</kbd>
                                    <div>
                                        <h4 class="font-bold text-slate-900">Confirm Action / Acknowledge</h4>
                                        <p class="text-slate-600 text-[11px] mt-0.5">Confirms current modal action, confirms cash entry, or acknowledges dialogue box.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Special Workflows Card -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Top-Up Workflow -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                            <span class="text-lg">💳</span>
                            <h3 class="font-bold text-slate-900 text-sm">How to Recharge Member Wallet at Counter</h3>
                        </div>
                        <ol class="list-decimal list-inside text-xs text-slate-600 space-y-2 leading-relaxed">
                            <li>Tap member's RFID card on reader or press <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[10px] font-bold text-slate-800">F2</kbd> and search their name.</li>
                            <li>In the Member Card header, click the <strong>⚡ Quick Top-Up</strong> button.</li>
                            <li>Select quick preset (e.g. ₹500, ₹1,000, ₹2,000) or type the exact recharge amount.</li>
                            <li>Select Payment Mode (<strong>Cash</strong> or <strong>UPI / Card POS</strong>).</li>
                            <li>Click <strong>Confirm Recharge</strong>. Balance updates immediately on the server ledger and prints an official top-up receipt.</li>
                        </ol>
                        <div class="p-3 bg-amber-50 border border-amber-200/80 rounded-xl text-[11px] text-amber-900 mt-2">
                            <strong>Accounting Note:</strong> Recharges increase member prepaid liabilities on Account 2010. They do not count as restaurant food revenue until food is actually ordered.
                        </div>
                    </div>

                    <!-- Guest Entry Slip Workflow -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                            <span class="text-lg">🎟️</span>
                            <h3 class="font-bold text-slate-900 text-sm">How to Issue Guest Day Passes</h3>
                        </div>
                        <ol class="list-decimal list-inside text-xs text-slate-600 space-y-2 leading-relaxed">
                            <li>On the billing screen, switch customer type from Member to <strong>Visiting Guest</strong>.</li>
                            <li>Select the registered Guest (must be sponsored by an active member or affiliated club).</li>
                            <li>Add the applicable Guest Pass tariff: <strong>Bar &amp; Card Room Day Pass</strong> or <strong>Gym/Sports Guest Pass</strong>.</li>
                            <li>Tender the fee via Cash (<kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[10px] font-bold text-slate-800">F9</kbd>) or UPI (<kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[10px] font-bold text-slate-800">F10</kbd>).</li>
                            <li>Issue the printed thermal receipt containing the guest permit barcode for turnstile verification.</li>
                        </ol>
                        <div class="p-3 bg-rose-50 border border-rose-200/80 rounded-xl text-[11px] text-rose-900 mt-2">
                            <strong>Strict Rule:</strong> Unpaid draft entries never generate gate access. Zero-rupee or complimentary guest passes are prohibited by club bylaws.
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 2: FRONT DESK & ACCOMMODATION DECK GUIDE                              -->
        <!-- ========================================================================= -->
        @if($activeTab === 'reception')
            <div class="space-y-6">
                <!-- Accommodation Deck Operations -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            🏨
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. Accommodation Deck Operations (Rooms 101–105)</h2>
                            <p class="text-xs text-slate-500">Live graphical board for front-desk guest room management, bookings, and housekeeping.</p>
                        </div>
                    </div>

                    <!-- Room Status Visual Palette -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 text-xs">
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block shrink-0"></span>
                                <h3 class="font-bold text-slate-900">Vacant Ready</h3>
                            </div>
                            <p class="text-slate-600 text-[11px] leading-relaxed">Room is cleaned, inspected, and ready for immediate walk-in check-in or booking.</p>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-teal-600 inline-block shrink-0"></span>
                                <h3 class="font-bold text-slate-900">Occupied</h3>
                            </div>
                            <p class="text-slate-600 text-[11px] leading-relaxed">Guest is currently in-house. Checkout date and guest folio details are displayed on the card.</p>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-blue-500 inline-block shrink-0"></span>
                                <h3 class="font-bold text-slate-900">Reserved</h3>
                            </div>
                            <p class="text-slate-600 text-[11px] leading-relaxed">Confirmed advance reservation. Fully protected by prepaid deposit interval locking.</p>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-amber-500 inline-block shrink-0"></span>
                                <h3 class="font-bold text-slate-900">Needs Cleaning</h3>
                            </div>
                            <p class="text-slate-600 text-[11px] leading-relaxed">Guest has checked out. Housekeeping in progress. Click <strong>Mark Ready</strong> once prepared.</p>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-rose-500 inline-block shrink-0"></span>
                                <h3 class="font-bold text-slate-900">Maintenance</h3>
                            </div>
                            <p class="text-slate-600 text-[11px] leading-relaxed">Room taken out of service for electrical, plumbing, or AC repair works.</p>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200/90 rounded-xl space-y-2 text-xs">
                        <h4 class="font-bold text-slate-900">Standard Check-In &amp; Check-Out Workflow</h4>
                        <ul class="list-disc list-inside text-slate-600 space-y-1.5 leading-relaxed">
                            <li><strong>New Reservation:</strong> Click <strong>+ New Room Reservation</strong>. Select Room, Guest, Check-in / Check-out dates, and collect advance tariff via Cash, UPI, or Member Wallet.</li>
                            <li><strong>Check-In:</strong> On arrival date, click the reserved room card and press <strong>Check-In</strong>. Room status flips to Occupied.</li>
                            <li><strong>Check-Out:</strong> Click Occupied room card and select <strong>Check-Out</strong>. Room automatically flips to <em>Needs Cleaning</em>.</li>
                            <li><strong>Housekeeping Turnaround:</strong> Once housekeeping staff sanitizes the room and places fresh linen, click <strong>Mark Cleaned &amp; Ready</strong> to restore <em>Vacant Ready</em>.</li>
                        </ul>
                    </div>
                </div>

                <!-- Badminton Court Schedule Matrix -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            🏸
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">2. Badminton Court Daily Schedule (6:00 AM – 10:00 PM)</h2>
                            <p class="text-xs text-slate-500">Hourly slot bookings, subscription plan detection, and court capacity management.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900">Hourly Slots</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Court time is strictly divided into 60-minute slots. Concurrent overlapping bookings for the same slot are blocked by pessimistic database interval locks.
                            </p>
                        </div>

                        <div class="p-4 bg-emerald-50/50 border border-emerald-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-emerald-950">Active Sports Subscribers</h3>
                            <p class="text-emerald-900 leading-relaxed">
                                When a member has an active Monthly or Annual Badminton Plan, the system automatically detects their entitlement and <strong>waives the hourly fee (₹0.00)</strong>.
                            </p>
                        </div>

                        <div class="p-4 bg-amber-50/50 border border-amber-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-amber-950">Hourly Pay-Per-Play</h3>
                            <p class="text-amber-900 leading-relaxed">
                                For members or guests without an active subscription, the hourly court tariff is debited from their prepaid balance or collected via cash/UPI before slot confirmation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 3: FINANCE, ACCOUNTS & CONTRACTOR SETTLEMENTS GUIDE                   -->
        <!-- ========================================================================= -->
        @if($activeTab === 'finance')
            <div class="space-y-6">
                <!-- Chart of Accounts Card -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            ⚖️
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. General Ledger &amp; 5-Digit Numbering Standard</h2>
                            <p class="text-xs text-slate-500">How accounts are structured in Malabar Club's double-entry accounting engine.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 text-xs">
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-blue-700 text-[11px] block">1000s SERIES</span>
                            <h3 class="font-bold text-slate-900">Assets</h3>
                            <p class="text-slate-600 text-[11px]">1010 Cash in Hand, 1020 Bank Clearing, 1030 Bar Inventory Stores.</p>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-rose-700 text-[11px] block">2000s SERIES</span>
                            <h3 class="font-bold text-slate-900">Liabilities</h3>
                            <p class="text-slate-600 text-[11px]">2010 Prepaid Wallet Advances, 2020 Contractor Payable, 2030 GST Liability.</p>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-purple-700 text-[11px] block">3000s SERIES</span>
                            <h3 class="font-bold text-slate-900">Capital &amp; Equity</h3>
                            <p class="text-slate-600 text-[11px]">3010 Club Capital &amp; General Reserves, Retained Operational Surplus.</p>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-emerald-700 text-[11px] block">4000s SERIES</span>
                            <h3 class="font-bold text-slate-900">Revenue / Income</h3>
                            <p class="text-slate-600 text-[11px]">4010 Guest Passes, 4020 Bar Sales, 4030 Rooms, 4040 Sports Subscriptions.</p>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-amber-700 text-[11px] block">5000s SERIES</span>
                            <h3 class="font-bold text-slate-900">Expenses</h3>
                            <p class="text-slate-600 text-[11px]">5010 COGS Liquor, 5020 Food Contractor, 5100s Utilities, 5200s Maintenance, 5400s Admin.</p>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200/90 rounded-xl space-y-2 text-xs">
                        <h4 class="font-bold text-slate-900">Protected System Accounts vs. Custom Expense Heads</h4>
                        <p class="text-slate-600 leading-relaxed">
                            Accounts marked with the <span class="text-rose-600 font-bold">🔒 System Lock</span> icon (e.g. 1010, 1020, 1030, 2010, 2020, 2030) are automated system accounts required for software operations; their codes cannot be changed or deleted. Custom expense heads (e.g. 5110 KSEB, 5120 Diesel, 5210 Maintenance, 5220 Court AMC) can be created, edited, and classified freely under the <strong>Chart of Accounts</strong> screen or on the fly using the <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-300 font-mono text-[10px] font-bold text-slate-800 shadow-2xs">+</kbd> button on Expense Vouchers.
                        </p>
                    </div>
                </div>

                <!-- Daily Food Contractor Settlement Card -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            🍲
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">2. External Food Contractor Pass-Through &amp; Daily Settlement</h2>
                            <p class="text-xs text-slate-500">Dining operations are contracted out. All food collections are reconciled and settled next-day.</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs leading-relaxed text-slate-600">
                        <p>
                            1. <strong>Automated Daily Compilation:</strong> Every morning, the system compiles the preceding business day's food billing lines (Cash, UPI, and Prepaid Wallet redemptions) into an unconfirmed Settlement Batch under <em>Procurement &amp; Food Contractor &rarr; Contractor Settlements</em>.
                        </p>
                        <p>
                            2. <strong>Management Audit &amp; Approval:</strong> The General Manager or Club Accountant reviews the batch turnover, refunds, and net payable before clicking <strong>Approve Settlement</strong>.
                        </p>
                        <p>
                            3. <strong>Bank Disbursement:</strong> Click <strong>Record Payment</strong>. Select the disbursing Club Bank Account (e.g. Canara Bank Current A/c), payment mode (NEFT/RTGS, Cheque), and enter the bank UTR transaction number.
                        </p>
                        <p>
                            4. <strong>Automated Double-Entry Posting:</strong> Confirming disbursement debits Contractor Payable Account (2020) and credits Bank Account (1020), synchronizing an official payment voucher into Tally Prime.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 4: PROCUREMENT, INVENTORY & STORES GUIDE                              -->
        <!-- ========================================================================= -->
        @if($activeTab === 'inventory')
            <div class="space-y-6">
                <!-- Inward Consignments Card -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            📦
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. Inward Stock Purchases &amp; Consignment Receipts</h2>
                            <p class="text-xs text-slate-500">Record deliveries from KSBC wholesale depots and commercial distributors.</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs leading-relaxed text-slate-600">
                        <p>
                            1. Navigate to <strong>Procurement &amp; Food Contractor &rarr; Purchase Receipts</strong> and click <strong>Create Purchase Receipt</strong>.
                        </p>
                        <p>
                            2. <strong>Supplier Selection:</strong> Select the authorized distributor (e.g. <em>KSBC-FL4 Wholesale Depot</em>). If dealing with a new supplier, click the <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[10px] font-bold text-slate-800">+</kbd> button in the dropdown to register the vendor with their 15-digit GSTIN on the fly.
                        </p>
                        <p>
                            3. <strong>Invoice Details:</strong> Enter the delivery challan or invoice number, invoice date, and consignment notes.
                        </p>
                        <p>
                            4. <strong>Item Repeater Lines:</strong> Add delivered items. For bar liquor, enter base quantities in milliliters (ml) — e.g. 10 bottles of 750ml = 7,500 ml. Enter unit cost in Rupees.
                        </p>
                        <p>
                            5. <strong>Automated Costing &amp; Ledger Posting:</strong> Clicking <strong>Create</strong> increments physical stock on hand, recalculates the item's <em>Moving Weighted-Average Cost (Paise)</em>, and automatically posts the financial journal: <span class="font-mono font-semibold text-slate-900">Debit 1030 (Inventory Stores) / Credit 1020 (Bank Clearing)</span>.
                        </p>
                    </div>
                </div>

                <!-- Stock Consumption Card -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            🍸
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">2. Bar Liquor Dispensing &amp; Milliliter (ml) Accuracy</h2>
                            <p class="text-xs text-slate-500">Exact liquor inventory tracking prevents unaccounted dispensing losses.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900">30ml Small Peg</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Dispensing a 30ml peg deducts exactly 30 units from the central ml stock on hand, logging consumption cost to Account 5010 (Liquor COGS).
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900">60ml Large Peg</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Dispensing a 60ml peg deducts exactly 60 units from stock. Bar stock reports display both ml volume and bottle equivalents (750ml).
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900">Full Bottle (750ml)</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Sealed bottle sales deduct 750ml from inventory stores. Barcode scanning instantly detects bottle size variants.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 5: EXECUTIVE GOVERNANCE & ADMIN GUIDE                                 -->
        <!-- ========================================================================= -->
        @if($activeTab === 'executive')
            <div class="space-y-6">
                <!-- Staff Onboarding Card -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            🛡️
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. Staff User Management &amp; Role-Based Access Control (RBAC)</h2>
                            <p class="text-xs text-slate-500">Administered under System &amp; Access Control &rarr; Staff &amp; User Access.</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs leading-relaxed text-slate-600">
                        <p>
                            1. <strong>Onboarding Staff:</strong> Click <strong>Register New Staff User</strong>. Enter Full Name, Staff ID (e.g. EMP-104), Designation, Email, Mobile Phone, and assign a temporary password (min 8 characters).
                        </p>
                        <p>
                            2. <strong>Assigning Roles:</strong> Select one or more preset roles:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 text-xs">
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl"><strong>Admin:</strong> Full system, user management, and settings.</div>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl"><strong>Manager:</strong> Operations, refunds, tariffs, and stock.</div>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl"><strong>Accountant:</strong> Ledgers, settlements, and P&amp;L reports.</div>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl"><strong>Auditor:</strong> Read-only financial audit registers.</div>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl"><strong>Reception:</strong> Accommodation Deck, courts, and KYC.</div>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl"><strong>Cashier:</strong> Counter POS, wallet recharges, and drawer shifts.</div>
                        </div>
                        <p>
                            3. <strong>Immediate Account Deactivation:</strong> When an employee leaves, toggle their status to <strong>Deactivated</strong>. This immediately cuts off login access to both back-office and POS terminal without deleting historical bills or shifts.
                        </p>
                        <p>
                            4. <strong>Audit Safeguards:</strong> Staff users with historical database records (shifts, bills, purchase receipts, or journal vouchers) cannot be hard-deleted to preserve legal audit integrity.
                        </p>
                    </div>
                </div>

                <!-- Executive Reporting Card -->
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            📊
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">2. Executive Reporting Hub (8 Core Statutory Reports)</h2>
                            <p class="text-xs text-slate-500">Accessible under Finance &amp; Audit Ledger &rarr; Executive Reports.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-slate-600">
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="text-slate-900">1. Sales &amp; Collections:</strong> Segregates external cash/UPI inflows from prepaid card redemptions.
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="text-slate-900">2. Income &amp; Expenditure:</strong> Official statement showing operating surplus or deficit.
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="text-slate-900">3. Till Drawer Reconciliation:</strong> Shift-by-shift expected cash vs physically counted cash variances.
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="text-slate-900">4. Food Contractor Audit:</strong> Daily turnover, post-settlement adjustments, and bank payout references.
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="text-slate-900">5. Bar Stock &amp; Consumption:</strong> Weighted-average stock valuation, sales COGS, and bottle reserves.
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="text-slate-900">6. Sports Plan Income:</strong> Gym and badminton subscription revenues and daily court utilization.
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="text-slate-900">7. Zero-Credit &amp; Payables Audit:</strong> Confirms 100% zero-credit compliance (0 member bad debts).
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="text-slate-900">8. Departmental P&amp;L:</strong> Segmented profitability for Bar, Rooms, Restaurant, and Sports.
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 6: CLUB BYLAWS & SYSTEM INTEGRITY MANUAL                              -->
        <!-- ========================================================================= -->
        @if($activeTab === 'bylaws')
            <div class="space-y-6">
                <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">
                            📜
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Malabar Club Operational Bylaws &amp; System Rules</h2>
                            <p class="text-xs text-slate-500">Approved club policies codified into the core transactional engine.</p>
                        </div>
                    </div>

                    <div class="space-y-3.5 text-xs leading-relaxed text-slate-600">
                        <div class="p-4 bg-amber-50/50 border border-amber-300/80 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-amber-950 text-sm">Rule 1: Strict Zero-Credit Policy (No Overdrafts)</h3>
                            <p class="text-amber-900">
                                No member, dependent, visiting guest, or affiliated reciprocal club member may receive goods, liquor, food, sports access, or room stays on credit or with a negative account balance. No role, including General Manager and Administrator, can override this policy through ordinary billing. Service fulfillment strictly requires confirmed payment or sufficient verified prepaid funds.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900 text-sm">Rule 2: Centralized Server Ledger Ownership</h3>
                            <p>
                                A member's RFID contactless smartcard does not store money. It stores an encrypted, unique identifier that links to the club's central database ledger. Offline prepaid spending is strictly disabled to prevent untraceable negative balances. Monetary values are calculated as exact integers in Indian Paise.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900 text-sm">Rule 3: Immutable Financial Records &amp; Linked Reversals</h3>
                            <p>
                                Posted tax invoices, stock movements, wallet recharges, and general ledger journals cannot be updated, edited, or deleted under any circumstance. Correcting a mistake requires an authorized, linked refund, credit note, or reversing journal. Database triggers permanently enforce append-only financial history.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900 text-sm">Rule 4: Guest Entry &amp; Sponsoring Eligibility</h3>
                            <p>
                                Guest entry for the bar and card room requires formal registration, an eligible sponsoring club member or affiliated reciprocal member, and the applicable paid day pass. Unpaid draft entries never activate gate access.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900 text-sm">Rule 5: Fiscal Period Closing Locks</h3>
                            <p>
                                Audited financial periods are locked by the Club Accountant. Once closed, the system strictly forbids posting backdated transactions into that date range to protect statutory compliance.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- PRINT-ONLY SINGLE-PAGE CASHIER CHEAT SHEET (DECISION D47)                 -->
        <!-- ========================================================================= -->
        <div class="hidden print:block p-8 bg-white text-slate-900 space-y-6">
            <div class="border-b-2 border-slate-900 pb-4 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black uppercase tracking-tight">Malabar Club</h1>
                    <p class="text-sm font-bold text-slate-700">Counter POS Keyboard Shortcuts &amp; Operational SOP</p>
                </div>
                <div class="text-right text-xs">
                    <p class="font-bold">Laminated Desk Reference</p>
                    <p class="text-slate-500">Palakkad, Kerala</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-xs font-mono">
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F2] MEMBER / CARD TAP</p>
                    <p class="text-slate-700 font-sans">Tap RFID smartcard or type member name / phone.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F3] ITEM / BARCODE</p>
                    <p class="text-slate-700 font-sans">Scan barcode or autocomplete item name.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F4] CART / QUANTITY</p>
                    <p class="text-slate-700 font-sans">Navigate rows with arrows; edit quantities.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F8] PREPAID CARD</p>
                    <p class="text-slate-700 font-sans">Debit member wallet balance (No credit permitted).</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F9] CASH PAYMENT</p>
                    <p class="text-slate-700 font-sans">Settle via physical drawer cash &amp; give change.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F10] DIGITAL UPI / POS</p>
                    <p class="text-slate-700 font-sans">Settle via card swipe terminal or club UPI QR.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[1, 2, 3] SERVING SIZE</p>
                    <p class="text-slate-700 font-sans">Select 1: 30ml Small, 2: 60ml Large, 3: Bottle.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[Esc] DISMISS / CANCEL</p>
                    <p class="text-slate-700 font-sans">Close active modal or dismiss alert dialog.</p>
                </div>
            </div>

            <div class="border-t border-slate-400 pt-3 text-[11px] text-slate-700 space-y-1">
                <p><strong>STRICT CLUB MANDATE:</strong> Zero credit is permitted for any member or guest. Transactions cannot proceed with insufficient funds.</p>
                <p><strong>DRAWER RECONCILIATION:</strong> Expected Cash = Opening Cash Float + Cash Sales + Cash Wallet Recharges. Prepaid card redemptions do not affect drawer cash.</p>
            </div>
        </div>

    </div>
</x-filament-panels::page>
