<x-filament-panels::page>
    <div x-data="{ printMode: 'procedure' }" :data-help-print-mode="printMode" @afterprint.window="printMode = 'procedure'" class="space-y-6">

        <!-- ========================================================================= -->
        <!-- 1. GOOGLE HELP CENTER STYLE HEADER                                        -->
        <!-- ========================================================================= -->
        <div class="bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-2xs print:hidden no-print">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 border border-blue-100">
                        <svg aria-hidden="true" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-medium text-slate-900 tracking-tight">
                            Malabar Club Operations Help &amp; Manual
                        </h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Procedural guidance and step-by-step instructions.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="printMode = 'desk-reference'; $nextTick(() => window.print())"
                        class="min-h-9 px-3.5 inline-flex items-center justify-center gap-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium text-xs rounded-lg transition-colors cursor-pointer shadow-2xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    >
                        <svg aria-hidden="true" class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print Desk Reference</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 2. CLEAN TWO-COLUMN HELP CENTER LAYOUT                                    -->
        <!-- ========================================================================= -->
        <div class="help-procedure-view bg-white border border-slate-200/90 rounded-xl shadow-2xs overflow-hidden print:overflow-visible flex flex-col md:flex-row items-stretch min-h-[680px]">
            
            <!-- --------------------------------------------------------------------- -->
            <!-- LEFT COLUMN: CLEAN TOPIC DIRECTORY & GOOGLE PILL SEARCH               -->
            <!-- --------------------------------------------------------------------- -->
            <div class="w-full md:w-72 shrink-0 border-b md:border-b-0 md:border-r border-slate-200/80 bg-slate-50/40 p-4 space-y-4 flex flex-col justify-between print:hidden no-print">
                
                <div class="space-y-3.5">
                    <!-- Accessible Search Input -->
                    <div class="relative">
                        <label for="help-topic-search" class="sr-only">Search operational help topics</label>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg aria-hidden="true" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            id="help-topic-search"
                            type="search"
                            wire:model.live.debounce.150ms="searchQuery"
                            placeholder="Search help topics, hotkeys, steps..."
                            class="w-full min-h-8.5 pl-8.5 pr-8 py-1.5 bg-white text-slate-900 placeholder:text-slate-400 text-xs rounded-full border border-slate-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all outline-hidden font-normal"
                        />
                        @if($searchQuery)
                            <button
                                type="button"
                                wire:click="clearSearch"
                                aria-label="Clear search query"
                                class="absolute inset-y-0 right-0 min-w-7 px-2 flex items-center justify-center text-slate-400 hover:text-slate-700 text-xs cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 rounded-r-full"
                            >
                                <svg aria-hidden="true" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>

                    <!-- Search Status Indicator -->
                    @if($searchQuery)
                        <div role="status" aria-live="polite" class="flex items-center justify-between text-[11px] text-slate-500 px-1">
                            <span>Search Results for: <strong class="text-slate-800">"{{ $searchQuery }}"</strong> ({{ count($this->filteredArticles) }})</span>
                            <button type="button" wire:click="clearSearch" class="text-blue-600 hover:underline font-medium cursor-pointer">Clear</button>
                        </div>
                    @endif

                    <!-- Topic Grouped Sections -->
                    <nav aria-label="Help Topics" class="max-h-[580px] overflow-y-auto space-y-3 pr-1">
                        @php
                            $matchedTopicsCount = 0;
                        @endphp
                        @foreach($this->groupedTopics as $groupKey => $group)
                            @if(count($group['topics']) > 0)
                                @php
                                    $matchedTopicsCount += count($group['topics']);
                                @endphp
                                <div class="space-y-0.5">
                                    <div class="px-2.5 py-1">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                            {{ $group['label'] }}
                                        </span>
                                    </div>

                                    <ul class="space-y-0.5">
                                        @foreach($group['topics'] as $topic)
                                            @php
                                                $isActive = ($activeTopic === $topic['id']);
                                            @endphp
                                            <li>
                                                <button
                                                    type="button"
                                                    wire:click="selectTopic('{{ $topic['id'] }}')"
                                                    @if($isActive) aria-current="page" @endif
                                                    class="w-full min-h-8 text-left px-2.5 py-1.5 rounded-lg text-xs transition-colors flex items-center justify-between gap-2 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600 {{ $isActive ? 'bg-blue-50 text-blue-700 font-medium' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-normal' }}"
                                                >
                                                    <span class="truncate">{{ $topic['title'] }}</span>
                                                    @if($isActive)
                                                        <span aria-hidden="true" class="w-1.5 h-1.5 rounded-full bg-blue-600 shrink-0"></span>
                                                    @endif
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach

                        @if($searchQuery && $matchedTopicsCount === 0)
                            <div class="px-2.5 py-4 text-center text-xs text-slate-500 space-y-2">
                                <p>No operational guides matched "{{ $searchQuery }}".</p>
                                <button type="button" wire:click="clearSearch" class="text-blue-600 hover:underline font-medium cursor-pointer">
                                    Reset search
                                </button>
                            </div>
                        @endif
                    </nav>
                </div>

                <!-- Dynamic Footer Counter -->
                <div class="pt-3 border-t border-slate-200/70 text-[11px] text-slate-500 flex items-center justify-between px-1">
                    <span>{{ count($this->getSearchableArticles()) }} operational guides</span>
                    <span>Malabar Club</span>
                </div>
            </div>

            <!-- --------------------------------------------------------------------- -->
            <!-- RIGHT COLUMN: GOOGLE-STYLE CLEAN ARTICLE READER                       -->
            <!-- --------------------------------------------------------------------- -->
            <div class="flex-1 p-6 sm:p-10 space-y-6 overflow-y-auto bg-white print:p-0 print:overflow-visible print:space-y-4">
                
                @php
                    $topic = $this->currentTopic;
                @endphp

                <!-- Printable Official SOP Letterhead (Only visible on paper / PDF) -->
                <div class="hidden print:block pb-3 mb-4 border-b-2 border-slate-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xl font-black uppercase tracking-tight text-slate-900">Malabar Club</p>
                            <p class="text-xs font-bold text-slate-700 tracking-wide uppercase">Standard Operating Procedure (SOP) &bull; {{ $topic['tab_label'] }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-600">
                            <p class="font-mono font-bold text-slate-900">{{ strtoupper($activeTopic) }}</p>
                            <p>Palakkad, Kerala</p>
                        </div>
                    </div>
                </div>

                <!-- Subtle Clean Breadcrumb -->
                <div class="flex items-center gap-1.5 text-xs text-slate-400 print:hidden no-print">
                    <button type="button" wire:click="setTab('{{ $topic['tab'] }}')" class="hover:text-slate-600 transition cursor-pointer">
                        Help Manual
                    </button>
                    <span aria-hidden="true">/</span>
                    <span class="text-slate-500">{{ $topic['tab_label'] }}</span>
                    <span aria-hidden="true">/</span>
                    <span class="text-slate-800 font-medium truncate">{{ $topic['title'] }}</span>
                </div>

                <!-- Article Header: Clean Typography, Zero Badge Clutter -->
                <div class="space-y-2 pb-4 border-b border-slate-100 print:border-slate-200">
                    <h2 class="text-2xl font-normal text-slate-900 tracking-tight leading-snug print:text-xl print:font-bold">
                        {{ $topic['title'] }}
                    </h2>

                    <div class="text-xs text-slate-500 print:text-slate-600 flex items-center gap-2 pt-0.5">
                        <span>For: <strong class="text-slate-700 print:text-slate-900 font-medium">{{ $topic['target_role'] }}</strong></span>
                        <span aria-hidden="true">&bull;</span>
                        <span>{{ $topic['estimated_time'] }}</span>
                    </div>

                    <p class="text-xs sm:text-sm text-slate-600 print:text-slate-800 leading-relaxed font-normal pt-2">
                        {{ $topic['summary'] }}
                    </p>
                </div>

                <!-- Step-by-Step Execution Sequence (Clean Numbered Flow) -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-1">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 print:text-slate-900 print:font-bold">
                            Standard Step-by-Step Procedure
                        </h3>
                        <span class="text-xs text-slate-400 print:text-slate-600">
                            {{ count($topic['steps'] ?? []) }} Steps
                        </span>
                    </div>

                    <ol class="space-y-4">
                        @foreach($topic['steps'] ?? [] as $step)
                            <li class="flex items-start gap-3.5 pb-4 border-b border-slate-100 last:border-b-0 print:border-slate-200 print:pb-3 print:break-inside-avoid">
                                <div aria-hidden="true" class="w-6 h-6 rounded-full bg-blue-50 text-blue-700 font-semibold text-xs flex items-center justify-center shrink-0 mt-0.5 border border-blue-200/60 print:border-slate-800 print:text-slate-900 print:bg-slate-100">
                                    {{ $step['number'] }}
                                </div>
                                <div class="space-y-1.5 flex-1 min-w-0">
                                    <h4 class="font-medium text-slate-900 text-sm print:font-bold">
                                        <span class="sr-only">Step {{ $step['number'] }}: </span>{{ $step['title'] }}
                                    </h4>
                                    <p class="text-xs sm:text-sm text-slate-600 print:text-slate-800 leading-relaxed">
                                        {{ $step['instruction'] }}
                                    </p>

                                    @if(!empty($step['result']))
                                        <p class="text-xs text-emerald-700 print:text-emerald-900 font-medium flex items-center gap-1.5 pt-0.5">
                                            <svg aria-hidden="true" class="w-3.5 h-3.5 text-emerald-600 print:text-emerald-800 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span>{{ $step['result'] }}</span>
                                        </p>
                                    @endif

                                    @if(!empty($step['tip']))
                                        <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-lg text-xs text-slate-600 flex items-start gap-2 mt-2 print:border-slate-300 print:text-slate-800 print:break-inside-avoid">
                                            <svg aria-hidden="true" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5 print:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <div class="leading-relaxed">
                                                <strong class="font-semibold text-slate-800">Tip:</strong> {{ $step['tip'] }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <!-- Clean Formula / Accounting Mapping Box -->
                @if(!empty($topic['formula']))
                    <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1 text-xs print:border-slate-300 print:bg-slate-50 print:break-inside-avoid">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 print:text-slate-800 block">
                            Key Standard / Formula / Double-Entry Mapping
                        </span>
                        <p class="font-mono text-xs sm:text-sm text-slate-800 font-medium">
                            {{ $topic['formula'] }}
                        </p>
                    </div>
                @endif

                <!-- Clean Policy Note -->
                @if(!empty($topic['policy_note']))
                    <div class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-600 flex items-start gap-2.5 print:border-slate-300 print:bg-slate-50 print:break-inside-avoid">
                        <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5 print:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div class="leading-relaxed">
                            <strong class="font-semibold text-slate-800">Policy Notice:</strong> {{ $topic['policy_note'] }}
                        </div>
                    </div>
                @endif

                <!-- Printable Official SOP Footer (Only visible on paper / PDF) -->
                <div class="hidden print:block pt-4 mt-6 border-t border-slate-300 text-[10px] text-slate-500">
                    <div class="flex items-center justify-between">
                        <span>Malabar Club Operations Manual &bull; Confidential &bull; Internal Club Use Only</span>
                        <span>Printed on {{ now()->format('d M Y, h:i A') }}</span>
                    </div>
                </div>

                <!-- On-Screen Clean Action Footer -->
                <div class="pt-6 border-t border-slate-100 flex items-center justify-end print:hidden no-print">
                    <button
                        type="button"
                        @click="printMode = 'procedure'; $nextTick(() => window.print())"
                        class="h-8.5 px-3.5 inline-flex items-center justify-center gap-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium text-xs rounded-lg transition-colors cursor-pointer shadow-2xs"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print this procedure</span>
                    </button>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 3. PRINT-ONLY SINGLE-PAGE CASHIER CHEAT SHEET (DECISION D47)              -->
        <!-- ========================================================================= -->
        <div class="help-desk-reference-view hidden p-8 bg-white text-slate-900 space-y-5">
            <div class="border-b-2 border-slate-900 pb-4 flex items-center justify-between">
                <div>
                    <p class="text-2xl font-black uppercase tracking-tight">Malabar Club</p>
                    <p class="text-sm font-bold text-slate-700">Counter POS Keyboard Shortcuts &amp; Operational SOP</p>
                </div>
                <div class="text-right text-xs">
                    <p class="font-bold">Laminated Desk Reference</p>
                    <p class="text-slate-500">Palakkad, Kerala</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3.5 text-xs font-mono">
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F2] MEMBER / ROOM / RFID</p>
                    <p class="text-slate-700 font-sans">Tap RFID smartcard or search member, phone, or checked-in room #.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F3] ITEM / BARCODE</p>
                    <p class="text-slate-700 font-sans">Scan bottle barcode or search item name; select with &uarr;/&darr; and Enter.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[1, 2, 3] SERVING SIZE</p>
                    <p class="text-slate-700 font-sans">Select 1: 30ml Small, 2: 60ml Large, 3: 750ml Bottle (or arrows).</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F4] CART / QUANTITY</p>
                    <p class="text-slate-700 font-sans">Focus cart quantity input; use Delete outside inputs to adjust lines.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F6] HOLD RUNNING BAR TAB</p>
                    <p class="text-slate-700 font-sans">Park open member tab with optional table note (settle before shift close).</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F8] PREPAID WALLET</p>
                    <p class="text-slate-700 font-sans">Debit member server wallet balance (Zero credit enforced).</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F9] CASH PAYMENT</p>
                    <p class="text-slate-700 font-sans">Settle via physical drawer cash &amp; compute return change.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F10] DIGITAL UPI / EDC</p>
                    <p class="text-slate-700 font-sans">Settle via card EDC terminal or club UPI QR with reference.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[P] PRINT RECEIPT</p>
                    <p class="text-slate-700 font-sans">Print 80mm thermal slip cleanly from the Receipt confirmation dialog.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[Enter / Esc] DIALOG CONTROL</p>
                    <p class="text-slate-700 font-sans">Enter confirms active dialog; Esc closes modals without data loss.</p>
                </div>
            </div>

            <div class="border-t border-slate-400 pt-3 text-[11px] text-slate-700 space-y-1">
                <p><strong>STRICT CLUB MANDATE:</strong> Zero credit is permitted for members, resident room guests, or visitors. Unsettled Running Tabs [F6] must be settled or voided before closing a shift.</p>
                <p><strong>DRAWER RECONCILIATION:</strong> Expected Cash = Opening Cash Float + Cash Sales + Cash Wallet Recharges. Prepaid redemptions and UPI receipts do not affect drawer cash.</p>
            </div>
        </div>

    </div>
</x-filament-panels::page>
