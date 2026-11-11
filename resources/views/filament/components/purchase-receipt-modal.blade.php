<div class="space-y-5 text-xs text-slate-700">
    <!-- Header Summary Card -->
    <div class="rounded-2xl bg-gradient-to-br from-slate-50 to-blue-50/20 border border-slate-200/90 p-5 space-y-3 shadow-2xs">
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200/80 pb-3 gap-2">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center font-extrabold text-white text-sm shadow-xs">
                    GRN
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Goods Inward Receipt Note</span>
                    <div class="font-mono font-extrabold text-slate-900 text-base">{{ $receipt->receipt_number }}</div>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Inventory Valuation Status</span>
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Perpetual Costing Updated
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-1 text-xs">
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Supplier / Vendor</span>
                <span class="font-extrabold text-slate-900 block mt-0.5">{{ $receipt->supplier?->name ?? '—' }}</span>
                @if($receipt->supplier?->gstin)
                    <span class="text-[10px] text-slate-500 block font-mono mt-0.5">GSTIN: {{ $receipt->supplier->gstin }}</span>
                @endif
            </div>
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Vendor Invoice #</span>
                <span class="font-bold text-slate-900 block font-mono mt-0.5">{{ $receipt->invoice_number }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Invoice Date</span>
                <span class="font-bold text-slate-900 block mt-0.5">{{ $receipt->invoice_date?->format('d M Y') ?? '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Net Consignment Total</span>
                <span class="font-mono font-extrabold text-emerald-800 block text-sm mt-0.5">₹{{ number_format($receipt->total_net_paise / 100, 2) }}</span>
            </div>
        </div>

        @if($receipt->notes)
            <div class="border-t border-slate-200/80 pt-2 text-slate-600 italic text-xs">
                Delivery Challan &amp; Notes: {{ $receipt->notes }}
            </div>
        @endif
    </div>

    <!-- Consignment Line Items Table -->
    <div class="rounded-2xl border border-slate-200/90 overflow-hidden shadow-2xs">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 font-bold text-slate-600 uppercase tracking-wider text-[11px] border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Inventory Stock Item</th>
                    <th class="px-4 py-3 text-right">Received Qty</th>
                    <th class="px-4 py-3 text-right">Unit Cost (₹)</th>
                    <th class="px-4 py-3 text-right">Input GST %</th>
                    <th class="px-4 py-3 text-right">Net Line Total (₹)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($lines as $line)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-4 py-3">
                            <span class="font-bold text-slate-900 block">{{ $line->item?->name ?? 'Stock Item' }}</span>
                            <span class="text-[10px] text-slate-500 font-mono font-semibold">SKU: {{ $line->item?->sku ?? '—' }} &bull; Base Dispense Unit: {{ $line->item?->base_unit ?? 'unit' }}</span>
                            @if(($line->discount_paise ?? 0) > 0)
                                <span class="inline-flex items-center px-1.5 py-0.5 mt-1 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    Supplier Cash Discount: -₹{{ number_format($line->discount_paise / 100, 2) }}
                                </span>
                            @endif
                            @if(!empty($line->scheme_notes))
                                <span class="text-[10px] text-slate-500 italic block mt-0.5">{{ $line->scheme_notes }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 text-sm">
                            {{ number_format($line->quantity, 3) }} <span class="text-xs font-normal text-slate-500">{{ $line->item?->base_unit }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700">
                            <div>₹{{ number_format($line->unit_cost_paise / 100, 2) }}</div>
                            @if(($line->gross_unit_cost_paise ?? 0) > $line->unit_cost_paise)
                                <div class="text-[10px] text-slate-400 line-through">₹{{ number_format($line->gross_unit_cost_paise / 100, 2) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-slate-600">{{ number_format($line->tax_percent, 1) }}%</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 text-sm">₹{{ number_format($line->line_total_paise / 100, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-50 border-t-2 border-slate-200 font-bold text-slate-900">
                @if(($receipt->total_discount_paise ?? 0) > 0)
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-right text-emerald-700 uppercase tracking-wider text-xs">Supplier Cash / Scheme Discount Applied:</td>
                        <td class="px-4 py-2 text-right font-mono text-emerald-700 font-semibold">-₹{{ number_format($receipt->total_discount_paise / 100, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td colspan="4" class="px-4 py-2.5 text-right uppercase tracking-wider text-xs">Net Stock Cost Subtotal:</td>
                    <td class="px-4 py-2.5 text-right font-mono font-bold">₹{{ number_format($receipt->total_gross_paise / 100, 2) }}</td>
                </tr>
                @if($receipt->total_tax_paise > 0)
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-right text-slate-600 uppercase tracking-wider text-xs">Input GST Credit (2030):</td>
                        <td class="px-4 py-2 text-right font-mono text-slate-600 font-semibold">₹{{ number_format($receipt->total_tax_paise / 100, 2) }}</td>
                    </tr>
                @endif
                <tr class="text-sm">
                    <td colspan="4" class="px-4 py-3 text-right uppercase tracking-wider font-extrabold text-slate-950">Net Inward Payable:</td>
                    <td class="px-4 py-3 text-right font-mono font-extrabold text-emerald-800 text-base">₹{{ number_format($receipt->total_net_paise / 100, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex items-center justify-between pt-1 text-[11px] text-slate-500">
        <span>* Automatically debited Inventory Stock (1030) and Input GST (2030), and credited Bank Clearing (1020).</span>
        <button type="button" onclick="window.print()" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition flex items-center gap-1 shadow-2xs print:hidden no-print cursor-pointer">
            🖨️ Print GRN Slip
        </button>
    </div>
</div>
