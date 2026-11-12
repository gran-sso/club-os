<x-filament-widgets::widget>
    <div @if (! $showCloseModal) wire:poll.visible.30s @endif class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-5">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 flex items-center justify-center font-bold text-lg">
                    🧾
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">Billing Counters &amp; Live Shifts</h3>
                    <p class="text-[11px] text-slate-500">Live station registers, drawer cash, shift billing &amp; open bar tabs</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $openCountersCount > 0 ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700' }}">
                    {{ $openCountersCount }} / {{ $totalCountersCount }} Open
                </span>

                @if ($openCountersCount > 0)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-mono font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                        Drawer Cash: ₹{{ number_format($totalExpectedCashPaise / 100, 2) }}
                    </span>
                @endif

                @if ($staleShiftsCount > 0)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                        ⚠️ {{ $staleShiftsCount }} Overdue {{ \Illuminate\Support\Str::plural('Shift', $staleShiftsCount) }}
                    </span>
                @endif

                <a href="{{ url('/admin/shifts') }}" class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1 ml-1">
                    <span>All Shifts</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        @if ($stations->isEmpty())
            <div class="p-4 bg-slate-50 dark:bg-slate-950/50 rounded-xl border border-slate-100 dark:border-slate-800 text-xs text-slate-500 text-center">
                No active billing counter stations are configured.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($stations as $station)
                    @php
                        /** @var \App\Domain\Billing\Models\Counter $counter */
                        $counter = $station['counter'];
                        /** @var \App\Domain\Billing\Models\Shift|null $openShift */
                        $openShift = $station['open_shift'];
                        /** @var \App\Domain\Billing\Models\Shift|null $lastClosed */
                        $lastClosed = $station['last_closed_shift'];
                        $isOpen = (bool) $station['is_open'];
                        $isStale = (bool) $station['is_stale'];

                        $cardBorder = match (true) {
                            $isStale => 'border-rose-500/40 bg-rose-500/[0.03] dark:bg-rose-950/20',
                            $isOpen => 'border-emerald-500/35 bg-emerald-500/[0.03] dark:bg-emerald-950/15',
                            default => 'border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40',
                        };
                    @endphp

                    <div class="rounded-xl border {{ $cardBorder }} p-4 flex flex-col justify-between gap-3.5">
                        <!-- Card Top: Counter & Live Status -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $isStale ? 'bg-rose-500 animate-pulse' : ($isOpen ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300 dark:bg-slate-600') }}"></span>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $counter->name }}</h4>
                                </div>
                                <div class="text-[11px] font-mono text-slate-500 mt-0.5 pl-4">
                                    {{ $counter->code }}
                                    @if ($counter->department)
                                        &bull; {{ $counter->department->name }}
                                    @endif
                                </div>
                            </div>

                            @if ($isStale)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30 shrink-0">
                                    Overdue
                                </span>
                            @elseif ($isOpen)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 shrink-0">
                                    Open
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200/70 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300/60 dark:border-slate-700 shrink-0">
                                    Closed
                                </span>
                            @endif
                        </div>

                        @if ($isOpen && $openShift)
                            <!-- Active Shift Details -->
                            <div class="space-y-3">
                                <div class="text-xs space-y-1 bg-white/80 dark:bg-slate-900/70 rounded-lg p-2.5 border border-slate-200/70 dark:border-slate-800">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono font-bold text-slate-900 dark:text-white">#{{ $openShift->shift_number }}</span>
                                        <span class="text-[11px] font-mono {{ $isStale ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-500' }}">
                                            {{ $openShift->business_date?->format('d M Y') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                                        <span class="truncate">Cashier: <strong class="text-slate-900 dark:text-white">{{ $openShift->cashier?->name ?? 'Staff' }}</strong></span>
                                        <span class="font-mono text-slate-500 shrink-0">Opened {{ $openShift->opened_at?->format('h:i A') }}</span>
                                    </div>
                                </div>

                                <!-- 3 Key Live Metrics -->
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-lg bg-white/80 dark:bg-slate-900/70 border border-slate-200/70 dark:border-slate-800 p-2">
                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Drawer Cash</div>
                                        <div class="text-xs font-extrabold font-mono text-slate-900 dark:text-white mt-0.5">
                                            ₹{{ number_format($station['expected_cash_paise'] / 100, 0) }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-mono">
                                            Float ₹{{ number_format($openShift->opening_float_paise / 100, 0) }}
                                        </div>
                                    </div>

                                    <div class="rounded-lg bg-white/80 dark:bg-slate-900/70 border border-slate-200/70 dark:border-slate-800 p-2">
                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Shift Sales</div>
                                        <div class="text-xs font-extrabold font-mono text-slate-900 dark:text-white mt-0.5">
                                            ₹{{ number_format($station['billed_total_paise'] / 100, 0) }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ $station['paid_bills_count'] }} {{ \Illuminate\Support\Str::plural('bill', $station['paid_bills_count']) }}
                                        </div>
                                    </div>

                                    <div class="rounded-lg border p-2 {{ $station['open_tabs_count'] > 0 ? 'bg-amber-500/10 border-amber-500/30' : 'bg-white/80 dark:bg-slate-900/70 border-slate-200/70 dark:border-slate-800' }}">
                                        <div class="text-[10px] font-semibold uppercase tracking-wider {{ $station['open_tabs_count'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-slate-500' }}">Open Tabs</div>
                                        <div class="text-xs font-extrabold font-mono mt-0.5 {{ $station['open_tabs_count'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-slate-900 dark:text-white' }}">
                                            {{ $station['open_tabs_count'] }}
                                        </div>
                                        <div class="text-[10px] {{ $station['open_tabs_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}">
                                            {{ $station['open_tabs_count'] > 0 ? 'Unsettled' : 'Clear' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Tender Mix -->
                                <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-1 px-0.5">
                                    <span>Cash ₹{{ number_format($station['cash_sales_paise'] / 100, 0) }}</span>
                                    <span>&bull;</span>
                                    <span>Digital ₹{{ number_format($station['digital_sales_paise'] / 100, 0) }}</span>
                                    <span>&bull;</span>
                                    <span>Wallet ₹{{ number_format($station['prepaid_sales_paise'] / 100, 0) }}</span>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="pt-2.5 border-t border-slate-200/70 dark:border-slate-800 flex items-center justify-between gap-2">
                                <a href="{{ url('/admin/shifts/' . $openShift->id . '/edit') }}" class="text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-amber-600 dark:hover:text-amber-400 hover:underline">
                                    Inspect Shift &rarr;
                                </a>

                                @if ($canManageShifts)
                                    <button
                                        type="button"
                                        wire:click="openCloseShiftModal({{ $openShift->id }})"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-600 hover:bg-rose-500 text-white transition cursor-pointer"
                                    >
                                        Close &amp; Reconcile
                                    </button>
                                @endif
                            </div>
                        @else
                            <!-- Idle / Closed Counter Details -->
                            <div class="space-y-2.5">
                                <div class="text-xs bg-white/70 dark:bg-slate-900/50 rounded-lg p-3 border border-slate-200/60 dark:border-slate-800/80">
                                    @if ($lastClosed)
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-[11px] font-semibold text-slate-500">Last Closed Shift</span>
                                            <span class="font-mono text-[11px] font-bold text-slate-700 dark:text-slate-300">#{{ $lastClosed->shift_number }}</span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-1">
                                            {{ $lastClosed->cashier?->name ?? 'Staff' }} &bull; {{ $lastClosed->closed_at?->format('d M, h:i A') ?? $lastClosed->business_date?->format('d M Y') }}
                                        </div>
                                        @php $diffPaise = (int) ($lastClosed->discrepancy_paise ?? 0); @endphp
                                        <div class="mt-1.5 text-[11px] font-mono">
                                            Cash Difference:
                                            <span class="font-bold {{ $diffPaise === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                ₹{{ number_format($diffPaise / 100, 2) }}
                                            </span>
                                        </div>
                                    @else
                                        <div class="text-[11px] text-slate-500">
                                            No active shift running on this counter.
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="pt-2.5 border-t border-slate-200/70 dark:border-slate-800 flex items-center justify-between gap-2">
                                <span class="text-[11px] text-slate-400">Station Ready</span>

                                @if ($canManageShifts)
                                    <a href="{{ url('/admin/shifts/create?counter_id=' . $counter->id) }}" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-900 dark:bg-amber-500 text-white dark:text-slate-950 hover:opacity-90 transition">
                                        + Open Shift
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Close & Reconcile Shift Modal -->
    @if ($showCloseModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-4"
            x-data
            x-on:keydown.escape.window="$wire.cancelCloseShiftModal()"
        >
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl max-w-md w-full p-5 space-y-4">
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">Close &amp; Reconcile Shift #{{ $closingShiftNumber }}</h4>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $closingCounterName }}</p>
                    </div>
                    <button type="button" wire:click="cancelCloseShiftModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg leading-none cursor-pointer">&times;</button>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">System Expected Cash</span>
                    <span class="text-base font-extrabold font-mono text-slate-900 dark:text-white">
                        ₹{{ number_format($closingExpectedCashPaise / 100, 2) }}
                    </span>
                </div>

                <form wire:submit="confirmCloseShift" class="space-y-3.5">
                    <div>
                        <label for="dashboard-counted-cash" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                            Actual Counted Cash in Drawer (₹) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="dashboard-counted-cash"
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="countedCash"
                            required
                            class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm font-mono text-slate-900 dark:text-white focus:border-amber-500 focus:ring-amber-500"
                        />
                        @error('countedCash')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="dashboard-close-notes" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1">
                            Closing &amp; Handover Notes
                        </label>
                        <textarea
                            id="dashboard-close-notes"
                            rows="2"
                            wire:model="closeNotes"
                            placeholder="Cash drop reference or reason for cash difference (if any)."
                            class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 focus:ring-amber-500"
                        ></textarea>
                        @error('closeNotes')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="cancelCloseShiftModal"
                            class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-xs cursor-pointer"
                        >
                            Confirm &amp; Close Shift
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
