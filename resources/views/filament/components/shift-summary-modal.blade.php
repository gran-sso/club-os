<div data-print-document="filament-modal" class="space-y-6 text-sm text-slate-800 print:bg-white print:text-slate-900" x-data="{ currentTab: 'bills', showSlipPreview: false }">
    <!-- 1. Shift Header & Overview Card -->
    <div class="p-5 bg-gradient-to-br from-slate-50 to-amber-50/30 rounded-2xl border border-slate-200/90 shadow-2xs print:bg-none print:bg-white print:border-slate-300 print:shadow-none">
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200/80 pb-3 gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-600 to-yellow-400 flex items-center justify-center font-black text-slate-950 text-base shadow-xs print:bg-none print:bg-slate-100 print:border-2 print:border-slate-900">
                    SFT
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-extrabold text-base tracking-tight text-slate-900">Shift #{{ $details['shift_number'] }}</h3>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider {{ $details['is_closed'] ? 'bg-slate-900 text-amber-400' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' }}">
                            {{ $details['status_label'] }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">
                        {{ $details['counter_name'] }} &bull; Cashier: <strong class="text-slate-700">{{ $details['cashier_name'] }}</strong>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 print:hidden no-print">
                <button type="button" @click="showSlipPreview = !showSlipPreview"
                    class="px-3.5 py-1.5 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                    <span>📄</span>
                    <span x-text="showSlipPreview ? 'Hide Handover Slip' : 'View Handover Slip'"></span>
                </button>
                <button type="button" onclick="window.print()"
                    class="px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                    <span>🖨️</span>
                    <span>Print Summary</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 pt-4 text-xs">
            <div>
                <span class="text-slate-500 font-bold uppercase tracking-wider block text-[10px]">Business Date</span>
                <span class="font-semibold text-slate-900 block mt-0.5">{{ $details['business_date'] }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-bold uppercase tracking-wider block text-[10px]">Opened At</span>
                <span class="font-semibold text-slate-900 block mt-0.5">{{ $details['opened_at'] }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-bold uppercase tracking-wider block text-[10px]">Closed At</span>
                <span class="font-semibold text-slate-900 block mt-0.5">{{ $details['closed_at'] }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-bold uppercase tracking-wider block text-[10px]">Operating Duration</span>
                <span class="font-mono font-bold text-amber-800 block mt-0.5">{{ $details['duration'] }}</span>
            </div>
        </div>
    </div>

    <!-- 80mm Slip Format Preview (Toggled by Button) -->
    @php
        $thermalPrinter = app(\App\Domain\Integrations\Contracts\ReceiptPrinterInterface::class);
        $slipContent = $thermalPrinter->render80mmShiftHandoverSlip($details);
    @endphp
    <div x-show="showSlipPreview" x-cloak class="p-4 bg-amber-50/40 rounded-2xl border border-dashed border-amber-300 space-y-2">
        <div class="flex items-center justify-between text-xs text-amber-900 font-bold">
            <span>80mm Thermal Slip Preview (Exact layout printed for physical cash bag)</span>
            <button type="button" @click="showSlipPreview = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">&times; Close</button>
        </div>
        <pre class="m-0 bg-white p-4 rounded-xl border border-slate-200 font-mono text-[11px] leading-relaxed whitespace-pre overflow-x-auto shadow-inner text-slate-800 max-h-72">{{ $slipContent }}</pre>
    </div>

    <!-- 2. Financial Turnover & Tender Inflow KPI Cards (Mobile-First Grid) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <!-- Billed Turnover -->
        <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase text-slate-400 block">Total Turnover</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 font-mono">{{ $details['billed_turnover']['total_bills'] }} Bills</span>
            </div>
            <span class="text-lg sm:text-xl font-black text-slate-900 mt-1 block font-mono">
                ₹{{ $details['billed_turnover']['net_rupees'] }}
            </span>
            <span class="text-[10px] text-slate-500 mt-0.5 block">GST: ₹{{ $details['billed_turnover']['tax_rupees'] }}</span>
        </div>

        <!-- Cash Inflow -->
        <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
            <span class="text-[10px] sm:text-[11px] font-bold uppercase text-slate-400 block">Physical Cash Inflow</span>
            <span class="text-lg sm:text-xl font-black text-slate-900 mt-1 block font-mono">
                ₹{{ $details['drawer_reconciliation']['total_cash_inflow_rupees'] }}
            </span>
            <span class="text-[10px] text-slate-500 mt-0.5 block">Bills + Wallet Recharges</span>
        </div>

        <!-- Digital / UPI Inflow -->
        <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
            <span class="text-[10px] sm:text-[11px] font-bold uppercase text-slate-400 block">Digital UPI / Cards</span>
            <span class="text-lg sm:text-xl font-black text-blue-900 mt-1 block font-mono">
                ₹{{ number_format(($details['tender_breakdown']['digital_sales_paise'] + $details['wallet_topups']['digital_topups_paise']) / 100, 2) }}
            </span>
            <span class="text-[10px] text-slate-500 mt-0.5 block">Bank / QR Clearing</span>
        </div>

        <!-- Prepaid Wallet Redemptions -->
        <div class="p-4 bg-white border border-amber-200 bg-amber-50/20 rounded-2xl shadow-xs">
            <span class="text-[10px] sm:text-[11px] font-bold uppercase text-amber-800 block">Prepaid Redemptions</span>
            <span class="text-lg sm:text-xl font-black text-amber-900 mt-1 block font-mono">
                ₹{{ $details['tender_breakdown']['prepaid_sales_rupees'] }}
            </span>
            <span class="text-[10px] text-amber-700 mt-0.5 block">Internal Ledger Debits</span>
        </div>
    </div>

    <!-- 3. Physical Cash Drawer Lifecycle & Reconciliation Box -->
    <div class="p-5 bg-white border border-slate-200 rounded-2xl shadow-xs space-y-4">
        <div class="flex flex-wrap items-center justify-between border-b border-slate-100 pb-3 gap-2">
            <div>
                <h4 class="font-extrabold text-xs uppercase tracking-wider text-slate-800">Physical Cash Drawer Lifecycle &amp; Reconciliation</h4>
                <p class="text-[11px] text-slate-500">Starting float, collections, mid-shift drops and physical till balancing.</p>
            </div>
            <div>
                @php
                    $dStatus = $details['drawer_reconciliation']['discrepancy_status'];
                @endphp
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase {{ $dStatus === 'Balanced' ? 'bg-emerald-50 text-emerald-800 border border-emerald-300' : ($dStatus === 'Shortage' ? 'bg-rose-50 text-rose-800 border border-rose-300' : ($dStatus === 'Surplus' ? 'bg-amber-50 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-700 border border-slate-300')) }}">
                    <span class="w-2 h-2 rounded-full {{ $dStatus === 'Balanced' ? 'bg-emerald-500' : ($dStatus === 'Shortage' ? 'bg-rose-500' : ($dStatus === 'Surplus' ? 'bg-amber-500' : 'bg-slate-400')) }}"></span>
                    <span>Till: {{ $dStatus }}</span>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">1. Starting Float</span>
                <span class="font-mono font-bold text-sm text-slate-900 block mt-1">₹{{ $details['drawer_reconciliation']['opening_float_rupees'] }}</span>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">(+) Cash Sales</span>
                <span class="font-mono font-bold text-sm text-emerald-700 block mt-1">+₹{{ $details['drawer_reconciliation']['cash_sales_rupees'] }}</span>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">(+) Cash Top-Ups</span>
                <span class="font-mono font-bold text-sm text-emerald-700 block mt-1">+₹{{ $details['drawer_reconciliation']['cash_topups_rupees'] }}</span>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">(-) Cash Drops</span>
                <span class="font-mono font-bold text-sm text-rose-700 block mt-1">-₹{{ $details['drawer_reconciliation']['cash_drops_rupees'] }}</span>
            </div>

            <div class="p-3 bg-amber-50/60 rounded-xl border border-amber-200">
                <span class="text-[10px] uppercase font-bold text-amber-900 block">Expected Cash</span>
                <span class="font-mono font-black text-sm text-amber-950 block mt-1">₹{{ $details['drawer_reconciliation']['expected_cash_rupees'] }}</span>
            </div>

            <div class="p-3 bg-slate-900 text-white rounded-xl">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Counted Cash</span>
                <span class="font-mono font-black text-sm text-amber-400 block mt-1">₹{{ $details['drawer_reconciliation']['counted_cash_rupees'] }}</span>
            </div>
        </div>

        @if (!empty($details['drawer_reconciliation']['notes']))
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                <span class="font-bold text-slate-700 uppercase text-[10px] block">Cashier Handover Notes / Remarks:</span>
                <p class="text-slate-600 mt-0.5 italic">{{ $details['drawer_reconciliation']['notes'] }}</p>
            </div>
        @endif
    </div>

    <!-- 4. Hospitality Attributions: Staff Tips & Food Contractor Breakdown -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Duty Staff Tips Collected -->
        <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="font-bold text-xs uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                    <span>🎖️</span> Duty Staff Tips Collected (Account 2040)
                </span>
                <span class="font-mono font-black text-sm text-slate-900">₹{{ $details['staff_tips']['total_tips_rupees'] }}</span>
            </div>
            @if (!empty($details['staff_tips']['breakdown']))
                <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1 text-xs">
                    @foreach ($details['staff_tips']['breakdown'] as $wt)
                        <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <span class="font-bold text-slate-900">{{ $wt['waiter_name'] }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">({{ $wt['waiter_code'] }}) &bull; {{ $wt['bills_count'] }} bill(s)</span>
                            </div>
                            <span class="font-mono font-bold text-emerald-700">₹{{ number_format($wt['total_tip_paise'] / 100, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-slate-400 italic">No voluntary tips were added on this shift.</p>
            @endif
        </div>

        <!-- Revenue Division: Club Bar vs External Food Contractor -->
        <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="font-bold text-xs uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                    <span>🍽️</span> Revenue Attribution Split
                </span>
                <span class="text-[10px] text-slate-400 uppercase font-semibold">Net Turnover</span>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Club Bar &amp; Beverages</span>
                    <span class="font-mono font-extrabold text-sm text-slate-900 block mt-1">₹{{ $details['billed_turnover']['club_bar_rupees'] }}</span>
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Direct Club Revenue</span>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">External Food Contractor</span>
                    <span class="font-mono font-extrabold text-sm text-amber-900 block mt-1">₹{{ $details['billed_turnover']['food_contractor_rupees'] }}</span>
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Contractor Settlement</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Interactive Itemized Activity Navigation Tabs (Mobile-Friendly) -->
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-200 pb-2 print:hidden no-print">
            <button type="button" @click="currentTab = 'bills'"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                :class="currentTab === 'bills' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
                <span>🧾</span>
                <span>Billed Transactions ({{ $details['billed_turnover']['all_bills_count'] }})</span>
            </button>

            <button type="button" @click="currentTab = 'topups'"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                :class="currentTab === 'topups' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
                <span>💰</span>
                <span>Member Wallet Top-Ups ({{ $details['wallet_topups']['total_count'] }})</span>
            </button>

            <button type="button" @click="currentTab = 'movements'"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                :class="currentTab === 'movements' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
                <span>🏧</span>
                <span>Drawer Cash Drops &amp; Floats ({{ $details['movements']->count() }})</span>
            </button>
        </div>

        <!-- TAB 1: Billed Transactions -->
        <div x-show="currentTab === 'bills'" class="border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs">
            <div class="overflow-x-auto max-h-80">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200 text-[11px] sticky top-0">
                        <tr>
                            <th class="p-3">Bill #</th>
                            <th class="p-3">Time</th>
                            <th class="p-3">Customer / Account</th>
                            <th class="p-3">Table / Bearer</th>
                            <th class="p-3">Tender</th>
                            <th class="p-3 text-right">Tip (₹)</th>
                            <th class="p-3 text-right">Net Bill (₹)</th>
                            <th class="p-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($details['bills'] as $b)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="p-3 font-mono font-bold text-slate-900">{{ $b->bill_number }}</td>
                                <td class="p-3 font-mono text-[11px] text-slate-500">{{ $b->created_at?->timezone(config('club.timezone', 'Asia/Kolkata'))->format('h:i A') }}</td>
                                <td class="p-3 font-semibold text-slate-800">
                                    <div class="truncate max-w-[150px]" title="{{ $b->customer_display_name }}">{{ $b->customer_display_name }}</div>
                                    @if ($b->accompanying_guest_name)
                                        <div class="text-[10px] text-amber-700 truncate">👤 {{ $b->accompanying_guest_name }}</div>
                                    @endif
                                </td>
                                <td class="p-3 text-[11px]">
                                    <div>{{ $b->table_number ?: 'Counter' }}</div>
                                    @if ($b->waiter)
                                        <div class="text-[10px] text-slate-400">{{ $b->waiter->name }}</div>
                                    @endif
                                </td>
                                <td class="p-3 uppercase font-bold text-[10px] text-slate-600">
                                    {{ $b->tender_type?->value ?? ($b->tender_type ?: 'Paid') }}
                                </td>
                                <td class="p-3 text-right font-mono text-emerald-700">
                                    {{ $b->tip_amount_paise > 0 ? ('₹' . number_format($b->tip_amount_paise / 100, 2)) : '—' }}
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-slate-900">
                                    ₹{{ number_format($b->net_amount_paise / 100, 2) }}
                                </td>
                                <td class="p-3 text-center">
                                    @php
                                        $bStatus = $b->status?->value ?? $b->status;
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-extrabold uppercase {{ $bStatus === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($bStatus === 'unsettled' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $bStatus }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-4 text-center text-slate-400 italic">No bills recorded on this shift.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: Member Wallet Top-Ups -->
        <div x-show="currentTab === 'topups'" x-cloak class="border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs">
            <div class="overflow-x-auto max-h-80">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200 text-[11px] sticky top-0">
                        <tr>
                            <th class="p-3">Receipt #</th>
                            <th class="p-3">Time</th>
                            <th class="p-3">Member Account</th>
                            <th class="p-3">Tender Method</th>
                            <th class="p-3 text-right">Top-Up Amount (₹)</th>
                            <th class="p-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($details['topup_receipts'] as $tr)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="p-3 font-mono font-bold text-slate-900">{{ $tr->receipt_number }}</td>
                                <td class="p-3 font-mono text-[11px] text-slate-500">{{ $tr->created_at?->timezone(config('club.timezone', 'Asia/Kolkata'))->format('h:i A') }}</td>
                                <td class="p-3 font-semibold text-slate-800">
                                    {{ $tr->customer?->name ?? 'Member' }}
                                    @if ($tr->customer?->membership_number)
                                        <span class="font-mono text-[10px] text-amber-700">({{ $tr->customer->membership_number }})</span>
                                    @endif
                                </td>
                                <td class="p-3 uppercase font-bold text-[10px] text-slate-600">
                                    {{ $tr->tender_type?->value ?? $tr->tender_type }}
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-emerald-700">
                                    ₹{{ number_format($tr->amount_paise / 100, 2) }}
                                </td>
                                <td class="p-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800">
                                        Confirmed
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center text-slate-400 italic">No prepaid wallet top-ups processed on this shift.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: Drawer Cash Movements -->
        <div x-show="currentTab === 'movements'" x-cloak class="border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs">
            <div class="overflow-x-auto max-h-80">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200 text-[11px] sticky top-0">
                        <tr>
                            <th class="p-3">Time</th>
                            <th class="p-3">Movement Type</th>
                            <th class="p-3 text-right">Amount (₹)</th>
                            <th class="p-3">Staff / Authorizer</th>
                            <th class="p-3">Remarks / Handover Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($details['movements'] as $mv)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="p-3 font-mono text-[11px] text-slate-500">{{ $mv->created_at?->timezone(config('club.timezone', 'Asia/Kolkata'))->format('h:i A') }}</td>
                                <td class="p-3 uppercase font-bold text-[11px]">
                                    @if ($mv->type === 'float_in')
                                        <span class="text-emerald-700">Additional Float In (+)</span>
                                    @elseif ($mv->type === 'cash_drop')
                                        <span class="text-rose-700">Mid-Shift Cash Drop (-)</span>
                                    @else
                                        <span class="text-slate-700">{{ str_replace('_', ' ', $mv->type) }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-slate-900">
                                    ₹{{ number_format($mv->amount_paise / 100, 2) }}
                                </td>
                                <td class="p-3 text-slate-700">
                                    {{ $mv->createdByUser?->name ?? 'System Staff' }}
                                </td>
                                <td class="p-3 text-slate-500 italic">
                                    {{ $mv->notes ?: '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4 text-center text-slate-400 italic">No cash drops or float adjustments recorded on this shift.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

