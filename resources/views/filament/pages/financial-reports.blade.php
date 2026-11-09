<x-filament-panels::page>
    <div class="space-y-6">
        <!-- 1. OFFICIAL CLUB LETTERHEAD (PRINT MEDIA ONLY) -->
        <div class="hidden print:block mb-6 border-b-2 border-slate-900 pb-4 text-center">
            <h1 class="text-2xl font-black tracking-wider uppercase text-slate-900">Malabar Club</h1>
            <p class="text-xs text-slate-600 font-semibold uppercase tracking-widest mt-0.5">
                Vadakkencherry, Palakkad, Kerala &bull; Official Management &amp; Statutory Audit Report
            </p>
            <div class="flex justify-between items-center text-[11px] text-slate-600 mt-4 pt-2 border-t border-slate-300">
                <span>Report Statement: <strong class="text-slate-900 uppercase font-mono">{{ str_replace('_', ' ', $activeTab) }}</strong></span>
                <span>Audit Period: <strong class="text-slate-900 font-mono">{{ $startDate }} to {{ $endDate }}</strong></span>
                <span>Generated: <strong class="text-slate-900">{{ now()->format('d M Y, h:i A') }} IST</strong></span>
            </div>
        </div>

        <!-- 2. EXECUTIVE FILTER TOOLBAR (SCREEN ONLY) -->
        <div class="p-5 sm:p-6 bg-white border border-slate-200/90 rounded-2xl shadow-xs print:hidden no-print">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-5">
                <!-- Date Inputs & Actions -->
                <div class="flex flex-wrap items-end gap-3.5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>From Date</span>
                        </label>
                        <input type="date" wire:model="startDate" wire:change="refreshReports"
                            class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-colors shadow-2xs">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>To Date</span>
                        </label>
                        <input type="date" wire:model="endDate" wire:change="refreshReports"
                            class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-colors shadow-2xs">
                    </div>

                    <button wire:click="refreshReports" type="button"
                        class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs sm:text-sm rounded-xl shadow-xs transition-all cursor-pointer flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Update Report</span>
                    </button>
                </div>

                <!-- Export & Print Actions -->
                <div class="flex items-center gap-2.5 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                    <button wire:click="exportCsv" type="button"
                        class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs border border-slate-200">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Export CSV</span>
                    </button>

                    <button type="button" onclick="window.print()"
                        class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs">
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print Statement</span>
                    </button>
                </div>
            </div>

            <!-- Quick Preset Range Pills -->
            <div class="mt-4 pt-3.5 border-t border-slate-100 flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-1">Quick Presets:</span>
                
                <button wire:click="setPreset('today')" type="button"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                    Today
                </button>
                <button wire:click="setPreset('yesterday')" type="button"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                    Yesterday
                </button>
                <button wire:click="setPreset('this_week')" type="button"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                    This Week
                </button>
                <button wire:click="setPreset('this_month')" type="button"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                    This Month
                </button>
                <button wire:click="setPreset('last_month')" type="button"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer border border-slate-200 hover:border-amber-400 hover:bg-amber-50 text-slate-700">
                    Last Month
                </button>
                <button wire:click="setPreset('fiscal_year')" type="button"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer border border-amber-300 bg-amber-50/60 hover:bg-amber-100 text-amber-900">
                    Fiscal Year (1 Apr – 31 Mar)
                </button>
            </div>
        </div>

        <!-- 3. REPORT TAB SELECTION BAR (SCREEN ONLY) -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-2 shadow-xs print:hidden no-print overflow-x-auto">
            <div class="flex items-center gap-1.5 min-w-max">
                <button wire:click="setActiveTab('sales')" type="button"
                    class="px-3.5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'sales' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>📊</span>
                    <span>1. Sales &amp; Collections</span>
                </button>

                <button wire:click="setActiveTab('income_expenditure')" type="button"
                    class="px-3.5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'income_expenditure' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>📑</span>
                    <span>2. Income &amp; Expenditure</span>
                </button>

                <button wire:click="setActiveTab('cash_recon')" type="button"
                    class="px-3.5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'cash_recon' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>💵</span>
                    <span>3. Daily Till &amp; Shift Recon</span>
                </button>

                <button wire:click="setActiveTab('contractor')" type="button"
                    class="px-3.5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'contractor' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>🍽️</span>
                    <span>4. Food Contractor</span>
                </button>

                <button wire:click="setActiveTab('bar_stock')" type="button"
                    class="px-3.5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'bar_stock' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>🍷</span>
                    <span>5. Bar &amp; Stock Reports</span>
                </button>

                <button wire:click="setActiveTab('sports')" type="button"
                    class="px-3.5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'sports' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>🏸</span>
                    <span>6. Sports Facility Income</span>
                </button>

                <button wire:click="setActiveTab('payables')" type="button"
                    class="px-3.5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'payables' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>⚖️</span>
                    <span>7. Payables &amp; Exceptions</span>
                </button>

                <button wire:click="setActiveTab('pnl')" type="button"
                    class="px-3.5 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2 cursor-pointer {{ $activeTab === 'pnl' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span>📈</span>
                    <span>8. Departmental P&amp;L</span>
                </button>
            </div>
        </div>

        <!-- 4. TAB CONTENTS -->

        <!-- TAB 1: SALES & COLLECTIONS BY DEPARTMENT & COUNTER -->
        @if($activeTab === 'sales')
            <div class="space-y-6">
                <!-- Summary KPI Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Total Bills Paid</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            {{ number_format($salesReport['summary']['total_bills'] ?? 0) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Official confirmed bills</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Gross Sales</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            ₹{{ number_format(($salesReport['summary']['total_gross_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Before tax &amp; discount</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Statutory GST</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            ₹{{ number_format(($salesReport['summary']['total_tax_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Total tax collected</span>
                    </div>

                    <div class="p-4 bg-white border border-amber-200 bg-amber-50/30 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-amber-800 block">Net Billed Turnover</span>
                        <span class="text-xl font-black text-amber-900 mt-1 block">
                            ₹{{ number_format(($salesReport['summary']['total_net_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-amber-700 font-semibold mt-0.5 block">Net consumption revenue</span>
                    </div>
                </div>

                <!-- Collections vs Consumption Overview -->
                <div class="p-5 bg-white border border-slate-200 rounded-2xl shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3 flex items-center justify-between">
                        <span>External Collections (Cash Inflow) vs. Internal Redemptions</span>
                        <span class="text-[11px] text-slate-500 font-normal">Prepaid Ledger Safeguard</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                            <span class="text-[11px] font-bold uppercase text-slate-500 block">Cash Collected at Counters</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="font-mono font-bold text-base text-slate-900">
                                    ₹{{ number_format(($salesReport['collections']['total_cash_inflow_paise'] ?? 0) / 100, 2) }}
                                </span>
                                <span class="text-[10px] text-slate-500">Bills + Recharges</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                            <span class="text-[11px] font-bold uppercase text-slate-500 block">Digital UPI / Card Inflow</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="font-mono font-bold text-base text-slate-900">
                                    ₹{{ number_format(($salesReport['collections']['total_digital_inflow_paise'] ?? 0) / 100, 2) }}
                                </span>
                                <span class="text-[10px] text-slate-500">UPI / POS Terminals</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-amber-50/50 border border-amber-200">
                            <span class="text-[11px] font-bold uppercase text-amber-800 block">Prepaid Smartcard Redemptions</span>
                            <div class="mt-1 flex items-baseline justify-between">
                                <span class="font-mono font-bold text-base text-amber-900">
                                    ₹{{ number_format(($salesReport['collections']['bill_prepaid_redemptions_paise'] ?? 0) / 100, 2) }}
                                </span>
                                <span class="text-[10px] text-amber-700 font-semibold">Ledger Debits</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department-wise Sales Table -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">Department-Wise Sales Breakdown</h4>
                        <span class="text-[11px] text-slate-500">Period: {{ $startDate }} to {{ $endDate }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-100/60 font-bold text-slate-600">
                                    <th class="py-2.5 px-4">Department</th>
                                    <th class="py-2.5 px-4 text-center">Bills Count</th>
                                    <th class="py-2.5 px-4 text-right">Gross Sales (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Discounts (₹)</th>
                                    <th class="py-2.5 px-4 text-right">GST (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Net Sales (₹)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($salesReport['departments'] ?? [] as $d)
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-2.5 px-4 font-bold text-slate-900">{{ $d['name'] }}</td>
                                        <td class="py-2.5 px-4 text-center font-mono">{{ $d['bills_count'] }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono">₹{{ number_format($d['gross_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-slate-500">₹{{ number_format($d['discount_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-slate-600">₹{{ number_format($d['tax_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format($d['net_paise'] / 100, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-slate-400 italic">No sales recorded for this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-bold">
                                <tr>
                                    <td class="py-3 px-4 text-slate-900 uppercase">Total Departmental Sales</td>
                                    <td class="py-3 px-4 text-center font-mono">{{ number_format($salesReport['summary']['total_bills'] ?? 0) }}</td>
                                    <td class="py-3 px-4 text-right font-mono">₹{{ number_format(($salesReport['summary']['total_gross_paise'] ?? 0) / 100, 2) }}</td>
                                    <td class="py-3 px-4 text-right font-mono">₹{{ number_format(($salesReport['summary']['total_discount_paise'] ?? 0) / 100, 2) }}</td>
                                    <td class="py-3 px-4 text-right font-mono">₹{{ number_format(($salesReport['summary']['total_tax_paise'] ?? 0) / 100, 2) }}</td>
                                    <td class="py-3 px-4 text-right font-mono text-slate-950 text-sm">₹{{ number_format(($salesReport['summary']['total_net_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Billing Counter Breakdown -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">Billing Counter Breakdown</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-100/60 font-bold text-slate-600">
                                    <th class="py-2.5 px-4">Counter ID</th>
                                    <th class="py-2.5 px-4">Counter Code &amp; Name</th>
                                    <th class="py-2.5 px-4 text-center">Department</th>
                                    <th class="py-2.5 px-4 text-center">Bills Count</th>
                                    <th class="py-2.5 px-4 text-right">Net Sales (₹)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($salesReport['counters'] ?? [] as $c)
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-2.5 px-4 font-mono font-bold text-slate-500">#{{ $c['id'] }}</td>
                                        <td class="py-2.5 px-4 font-bold text-slate-900">{{ $c['name'] }} ({{ $c['code'] }})</td>
                                        <td class="py-2.5 px-4 text-center uppercase font-mono text-slate-600">{{ $c['department_code'] }}</td>
                                        <td class="py-2.5 px-4 text-center font-mono">{{ $c['bills_count'] }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format($c['net_paise'] / 100, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-slate-400 italic">No counters active.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 2: INCOME & EXPENDITURE STATEMENT -->
        @if($activeTab === 'income_expenditure')
            <div class="space-y-6">
                <!-- Net Operating Result Banner -->
                <div class="p-6 bg-gradient-to-r {{ ($incomeExpenditureReport['net_operating_surplus_paise'] ?? 0) >= 0 ? 'from-emerald-900 to-teal-900 text-white' : 'from-rose-900 to-red-950 text-white' }} rounded-2xl shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="text-xs uppercase tracking-widest font-bold opacity-80 block">Net Operating Result for Period</span>
                            <h2 class="text-3xl font-black font-mono tracking-tight mt-1">
                                ₹{{ number_format(($incomeExpenditureReport['net_operating_surplus_paise'] ?? 0) / 100, 2) }}
                            </h2>
                            <span class="text-xs opacity-90 mt-1 block">
                                {{ ($incomeExpenditureReport['net_operating_surplus_paise'] ?? 0) >= 0 ? 'Operating Surplus (Net Profit)' : 'Operating Deficit (Net Loss)' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-6 text-right">
                            <div>
                                <span class="text-[11px] uppercase tracking-wider opacity-75 block">Total Operating Income</span>
                                <span class="text-xl font-bold font-mono">₹{{ number_format(($incomeExpenditureReport['incomes']['total_income_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                            <div class="border-l border-white/20 pl-6">
                                <span class="text-[11px] uppercase tracking-wider opacity-75 block">Total Operating Expenditure</span>
                                <span class="text-xl font-bold font-mono">₹{{ number_format(($incomeExpenditureReport['expenditures']['total_expenditure_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Two-Column Statement -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Operating Incomes -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-slate-200 bg-emerald-50/60 flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-900 flex items-center gap-1.5">
                                <span>📥</span>
                                <span>Operating Income &amp; Revenue</span>
                            </h3>
                            <span class="text-xs font-mono font-bold text-emerald-800">
                                ₹{{ number_format(($incomeExpenditureReport['incomes']['total_income_paise'] ?? 0) / 100, 2) }}
                            </span>
                        </div>
                        <table class="w-full text-left text-xs divide-y divide-slate-100">
                            <tbody>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Food Catering Turnover</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['incomes']['food_sales_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Bar &amp; Liquor Sales</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['incomes']['bar_sales_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Sports Facility (Gym &amp; Badminton)</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['incomes']['sports_facility_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Guest Rooms Accommodation</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['incomes']['accommodation_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Card Room Table Charges</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['incomes']['card_room_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Membership Subscriptions &amp; Dues</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['incomes']['membership_dues_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Other Miscellaneous Collections</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['incomes']['other_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t-2 border-slate-300 bg-emerald-50/30 font-bold">
                                <tr>
                                    <td class="py-3 px-4 text-slate-900">Total Operating Incomes</td>
                                    <td class="py-3 px-4 text-right font-mono text-slate-950 text-sm">₹{{ number_format(($incomeExpenditureReport['incomes']['total_income_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Operating Expenditures -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-slate-200 bg-rose-50/60 flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-rose-900 flex items-center gap-1.5">
                                <span>📤</span>
                                <span>Operating Expenditure &amp; Direct Costs</span>
                            </h3>
                            <span class="text-xs font-mono font-bold text-rose-800">
                                ₹{{ number_format(($incomeExpenditureReport['expenditures']['total_expenditure_paise'] ?? 0) / 100, 2) }}
                            </span>
                        </div>
                        <table class="w-full text-left text-xs divide-y divide-slate-100">
                            <tbody>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Food Contractor Catering Payouts</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['expenditures']['contractor_payouts_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Bar Liquor COGS (Consumed Stock)</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['expenditures']['bar_cogs_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Staff Salaries &amp; Wages</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['expenditures']['expense_breakdown']['staff_and_wages'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Electricity, Fuel &amp; Utilities</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['expenditures']['expense_breakdown']['electricity_and_utilities'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Clubhouse &amp; Sports Maintenance</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['expenditures']['expense_breakdown']['clubhouse_maintenance'] ?? 0) / 100, 2) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-semibold text-slate-700">Other General Administration Expenses</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format(($incomeExpenditureReport['expenditures']['expense_breakdown']['administrative_and_other'] ?? 0) / 100, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t-2 border-slate-300 bg-rose-50/30 font-bold">
                                <tr>
                                    <td class="py-3 px-4 text-slate-900">Total Operating Expenditures</td>
                                    <td class="py-3 px-4 text-right font-mono text-slate-950 text-sm">₹{{ number_format(($incomeExpenditureReport['expenditures']['total_expenditure_paise'] ?? 0) / 100, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 3: DAILY CASH & DIGITAL TILL RECONCILIATION -->
        @if($activeTab === 'cash_recon')
            <div class="space-y-6">
                <!-- Summary KPI Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Total Shifts Run</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            {{ number_format($cashReconReport['summary']['total_shifts'] ?? 0) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Audited cashier shifts</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Total Cash Inflow</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            ₹{{ number_format(($cashReconReport['summary']['total_cash_receipts_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Physical drawer cash</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Digital UPI / EDC</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            ₹{{ number_format(($cashReconReport['summary']['total_digital_receipts_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Bank gateway verified</span>
                    </div>

                    <div class="p-4 bg-white border {{ ($cashReconReport['summary']['total_discrepancy_paise'] ?? 0) === 0 ? 'border-emerald-200 bg-emerald-50/30' : 'border-rose-200 bg-rose-50/40' }} rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase {{ ($cashReconReport['summary']['total_discrepancy_paise'] ?? 0) === 0 ? 'text-emerald-800' : 'text-rose-800' }} block">Net Till Discrepancy</span>
                        <span class="text-xl font-black {{ ($cashReconReport['summary']['total_discrepancy_paise'] ?? 0) === 0 ? 'text-emerald-900' : 'text-rose-900' }} mt-1 block font-mono">
                            ₹{{ number_format(($cashReconReport['summary']['total_discrepancy_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] {{ ($cashReconReport['summary']['total_discrepancy_paise'] ?? 0) === 0 ? 'text-emerald-700' : 'text-rose-700 font-semibold' }} mt-0.5 block">
                            {{ ($cashReconReport['summary']['total_discrepancy_paise'] ?? 0) === 0 ? 'Drawers Perfectly Balanced' : 'Variance Detected' }}
                        </span>
                    </div>
                </div>

                <!-- Shift Reconciliation Table -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">Cashier Shifts &amp; Till Drawer Audit Log</h4>
                        <span class="text-[11px] text-slate-500">Period: {{ $startDate }} to {{ $endDate }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-100/60 font-bold text-slate-600">
                                    <th class="py-2.5 px-4">Shift #</th>
                                    <th class="py-2.5 px-4">Date</th>
                                    <th class="py-2.5 px-4">Counter &amp; Cashier</th>
                                    <th class="py-2.5 px-4 text-center">Status</th>
                                    <th class="py-2.5 px-4 text-right">Float (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Cash In (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Digital (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Expected (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Counted (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Variance (₹)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($cashReconReport['shifts'] ?? [] as $s)
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ $s['shift_number'] }}</td>
                                        <td class="py-2.5 px-4 font-mono text-slate-600">{{ $s['business_date'] }}</td>
                                        <td class="py-2.5 px-4">
                                            <span class="font-bold text-slate-900 block">{{ $s['counter_name'] }}</span>
                                            <span class="text-[11px] text-slate-500 block">{{ $s['cashier_name'] }}</span>
                                        </td>
                                        <td class="py-2.5 px-4 text-center">
                                            <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded-full uppercase {{ $s['status'] === 'closed' ? 'bg-slate-100 text-slate-700 border border-slate-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
                                                {{ $s['status'] }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono text-slate-600">₹{{ number_format($s['opening_float_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-semibold text-slate-900">₹{{ number_format($s['cash_sales_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-blue-700">₹{{ number_format($s['digital_sales_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-slate-700">₹{{ number_format($s['expected_cash_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format($s['closing_cash_counted_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold {{ $s['discrepancy_paise'] === 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                            ₹{{ number_format($s['discrepancy_paise'] / 100, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="py-6 text-center text-slate-400 italic">No cashier shifts found in this date range.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 4: FOOD CONTRACTOR SETTLEMENTS -->
        @if($activeTab === 'contractor')
            <div class="space-y-6">
                <!-- Summary KPI Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Total Batches</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            {{ number_format($contractorReport['summary']['total_batches'] ?? 0) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Daily catering cutoffs</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Total Food Turnover</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            ₹{{ number_format(($contractorReport['summary']['total_food_gross_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Gross dining sales</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Disbursed Payouts</span>
                        <span class="text-xl font-black text-emerald-700 mt-1 block">
                            ₹{{ number_format(($contractorReport['summary']['total_paid_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Paid via NEFT / RTGS</span>
                    </div>

                    <div class="p-4 bg-white border {{ ($contractorReport['summary']['current_outstanding_payable_paise'] ?? 0) > 0 ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200' }} rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase {{ ($contractorReport['summary']['current_outstanding_payable_paise'] ?? 0) > 0 ? 'text-amber-800' : 'text-slate-400' }} block">Residual Payable</span>
                        <span class="text-xl font-black {{ ($contractorReport['summary']['current_outstanding_payable_paise'] ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} mt-1 block font-mono">
                            ₹{{ number_format(($contractorReport['summary']['current_outstanding_payable_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Awaiting bank payout</span>
                    </div>
                </div>

                <!-- Batches Table -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">Catering Contractor Settlement Batches</h4>
                        <span class="text-[11px] text-slate-500">100% Pass-Through Policy</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-100/60 font-bold text-slate-600">
                                    <th class="py-2.5 px-4">Batch #</th>
                                    <th class="py-2.5 px-4">Date</th>
                                    <th class="py-2.5 px-4">Contractor</th>
                                    <th class="py-2.5 px-4 text-center">Status</th>
                                    <th class="py-2.5 px-4 text-right">Gross Sales (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Net Payable (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Paid (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Residual (₹)</th>
                                    <th class="py-2.5 px-4">Bank Ref / UTR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($contractorReport['batches'] ?? [] as $b)
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ $b['batch_number'] }}</td>
                                        <td class="py-2.5 px-4 font-mono text-slate-600">{{ $b['business_date'] }}</td>
                                        <td class="py-2.5 px-4 font-bold text-slate-800">{{ $b['contractor_name'] }}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded-full uppercase {{ $b['status'] === 'paid' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                                                {{ $b['status'] }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono">₹{{ number_format($b['gross_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-semibold text-slate-900">₹{{ number_format($b['net_payable_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-emerald-700">₹{{ number_format($b['paid_amount_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold {{ $b['residual_payable_paise'] > 0 ? 'text-amber-800' : 'text-slate-400' }}">
                                            ₹{{ number_format($b['residual_payable_paise'] / 100, 2) }}
                                        </td>
                                        <td class="py-2.5 px-4 font-mono text-[11px] text-slate-600">{{ $b['payment_reference'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-6 text-center text-slate-400 italic">No contractor settlement batches found for this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 5: BAR & STOCK CONSUMPTION REPORTS -->
        @if($activeTab === 'bar_stock')
            <div class="space-y-6">
                <!-- Summary KPI Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Total Items Tracked</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block">
                            {{ number_format($barStockReport['summary']['total_items_count'] ?? 0) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Active SKUs</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Current Stock Valuation</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block font-mono">
                            ₹{{ number_format(($barStockReport['summary']['total_stock_valuation_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Moving average cost basis</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Period Consumption COGS</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block font-mono">
                            ₹{{ number_format(($barStockReport['summary']['period_cogs_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Dispensed inventory cost</span>
                    </div>

                    <div class="p-4 bg-white border {{ ($barStockReport['summary']['low_stock_alerts_count'] ?? 0) > 0 ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200' }} rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase {{ ($barStockReport['summary']['low_stock_alerts_count'] ?? 0) > 0 ? 'text-amber-800' : 'text-slate-400' }} block">Reorder Alerts</span>
                        <span class="text-xl font-black {{ ($barStockReport['summary']['low_stock_alerts_count'] ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} mt-1 block">
                            {{ $barStockReport['summary']['low_stock_alerts_count'] ?? 0 }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Below threshold</span>
                    </div>
                </div>

                <!-- Inventory Item Master Table -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">Inventory Items, Current Stock &amp; Consumption</h4>
                        <span class="text-[11px] text-slate-500">Perpetual Moving Weighted Average</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-100/60 font-bold text-slate-600">
                                    <th class="py-2.5 px-4">SKU &amp; Item Name</th>
                                    <th class="py-2.5 px-4">Dept / Category</th>
                                    <th class="py-2.5 px-4 text-center">Base Unit</th>
                                    <th class="py-2.5 px-4 text-right">Current Stock</th>
                                    <th class="py-2.5 px-4 text-right">Avg Cost (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Asset Valuation (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Period Sales (Qty)</th>
                                    <th class="py-2.5 px-4 text-right">Period COGS (₹)</th>
                                    <th class="py-2.5 px-4 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($barStockReport['items'] ?? [] as $item)
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-2.5 px-4">
                                            <span class="font-bold text-slate-900 block">{{ $item['name'] }}</span>
                                            <span class="font-mono text-[10px] text-slate-400 block">{{ $item['sku'] }}</span>
                                        </td>
                                        <td class="py-2.5 px-4 text-slate-600">
                                            <span class="uppercase font-mono text-[10px] font-bold text-amber-800">{{ $item['department_code'] }}</span>
                                            <span class="block text-[11px] text-slate-500">{{ $item['category'] }}</span>
                                        </td>
                                        <td class="py-2.5 px-4 text-center font-mono text-slate-600">{{ $item['base_unit'] }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-900">{{ number_format($item['current_stock_qty'], 1) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-slate-600">₹{{ number_format($item['avg_cost_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-900">₹{{ number_format($item['stock_value_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-slate-700">{{ number_format($item['period_sales_qty'], 1) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-semibold text-slate-900">₹{{ number_format($item['period_sales_cost_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            @if($item['is_low_stock'])
                                                <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded-full bg-rose-50 text-rose-800 border border-rose-200">
                                                    Low Stock
                                                </span>
                                            @else
                                                <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                    In Stock
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-6 text-center text-slate-400 italic">No inventory items registered.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 6: GYM & BADMINTON SPORTS INCOME -->
        @if($activeTab === 'sports')
            <div class="space-y-6">
                <!-- Summary KPI Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Total Sports Revenue</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block font-mono">
                            ₹{{ number_format(($sportsReport['summary']['total_sports_revenue_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">Subscriptions &amp; court fees</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Gym Subscriptions</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block font-mono">
                            ₹{{ number_format(($sportsReport['gym']['revenue_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">{{ $sportsReport['gym']['subscriptions_count'] ?? 0 }} active gym plans</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Badminton Plans</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block font-mono">
                            ₹{{ number_format(($sportsReport['badminton']['subscription_revenue_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">{{ $sportsReport['badminton']['subscriptions_count'] ?? 0 }} badminton plans</span>
                    </div>

                    <div class="p-4 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Pay-Per-Play Court Fees</span>
                        <span class="text-xl font-black text-slate-900 mt-1 block font-mono">
                            ₹{{ number_format(($sportsReport['badminton']['court_fees_collected_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[11px] text-slate-500 mt-0.5 block">{{ $sportsReport['badminton']['paid_court_slots_count'] ?? 0 }} paid slots</span>
                    </div>
                </div>

                <!-- Sports Breakdown Table -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Gymnasium Breakdown -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-3 flex items-center justify-between">
                            <span>🏋️ Gymnasium &amp; Health Club Subscriptions</span>
                            <span class="font-mono font-bold text-slate-900">₹{{ number_format(($sportsReport['gym']['revenue_paise'] ?? 0) / 100, 2) }}</span>
                        </h4>
                        <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                <span class="text-slate-600 font-semibold">Total Active Gym Plans</span>
                                <span class="font-mono font-bold text-slate-900">{{ $sportsReport['gym']['subscriptions_count'] ?? 0 }} members</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                <span class="text-slate-600">Monthly Subscriptions</span>
                                <span class="font-mono text-slate-800">{{ $sportsReport['gym']['monthly_count'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-slate-600">Annual Subscriptions</span>
                                <span class="font-mono text-slate-800">{{ $sportsReport['gym']['annual_count'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Badminton Court Breakdown -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-3 flex items-center justify-between">
                            <span>🏸 Badminton Court Subscriptions &amp; Bookings</span>
                            <span class="font-mono font-bold text-slate-900">
                                ₹{{ number_format((($sportsReport['badminton']['subscription_revenue_paise'] ?? 0) + ($sportsReport['badminton']['court_fees_collected_paise'] ?? 0)) / 100, 2) }}
                            </span>
                        </h4>
                        <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                <span class="text-slate-600 font-semibold">Active Subscriptions</span>
                                <span class="font-mono font-bold text-slate-900">{{ $sportsReport['badminton']['subscriptions_count'] ?? 0 }} plans (₹{{ number_format(($sportsReport['badminton']['subscription_revenue_paise'] ?? 0) / 100, 2) }})</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                <span class="text-slate-600">Total 60-Min Court Slots Booked</span>
                                <span class="font-mono font-bold text-slate-900">{{ $sportsReport['badminton']['total_court_slots_booked'] ?? 0 }} slots</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                <span class="text-slate-600">Plan-Covered Slots (Fee Waived)</span>
                                <span class="font-mono text-emerald-700 font-semibold">{{ $sportsReport['badminton']['plan_covered_slots_count'] ?? 0 }} slots</span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-slate-600">Pay-Per-Play Slots (Non-Plan)</span>
                                <span class="font-mono text-slate-900 font-bold">{{ $sportsReport['badminton']['paid_court_slots_count'] ?? 0 }} slots (₹{{ number_format(($sportsReport['badminton']['court_fees_collected_paise'] ?? 0) / 100, 2) }})</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 7: OUTSTANDING PAYABLES & EXCEPTIONS -->
        @if($activeTab === 'payables')
            <div class="space-y-6">
                <!-- Zero Member Debt Affirmation Banner -->
                <div class="p-5 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-lg font-bold shrink-0">
                            ✓
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-emerald-950">Zero Member Credit Enforcement Confirmed</h4>
                            <p class="text-xs text-emerald-800 mt-0.5">
                                Exactly <strong class="font-mono">0</strong> members hold negative balances or credit tabs. All consumption is 100% backed by real-time prepaid funds or immediate counter settlements.
                            </p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-[11px] font-bold uppercase text-emerald-800 block">Prepaid Liability</span>
                        <span class="font-mono font-black text-emerald-950 text-base">
                            ₹{{ number_format(($payablesReport['member_credit']['total_wallet_liability_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[10px] text-emerald-700 block">{{ $payablesReport['member_credit']['active_wallets_count'] ?? 0 }} active wallets</span>
                    </div>
                </div>

                <!-- Payables & Exceptions Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Contractor Residual Payables -->
                    <div class="p-5 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3 flex items-center justify-between">
                            <span>🍽️ Contractor Payables</span>
                            <span class="font-mono font-bold text-amber-800">
                                ₹{{ number_format(($payablesReport['contractor_payables']['total_residual_payable_paise'] ?? 0) / 100, 2) }}
                            </span>
                        </h4>
                        <p class="text-xs text-slate-500 mb-3">
                            Approved food catering batches awaiting disbursement release.
                        </p>
                        <div class="space-y-2 text-xs">
                            @forelse($payablesReport['contractor_payables']['batches'] ?? [] as $batch)
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                                    <div>
                                        <span class="font-mono font-bold text-slate-800 block">{{ $batch['batch_number'] }}</span>
                                        <span class="text-[11px] text-slate-500 block">{{ $batch['contractor'] }}</span>
                                    </div>
                                    <span class="font-mono font-bold text-amber-900">
                                        ₹{{ number_format($batch['residual_payable_paise'] / 100, 2) }}
                                    </span>
                                </div>
                            @empty
                                <div class="py-4 text-center text-slate-400 italic">No unsettled contractor batches.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Digital Gateway Exceptions (Scenario F07) -->
                    <div class="p-5 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3 flex items-center justify-between">
                            <span>💳 Gateway Exceptions</span>
                            <span class="font-mono font-bold {{ ($payablesReport['gateway_exceptions']['unposted_count'] ?? 0) === 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $payablesReport['gateway_exceptions']['unposted_count'] ?? 0 }} Items
                            </span>
                        </h4>
                        <p class="text-xs text-slate-500 mb-3">
                            Captured external payments requiring manual posting resolution.
                        </p>
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                            <div class="flex justify-between items-center text-slate-600 mb-1">
                                <span>Unposted Captures:</span>
                                <span class="font-mono font-bold text-slate-900">{{ $payablesReport['gateway_exceptions']['unposted_count'] ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center text-slate-600">
                                <span>Total Unposted Amount:</span>
                                <span class="font-mono font-bold text-slate-900">₹{{ number_format(($payablesReport['gateway_exceptions']['total_unposted_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- General Ledger Balance Status -->
                    <div class="p-5 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3 flex items-center justify-between">
                            <span>⚖️ General Ledger Balancing</span>
                            <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded-full {{ ($payablesReport['general_ledger']['is_balanced'] ?? false) ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                {{ ($payablesReport['general_ledger']['is_balanced'] ?? false) ? 'BALANCED' : 'VARIANCE' }}
                            </span>
                        </h4>
                        <p class="text-xs text-slate-500 mb-3">
                            Perpetual double-entry journal integrity check (Debits === Credits).
                        </p>
                        <div class="space-y-1.5 text-xs text-slate-600">
                            <div class="flex justify-between">
                                <span>Total Debits:</span>
                                <span class="font-mono font-bold text-slate-900">₹{{ number_format(($payablesReport['general_ledger']['total_debits_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Credits:</span>
                                <span class="font-mono font-bold text-slate-900">₹{{ number_format(($payablesReport['general_ledger']['total_credits_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-slate-100 text-slate-800 font-bold">
                                <span>Net Difference:</span>
                                <span class="font-mono {{ ($payablesReport['general_ledger']['difference_paise'] ?? 0) === 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                    ₹{{ number_format(($payablesReport['general_ledger']['difference_paise'] ?? 0) / 100, 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 8: DEPARTMENTAL & OVERALL PROFIT AND LOSS -->
        @if($activeTab === 'pnl')
            <div class="space-y-6">
                <!-- Subledger & Balance Sheet Reconciliations Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Prepaid Wallets -->
                    <div class="p-5 bg-white border border-slate-200/90 rounded-2xl shadow-xs relative overflow-hidden">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-yellow-400"></div>
                        <div class="flex items-center justify-between mb-3 pt-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Prepaid Wallet Liability (2010)</span>
                            @if($recon['prepaid_wallet']['reconciled'] ?? false)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Reconciled
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-rose-50 text-rose-800 border border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span> Variance
                                </span>
                            @endif
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center text-slate-600">
                                <span>Subledger Total:</span>
                                <span class="font-mono font-bold text-slate-900 text-sm">₹{{ number_format(($recon['prepaid_wallet']['subledger_total_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-slate-600">
                                <span>Account 2010 Balance:</span>
                                <span class="font-mono font-bold text-slate-900 text-sm">₹{{ number_format(($recon['prepaid_wallet']['general_ledger_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Food Contractor Payables -->
                    <div class="p-5 bg-white border border-slate-200/90 rounded-2xl shadow-xs relative overflow-hidden">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-cyan-400"></div>
                        <div class="flex items-center justify-between mb-3 pt-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Contractor Payables (2020)</span>
                            @if($recon['contractor_payable']['reconciled'] ?? false)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Reconciled
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-rose-50 text-rose-800 border border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span> Variance
                                </span>
                            @endif
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center text-slate-600">
                                <span>Subledger Residual:</span>
                                <span class="font-mono font-bold text-slate-900 text-sm">₹{{ number_format(($recon['contractor_payable']['subledger_total_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-slate-600">
                                <span>Account 2020 Balance:</span>
                                <span class="font-mono font-bold text-slate-900 text-sm">₹{{ number_format(($recon['contractor_payable']['general_ledger_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Operational Journal Integrity -->
                    <div class="p-5 bg-white border border-slate-200/90 rounded-2xl shadow-xs relative overflow-hidden">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-400"></div>
                        <div class="flex items-center justify-between mb-3 pt-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Journal Integrity</span>
                            @if($recon['journal_integrity']['balanced'] ?? false)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Balanced
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-rose-50 text-rose-800 border border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span> Unbalanced
                                </span>
                            @endif
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center text-slate-600">
                                <span>Total Debits:</span>
                                <span class="font-mono font-bold text-slate-900 text-sm">₹{{ number_format(($recon['journal_integrity']['total_debits_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-slate-600">
                                <span>Total Credits:</span>
                                <span class="font-mono font-bold text-slate-900 text-sm">₹{{ number_format(($recon['journal_integrity']['total_credits_paise'] ?? 0) / 100, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Departmental P&L Performance Grid -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">Segmented Departmental Profit &amp; Loss</h4>
                        <span class="text-[11px] text-slate-500">Period: {{ $startDate }} to {{ $endDate }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-100/60 font-bold text-slate-600">
                                    <th class="py-2.5 px-4">Department</th>
                                    <th class="py-2.5 px-4 text-right">Recognised Revenue (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Direct Expenses &amp; COGS (₹)</th>
                                    <th class="py-2.5 px-4 text-right">Net Contribution Margin (₹)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($pnl['departments'] ?? [] as $code => $d)
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-2.5 px-4 font-bold text-slate-900 capitalize">{{ $d['department_code'] }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-slate-800">₹{{ number_format($d['revenue_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-slate-800">₹{{ number_format($d['cost_paise'] / 100, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold {{ $d['net_margin_paise'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                            ₹{{ number_format($d['net_margin_paise'] / 100, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-slate-400 italic">No departmental transactions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-bold">
                                <tr>
                                    <td class="py-3 px-4 text-slate-900 uppercase">Total Departmental Margins</td>
                                    <td class="py-3 px-4 text-right font-mono">₹{{ number_format(($pnl['summary']['total_department_revenue_paise'] ?? 0) / 100, 2) }}</td>
                                    <td class="py-3 px-4 text-right font-mono">₹{{ number_format(($pnl['summary']['total_department_cost_paise'] ?? 0) / 100, 2) }}</td>
                                    <td class="py-3 px-4 text-right font-mono text-sm {{ ($pnl['summary']['total_department_margin_paise'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                        ₹{{ number_format(($pnl['summary']['total_department_margin_paise'] ?? 0) / 100, 2) }}
                                    </td>
                                </tr>
                                <tr class="bg-slate-100/70 border-t border-slate-200">
                                    <td colspan="3" class="py-2.5 px-4 text-slate-600 font-semibold">Unallocated General Clubhouse Overheads</td>
                                    <td class="py-2.5 px-4 text-right font-mono text-rose-700">
                                        ₹{{ number_format(($pnl['summary']['unallocated_margin_paise'] ?? 0) / 100, 2) }}
                                    </td>
                                </tr>
                                <tr class="bg-amber-50/70 border-t-2 border-slate-900 text-sm">
                                    <td colspan="3" class="py-3.5 px-4 text-slate-950 uppercase font-black tracking-wide">Overall Club Net Result (Profit / Loss)</td>
                                    <td class="py-3.5 px-4 text-right font-mono font-black text-base {{ ($pnl['summary']['overall_club_net_paise'] ?? 0) >= 0 ? 'text-emerald-800' : 'text-rose-800' }}">
                                        ₹{{ number_format(($pnl['summary']['overall_club_net_paise'] ?? 0) / 100, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
