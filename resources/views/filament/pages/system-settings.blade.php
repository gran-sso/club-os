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
                            <h2 class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight truncate">Clubhouse &amp; System Configuration</h2>
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
                <span>Club Identity &amp; GSTIN</span>
            </button>

            <button type="button"
                wire:click="setTab('billing')"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shrink-0 {{ $activeTab === 'billing' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>💳</span>
                <span>Billing &amp; Prepaid Wallet</span>
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
                <span>Tally Prime LAN</span>
            </button>

            <button type="button"
                wire:click="setTab('hardware')"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shrink-0 {{ $activeTab === 'hardware' ? 'bg-slate-900 text-amber-400 shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <span>🖨️</span>
                <span>Receipt Printing</span>
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
                        <h3 class="text-sm font-extrabold text-slate-900">Statutory Tax &amp; Society Compliance</h3>
                        <p class="text-xs text-slate-500">Government registration numbers mandatory for input tax credit audits.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label for="setting-club-gstin" class="block text-xs font-bold text-slate-700 mb-1">Statutory GSTIN (Goods &amp; Services Tax #)</label>
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
                        <label for="setting-receipt-footer" class="block text-xs font-bold text-slate-700 mb-1">Bill &amp; Receipt Footer Statutory Notation</label>
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
                        <h3 class="text-sm font-extrabold text-slate-900">Prepaid Wallet Tender Boundaries</h3>
                        <p class="text-xs text-slate-500">Enforce monetary limits on top-ups and counter card debits.</p>
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
                                    <span class="text-xs font-bold text-slate-900">Allow Split Tender Checkout</span>
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

