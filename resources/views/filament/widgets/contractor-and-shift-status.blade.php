<x-filament-widgets::widget>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-lg">
                    🍽️
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">Food Contractor &amp; Settlements</h3>
                    <p class="text-[11px] text-slate-500">Daily catering payables and settlement status</p>
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
    </div>
</x-filament-widgets::widget>

