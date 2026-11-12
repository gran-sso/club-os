<div data-print-document="filament-modal" class="space-y-5 text-sm text-slate-800 print:bg-white print:text-slate-900">
    <!-- Club Header & Bill Metadata -->
    <div class="p-5 bg-gradient-to-br from-slate-50 to-amber-50/30 rounded-2xl border border-slate-200/90 shadow-2xs print:bg-none print:bg-white print:border-slate-300 print:shadow-none">
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200/80 pb-3 gap-2">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-600 to-yellow-400 flex items-center justify-center font-extrabold text-slate-950 text-sm shadow-xs print:bg-none print:bg-slate-100 print:border-2 print:border-slate-900 print:text-slate-950">
                    MC
                </div>
                <div>
                    <h3 class="font-extrabold text-sm tracking-tight text-slate-900 uppercase">{{ \App\Domain\Shared\Models\SystemSetting::get('club_name', 'Malabar Club') }}</h3>
                    <p class="text-[10px] text-slate-500 font-medium">{{ \App\Domain\Shared\Models\SystemSetting::get('club_address', 'Vadakkencherry, Palakkad, Kerala') }} &bull; GSTIN: {{ \App\Domain\Shared\Models\SystemSetting::get('club_gstin', '32AABCM1234F1Z5') }} &bull; Official Tax Invoice</p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Bill Status</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ ($bill->status?->value ?? $bill->status) === 'paid' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ ($bill->status?->value ?? $bill->status) === 'paid' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    {{ $bill->status?->value ?? $bill->status }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-3.5 text-xs">
            <div>
                <span class="text-slate-500 font-bold uppercase tracking-wider block text-[10px]">Invoice / Bill #</span>
                <span class="font-mono font-extrabold text-slate-900 text-sm block mt-0.5">{{ $bill->bill_number }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-bold uppercase tracking-wider block text-[10px]">Fiscal Business Date</span>
                <span class="font-semibold text-slate-800 block mt-0.5">{{ $bill->business_date ? \Carbon\Carbon::parse($bill->business_date)->format('d M Y') : '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-bold uppercase tracking-wider block text-[10px]">Operating Department</span>
                <span class="font-bold text-amber-800 capitalize block mt-0.5">{{ str_replace('_', ' ', $bill->department_code) }}</span>
            </div>
            <div>
                <span class="text-slate-500 font-bold uppercase tracking-wider block text-[10px]">Customer / Account</span>
                <span class="font-semibold text-slate-900 block mt-0.5 truncate" title="{{ $bill->customer_display_name }}">
                    {{ $bill->customer_display_name }}
                </span>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="border border-slate-200/90 rounded-2xl overflow-hidden print:overflow-visible shadow-2xs">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200 text-[11px]">
                <tr>
                    <th class="p-3">Item / Service Description</th>
                    <th class="p-3 text-right">Unit Rate (₹)</th>
                    <th class="p-3 text-center">Qty</th>
                    <th class="p-3 text-right">GST (₹)</th>
                    <th class="p-3 text-right">Line Total (₹)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach ($bill->lines as $line)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="p-3 font-semibold text-slate-900">
                            <div>{{ $line->item_name ?: 'Service Line' }}</div>
                            @if (! empty($line->serving_name))
                                <span class="text-[10px] text-slate-500 font-normal block">Dispense Serving: {{ $line->serving_name }}</span>
                            @endif
                            @if (($line->discount_amount_paise ?? 0) > 0 || (float) ($line->free_quantity ?? 0) > 0)
                                <span class="inline-flex items-center px-1.5 py-0.5 mt-1 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    Offer: {{ $line->promotion_name ?? 'Promo Discount' }}
                                    @if ((float) ($line->free_quantity ?? 0) > 0)
                                        ({{ (float) $line->free_quantity }} Free)
                                    @endif
                                    &bull; -₹{{ number_format(($line->discount_amount_paise ?? 0) / 100, 2) }}
                                </span>
                            @endif
                        </td>
                        <td class="p-3 text-right font-mono text-slate-700">₹{{ number_format(($line->unit_price_paise ?? 0) / 100, 2) }}</td>
                        <td class="p-3 text-center font-bold text-slate-900">{{ $line->quantity }}</td>
                        <td class="p-3 text-right text-slate-500 font-mono">₹{{ number_format($line->tax_amount_paise / 100, 2) }}</td>
                        <td class="p-3 text-right font-bold text-slate-900 font-mono text-sm">₹{{ number_format($line->line_total_paise / 100, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Financial Totals Breakdown & Confirmed Tenders -->
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-start pt-1">
        <!-- Confirmed Tenders -->
        <div class="sm:col-span-6 space-y-2">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Confirmed Payments</span>
            @if ($bill->receipts->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($bill->receipts as $rcp)
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs flex justify-between items-center shadow-2xs">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <div>
                                    <span class="font-bold text-slate-800 capitalize">{{ str_replace('_', ' ', $rcp->tender_type?->value ?? $rcp->tender_type) }}</span>
                                    <span class="text-[10px] text-slate-500 font-mono block">{{ $rcp->receipt_number }}</span>
                                </div>
                            </div>
                            <div class="font-mono font-extrabold text-emerald-800 text-sm">
                                ₹{{ number_format($rcp->amount_paise / 100, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-3 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs text-slate-500 italic">
                    No confirmed receipts posted yet.
                </div>
            @endif
        </div>

        <!-- Totals Summary Box -->
        <div class="sm:col-span-6">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/90 space-y-2 text-xs shadow-2xs">
                <div class="flex justify-between text-slate-600">
                    <span>Gross Subtotal:</span>
                    <span class="font-mono font-semibold">₹{{ number_format($bill->gross_amount_paise / 100, 2) }}</span>
                </div>
                @if (($bill->discount_amount_paise ?? 0) > 0)
                    <div class="flex justify-between text-emerald-700 font-semibold">
                        <span>Promo / Offer Discount:</span>
                        <span class="font-mono">-₹{{ number_format($bill->discount_amount_paise / 100, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-slate-600">
                    <span>GST Tax:</span>
                    <span class="font-mono font-semibold">₹{{ number_format($bill->tax_amount_paise / 100, 2) }}</span>
                </div>
                <div class="pt-2 border-t border-slate-200 flex justify-between items-center font-bold text-slate-900 text-sm">
                    <span class="text-xs uppercase tracking-wider">Net Amount Settled:</span>
                    <span class="font-mono text-base font-extrabold text-emerald-800">₹{{ number_format($bill->net_amount_paise / 100, 2) }}</span>
                </div>
            </div>

            <div class="flex justify-end pt-3 print:hidden no-print">
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs rounded-xl transition flex items-center gap-1.5 shadow-2xs print:hidden no-print cursor-pointer">
                    <svg width="16" height="16" class="w-4 h-4 text-slate-600 shrink-0" style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>Print Bill Receipt</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Official Statutory Receipt Note -->
    <div class="pt-3 border-t border-slate-200/80 text-center">
        <p class="text-[11px] text-slate-500 font-medium italic">
            {{ \App\Domain\Shared\Models\SystemSetting::get('receipt_footer_note', 'Thank you for visiting Malabar Club. Alcohol consumption is injurious to health.') }}
        </p>
    </div>
</div>
