<x-filament-widgets::widget>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-lg">
                    🍽️
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">Contractor &amp; Counter Shifts</h3>
                    <p class="text-[11px] text-slate-500">Catering payables and open cash registers</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/admin/contractor-settlements/contractor-settlement-batches') }}" class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1">
                    <span>Settlements</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- Section 1: Food Catering Contractor Daily Accumulator -->
        <div class="p-3.5 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-100 dark:border-slate-800/80 space-y-2">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Today's Qualified Food Sales</span>
                    <div class="text-lg font-extrabold text-slate-900 dark:text-white font-mono mt-0.5">
                        ₹{{ $todayFoodRupees }}
                    </div>
                </div>
                @if ($latestBatch)
                    @php
                        $rawStatus = $latestBatch->status instanceof \BackedEnum ? $latestBatch->status->value : (string) $latestBatch->status;
                        $batchStatusColor = match ($rawStatus) {
                            'settled', 'paid' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
                            'partially_paid' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30',
                            'approved' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/30',
                            default => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/30',
                        };
                    @endphp
                    <div class="text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $latestBatch->contractor?->name ?? 'Catering' }} Batch</span>
                        <div class="mt-0.5">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $batchStatusColor }}">
                                {{ ucfirst(str_replace('_', ' ', $rawStatus)) }} (₹{{ number_format($latestBatch->net_payable_paise / 100, 2) }})
                            </span>
                        </div>
                    </div>
                @endif
            </div>
            <p class="text-[11px] text-slate-500 leading-tight">
                Cash, digital, and member wallet food orders automatically pool for next morning contractor settlement. Unused wallet balances are excluded.
            </p>
        </div>

        <!-- Section 2: Active Counter Shifts -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5">
                    <span>⚡</span>
                    <span class="font-bold uppercase tracking-wider text-slate-500 text-[10px]">Active POS Stations</span>
                </div>
                <a href="{{ url('/admin/shifts') }}" class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                    All Shifts &rarr;
                </a>
            </div>

            @if ($staleShiftsCount > 0)
                <div class="p-2.5 bg-rose-500/10 border border-rose-500/30 rounded-xl text-xs text-rose-700 dark:text-rose-400 flex items-center justify-between">
                    <span>⚠️ <strong>{{ $staleShiftsCount }}</strong> unclosed shift(s) from previous date requires cash balancing!</span>
                    <a href="{{ url('/admin/shifts') }}" class="underline font-bold text-[11px]">Audit</a>
                </div>
            @endif

            @if ($openShifts->isEmpty())
                <div class="p-3 bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-slate-100 dark:border-slate-800 text-xs text-slate-500 text-center">
                    No counter shifts are currently open. Open a station to begin cashier billing.
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @foreach ($openShifts as $shift)
                        <div class="py-2 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ $shift->counter?->name ?? 'POS Station' }}</span>
                                    <span class="text-[11px] text-slate-500 block">Cashier: {{ $shift->cashier?->name ?? 'Staff' }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-mono text-xs font-semibold text-slate-900 dark:text-white">Float: ₹{{ number_format($shift->opening_float_paise / 100, 2) }}</span>
                                <span class="text-[10px] text-slate-400 block font-mono">{{ $shift->created_at?->format('h:i A') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>

