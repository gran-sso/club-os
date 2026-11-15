<x-filament-widgets::widget>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20 flex items-center justify-center font-bold text-lg">
                    🛡️
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">Accounts Health &amp; Tally Sync</h3>
                    <p class="text-[11px] text-slate-500">GL balancing, fiscal locks, and sync outbox</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/admin/integration-status') }}" class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1">
                    <span>Integrations</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- Section 1: Tally Accounting Sync Status -->
        <div class="p-3.5 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-100 dark:border-slate-800/80 space-y-2.5">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-900 dark:text-white">Tally Accounting Sync</span>
                    <span class="text-[10px] font-mono px-2 py-0.2 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                        {{ strtoupper($tallyStatus['mode'] ?? 'SIMULATOR') }}
                    </span>
                </div>
                <span class="text-[11px] text-slate-400 font-mono">
                    Last: {{ !empty($tallyStatus['last_synced_at']) ? date('H:i', strtotime($tallyStatus['last_synced_at'])) : 'Recent' }}
                </span>
            </div>

            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <a href="{{ url('/admin/integration-status') }}" class="p-2 rounded-lg bg-emerald-500/10 border border-emerald-500/20 hover:border-emerald-500/50 hover:bg-emerald-500/15 transition no-underline block">
                    <span class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">{{ $outboxAcknowledged }}</span>
                    <span class="text-[10px] block text-slate-500">Synced Today &rarr;</span>
                </a>
                <a href="{{ url('/admin/integration-status') }}" class="p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 hover:border-amber-500/50 hover:bg-amber-500/15 transition no-underline block">
                    <span class="text-xs font-extrabold text-amber-600 dark:text-amber-400 font-mono">{{ $outboxPending }}</span>
                    <span class="text-[10px] block text-slate-500">Waiting &rarr;</span>
                </a>
                <a href="{{ url('/admin/integration-status') }}" class="p-2 rounded-lg bg-rose-500/10 border border-rose-500/20 hover:border-rose-500/50 hover:bg-rose-500/15 transition no-underline block">
                    <span class="text-xs font-extrabold text-rose-600 dark:text-rose-400 font-mono">{{ $outboxFailed }}</span>
                    <span class="text-[10px] block text-slate-500">Retries &rarr;</span>
                </a>
            </div>
        </div>

        <!-- Section 2: Audit Safeguards & Statutory Compliance -->
        <div class="space-y-2 text-xs">
            <div class="flex items-center justify-between text-xs">
                <span class="font-bold uppercase tracking-wider text-slate-500 text-[10px]">Financial Safeguards</span>
                <a href="{{ url('/admin/financial-reports') }}" class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                    P&amp;L Reports &rarr;
                </a>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <!-- GL Balance Check -->
                <a href="{{ url('/admin/financial-reports') }}" class="p-2.5 rounded-xl border {{ $isGlBalanced ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-400 hover:border-emerald-500/50' : 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-400 hover:border-rose-500/50' }} flex items-center justify-between transition no-underline">
                    <div>
                        <span class="text-[10px] font-bold uppercase block text-slate-500">General Ledger</span>
                        <span class="text-xs font-extrabold">{{ $isGlBalanced ? '✓ Balanced (100%)' : '⚠ Discrepancy' }}</span>
                    </div>
                    <span class="text-base">{{ $isGlBalanced ? '⚖️' : '🚨' }}</span>
                </a>

                <!-- Fiscal Period Lock -->
                <a href="{{ url('/admin/financial-periods') }}" class="p-2.5 rounded-xl border bg-slate-50 hover:bg-slate-100 dark:bg-slate-950/40 dark:hover:bg-slate-900 border-slate-200 hover:border-slate-300 dark:border-slate-800 flex items-center justify-between transition no-underline">
                    <div>
                        <span class="text-[10px] font-bold uppercase block text-slate-500">Fiscal Period</span>
                        <span class="text-xs font-extrabold font-mono text-slate-900 dark:text-white">
                            {{ $activePeriod?->period_code ?? 'Open Period' }}
                        </span>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold">
                        Open &rarr;
                    </span>
                </a>

                <!-- Unposted Payment Captures (Scenario F07) -->
                <a href="{{ url('/admin/bills') }}" class="p-2.5 rounded-xl border {{ $unpostedCapturesCount === 0 ? 'bg-slate-50 hover:bg-slate-100 dark:bg-slate-950/40 dark:hover:bg-slate-900 border-slate-200 hover:border-slate-300 dark:border-slate-800' : 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-400 hover:border-rose-500/50' }} flex items-center justify-between transition no-underline">
                    <div>
                        <span class="text-[10px] font-bold uppercase block text-slate-500">Unrecorded Online Payments</span>
                        <span class="text-xs font-extrabold font-mono {{ $unpostedCapturesCount === 0 ? 'text-slate-900 dark:text-white' : 'text-rose-600' }}">
                            {{ $unpostedCapturesCount }} Pending
                        </span>
                    </div>
                    <span class="text-base">{{ $unpostedCapturesCount === 0 ? '✅' : '⚠️' }}</span>
                </a>

                <!-- MTD GST Liability -->
                <a href="{{ url('/admin/financial-reports') }}" class="p-2.5 rounded-xl border bg-slate-50 hover:bg-slate-100 dark:bg-slate-950/40 dark:hover:bg-slate-900 border-slate-200 hover:border-slate-300 dark:border-slate-800 flex items-center justify-between transition no-underline">
                    <div>
                        <span class="text-[10px] font-bold uppercase block text-slate-500">MTD GST Collected</span>
                        <span class="text-xs font-extrabold font-mono text-slate-900 dark:text-white">
                            ₹{{ $monthGstRupees }}
                        </span>
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono">{{ config('club.gstin', 'GST Registered') }} &rarr;</span>
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>

