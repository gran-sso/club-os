<div class="space-y-4 text-xs text-slate-800">
    <!-- Account Summary Header -->
    <div class="p-4 bg-slate-900 text-white rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-extrabold tracking-tight text-white">{{ $account->account_name }}</span>
                @if ($account->is_primary)
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-400 text-slate-950">Primary</span>
                @endif
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">
                {{ $account->bank_name }} &bull; {{ $account->branch_name }} &bull; IFSC: <span class="font-mono font-bold text-slate-300">{{ $account->ifsc_code }}</span>
            </div>
            <div class="font-mono text-xs text-slate-300 mt-1">
                A/c: <span class="font-bold tracking-wider">{{ $account->account_number }}</span>
            </div>
        </div>
        <div class="sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-800">
            <span class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block">Cleared Bank Balance</span>
            <span class="font-mono font-black text-emerald-400 text-lg">₹{{ number_format($account->current_balance, 2) }}</span>
        </div>
    </div>

    <!-- Passbook / Ledger Transactions Table -->
    @php
        $transactions = $account->transactions()->with(['journalHeader', 'transferToAccount'])->latest('business_date')->latest('id')->take(50)->get();
    @endphp

    @if ($transactions->isEmpty())
        <div class="p-8 text-center text-slate-400 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
            <span class="text-2xl block mb-1">🏦</span>
            <p class="font-bold text-slate-600">No bank transactions posted yet.</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Cash deposits and disbursements will appear here once posted.</p>
        </div>
    @else
        <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-100/80 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="p-2.5">Date</th>
                            <th class="p-2.5">Voucher #</th>
                            <th class="p-2.5">Transaction Type</th>
                            <th class="p-2.5">Reference / Slip</th>
                            <th class="p-2.5">Description &amp; Handled By</th>
                            <th class="p-2.5 text-right">Inflow (Deposit)</th>
                            <th class="p-2.5 text-right">Outflow (Payout)</th>
                            <th class="p-2.5 text-center">GL Journal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($transactions as $txn)
                            @php
                                $isInflow = in_array($txn->type, ['cash_deposit', 'adjustment', 'interest_credit']);
                                if ($txn->type === 'inter_bank_transfer') {
                                    $isInflow = $txn->transfer_to_bank_account_id === $account->id;
                                }
                                $typeLabel = match($txn->type) {
                                    'cash_deposit' => 'Cash Deposit',
                                    'inter_bank_transfer' => 'Inter-Bank Transfer',
                                    'expense_payout' => 'Expense Payout',
                                    'contractor_payout' => 'Contractor Payout',
                                    default => ucfirst(str_replace('_', ' ', $txn->type)),
                                };
                                $typeColor = $isInflow ? 'emerald' : 'slate';
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition text-xs">
                                <td class="p-2.5 font-mono text-slate-600">{{ $txn->business_date?->format('d M Y') ?? '—' }}</td>
                                <td class="p-2.5 font-mono font-bold text-slate-900">{{ $txn->transaction_number }}</td>
                                <td class="p-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-{{ $typeColor }}-50 text-{{ $typeColor }}-800 border border-{{ $typeColor }}-200">
                                        {{ $typeLabel }}
                                    </span>
                                </td>
                                <td class="p-2.5 font-mono font-semibold text-slate-700">{{ $txn->reference_number ?? '—' }}</td>
                                <td class="p-2.5 text-slate-600">
                                    <div class="line-clamp-1 font-medium text-slate-800">{{ $txn->description }}</div>
                                    @if ($txn->deposited_by)
                                        <div class="text-[10px] text-slate-400">By: {{ $txn->deposited_by }}</div>
                                    @endif
                                </td>
                                <td class="p-2.5 text-right font-mono font-bold text-emerald-700">
                                    {{ $isInflow ? '₹' . number_format($txn->amount, 2) : '—' }}
                                </td>
                                <td class="p-2.5 text-right font-mono font-bold text-rose-700">
                                    {{ !$isInflow ? '₹' . number_format($txn->amount, 2) : '—' }}
                                </td>
                                <td class="p-2.5 text-center font-mono text-[11px] text-slate-500">
                                    {{ $txn->journalHeader?->journal_number ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

