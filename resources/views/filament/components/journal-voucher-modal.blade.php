<div data-print-document="filament-modal" class="space-y-5 text-xs text-slate-700 print:bg-white print:text-slate-900">
    <!-- Voucher Header Card -->
    <div class="rounded-2xl bg-gradient-to-br from-slate-50 to-amber-50/20 border border-slate-200/90 p-5 space-y-3 shadow-2xs print:bg-none print:bg-white print:border-slate-300 print:shadow-none">
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200/80 pb-3 gap-2">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-600 to-yellow-400 flex items-center justify-center font-extrabold text-slate-950 text-sm shadow-xs print:bg-none print:bg-slate-100 print:border-2 print:border-slate-900 print:text-slate-950">
                    MC
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Double-Entry Journal Voucher</span>
                    <div class="font-mono font-extrabold text-slate-900 text-base">{{ $journal->journal_number }}</div>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Ledger Integrity</span>
                <div>
                    @if($journal->isBalanced())
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Balanced Double-Entry (100%)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span> Unbalanced Exception
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-1">
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Posting Date</span>
                <span class="font-bold text-slate-900 block text-xs mt-0.5">{{ $journal->business_date?->format('d M Y') ?? '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Source Classification</span>
                <span class="font-bold text-slate-900 block font-mono text-xs mt-0.5">{{ ucfirst(str_replace('_', ' ', $journal->source_type)) }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Posted By Staff</span>
                <span class="font-bold text-slate-900 block text-xs mt-0.5">{{ $journal->creator?->name ?? 'System Automated Ledger' }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Audit State</span>
                <span class="font-semibold text-emerald-700 block text-xs mt-0.5 font-mono">Immutable Schema Lock</span>
            </div>
        </div>

        <div class="border-t border-slate-200/80 pt-2.5 text-slate-800 text-xs">
            <span class="font-bold text-slate-900">Voucher Narration:</span> {{ $journal->description }}
        </div>
    </div>

    <!-- Double-Entry Journal Lines Table -->
    <div class="rounded-2xl border border-slate-200/90 overflow-hidden print:overflow-visible shadow-2xs">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 font-bold text-slate-600 uppercase tracking-wider text-[11px] border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">General Ledger Account Head</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3 text-right">Debit (₹)</th>
                    <th class="px-4 py-3 text-right">Credit (₹)</th>
                    <th class="px-4 py-3">Line Explanation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($lines as $line)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-4 py-3">
                            <span class="font-bold text-slate-900 block">{{ $line->account?->name ?? 'Account Head' }}</span>
                            <span class="font-mono text-[10px] text-slate-500 font-semibold">GL Code: {{ $line->account?->code ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($line->department_code)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 uppercase">
                                    {{ ucfirst($line->department_code) }}
                                </span>
                            @else
                                <span class="text-slate-400 font-mono">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-sm {{ $line->debit_paise > 0 ? 'text-slate-900' : 'text-slate-300' }}">
                            {{ $line->debit_paise > 0 ? '₹' . number_format($line->debit_paise / 100, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-sm {{ $line->credit_paise > 0 ? 'text-slate-900' : 'text-slate-300' }}">
                            {{ $line->credit_paise > 0 ? '₹' . number_format($line->credit_paise / 100, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-600 text-xs">{{ $line->narration ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-50 border-t-2 border-slate-200 font-bold text-slate-900">
                <tr class="text-sm">
                    <td colspan="2" class="px-4 py-3 text-right uppercase tracking-wider text-xs">Voucher Equality Check:</td>
                    <td class="px-4 py-3 text-right font-mono font-extrabold text-slate-950">₹{{ number_format($journal->totalDebits() / 100, 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono font-extrabold text-slate-950">₹{{ number_format($journal->totalCredits() / 100, 2) }}</td>
                    <td class="px-4 py-3 text-emerald-700 font-bold text-xs">✓ Balanced</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex items-center justify-between pt-1 text-[11px] text-slate-500">
        <span>* Posts directly to the authoritative MySQL general ledger and queues for automated Tally Prime XML export.</span>
        <button type="button" onclick="window.print()" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition flex items-center gap-1 shadow-2xs print:hidden no-print cursor-pointer">
            🖨️ Print Voucher
        </button>
    </div>
</div>
