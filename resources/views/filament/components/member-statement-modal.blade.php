<div class="space-y-5 text-sm text-slate-800">
    <!-- Club & Member Info Header Card -->
    <div class="p-5 bg-gradient-to-br from-slate-50 to-amber-50/30 rounded-2xl border border-slate-200/90 shadow-2xs flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-600 via-amber-500 to-yellow-400 flex items-center justify-center font-black text-slate-950 text-base shadow-sm shadow-amber-500/20">
                {{ config('club.short_name', 'MC') }}
            </div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-amber-800">{{ config('club.name', 'Malabar Club') }} &bull; {{ config('club.location', 'Vadakkencherry') }}</div>
                <h3 class="text-base font-extrabold text-slate-900 tracking-tight">{{ $member->name }}</h3>
                <div class="text-xs text-slate-600 flex items-center gap-2 mt-0.5">
                    <span class="font-mono font-bold text-amber-900 bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200"># {{ $member->membership_number }}</span>
                    <span>&bull; {{ $member->category?->name ?? 'Ordinary' }}</span>
                    <span>&bull; Card: <strong class="font-mono text-slate-900">{{ $member->activeCard?->card_number ?? 'No Card' }}</strong></span>
                </div>
            </div>
        </div>

        <div class="p-3.5 bg-white rounded-xl border border-slate-200/90 text-right shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Authoritative Wallet Balance</span>
            <span class="text-2xl font-extrabold text-emerald-800 font-mono">₹{{ number_format($balanceRupees, 2) }}</span>
            <span class="text-[9px] text-slate-500 block uppercase font-semibold mt-0.5">Strict No-Credit Active</span>
        </div>
    </div>

    <!-- Statement Transaction Ledger Table -->
    <div class="border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200 text-[11px]">
                <tr>
                    <th class="p-3">Posting Timestamp</th>
                    <th class="p-3">Transaction / Reference</th>
                    <th class="p-3 text-right">Debit (₹)</th>
                    <th class="p-3 text-right">Credit (₹)</th>
                    <th class="p-3 text-right">Running Balance (₹)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($entries as $entry)
                    @php
                        $isDebit = $entry->amount_paise < 0;
                        $amountAbs = abs($entry->amount_paise) / 100;
                    @endphp
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="p-3 text-slate-600 font-mono text-xs">
                            {{ $entry->created_at->format('d M Y, H:i') }}
                        </td>
                        <td class="p-3">
                            <span class="font-bold text-slate-900 capitalize block">{{ str_replace('_', ' ', $entry->type) }}</span>
                            <span class="text-[10px] text-slate-500 font-mono block">{{ $entry->reference_type }} #{{ $entry->reference_id }}</span>
                        </td>
                        <td class="p-3 text-right font-mono font-bold text-sm {{ $isDebit ? 'text-rose-700' : 'text-slate-300' }}">
                            {{ $isDebit ? '₹' . number_format($amountAbs, 2) : '—' }}
                        </td>
                        <td class="p-3 text-right font-mono font-bold text-sm {{ !$isDebit ? 'text-emerald-700' : 'text-slate-300' }}">
                            {{ !$isDebit ? '₹' . number_format($amountAbs, 2) : '—' }}
                        </td>
                        <td class="p-3 text-right font-mono font-extrabold text-slate-900 text-sm">
                            ₹{{ number_format(($entry->balance_after_paise ?? 0) / 100, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-400 text-xs">
                            No ledger transactions recorded yet for this member account.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between text-xs text-slate-500 pt-1 gap-2">
        <span>* Server-authoritative central ledger statement. All transactions are backed by immutable database records.</span>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs print:hidden no-print cursor-pointer">
            <svg width="16" height="16" class="w-4 h-4 text-slate-600 shrink-0" style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            <span>Print Official Statement</span>
        </button>
    </div>
</div>
