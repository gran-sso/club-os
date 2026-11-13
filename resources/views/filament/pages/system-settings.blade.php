<x-filament-panels::page>
    <div class="space-y-6">

        <!-- Top Header & Save Actions Bar -->
        <div class="p-5 bg-white border border-slate-200/90 rounded-2xl shadow-xs">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                
                <!-- Left: Icon & Title Hierarchy -->
                <div class="flex items-start sm:items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center font-black text-slate-950 text-base shadow-xs shrink-0 mt-0.5 sm:mt-0">
                        ⚙️
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight truncate">Club Settings &amp; Rules</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200 shrink-0">
                                Master
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5 line-clamp-1 sm:line-clamp-none">
                            Parameters configured here dynamically enforce rules across all billing, front-desk, and financial modules.
                        </p>
                    </div>
                </div>

                <!-- Right: Action Buttons Group (Identical Heights, Baselines, and Icons) -->
                <div class="flex items-center gap-2.5 shrink-0 w-full sm:w-auto justify-start sm:justify-end pt-2 sm:pt-0 border-t border-slate-100 sm:border-t-0">
                    <button type="button"
                        wire:click="resetToDefaults"
                        wire:confirm="Are you sure you want to restore standard Malabar Club defaults for all configuration fields?"
                        class="h-10 px-4 inline-flex items-center justify-center gap-2 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 font-bold text-xs rounded-xl border border-slate-300 shadow-2xs transition-all active:scale-98 cursor-pointer shrink-0">
                        <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Restore Defaults</span>
                    </button>

                    <button type="button"
                        wire:click="saveSettings"
                        class="h-10 px-5 inline-flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-bold text-xs rounded-xl border border-amber-500/80 shadow-xs transition-all active:scale-98 cursor-pointer shrink-0">
                        <svg class="w-4 h-4 text-slate-950 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Save Configuration</span>
                    </button>
                </div>

            </div>
        </div>

        <!-- Validation Errors Summary Banner (covers hidden tabs too) -->
        @if ($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-900 text-xs space-y-1.5">
                <div class="font-extrabold flex items-center gap-2">
                    <span>⚠️</span>
                    <span>Please fix the following setting errors before saving:</span>
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-[11px] font-medium">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Settings Navigation Tabs -->
        <div class="flex items-center gap-1.5 border-b border-slate-200 pb-2 overflow-x-auto select-none">
            <button type="button"
                wire:click="setTab('identity')"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shrink-0 {{ $activeTab === 'identity' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>🏢</span>
                <span>Club Info &amp; GST</span>
            </button>

            <button type="button"
                wire:click="setTab('billing')"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shrink-0 {{ $activeTab === 'billing' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>💳</span>
                <span>Cashier &amp; Smartcard Limits</span>
            </button>

            <button type="button"
                wire:click="setTab('facilities')"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shrink-0 {{ $activeTab === 'facilities' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>🏸</span>
                <span>Facilities &amp; Timings</span>
            </button>

            <button type="button"
                wire:click="setTab('tally')"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shrink-0 {{ $activeTab === 'tally' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>📊</span>
                <span>Tally Connection</span>
            </button>

            <button type="button"
                wire:click="setTab('hardware')"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shrink-0 {{ $activeTab === 'hardware' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>🖨️</span>
                <span>Hardware &amp; Gate Control</span>
            </button>

            <button type="button"
                wire:click="setTab('audit')"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shrink-0 {{ $activeTab === 'audit' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>🛡️</span>
                <span>Audit Trail &amp; Retention</span>
            </button>
        </div>

        <!-- ================================================================= -->
        <!-- TAB 1: CLUB IDENTITY & STATUTORY INVOICING                        -->
        <!-- ================================================================= -->
        @if ($activeTab === 'identity')
            <div class="space-y-5 animate-fade-in">
                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Legal Business Profile &amp; Letterhead</h3>
                        <p class="text-xs text-slate-500">Printed on the header of all tax invoices, member statements, and payment receipts.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label for="setting-club-name" class="block text-xs font-bold text-slate-700 mb-1">Club Legal Name</label>
                            <input type="text" id="setting-club-name" wire:model="clubName"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <span class="text-[10px] text-slate-400 mt-1 block">Full legal name of the registered society.</span>
                        </div>

                        <div>
                            <label for="setting-club-tagline" class="block text-xs font-bold text-slate-700 mb-1">Tagline / Designation</label>
                            <input type="text" id="setting-club-tagline" wire:model="clubTagline"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <span class="text-[10px] text-slate-400 mt-1 block">Subtitle displayed on digital member passes.</span>
                        </div>
                    </div>

                    <div>
                        <label for="setting-club-address" class="block text-xs font-bold text-slate-700 mb-1">Registered Clubhouse Physical Address</label>
                        <input type="text" id="setting-club-address" wire:model="clubAddress"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="setting-club-phone" class="block text-xs font-bold text-slate-700 mb-1">Office Telephone / Mobile</label>
                            <input type="text" id="setting-club-phone" wire:model="clubPhone"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>

                        <div>
                            <label for="setting-club-email" class="block text-xs font-bold text-slate-700 mb-1">Official Communications Email</label>
                            <input type="email" id="setting-club-email" wire:model="clubEmail"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Club Tax Details (GST &amp; PAN)</h3>
                        <p class="text-xs text-slate-500">Government registration numbers required for tax invoices and audits.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label for="setting-club-gstin" class="block text-xs font-bold text-slate-700 mb-1">Club GST Number (GSTIN)</label>
                            <input type="text" id="setting-club-gstin" wire:model="clubGstin" maxlength="20"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold uppercase text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <span class="text-[10px] text-slate-400 mt-1 block">15-character GSTIN (Kerala state prefix: 32).</span>
                        </div>

                        <div>
                            <label for="setting-club-pan" class="block text-xs font-bold text-slate-700 mb-1">Permanent Account Number (PAN)</label>
                            <input type="text" id="setting-club-pan" wire:model="clubPan" maxlength="15"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold uppercase text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <span class="text-[10px] text-slate-400 mt-1 block">Income tax registration number of the society.</span>
                        </div>
                    </div>

                    <div>
                        <label for="setting-receipt-footer" class="block text-xs font-bold text-slate-700 mb-1">Receipt Footer Message</label>
                        <textarea id="setting-receipt-footer" wire:model="receiptFooterNote" rows="2"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
                        <span class="text-[10px] text-slate-400 mt-1 block">Printed at the bottom of all 80mm counter receipts.</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 2: BILLING & PREPAID WALLET POLICIES                          -->
        <!-- ================================================================= -->
        @if ($activeTab === 'billing')
            <div class="space-y-5 animate-fade-in">
                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Smartcard Top-Up &amp; Till Limits</h3>
                        <p class="text-xs text-slate-500">Set money limits for cashiers opening shifts and topping up member smartcards.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <div>
                            <label for="setting-default-float" class="block text-xs font-bold text-slate-700 mb-1">Standard Drawer Float (₹)</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 font-mono text-slate-400 font-bold text-xs">₹</span>
                                <input type="number" id="setting-default-float" wire:model="defaultOpeningFloat" min="0" step="100"
                                    class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1 block">Default cash float for opening new cashier shifts.</span>
                        </div>

                        <div>
                            <label for="setting-min-recharge" class="block text-xs font-bold text-slate-700 mb-1">Minimum Recharge (₹)</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 font-mono text-slate-400 font-bold text-xs">₹</span>
                                <input type="number" id="setting-min-recharge" wire:model="minWalletRecharge" min="1" step="100"
                                    class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1 block">Minimum permitted top-up amount.</span>
                        </div>

                        <div>
                            <label for="setting-max-recharge" class="block text-xs font-bold text-slate-700 mb-1">Maximum Single Top-Up (₹)</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 font-mono text-slate-400 font-bold text-xs">₹</span>
                                <input type="number" id="setting-max-recharge" wire:model="maxWalletRecharge" min="1000" step="1000"
                                    class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1 block">Prevents accidental counter data-entry errors.</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label for="setting-lost-card-fee" class="block text-xs font-bold text-slate-700 mb-1">Lost RFID Smartcard Replacement Fee (₹)</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 font-mono text-slate-400 font-bold text-xs">₹</span>
                                <input type="number" id="setting-lost-card-fee" wire:model="lostCardFee" min="0" step="10"
                                    class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1 block">Charged when issuing a new RFID card for lost credentials.</span>
                        </div>

                        <div class="flex items-center pt-5">
                            <label class="flex items-center gap-3 cursor-pointer select-none">
                                <input type="checkbox" wire:model="allowSplitTender" class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500">
                                <div>
                                    <span class="text-xs font-bold text-slate-900">Allow Split Payment (Two Methods on One Bill)</span>
                                    <p class="text-[10px] text-slate-500">Allow bills to be settled using part Cash and part Digital payment.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Inventory & Stock Enforcement</h3>
                        <p class="text-xs text-slate-500">Control stock verification rules for counter billing and POS terminals.</p>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-start gap-3 cursor-pointer select-none">
                            <input type="checkbox" wire:model="allowZeroStockSales" class="w-4 h-4 mt-0.5 rounded text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-bold text-slate-900">Allow Selling Zero / Negative Stock Items on POS</span>
                                <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed">
                                    When enabled, cashiers can bill items on the POS even if current recorded stock is zero or insufficient (e.g. physical stock has arrived, but storekeeper has not yet entered purchase receipts). Stock balance will track into negative until stock inward is posted. When disabled, out-of-stock items are strictly blocked from being billed.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 3: FACILITIES & FRONT DESK TIMINGS                            -->
        <!-- ================================================================= -->
        @if ($activeTab === 'facilities')
            <div class="space-y-5 animate-fade-in">
                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Accommodation Check-In &amp; Check-Out Standard Times</h3>
                        <p class="text-xs text-slate-500">Used for housekeeping scheduling and billing day calculations.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label for="setting-checkin-time" class="block text-xs font-bold text-slate-700 mb-1">Standard Check-In Time</label>
                            <input type="time" id="setting-checkin-time" wire:model="checkInTime"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>

                        <div>
                            <label for="setting-checkout-time" class="block text-xs font-bold text-slate-700 mb-1">Standard Check-Out Time</label>
                            <input type="time" id="setting-checkout-time" wire:model="checkOutTime"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Badminton Court Operating Hours &amp; Reservation Limits</h3>
                        <p class="text-xs text-slate-500">Controls court schedule generation and member booking advance limits.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <div>
                            <label for="setting-court-open" class="block text-xs font-bold text-slate-700 mb-1">Court Daily Opening</label>
                            <input type="time" id="setting-court-open" wire:model="courtOpeningTime"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>

                        <div>
                            <label for="setting-court-close" class="block text-xs font-bold text-slate-700 mb-1">Court Daily Closing</label>
                            <input type="time" id="setting-court-close" wire:model="courtClosingTime"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>

                        <div>
                            <label for="setting-advance-days" class="block text-xs font-bold text-slate-700 mb-1">Advance Booking Window (Days)</label>
                            <input type="number" id="setting-advance-days" wire:model="courtAdvanceBookingDays" min="1" max="30"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <span class="text-[10px] text-slate-400 mt-1 block">Maximum days in advance a member can reserve.</span>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Baseline Guest Daily Entry Pass Rates (₹ Excl. GST)</h3>
                        <p class="text-xs text-slate-500">Initial base tariff rates for guest passes issued at reception.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <div>
                            <label for="setting-guest-bar" class="block text-xs font-bold text-slate-700 mb-1">Bar &amp; Card Room Pass (₹)</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 font-mono text-slate-400 font-bold text-xs">₹</span>
                                <input type="number" id="setting-guest-bar" wire:model="guestBarEntryFee" min="0" step="10"
                                    class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="setting-guest-court" class="block text-xs font-bold text-slate-700 mb-1">Badminton Court Pass (₹)</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 font-mono text-slate-400 font-bold text-xs">₹</span>
                                <input type="number" id="setting-guest-court" wire:model="guestBadmintonEntryFee" min="0" step="10"
                                    class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="setting-guest-gym" class="block text-xs font-bold text-slate-700 mb-1">Gym Daily Pass (₹)</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 font-mono text-slate-400 font-bold text-xs">₹</span>
                                <input type="number" id="setting-guest-gym" wire:model="guestGymEntryFee" min="0" step="10"
                                    class="w-full pl-8 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 4: TALLY PRIME LAN CONFIGURATION                              -->
        <!-- ================================================================= -->
        @if ($activeTab === 'tally')
            <div class="space-y-5 animate-fade-in">
                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900">Tally Prime XML Server Connection</h3>
                            <p class="text-xs text-slate-500">Configure the local network address of the dedicated Tally Prime accounting workstation.</p>
                        </div>

                        <button type="button"
                            wire:click="testTallyConnection"
                            class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-amber-400 font-bold text-xs rounded-xl transition shadow-xs cursor-pointer flex items-center gap-1.5 shrink-0">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>Test Connection</span>
                        </button>
                    </div>

                    @if ($tallyTestResult)
                        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-900 text-xs font-semibold flex items-center gap-2">
                            <span>✓</span>
                            <span>{{ $tallyTestResult }}</span>
                        </div>
                    @endif

                    @if ($tallyTestError)
                        <div class="p-3 bg-rose-50 border border-rose-200 rounded-2xl text-rose-900 text-xs font-semibold flex items-center gap-2">
                            <span>⚠️</span>
                            <span>{{ $tallyTestError }}</span>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label for="setting-tally-driver" class="block text-xs font-bold text-slate-700 mb-1">Integration Driver Mode</label>
                            <select id="setting-tally-driver" wire:model="tallyDriver"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                <option value="simulator">Development Simulator (Offline Mock Adapter)</option>
                                <option value="live">Live Tally Prime HTTP XML Server (On-Premise LAN)</option>
                            </select>
                            <span class="text-[10px] text-slate-400 mt-1 block">Live mode sends XML vouchers to Tally's HTTP port.</span>
                        </div>

                        <div>
                            <label for="setting-tally-company" class="block text-xs font-bold text-slate-700 mb-1">Tally Target Company Name</label>
                            <input type="text" id="setting-tally-company" wire:model="tallyCompany"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <span class="text-[10px] text-slate-400 mt-1 block">Must exactly match the opened company title in Tally Prime.</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="setting-tally-host" class="block text-xs font-bold text-slate-700 mb-1">Tally Computer LAN IP / Hostname</label>
                            <input type="text" id="setting-tally-host" wire:model="tallyHost" placeholder="e.g. 192.168.1.50 or 127.0.0.1"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <span class="text-[10px] text-slate-400 mt-1 block">Static IP address of the Windows PC running Tally.</span>
                        </div>

                        <div>
                            <label for="setting-tally-port" class="block text-xs font-bold text-slate-700 mb-1">XML Server Port</label>
                            <input type="number" id="setting-tally-port" wire:model="tallyPort" min="1" max="65535"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <span class="text-[10px] text-slate-400 mt-1 block">Default Tally HTTP port is 9000.</span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-3">
                        <label for="setting-tally-cost-centres" class="flex items-start gap-3 cursor-pointer select-none">
                            <input type="checkbox" id="setting-tally-cost-centres" wire:model="tallyIncludeCostCentres" class="w-4 h-4 mt-0.5 rounded text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-bold text-slate-900">Include Department Cost Centres in Tally XML</span>
                                <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed">
                                    Enable only if Cost Centres are turned on inside TallyPrime (F11 &rarr; Show more features &rarr; Enable Cost Centres). Applied to Revenue and Expense ledger lines.
                                </p>
                            </div>
                        </label>

                        <label for="setting-tally-auto-sync" class="flex items-start gap-3 cursor-pointer select-none">
                            <input type="checkbox" id="setting-tally-auto-sync" wire:model="tallyAutoSync" class="w-4 h-4 mt-0.5 rounded text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-bold text-slate-900">Enable Automatic 15-Minute Outbox Sync</span>
                                <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed">
                                    Automatically runs <code class="font-mono text-slate-700">php artisan tally:sync</code> when Tally Mode is set to Live LAN / VPN. Leave disabled on cloud hosting unless Tally can be reached over a secure VPN tunnel (use XML Download or Pull-Bridge instead).
                                </p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 5: RECEIPT PRINTING & HARDWARE                                -->
        <!-- ================================================================= -->
        @if ($activeTab === 'hardware')
            <div class="space-y-5 animate-fade-in">
                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Thermal Receipt Formatting &amp; Hardware Preferences</h3>
                        <p class="text-xs text-slate-500">Configure how paper chits and billing receipts format for customer handover.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label for="setting-paper-width" class="block text-xs font-bold text-slate-700 mb-1">Thermal Receipt Paper Width</label>
                            <select id="setting-paper-width" wire:model="receiptPaperWidth"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                <option value="80mm">80mm (Standard Desktop Counter Terminal)</option>
                                <option value="58mm">58mm (Compact / Handheld Mobile Terminal)</option>
                            </select>
                            <span class="text-[10px] text-slate-400 mt-1 block">Defines column wrapping and character pitch on printed chits.</span>
                        </div>
                    </div>

                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="checkbox" wire:model="autoPrintReceipt" class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-bold text-slate-900">Auto-Print Receipt Dialog on Checkout</span>
                                <p class="text-[10px] text-slate-500">Immediately open the browser print dialog as soon as a cashier completes a bill.</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="checkbox" wire:model="printWalletBalanceOnReceipt" class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-bold text-slate-900">Print Remaining Prepaid Balance on Member Receipts</span>
                                <p class="text-[10px] text-slate-500">Prints "Remaining Wallet Balance: ₹X,XXX.XX" at the bottom of the customer slip.</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="checkbox" wire:model="printGstBreakdownOnReceipt" class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-bold text-slate-900">Print Detailed CGST &amp; SGST Split Breakdown</span>
                                <p class="text-[10px] text-slate-500">Explicitly prints 9% CGST + 9% SGST breakdown rows on customer tax invoices.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Entrance & Turnstile Gate Access Control Card -->
                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                <span>🚪</span>
                                <span>Clubhouse Entrance &amp; Turnstile Gate Control</span>
                            </h3>
                            <p class="text-xs text-slate-500">Configure whether entry is governed by automated motorized turnstiles or staff-operated reception desk verification.</p>
                        </div>
                        <div>
                            @if ($enableTurnstileGate)
                                <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Automated Turnstiles Active</span>
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    <span>Staff Reception Desk Mode (Turnstiles Standby)</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 space-y-4">
                        <label class="flex items-start gap-3 cursor-pointer select-none p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 hover:bg-slate-100/60 transition-colors">
                            <input type="checkbox" wire:model.live="enableTurnstileGate" class="w-4 h-4 mt-0.5 rounded text-amber-600 focus:ring-amber-500">
                            <div>
                                <span class="text-xs font-bold text-slate-900 block">Enable Automated Physical Turnstiles &amp; Motorized Barriers</span>
                                <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                    Turn this <strong class="text-slate-700">OFF</strong> if Malabar Club operates with reception staff checking members manually at the front desk. Disabling physical turnstiles prevents false hardware disconnect warnings while leaving member RFID cards 100% active for POS tap-and-pay and member identification.
                                </p>
                            </div>
                        </label>

                        @if (! $enableTurnstileGate)
                            <!-- Informative Reception Mode Card -->
                            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 text-amber-900 text-xs space-y-2">
                                <div class="font-bold flex items-center gap-1.5 text-amber-950">
                                    <span>ℹ️</span>
                                    <span>Staff-Operated Reception Desk Mode (Current Club Setup)</span>
                                </div>
                                <ul class="list-disc list-inside space-y-1 text-[11px] text-amber-900/90 pl-1">
                                    <li><strong>Reception Verification:</strong> Front desk staff check member validity on the screen or counter reader.</li>
                                    <li><strong>Counter POS Tap-and-Pay:</strong> Members continue to tap their cards at the Bar, Restaurant, and Front Desk without disruption.</li>
                                    <li><strong>Clean Dashboard:</strong> Hardware disconnect warnings and optical barrier timeout alerts are silenced.</li>
                                    <li><strong>Future Ready:</strong> When physical turnstiles are installed in the future, simply enable this toggle to activate automated gate syncing.</li>
                                </ul>
                            </div>
                        @else
                            <!-- Turnstile Specific Options (When Hardware Enabled) -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-200 animate-fade-in">
                                <div>
                                    <label for="setting-guest-pass-mode" class="block text-xs font-bold text-slate-700 mb-1">Guest Pass Entry Verification</label>
                                    <select id="setting-guest-pass-mode" wire:model="guestPassVerificationMode"
                                        class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                        <option value="reception">Reception Desk Inspection (Human Verification)</option>
                                        <option value="turnstile">Automated Barcode / QR Optical Barrier Scan</option>
                                    </select>
                                    <span class="text-[10px] text-slate-400 mt-1 block">Determines how day visitor slips are validated at the clubhouse door.</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 6: AUDIT TRAIL RETENTION & SECURITY POLICY                    -->
        <!-- ================================================================= -->
        @if ($activeTab === 'audit')
            <div class="space-y-5 animate-fade-in">
                <div class="p-6 bg-white border border-slate-200 rounded-3xl shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">User Action Log &amp; Audit Trail Retention</h3>
                        <p class="text-xs text-slate-500">Configure how long staff action logs, master pricing modifications, and security events are retained before automated scheduled pruning.</p>
                    </div>

                    @php
                        $totalAuditLogs = \App\Domain\Shared\Models\AuditLog::count();
                        $oldestAuditLog = \App\Domain\Shared\Models\AuditLog::orderBy('created_at')->value('created_at');
                    @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 bg-slate-50 border border-slate-200/90 rounded-2xl">
                        <div>
                            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Active Retention Window</div>
                            <div class="text-base font-extrabold text-slate-900 mt-0.5">{{ $auditLogRetentionDays }} Days</div>
                            <div class="text-[10px] text-slate-400">Scheduled pruning runs daily</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Logged Actions</div>
                            <div class="text-base font-extrabold text-slate-900 mt-0.5">{{ number_format($totalAuditLogs) }} Entries</div>
                            <div class="text-[10px] text-slate-400">Across all staff and modules</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Oldest Log on Record</div>
                            <div class="text-base font-extrabold text-slate-900 mt-0.5">
                                {{ $oldestAuditLog ? $oldestAuditLog->format('d M Y') : 'No logs yet' }}
                            </div>
                            <div class="text-[10px] text-slate-400">Earliest active audit trail timestamp</div>
                        </div>
                    </div>

                    <div class="space-y-4 pt-2">
                        <div class="max-w-md">
                            <label for="setting-audit-retention" class="block text-xs font-bold text-slate-700 mb-1">
                                Action Log Retention Period (Days)
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" id="setting-audit-retention" wire:model="auditLogRetentionDays" min="7" max="365" step="1"
                                    class="w-32 px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                <span class="text-xs text-slate-600 font-semibold">Days</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">
                                Sets the rolling retention threshold. Log entries older than this number of days will be automatically deleted by the daily scheduler.
                                <br><span class="text-slate-600 font-medium">Recommended: <strong>30 to 45 days</strong> so records cover monthly managing committee reviews. (Min: 7 days, Max: 365 days).</span>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-2">
                            <span class="text-xs font-bold text-slate-600 mr-1">Quick Presets:</span>
                            @foreach ([15, 30, 45, 60, 90, 180] as $presetDays)
                                <button type="button"
                                    wire:click="$set('auditLogRetentionDays', {{ $presetDays }})"
                                    class="px-2.5 py-1 text-xs font-bold rounded-lg border transition-all cursor-pointer {{ $auditLogRetentionDays === $presetDays ? 'bg-amber-100 border-amber-300 text-amber-900 shadow-2xs' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                                    {{ $presetDays }} Days
                                </button>
                            @endforeach
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <span class="text-xs font-bold text-slate-900">Manual Pruning Trigger</span>
                                <p class="text-[11px] text-slate-500">Force immediate cleanup of any log entries older than {{ $auditLogRetentionDays }} days.</p>
                            </div>
                            <button type="button"
                                wire:click="pruneAuditLogsNow"
                                wire:confirm="Are you sure you want to permanently delete all audit log records older than {{ $auditLogRetentionDays }} days?"
                                class="h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 border border-slate-300 hover:border-rose-300 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0">
                                <span>🧹</span>
                                <span>Prune Expired Logs Now</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Bottom Save Bar -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="button"
                wire:click="saveSettings"
                class="h-10 px-6 inline-flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-bold text-xs rounded-xl border border-amber-500/80 shadow-xs transition-all active:scale-98 cursor-pointer">
                <svg class="w-4 h-4 text-slate-950 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save All Settings</span>
            </button>
        </div>

    </div>
</x-filament-panels::page>

