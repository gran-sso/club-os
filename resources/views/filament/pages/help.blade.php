<x-filament-panels::page>
    <div class="space-y-6">

        <!-- ========================================================================= -->
        <!-- 1. TOP HEADER & DESK PRINT ACTION                                         -->
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
                            Interactive procedural manual: select an operational procedure from the left menu to view step-by-step instructions.
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
        </div>

        <!-- ========================================================================= -->
        <!-- 2. ENTERPRISE TWO-COLUMN APPLICATION MANUAL LAYOUT                        -->
        <!-- ========================================================================= -->
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden flex flex-col lg:flex-row items-stretch print:hidden no-print min-h-[720px]">
            
            <!-- --------------------------------------------------------------------- -->
            <!-- LEFT COLUMN: PROCEDURES DIRECTORY & SEARCH                            -->
            <!-- --------------------------------------------------------------------- -->
            <div class="w-full lg:w-80 shrink-0 border-b lg:border-b-0 lg:border-r border-slate-200/90 bg-slate-50/70 p-4 space-y-4 flex flex-col justify-between">
                
                <div class="space-y-4">
                    <!-- Live Topic Filter Input -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.150ms="searchQuery"
                            placeholder="Filter procedures..."
                            class="w-full pl-9 pr-8 py-2 bg-white text-slate-900 placeholder:text-slate-400 text-xs rounded-xl border border-slate-300 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all outline-hidden shadow-2xs font-medium"
                        />
                        @if($searchQuery)
                            <button
                                type="button"
                                wire:click="clearSearch"
                                class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-700 text-xs cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>

                    <!-- Filter / Search Status Indicator -->
                    @if($searchQuery)
                        <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
                            <span>Search Results for: <strong class="text-slate-800">"{{ $searchQuery }}"</strong></span>
                            <button type="button" wire:click="clearSearch" class="text-amber-700 hover:underline font-bold cursor-pointer">Clear</button>
                        </div>
                    @endif

                    <!-- Topic Sections List -->
                    <div class="max-h-[600px] overflow-y-auto space-y-4 pr-1">
                        @foreach($this->groupedTopics as $groupKey => $group)
                            @if(count($group['topics']) > 0)
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between px-2 py-1">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                                            {{ $group['label'] }}
                                        </span>
                                        <span class="text-[10px] font-bold text-slate-400 font-mono">
                                            {{ count($group['topics']) }}
                                        </span>
                                    </div>

                                    <div class="space-y-0.5">
                                        @foreach($group['topics'] as $topic)
                                            @php
                                                $isActive = ($activeTopic === $topic['id']);
                                            @endphp
                                            <button
                                                type="button"
                                                wire:click="selectTopic('{{ $topic['id'] }}')"
                                                class="w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between gap-2 cursor-pointer {{ $isActive ? 'bg-amber-500 text-slate-950 font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-200/70 hover:text-slate-950 font-medium' }}"
                                            >
                                                <div class="flex items-center gap-2 truncate">
                                                    <span class="shrink-0 text-sm">{{ $topic['icon'] }}</span>
                                                    <span class="truncate">{{ $topic['title'] }}</span>
                                                </div>
                                                @if($isActive)
                                                    <span class="text-slate-950 font-black shrink-0">&rarr;</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <!-- Footer Summary of Procedures -->
                <div class="pt-3 border-t border-slate-200/80 text-[11px] text-slate-500 flex items-center justify-between">
                    <span>14 Standard Operating Guides</span>
                    <span class="font-bold text-amber-700">Malabar Club</span>
                </div>
            </div>

            <!-- --------------------------------------------------------------------- -->
            <!-- RIGHT COLUMN: DEDICATED STEP-BY-STEP PROCEDURE READER                 -->
            <!-- --------------------------------------------------------------------- -->
            <div class="flex-1 p-6 sm:p-8 space-y-6 overflow-y-auto bg-white">
                
                @php
                    $topic = $this->currentTopic;
                @endphp

                <!-- Article Breadcrumbs -->
                <div class="flex items-center gap-1.5 text-xs text-slate-400 font-medium">
                    <button type="button" wire:click="setTab('{{ $topic['tab'] }}')" class="hover:text-slate-700 transition cursor-pointer">
                        Help Manual
                    </button>
                    <span>/</span>
                    <span class="text-slate-600 font-semibold">{{ $topic['tab_label'] }}</span>
                    <span>/</span>
                    <span class="text-slate-900 font-bold truncate">{{ $topic['title'] }}</span>
                </div>

                <!-- Article Header & Meta Details -->
                <div class="space-y-3 pb-5 border-b border-slate-100">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-900 border border-amber-200">
                            {{ $topic['tab_label'] }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                            👤 {{ $topic['target_role'] }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                            ⏱️ {{ $topic['estimated_time'] }}
                        </span>
                    </div>

                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <span>{{ $topic['icon'] }}</span>
                        <span>{{ $topic['title'] }}</span>
                    </h1>

                    <div class="p-4 bg-slate-50 border-l-4 border-amber-500 rounded-r-xl">
                        <p class="text-xs sm:text-sm text-slate-700 leading-relaxed font-medium">
                            {{ $topic['summary'] }}
                        </p>
                    </div>
                </div>

                <!-- Step-by-Step Execution Sequence -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-400">
                            Standard Step-by-Step Procedure
                        </h2>
                        <span class="text-xs text-slate-400 font-medium">
                            {{ count($topic['steps'] ?? []) }} Steps to Complete
                        </span>
                    </div>

                    <div class="space-y-3.5">
                        @foreach($topic['steps'] ?? [] as $step)
                            <div class="p-4 bg-slate-50 hover:bg-slate-50/80 border border-slate-200/90 rounded-2xl transition-all space-y-2">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-7 h-7 rounded-lg bg-slate-900 text-amber-300 flex items-center justify-center font-mono font-bold text-xs shrink-0 shadow-xs mt-0.5">
                                        {{ sprintf('%02d', $step['number']) }}
                                    </div>
                                    <div class="space-y-1.5 flex-1 min-w-0">
                                        <h3 class="font-bold text-slate-900 text-sm">
                                            {{ $step['title'] }}
                                        </h3>
                                        <p class="text-xs text-slate-600 leading-relaxed">
                                            {{ $step['instruction'] }}
                                        </p>

                                        @if(!empty($step['result']))
                                            <div class="pt-1 text-[11px] text-slate-500 font-medium flex items-center gap-1.5">
                                                <span class="text-emerald-600 font-bold">✓ Outcome:</span>
                                                <span>{{ $step['result'] }}</span>
                                            </div>
                                        @endif

                                        @if(!empty($step['tip']))
                                            <div class="p-2.5 bg-amber-50/70 border border-amber-200/80 rounded-xl text-[11px] text-amber-950 mt-2 flex items-start gap-2">
                                                <span class="shrink-0 text-amber-700 font-bold">💡 Tip:</span>
                                                <span class="leading-relaxed">{{ $step['tip'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Statutory Formula or Accounting Rule Callout Banner -->
                @if(!empty($topic['formula']))
                    <div class="p-4 bg-amber-50/70 border border-amber-200/90 rounded-2xl space-y-1 text-xs">
                        <span class="font-mono font-bold text-[10px] uppercase tracking-wider text-amber-800 block">
                            Key Standard / Formula / Double-Entry Mapping
                        </span>
                        <p class="font-bold text-slate-900 text-xs sm:text-sm font-mono">
                            {{ $topic['formula'] }}
                        </p>
                    </div>
                @endif

                <!-- Operational Policy Note -->
                @if(!empty($topic['policy_note']))
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex items-start gap-3 text-xs text-slate-600">
                        <span class="text-base text-slate-400">📜</span>
                        <div>
                            <strong class="text-slate-900">Governance Notice:</strong>
                            <p class="mt-0.5 leading-relaxed">{{ $topic['policy_note'] }}</p>
                        </div>
                    </div>
                @endif

                <!-- Topic Tags & Search Keywords -->
                <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-[11px] text-slate-400 font-medium mr-1">Indexed keywords:</span>
                        @foreach($topic['tags'] ?? [] as $tag)
                            <button
                                type="button"
                                wire:click="$set('searchQuery', '{{ $tag }}')"
                                class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-slate-200 text-[10px] font-mono text-slate-600 transition cursor-pointer"
                            >
                                #{{ $tag }}
                            </button>
                        @endforeach
                    </div>

                    <button
                        type="button"
                        onclick="window.print()"
                        class="text-xs font-bold text-amber-700 hover:text-amber-800 transition flex items-center gap-1 cursor-pointer"
                    >
                        <span>🖨️ Print This Procedure</span>
                    </button>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 3. PRINT-ONLY SINGLE-PAGE CASHIER CHEAT SHEET (DECISION D47)              -->
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
