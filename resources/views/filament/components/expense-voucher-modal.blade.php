<div class="space-y-5 text-xs text-slate-700">
    <!-- Voucher Header Card -->
    <div class="rounded-2xl bg-gradient-to-br from-slate-50 to-rose-50/20 border border-slate-200/90 p-5 space-y-3 shadow-2xs">
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200/80 pb-3 gap-2">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-rose-600 to-amber-500 flex items-center justify-center font-extrabold text-white text-sm shadow-xs">
                    PV
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Malabar Club &bull; Disbursement Voucher</span>
                    <div class="font-mono font-extrabold text-slate-900 text-base">{{ $expense->expense_number }}</div>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Disbursement Status</span>
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> {{ ucfirst($expense->status) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-1 text-xs">
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Payee / Beneficiary</span>
                <span class="font-extrabold text-slate-900 block text-sm mt-0.5">{{ $expense->payee }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Voucher Date</span>
                <span class="font-bold text-slate-900 block mt-0.5">{{ $expense->expense_date?->format('d M Y') ?? '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Disbursed Amount</span>
                <span class="font-mono font-extrabold text-rose-600 block text-lg mt-0.5">₹{{ number_format($expense->amount_paise / 100, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Accounting Ledger & Attribution Card -->
    <div class="rounded-2xl border border-slate-200/90 bg-white p-5 space-y-4 shadow-2xs">
        <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-2">
            <span class="w-1.5 h-3.5 bg-amber-500 rounded-full inline-block"></span>
            <span>General Ledger &amp; Department Attribution</span>
        </h4>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">General Ledger Head</span>
                <span class="font-bold text-slate-900 block mt-0.5">{{ $expense->account?->name ?? 'Operating Expenses (5030)' }}</span>
                <span class="text-[10px] text-amber-800 font-mono font-semibold block mt-1">GL Account Code: {{ $expense->account?->code ?? '5030' }}</span>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Operating Department</span>
                <span class="font-bold text-slate-900 block mt-0.5">{{ $expense->department?->name ?? 'General Clubhouse' }}</span>
                <span class="text-[10px] text-slate-500 block mt-1">Attributed for Departmental P&amp;L</span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1 text-xs">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Banking Instrument</span>
                <span class="font-bold text-slate-900 block mt-0.5">
                    {{ match($expense->payment_method) {
                        'bank_transfer' => 'NEFT / RTGS / Bank Transfer',
                        'cheque' => 'Account Payee Cheque',
                        'cash' => 'Counter Petty Cash Float',
                        default => ucfirst($expense->payment_method)
                    } }}
                </span>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Bank UTR / Cheque Ref #</span>
                <span class="font-mono font-bold text-slate-900 block mt-0.5">{{ $expense->document_reference ?? '—' }}</span>
            </div>
        </div>

        @if($expense->notes)
            <div class="pt-2 border-t border-slate-100 text-xs">
                <span class="text-slate-500 font-bold block mb-0.5">Voucher Narration &amp; Notes:</span>
                <span class="text-slate-800 italic block">{{ $expense->notes }}</span>
            </div>
        @endif
    </div>

    <div class="flex items-center justify-between pt-1 text-[11px] text-slate-500">
        <span>* Approved club operational expenditure. Subject to fiscal period closing lock.</span>
        <button type="button" onclick="window.print()" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition flex items-center gap-1 shadow-2xs print:hidden no-print cursor-pointer">
            🖨️ Print Voucher
        </button>
    </div>
</div>
