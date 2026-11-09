<div x-data="{
        isSubmitting: false,
        showCloseShiftModal: false,
        getWire() {
            return this.$wire || (typeof $wire !== 'undefined' ? $wire : null);
        },
        getProp(name) {
            const w = this.getWire();
            if (!w) return null;
            if (typeof w.$get === 'function') return w.$get(name);
            if (typeof w.get === 'function') return w.get(name);
            return w[name];
        },
        handleGlobalKeydown(e) {
            const wire = this.getWire();
            if (!wire) return;

            // 0. ERROR DIALOG BOX HANDLING (Enter, Esc, Space dismisses the alert dialog)
            if (this.getProp('errorMessage')) {
                if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    wire.set('errorMessage', null);
                    return;
                }
                // Intercept other keypresses while error alert is active
                return;
            }

            // If shift is closed, disable billing shortcuts
            if (!this.getProp('activeShift')) {
                if (e.key === 'Escape') {
                    if (this.getProp('successMessage')) {
                        wire.set('successMessage', null);
                    }
                }
                return;
            }

            // Close Shift Modal Key Handling
            if (this.showCloseShiftModal) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this.showCloseShiftModal = false;
                    return;
                }
                return;
            }

            // 1. RECEIPT PREVIEW MODAL (P to Print, Enter / Esc to Close and Reset)
            if (this.getProp('showReceiptModal')) {
                if (e.key === 'p' || e.key === 'P') {
                    e.preventDefault();
                    window.print();
                    return;
                }
                if (e.key === 'Enter' || e.key === 'Escape') {
                    e.preventDefault();
                    wire.closeReceiptModal();
                    return;
                }
                return;
            }

            // 2. PAYMENT CONFIRMATION MODAL (Enter to Settle, Esc to Cancel)
            if (this.getProp('showPaymentConfirmModal')) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!this.isSubmitting) {
                        this.isSubmitting = true;
                        wire.confirmCheckout().finally(() => { this.isSubmitting = false; });
                    }
                    return;
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    wire.closePaymentConfirmModal();
                    return;
                }
                return;
            }

            // 3. MULTI-SERVING SELECTION MODAL (1, 2, 3, Arrow Keys, Enter, Esc)
            if (this.getProp('showServingModal')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    wire.closeServingModal();
                    return;
                }
                if (['1', '2', '3'].includes(e.key)) {
                    e.preventDefault();
                    const idx = parseInt(e.key) - 1;
                    const options = this.getProp('servingItemOptions');
                    if (options && options[idx] && options[idx].tariff_id) {
                        wire.selectServingAndAddToCart(options[idx].tariff_id);
                    }
                    return;
                }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    wire.navigateServingOptions('down');
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    wire.navigateServingOptions('up');
                    return;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    wire.selectHighlightedServing();
                    return;
                }
                return;
            }

            // 4. ESCAPE KEY (Cascading Clear: Quantity Input -> Autocomplete/Search -> Cart Clear -> Messages)
            if (e.key === 'Escape') {
                e.preventDefault();

                // If currently editing a cart quantity input, blur and return focus to item search
                if (document.activeElement?.classList?.contains('cart-qty-input')) {
                    document.activeElement.blur();
                    const itemInput = this.$refs.itemSearchInput || document.getElementById('item-search-input');
                    itemInput?.focus();
                    return;
                }

                // If item search autocomplete or search query has text, clear it
                const itemResults = this.getProp('itemSearchResults');
                const itemSearch = this.getProp('itemSearch');
                if ((itemResults && itemResults.length > 0) || (itemSearch && itemSearch.length > 0)) {
                    wire.set('itemSearchResults', []);
                    wire.set('itemSearch', '');
                    return;
                }

                // If customer search autocomplete or search query has text, clear it
                const custResults = this.getProp('customerSearchResults');
                const custQuery = this.getProp('searchQuery');
                if (custResults && custResults.length > 0) {
                    wire.set('customerSearchResults', []);
                    return;
                }
                if (custQuery && custQuery.length > 0) {
                    wire.set('searchQuery', '');
                    return;
                }

                // If cart has items, CLEAR CART!
                const cart = this.getProp('cart');
                const hasCart = cart && (Array.isArray(cart) ? cart.length > 0 : Object.keys(cart).length > 0);
                if (hasCart) {
                    wire.clearCart();
                    return;
                }

                // Dismiss any alerts/messages if present
                if (this.getProp('errorMessage')) {
                    wire.set('errorMessage', null);
                }
                if (this.getProp('successMessage')) {
                    wire.set('successMessage', null);
                }

                // Blur any focused element
                if (document.activeElement && ['INPUT', 'BUTTON'].includes(document.activeElement.tagName)) {
                    document.activeElement.blur();
                }
                return;
            }

            // 5. FUNCTION KEYS (Standard Ready Billing State)
            if (e.key === 'F2') {
                e.preventDefault();
                const input = this.$refs.customerSearchInput || document.getElementById('customer-search-input');
                input?.focus();
                input?.select();
                return;
            }
            if (e.key === 'F3') {
                e.preventDefault();
                const input = this.$refs.itemSearchInput || document.getElementById('item-search-input');
                input?.focus();
                input?.select();
                return;
            }
            if (e.key === 'F4') {
                e.preventDefault();
                const firstQty = document.querySelector('.cart-qty-input');
                if (firstQty) {
                    firstQty.focus();
                    firstQty.select();
                }
                return;
            }
            if (e.key === 'F8') {
                e.preventDefault();
                wire.initiateCheckout('prepaid');
                return;
            }
            if (e.key === 'F9') {
                e.preventDefault();
                wire.initiateCheckout('cash');
                return;
            }
            if (e.key === 'F10') {
                e.preventDefault();
                wire.initiateCheckout('digital');
                return;
            }

            // 6. DELETE KEY (Reset selected member when not actively typing in a text field)
            if (e.key === 'Delete' && !['INPUT', 'TEXTAREA'].includes(e.target?.tagName)) {
                if (this.getProp('selectedMember')) {
                    e.preventDefault();
                    wire.resetMember();
                    return;
                }
            }
        }
    }"
    x-on:keydown.window="handleGlobalKeydown($event)"
    x-on:focus-item-search.window="$nextTick(() => { $refs.itemSearchInput?.focus(); $refs.itemSearchInput?.select(); })"
    x-on:focus-customer-search.window="$nextTick(() => { $refs.customerSearchInput?.focus(); $refs.customerSearchInput?.select(); })"
    class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 pb-20 w-full space-y-5">

    <!-- Notifications / Alerts -->
    @if ($successMessage)
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 flex items-center justify-between shadow-xs animate-fade-in" role="alert">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold">✓</div>
                <div class="font-medium text-sm">{{ $successMessage }}</div>
            </div>
            <button wire:click="$set('successMessage', null)" class="text-emerald-700 hover:text-emerald-900 text-lg font-bold">×</button>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- ERROR ALERT DIALOGUE BOX (MODAL)           -->
    <!-- ========================================== -->
    @if ($errorMessage)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in" role="alertdialog" aria-modal="true" aria-labelledby="error-modal-title">
            <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-rose-200 p-6 space-y-4 text-center transform transition-all scale-100 animate-scale-up">
                <!-- Glowing Alert Icon -->
                <div class="w-16 h-16 rounded-2xl bg-rose-100 text-rose-600 border border-rose-200 flex items-center justify-center mx-auto shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <!-- Title & Message -->
                <div class="space-y-1.5">
                    <h3 id="error-modal-title" class="text-lg font-extrabold text-slate-900 tracking-tight">
                        Transaction Alert
                    </h3>
                    <div class="p-3.5 bg-rose-50/80 rounded-2xl border border-rose-200/80 text-rose-950 font-semibold text-sm leading-relaxed">
                        {{ $errorMessage }}
                    </div>
                </div>

                <!-- Dismiss Button -->
                <div class="pt-2">
                    <button type="button"
                            wire:click="$set('errorMessage', null)"
                            @click="$wire.set('errorMessage', null)"
                            autofocus
                            class="w-full py-3 bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-700 hover:to-rose-800 active:scale-[0.98] text-white font-bold text-sm rounded-xl transition shadow-lg shadow-rose-600/25 flex items-center justify-center gap-2 cursor-pointer">
                        <span>Acknowledge & Continue</span>
                        <kbd class="px-2 py-0.5 text-xs font-mono bg-rose-900/60 rounded text-rose-100 border border-rose-500/40">↵ Enter or Esc</kbd>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- 1. SHIFT STATUS & WORKSPACE GATEWAY -->
    <!-- ========================================== -->
    @if (!$activeShift)
        <!-- CLEAN, DISTRACTION-FREE OPEN COUNTER SHIFT GATEWAY -->
        <div class="min-h-[70vh] flex items-center justify-center py-6 sm:py-12">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-8 sm:p-10 shadow-xl text-center max-w-lg w-full space-y-6 animate-fade-in">
                <!-- Icon & Header -->
                <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center mx-auto text-amber-700 text-3xl font-bold shadow-2xs">
                    💼
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Open Counter Shift</h2>
                    <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                        Welcome, <strong class="text-slate-800">{{ auth()->user()->name }}</strong>. Select your assigned counter and declare opening cash to begin billing.
                    </p>
                </div>

                <!-- Form -->
                <form wire:submit.prevent="openShift" class="space-y-5 text-left pt-2">
                    <div>
                        <label for="select-counter-input" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Assigned Billing Counter
                        </label>
                        <select id="select-counter-input" wire:model="selectedCounterId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-slate-900 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-colors">
                            @foreach ($counters as $cnt)
                                <option value="{{ $cnt->id }}">{{ $cnt->name }} ({{ $cnt->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="opening-cash-input" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Opening Cash (₹)
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-slate-400 font-mono font-bold text-base pointer-events-none">₹</span>
                            <input type="number" id="opening-cash-input" step="10" wire:model="openingFloat" autofocus
                                class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-4 py-3 text-slate-900 font-mono font-bold text-base focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-colors">
                        </div>
                        <span class="text-[11px] text-slate-400 mt-1.5 block">Standard opening cash in drawer: ₹2,000.00</span>
                    </div>

                    <button type="submit" class="w-full py-3.5 px-5 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-sm rounded-xl shadow-md shadow-amber-500/20 active:scale-98 transition-all cursor-pointer flex items-center justify-center gap-2">
                        <span>Open Shift & Start Counter</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-white/80 text-slate-950 border border-amber-600 font-mono text-[11px] shadow-2xs font-bold">↵ Enter</kbd>
                    </button>
                </form>
            </div>
        </div>
    @else
        <!-- MAIN TWO-COLUMN POS WORKSPACE -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        <!-- LEFT COLUMN: Customer Selection & Wallet Top-Up (5 cols) -->
        <div class="lg:col-span-5 space-y-5">

            <!-- Customer Search / Identification Card -->
            <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs relative">
                <div class="flex items-center justify-between mb-3">
                    <label for="customer-search-input" class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <span>👤</span>
                        <span>Member Identification</span>
                        <kbd class="px-1.5 py-0.2 rounded bg-slate-100 text-amber-700 font-mono text-[10px] border border-slate-300 font-bold">F2</kbd>
                    </label>
                    @if ($selectedMember)
                        <button wire:click="resetMember" type="button" class="text-xs text-rose-600 hover:text-rose-800 font-semibold focus:outline-none">
                            Change [Del]
                        </button>
                    @endif
                </div>

                <!-- Live Search Input -->
                <div class="relative">
                    <input type="text"
                        id="customer-search-input"
                        x-ref="customerSearchInput"
                        wire:model.live.debounce.150ms="searchQuery"
                        wire:keydown.arrow-down.prevent="navigateCustomerResults('down')"
                        wire:keydown.arrow-up.prevent="navigateCustomerResults('up')"
                        wire:keydown.enter.prevent="selectHighlightedCustomer"
                        placeholder="Scan card, type membership #, name, or phone..."
                        aria-label="Search members by card number, membership ID, name, or phone"
                        class="w-full bg-slate-50 border border-slate-300 rounded-2xl pl-11 pr-20 py-3 text-slate-900 placeholder-slate-400 font-mono text-sm focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none">
                    
                    <div class="absolute left-3.5 top-3.5 text-slate-400 pointer-events-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    @if ($searchQuery)
                        <button type="button" wire:click="resetMember" class="absolute right-3 top-2.5 px-2 py-1 text-xs text-slate-400 hover:text-slate-700 rounded-lg bg-slate-100">
                            Clear
                        </button>
                    @endif
                </div>

                <!-- Floating Customer Autocomplete Results Dropdown -->
                @if (!empty($customerSearchResults))
                    <div class="absolute left-5 right-5 top-[92px] z-40 bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden divide-y divide-slate-100">
                        @foreach ($customerSearchResults as $idx => $cMatch)
                            <div wire:click="selectCustomerById({{ $cMatch['id'] }})"
                                class="p-3 cursor-pointer flex items-center justify-between text-xs transition-colors {{ $highlightedCustomerIndex === $idx ? 'bg-amber-50 text-amber-950 border-l-4 border-amber-500' : 'text-slate-700 hover:bg-slate-50' }}">
                                <div>
                                    <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                        <span>{{ $cMatch['name'] }}</span>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-amber-800 border border-slate-200 font-semibold">{{ $cMatch['membership_number'] }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">
                                        Card: <span class="font-mono">{{ $cMatch['card_number'] }}</span> • Phone: {{ $cMatch['phone'] }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono font-bold text-amber-700">₹{{ number_format($cMatch['balance_paise'] / 100, 2) }}</span>
                                    <span class="block text-[9px] uppercase font-semibold text-slate-400">{{ $cMatch['category'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Quick Member Shortcut Badges for fast cashier demo testing -->
                <div class="mt-2.5 flex items-center gap-1.5 flex-wrap">
                    <span class="text-[10px] text-slate-400 font-semibold uppercase">Demo:</span>
                    <button wire:click="$set('searchQuery', 'CARD-VENU-001'); searchMember" type="button" class="text-[11px] px-2 py-0.5 rounded-md bg-slate-100 hover:bg-amber-50 hover:border-amber-300 text-slate-700 border border-slate-200 font-mono transition-colors cursor-pointer">Venu (MC-001)</button>
                    <button wire:click="$set('searchQuery', 'CARD-PRIYA-002'); searchMember" type="button" class="text-[11px] px-2 py-0.5 rounded-md bg-slate-100 hover:bg-amber-50 hover:border-amber-300 text-slate-700 border border-slate-200 font-mono transition-colors cursor-pointer">Priya (MC-002)</button>
                    <button wire:click="$set('searchQuery', 'CARD-RAJESH-003'); searchMember" type="button" class="text-[11px] px-2 py-0.5 rounded-md bg-slate-100 hover:bg-amber-50 hover:border-amber-300 text-slate-700 border border-slate-200 font-mono transition-colors cursor-pointer">Rajesh (MC-003)</button>
                </div>
            </div>

            <!-- Active Member Details Card & Authoritative Wallet Balance -->
            @if ($selectedMember)
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs animate-fade-in">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center font-bold text-slate-950 text-xl shadow-md shadow-amber-500/20">
                                {{ substr($selectedMember->person->first_name ?? 'M', 0, 1) }}{{ substr($selectedMember->person->last_name ?? '', 0, 1) }}
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-slate-900 tracking-tight">
                                    {{ $selectedMember->person->first_name }} {{ $selectedMember->person->last_name }}
                                </h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-mono font-semibold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">{{ $selectedMember->membership_number }}</span>
                                    <span class="text-xs text-slate-500">{{ $selectedMember->category->name }}</span>
                                </div>
                            </div>
                        </div>

                        <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 font-medium capitalize">
                            {{ $selectedMember->status->value }}
                        </span>
                    </div>

                    <!-- Authoritative Wallet Box -->
                    <div class="mt-6 p-4 rounded-2xl bg-gradient-to-br from-amber-50 via-amber-100/40 to-orange-50 border border-amber-300/80 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-900 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Authoritative Prepaid Balance
                            </span>
                            <span class="text-[10px] text-slate-500 font-mono">Server Ledger Verified</span>
                        </div>
                        <div class="text-3xl font-extrabold font-mono text-amber-950 mt-1">
                            ₹{{ number_format($memberWalletBalancePaise / 100, 2) }}
                        </div>
                        <div class="text-[11px] text-slate-600 mt-1 flex items-center justify-between">
                            <span>Strict No-Credit Active</span>
                            <span>Active Card: <strong class="text-slate-800 font-mono">{{ $activeCard ? $activeCard->card_number : 'None' }}</strong></span>
                        </div>
                    </div>

                    <!-- Dependents (if any) -->
                    @if ($selectedMember->dependents->isNotEmpty())
                        <div class="mt-4 pt-3 border-t border-slate-200">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Registered Dependents</span>
                            <div class="mt-2 space-y-1.5">
                                @foreach ($selectedMember->dependents as $dep)
                                    <div class="flex items-center justify-between text-xs px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200">
                                        <span class="text-slate-700">{{ $dep->person->first_name }} {{ $dep->person->last_name }} ({{ $dep->relationship }})</span>
                                        <span class="text-[10px] px-2 py-0.5 rounded {{ $dep->can_spend_wallet ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $dep->can_spend_wallet ? 'Can Spend Wallet' : 'No Wallet Rights' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Wallet Top-Up Section -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-slate-900 mb-3 flex items-center gap-2">
                        <span class="text-amber-500">⚡</span> Wallet Recharge
                    </h4>

                    <!-- Quick amount buttons -->
                    <div class="grid grid-cols-4 gap-2 mb-3">
                        <button type="button" wire:click="$set('rechargeAmount', 500)" class="py-1.5 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer {{ $rechargeAmount == 500 ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">₹500</button>
                        <button type="button" wire:click="$set('rechargeAmount', 1000)" class="py-1.5 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer {{ $rechargeAmount == 1000 ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">₹1,000</button>
                        <button type="button" wire:click="$set('rechargeAmount', 2000)" class="py-1.5 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer {{ $rechargeAmount == 2000 ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">₹2,000</button>
                        <button type="button" wire:click="$set('rechargeAmount', 5000)" class="py-1.5 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer {{ $rechargeAmount == 5000 ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">₹5,000</button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Recharge Amount (₹)</label>
                            <input type="number" step="50" wire:model="rechargeAmount" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-slate-900 font-mono font-bold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center space-x-2 p-2.5 rounded-xl border {{ $rechargeTender === 'cash' ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-slate-50 border-slate-200 text-slate-600' }} cursor-pointer">
                                <input type="radio" value="cash" wire:model="rechargeTender" class="text-amber-500">
                                <span class="text-xs font-semibold">Cash Payment</span>
                            </label>
                            <label class="flex items-center space-x-2 p-2.5 rounded-xl border {{ $rechargeTender === 'digital' ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-slate-50 border-slate-200 text-slate-600' }} cursor-pointer">
                                <input type="radio" value="digital" wire:model="rechargeTender" class="text-amber-500">
                                <span class="text-xs font-semibold">UPI / Card</span>
                            </label>
                        </div>

                        @if ($rechargeTender === 'digital')
                            <div>
                                <input type="text" wire:model="rechargeRef" placeholder="UPI Reference / UTR Number..."
                                    class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs text-slate-900 placeholder-slate-400 font-mono focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                        @endif

                        <button wire:click="rechargeWallet"
                            wire:loading.attr="disabled"
                            type="button"
                            class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm shadow-md shadow-emerald-500/20 active:scale-98 transition-all flex items-center justify-center space-x-2 cursor-pointer">
                            <span>Confirm Recharge & Issue Receipt</span>
                        </button>
                    </div>
                </div>
            @else
                <div class="bg-slate-50/80 border border-dashed border-slate-300 rounded-3xl p-8 text-center text-slate-500">
                    <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center mx-auto mb-3 text-slate-500 shadow-2xs">
                        💳
                    </div>
                    <div class="font-bold text-sm text-slate-700">No Customer Selected</div>
                    <p class="text-xs mt-1 text-slate-500">Scan member card or search above to view prepaid balance or sponsor guest passes.</p>
                </div>
            @endif
        </div>

        <!-- RIGHT COLUMN: Prominent Item Search & Current Order / Bill (7 cols) -->
        <div class="lg:col-span-7 space-y-5">

            <!-- Prominent Keyboard-Driven Item Search Bar -->
            <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs relative">
                <div class="flex items-center justify-between mb-2">
                    <label for="item-search-input" class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <span>🔍</span>
                        <span>Item Search & Quick Add</span>
                        <kbd class="px-1.5 py-0.2 rounded bg-slate-100 text-amber-700 font-mono text-[10px] border border-slate-300 font-bold">F3</kbd>
                    </label>
                    <span class="text-[11px] text-slate-500">Arrow Up/Down to navigate • Enter to add</span>
                </div>

                <div class="relative">
                    <input type="text"
                        id="item-search-input"
                        x-ref="itemSearchInput"
                        wire:model.live.debounce.150ms="itemSearch"
                        wire:keydown.arrow-down.prevent="navigateItemResults('down')"
                        wire:keydown.arrow-up.prevent="navigateItemResults('up')"
                        wire:keydown.enter.prevent="selectHighlightedItem"
                        placeholder="Type item name, code or barcode (e.g. Beer, Meals, 890123400001)..."
                        aria-label="Search saleable items by name, item code and barcode"
                        class="w-full bg-slate-50 border border-slate-300 rounded-2xl pl-11 pr-24 py-3.5 text-slate-900 placeholder-slate-400 font-mono text-sm focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none">
                    
                    <div class="absolute left-3.5 top-4 text-slate-400 pointer-events-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    @if ($itemSearch)
                        <button type="button"
                            wire:click="$set('itemSearch', ''); $set('itemSearchResults', []);"
                            class="absolute right-3 top-3 px-2.5 py-1 text-xs text-slate-500 hover:text-slate-800 rounded-lg bg-slate-100 cursor-pointer">
                            Clear
                        </button>
                    @endif
                </div>

                <!-- Floating Item Search Autocomplete Results -->
                @if (!empty($itemSearchResults))
                    <div class="absolute left-5 right-5 top-[95px] z-50 bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden divide-y divide-slate-100 max-h-80 overflow-y-auto">
                        @foreach ($itemSearchResults as $idx => $itemMatch)
                            <div wire:click="{{ $itemMatch['has_multiple_servings'] ? 'openServingModalForItem(' . json_encode($itemMatch) . ')' : 'addToCart(' . $itemMatch['tariff_id'] . ')' }}"
                                class="p-3.5 cursor-pointer flex items-center justify-between text-xs transition-colors {{ $highlightedItemIndex === $idx ? 'bg-amber-50 text-amber-950 border-l-4 border-amber-500' : 'text-slate-700 hover:bg-slate-50' }}">
                                <div class="flex-1 min-w-0 pr-4">
                                    <div class="font-bold flex items-center gap-2 truncate">
                                        <span class="text-sm text-slate-900">{{ $itemMatch['name'] }}</span>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 uppercase font-semibold">{{ $itemMatch['code'] }}</span>
                                        @if ($itemMatch['barcode'])
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200">🏷 {{ $itemMatch['barcode'] }}</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $itemMatch['has_multiple_servings'] ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $itemMatch['serving_display'] }}
                                        </span>
                                        <span class="{{ $itemMatch['is_in_stock'] ? 'text-emerald-700' : 'text-rose-600 font-bold' }}">
                                            @if ($itemMatch['stock_qty'] !== null)
                                                ● {{ $itemMatch['is_in_stock'] ? "Stock: {$itemMatch['stock_qty']} {$itemMatch['base_unit']}" : "OUT OF STOCK" }}
                                            @else
                                                ● Available
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <div class="font-bold font-mono text-sm text-amber-700">
                                        {{ $itemMatch['rate_display'] }}
                                    </div>
                                    <span class="inline-block mt-1 text-[11px] font-semibold text-slate-500">
                                        {{ $itemMatch['has_multiple_servings'] ? 'Select Unit ↵' : '+ Add to bill ↵' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Guest Registration Details (If Guest Pass In Cart) -->
            @if ($isGuestSale)
                <div class="bg-amber-50/70 border border-amber-200 rounded-3xl p-5 shadow-xs animate-fade-in">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-900 mb-3">
                        <span>👤</span> Guest Entry Registration (Mandatory Sponsor)
                    </div>
                    <p class="text-xs text-slate-600 mb-3">
                        Guest access requires an eligible sponsoring member. Sponsoring Member:
                        <strong class="text-slate-900">{{ $selectedMember ? ($selectedMember->person->first_name . ' ' . $selectedMember->person->last_name . ' (' . $selectedMember->membership_number . ')') : 'None Selected — Please select a member!' }}</strong>
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-slate-700 font-semibold mb-1">Guest First Name *</label>
                            <input type="text" wire:model="guestFirstName" placeholder="e.g. Rahul"
                                class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-700 font-semibold mb-1">Guest Last Name</label>
                            <input type="text" wire:model="guestLastName" placeholder="e.g. Varma"
                                class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-700 font-semibold mb-1">Mobile Phone</label>
                            <input type="text" wire:model="guestPhone" placeholder="98XXXXXXXX"
                                class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            @endif

            <!-- Active Cart & Financial Checkout Section (Prominent Order Board) -->
            <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2.5">
                        <span class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>🧾</span>
                            <span>Current Order / Bill</span>
                        </span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-mono font-bold">
                            {{ count($cart) }} {{ count($cart) === 1 ? 'item' : 'items' }}
                        </span>
                        <kbd class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-mono text-[10px] border border-slate-300">F4 Focus Qty</kbd>
                    </div>
                    @if (count($cart) > 0)
                        <button wire:click="clearCart" type="button" class="text-xs text-rose-600 hover:text-rose-800 hover:bg-rose-50 px-2.5 py-1 rounded-lg border border-transparent hover:border-rose-200 font-semibold transition-all focus:outline-none cursor-pointer">
                            Clear Order [Del All]
                        </button>
                    @endif
                </div>

                @if (empty($cart))
                    <!-- Empty Cart State with Cashier Quick-Guide -->
                    <div class="py-12 px-4 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center mx-auto mb-4 text-2xl shadow-2xs">
                            🧾
                        </div>
                        <h4 class="text-base font-bold text-slate-900 mb-1">Bill is ready for items</h4>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mb-6">
                            Press <kbd class="px-1.5 py-0.5 rounded bg-slate-100 text-amber-800 font-mono text-xs border border-slate-300 font-bold">F3</kbd> or scan a barcode to quickly search and add saleable items.
                        </p>

                        <!-- Keyboard Quick Reference Cards -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-w-lg mx-auto text-left">
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] text-slate-500 font-semibold uppercase">Search / Scan</span>
                                    <kbd class="px-1.5 py-0.5 rounded bg-white text-amber-800 font-mono font-bold text-[10px] border border-slate-300">F3</kbd>
                                </div>
                                <div class="text-[11px] font-medium text-slate-700">Quick add items</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] text-slate-500 font-semibold uppercase">Customer</span>
                                    <kbd class="px-1.5 py-0.5 rounded bg-white text-amber-800 font-mono font-bold text-[10px] border border-slate-300">F2</kbd>
                                </div>
                                <div class="text-[11px] font-medium text-slate-700">Swipe or card find</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] text-slate-500 font-semibold uppercase">Prepaid Pay</span>
                                    <kbd class="px-1.5 py-0.5 rounded bg-white text-amber-800 font-mono font-bold text-[10px] border border-slate-300">F8</kbd>
                                </div>
                                <div class="text-[11px] font-medium text-slate-700">Server ledger debit</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] text-slate-500 font-semibold uppercase">Cash Drawer</span>
                                    <kbd class="px-1.5 py-0.5 rounded bg-white text-emerald-800 font-mono font-bold text-[10px] border border-slate-300">F9</kbd>
                                </div>
                                <div class="text-[11px] font-medium text-slate-700">Physical drawer</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] text-slate-500 font-semibold uppercase">Digital / UPI</span>
                                    <kbd class="px-1.5 py-0.5 rounded bg-white text-blue-800 font-mono font-bold text-[10px] border border-slate-300">F10</kbd>
                                </div>
                                <div class="text-[11px] font-medium text-slate-700">Gateway reference</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] text-slate-500 font-semibold uppercase">Edit Qty</span>
                                    <kbd class="px-1.5 py-0.5 rounded bg-white text-slate-700 font-mono font-bold text-[10px] border border-slate-300">F4</kbd>
                                </div>
                                <div class="text-[11px] font-medium text-slate-700">Jump to bill lines</div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Spacious Order Table with Full Item Details -->
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white mb-5">
                        <div class="max-h-[30rem] overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 sticky top-0 z-10 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-600">
                                    <tr>
                                        <th class="py-2.5 pl-4 pr-2 w-10 text-center">#</th>
                                        <th class="py-2.5 px-3">Item Description</th>
                                        <th class="py-2.5 px-3 text-right">Rate & GST</th>
                                        <th class="py-2.5 px-3 text-center w-36">Quantity</th>
                                        <th class="py-2.5 px-3 text-right">Net Total</th>
                                        <th class="py-2.5 pl-2 pr-4 text-center w-16">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-800">
                                    @foreach ($cart as $index => $item)
                                        @php
                                            $lineGross = ($item['quantity'] * $item['unit_price_paise']) / 100.0;
                                            $lineTax = round(($lineGross * $item['tax_percent']) / 100, 2);
                                            $lineNet = $lineGross + $lineTax;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="py-3 pl-4 pr-2 text-center font-mono text-[11px] text-slate-400">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="py-3 px-3">
                                                <div class="font-bold text-slate-900 text-sm flex items-center gap-2 flex-wrap">
                                                    <span>{{ $item['name'] }}</span>
                                                    @if (!empty($item['serving_name']))
                                                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 font-semibold">
                                                            {{ $item['serving_name'] }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                                    Base: ₹{{ number_format($item['unit_price_paise'] / 100, 2) }}
                                                </div>
                                            </td>
                                            <td class="py-3 px-3 text-right font-mono text-[11px] text-slate-600">
                                                <div>₹{{ number_format($item['unit_price_paise'] / 100, 2) }}</div>
                                                <div class="text-[10px] text-slate-400">+{{ $item['tax_percent'] }}% GST</div>
                                            </td>
                                            <td class="py-3 px-3 text-center">
                                                <!-- Keyboard Editable Quantity Input -->
                                                <div class="inline-flex items-center space-x-1.5">
                                                    <button type="button" wire:click="updateQuantity({{ $index }}, -1)"
                                                        class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 flex items-center justify-center font-bold text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"
                                                        aria-label="Decrease quantity">-</button>
                                                    
                                                    <input type="number"
                                                        min="1"
                                                        step="1"
                                                        value="{{ $item['quantity'] }}"
                                                        wire:change="setCartQuantity({{ $index }}, $event.target.value)"
                                                        wire:keydown.enter.prevent="$wire.setCartQuantity({{ $index }}, $event.target.value); $dispatch('focus-item-search');"
                                                        class="cart-qty-input w-12 text-center bg-slate-50 border border-slate-300 rounded-lg py-1 font-mono text-slate-900 text-xs font-bold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                                        aria-label="Item quantity for {{ $item['name'] }}">
                                                    
                                                    <button type="button" wire:click="updateQuantity({{ $index }}, 1)"
                                                        class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 flex items-center justify-center font-bold text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"
                                                        aria-label="Increase quantity">+</button>
                                                </div>
                                            </td>
                                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-900 text-sm">
                                                ₹{{ number_format($lineNet, 2) }}
                                            </td>
                                            <td class="py-3 pl-2 pr-4 text-center">
                                                <button type="button" wire:click="removeFromCart({{ $index }})"
                                                    class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-colors focus:outline-none cursor-pointer"
                                                    title="Remove item [Del]"
                                                    aria-label="Remove item">
                                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Cart Financial Totals -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2 mb-5">
                        <div class="flex justify-between text-xs text-slate-600">
                            <span>Gross Subtotal</span>
                            <span class="font-mono text-slate-900 font-semibold">₹{{ number_format($cartGross, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-600">
                            <span>GST & Taxes (Statutory Breakdown)</span>
                            <span class="font-mono text-slate-900 font-semibold">₹{{ number_format($cartTax, 2) }}</span>
                        </div>
                        <div class="pt-2.5 border-t border-slate-200 flex justify-between items-center">
                            <div>
                                <span class="text-sm font-bold text-slate-900 block">Net Total Payable</span>
                                <span class="text-[10px] text-slate-500 font-mono">Inclusive of all taxes</span>
                            </div>
                            <span class="text-3xl font-black font-mono text-amber-700">₹{{ number_format($cartNet, 2) }}</span>
                        </div>
                    </div>

                    <!-- Tender Selection & Checkout Action Bar -->
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2.5 flex items-center justify-between">
                            <span>Select Tender Method (Strict No-Credit)</span>
                            <span class="text-[11px] text-amber-700 font-bold">Press F8, F9, or F10</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                            <!-- Option 1: Prepaid Wallet [F8] -->
                            @php
                                $totalPaise = (int) round($cartNet * 100);
                                $hasSufficientPrepaid = $selectedMember && ($memberWalletBalancePaise >= $totalPaise);
                            @endphp
                            <button type="button"
                                wire:click="initiateCheckout('prepaid')"
                                wire:loading.attr="disabled"
                                @if (!$hasSufficientPrepaid) disabled @endif
                                class="p-3.5 rounded-2xl border text-left flex flex-col justify-between transition-all focus:outline-none focus:ring-2 focus:ring-amber-400 {{ $hasSufficientPrepaid ? 'bg-amber-500 hover:bg-amber-400 border-amber-500 text-slate-950 cursor-pointer active:scale-95 shadow-md shadow-amber-500/20' : 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed opacity-60' }}">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-xs">Prepaid Wallet</span>
                                        <kbd class="px-1.5 py-0.5 rounded bg-amber-600/20 text-slate-950 font-bold text-[10px] border border-amber-600/30">F8</kbd>
                                    </div>
                                    <div class="text-[10px] mt-1 font-medium">Avail: ₹{{ number_format($memberWalletBalancePaise / 100, 2) }}</div>
                                </div>
                                <div class="mt-2 text-[11px] font-bold font-mono">
                                    {{ $hasSufficientPrepaid ? '✓ Pay ₹' . number_format($cartNet, 2) : 'Insufficient Funds' }}
                                </div>
                            </button>

                            <!-- Option 2: Cash Payment [F9] -->
                            <button type="button"
                                wire:click="initiateCheckout('cash')"
                                wire:loading.attr="disabled"
                                class="p-3.5 rounded-2xl border border-emerald-600 bg-emerald-600 hover:bg-emerald-500 text-white text-left flex flex-col justify-between transition-all cursor-pointer active:scale-95 focus:outline-none focus:ring-2 focus:ring-emerald-400 shadow-md shadow-emerald-600/20">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-xs">Cash Payment</span>
                                        <kbd class="px-1.5 py-0.5 rounded bg-emerald-700 text-white font-bold text-[10px] border border-emerald-500">F9</kbd>
                                    </div>
                                    <div class="text-[10px] text-emerald-100 mt-1">Physical Cash</div>
                                </div>
                                <div class="mt-2 text-[11px] font-bold font-mono text-white">
                                    Pay ₹{{ number_format($cartNet, 2) }}
                                </div>
                            </button>

                            <!-- Option 3: UPI / Digital [F10] -->
                            <button type="button"
                                wire:click="initiateCheckout('digital')"
                                wire:loading.attr="disabled"
                                class="p-3.5 rounded-2xl border border-blue-600 bg-blue-600 hover:bg-blue-500 text-white text-left flex flex-col justify-between transition-all cursor-pointer active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400 shadow-md shadow-blue-600/20">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-xs">UPI / Digital</span>
                                        <kbd class="px-1.5 py-0.5 rounded bg-blue-700 text-white font-bold text-[10px] border border-blue-500">F10</kbd>
                                    </div>
                                    <div class="text-[10px] text-blue-100 mt-1">Gateway Clearing</div>
                                </div>
                                <div class="mt-2 text-[11px] font-bold font-mono text-white">
                                    Pay ₹{{ number_format($cartNet, 2) }}
                                </div>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 1: Explicit Serving Selection Dialog -->
    <!-- ========================================== -->
    @if ($showServingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog"
            aria-modal="true"
            aria-labelledby="serving-modal-title">
            <div class="bg-white border border-slate-200 rounded-3xl p-6 max-w-lg w-full shadow-2xl relative"
                x-on:keydown.arrow-down.prevent="$wire.navigateServingOptions('down')"
                x-on:keydown.arrow-up.prevent="$wire.navigateServingOptions('up')"
                x-on:keydown.enter.prevent="$wire.selectHighlightedServing()"
                x-on:keydown.1.prevent="$wire.selectServingAndAddToCart({{ $servingItemOptions[0]['tariff_id'] ?? 0 }})"
                x-on:keydown.2.prevent="$wire.selectServingAndAddToCart({{ $servingItemOptions[1]['tariff_id'] ?? 0 }})"
                x-on:keydown.3.prevent="$wire.selectServingAndAddToCart({{ $servingItemOptions[2]['tariff_id'] ?? 0 }})">
                
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4">
                    <div>
                        <span class="text-[11px] uppercase font-bold tracking-wider text-amber-700">Serving Size Selection</span>
                        <h3 id="serving-modal-title" class="text-lg font-bold text-slate-900 mt-0.5">{{ $servingItemName }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $servingItemStockText }}</p>
                    </div>
                    <button wire:click="closeServingModal" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer" aria-label="Close serving dialog">×</button>
                </div>

                <p class="text-xs text-slate-600 mb-4">
                    This item has multiple configured serving sizes. Select unit with number key [1, 2, 3] or navigate with Arrow keys and press <kbd class="px-1 py-0.5 rounded bg-slate-100 text-amber-800 font-bold border border-slate-300">Enter</kbd>:
                </p>

                <div class="space-y-2.5 mb-6">
                    @foreach ($servingItemOptions as $sIdx => $sOpt)
                        <div wire:click="selectServingAndAddToCart({{ $sOpt['tariff_id'] }})"
                            class="p-3.5 rounded-2xl border cursor-pointer flex items-center justify-between text-xs transition-all {{ $highlightedServingIndex === $sIdx ? 'bg-amber-50 border-amber-400 text-slate-900 shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                            <div class="flex items-center space-x-3">
                                <span class="w-6 h-6 rounded-lg bg-white text-amber-700 font-mono font-bold flex items-center justify-center text-xs border border-slate-300 shadow-2xs">
                                    {{ $sIdx + 1 }}
                                </span>
                                <div>
                                    <div class="font-bold text-slate-900 text-sm">{{ $sOpt['serving_name'] }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                        Rate: ₹{{ number_format($sOpt['rate_paise'] / 100, 2) }} + {{ $sOpt['tax_percent'] }}% GST
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold font-mono text-base text-amber-700">
                                    ₹{{ number_format($sOpt['net_rate_paise'] / 100, 2) }}
                                </div>
                                <span class="text-[10px] text-slate-500">Press {{ $sIdx + 1 }} or ↵</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-200">
                    <span class="text-[11px] text-slate-500 font-mono">[Esc] Cancel without adding</span>
                    <button wire:click="closeServingModal" type="button" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 2: Payment Tender Confirmation Dialog -->
    <!-- ========================================== -->
    @if ($showPaymentConfirmModal)
        @php
            $isPrepaid = $pendingTenderType === 'prepaid';
            $totalPaise = (int) round($cartNet * 100);
            $hasSufficient = !$isPrepaid || ($selectedMember && $memberWalletBalancePaise >= $totalPaise);
            $tenderLabel = match($pendingTenderType) {
                'prepaid' => 'Prepaid Wallet',
                'cash' => 'Cash Payment',
                'digital' => 'UPI / Card Digital',
                default => strtoupper($pendingTenderType ?? '')
            };
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog"
            aria-modal="true"
            aria-labelledby="payment-confirm-title">
            <div class="bg-white border border-slate-200 rounded-3xl p-6 max-w-md w-full shadow-2xl relative text-slate-900">
                
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4">
                    <div>
                        <span class="text-[11px] uppercase font-bold tracking-wider text-amber-700">Payment Authorization</span>
                        <h3 id="payment-confirm-title" class="text-lg font-bold text-slate-900 mt-0.5">Confirm Bill Settlement</h3>
                    </div>
                    <button wire:click="closePaymentConfirmModal" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer" aria-label="Cancel checkout">×</button>
                </div>

                <div class="space-y-4 mb-6 text-xs">
                    <!-- Customer Details -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-[10px] uppercase font-bold text-slate-500">Customer</div>
                        <div class="font-bold text-sm text-slate-900 mt-0.5">
                            @if ($selectedMember)
                                {{ $selectedMember->person->first_name }} {{ $selectedMember->person->last_name }} ({{ $selectedMember->membership_number }})
                            @elseif ($isGuestSale && !empty($guestFirstName))
                                Guest: {{ $guestFirstName }} {{ $guestLastName }}
                            @else
                                Counter Guest
                            @endif
                        </div>
                    </div>

                    <!-- Tender Details & Amount -->
                    <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-300">
                        <div class="flex justify-between items-center text-slate-600 mb-1">
                            <span>Settlement Tender</span>
                            <span class="font-bold text-amber-800 font-mono text-sm">{{ $tenderLabel }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-amber-200">
                            <span class="font-bold text-slate-900 text-sm">Total Amount Payable</span>
                            <span class="text-2xl font-extrabold font-mono text-amber-700">₹{{ number_format($cartNet, 2) }}</span>
                        </div>
                    </div>

                    <!-- Prepaid Specific Checks -->
                    @if ($isPrepaid)
                        <div class="p-3.5 rounded-2xl {{ $hasSufficient ? 'bg-emerald-50 border border-emerald-300 text-emerald-900' : 'bg-rose-50 border border-rose-300 text-rose-900' }}">
                            <div class="flex justify-between">
                                <span>Available Prepaid Balance:</span>
                                <span class="font-mono font-bold">₹{{ number_format($memberWalletBalancePaise / 100, 2) }}</span>
                            </div>
                            @if ($hasSufficient)
                                <div class="flex justify-between mt-1 text-[11px] text-slate-600">
                                    <span>Remaining Balance After Sale:</span>
                                    <span class="font-mono text-emerald-700 font-bold">₹{{ number_format(($memberWalletBalancePaise - $totalPaise) / 100, 2) }}</span>
                                </div>
                            @else
                                <div class="mt-2 font-bold text-[11px] text-rose-700">
                                    ⚠️ INSUFFICIENT BALANCE: Cannot settle bill. Strict no-credit policy enforced.
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Action Buttons: Enter to Confirm, Esc to Cancel -->
                <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-200">
                    <button wire:click="closePaymentConfirmModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                        Cancel [Esc]
                    </button>

                    <button wire:click="confirmCheckout"
                        wire:loading.attr="disabled"
                        @if (!$hasSufficient || $isProcessingPayment) disabled @endif
                        type="button"
                        class="flex-1 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs shadow-md shadow-amber-500/20 active:scale-95 transition-all flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer {{ !$hasSufficient ? 'opacity-50 cursor-not-allowed' : '' }}">
                        <span wire:loading.remove wire:target="confirmCheckout">Confirm & Print Receipt [Enter] ↵</span>
                        <span wire:loading wire:target="confirmCheckout">Settling Bill...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 3: 80mm Thermal Receipt Preview Modal -->
    <!-- ========================================== -->
    @if ($showReceiptModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 animate-fade-in print:p-0 print:static print:bg-transparent print:backdrop-blur-none"
            role="dialog"
            aria-modal="true"
            aria-labelledby="receipt-modal-title">
            <div class="bg-white text-slate-900 rounded-3xl p-6 max-w-sm w-full shadow-2xl relative border border-slate-200 print:shadow-none print:border-none print:p-0 print:max-w-none">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-4">
                    <div>
                        <span id="receipt-modal-title" class="text-xs font-bold text-slate-500 uppercase tracking-wider">80mm Thermal Receipt</span>
                        @if ($lastReceiptNumber)
                            <div class="text-[11px] font-mono text-slate-800 font-semibold mt-0.5">Bill: {{ $lastReceiptNumber }}</div>
                        @endif
                    </div>
                    <button wire:click="closeReceiptModal" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer print:hidden no-print" aria-label="Close receipt">×</button>
                </div>

                <!-- Receipt Paper Simulation -->
                <div class="bg-amber-50/40 p-4 rounded-xl border border-dashed border-slate-300 font-mono text-[11px] leading-relaxed whitespace-pre overflow-x-auto shadow-inner text-slate-800 max-h-96 print:max-h-none print:border-none print:bg-transparent print:p-0">
{{ $receiptContent }}
                </div>

                <div class="mt-5 flex items-center justify-between gap-3 print:hidden no-print">
                    <button onclick="window.print()" type="button" class="flex-1 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-slate-900 cursor-pointer print:hidden no-print">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Print Receipt [P]
                    </button>
                    <button wire:click="closeReceiptModal" type="button" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-colors focus:outline-none focus:ring-2 focus:ring-slate-300 cursor-pointer print:hidden no-print">
                        Done / Next [Esc]
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 4: Reconcile & Close Counter Dialog -->
    <!-- ========================================== -->
    <div x-show="showCloseShiftModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 animate-fade-in"
        role="dialog"
        aria-modal="true"
        aria-labelledby="close-counter-dialog-title"
        style="display: none;">
        <div @click.away="showCloseShiftModal = false" class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl relative text-slate-900">
            <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4">
                <div>
                    <span class="text-[11px] uppercase font-bold tracking-wider text-rose-600">Shift Reconciliation</span>
                    <h3 id="close-counter-dialog-title" class="text-lg font-bold text-slate-900 mt-0.5">Reconcile & Close Counter</h3>
                </div>
                <button @click="showCloseShiftModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer" aria-label="Close dialog">×</button>
            </div>

            <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                Count the physical currency notes and coins in the cash drawer. Prepaids and digital card/UPI tenders reconcile automatically.
            </p>

            <form wire:submit.prevent="closeShift" @submit="showCloseShiftModal = false" class="space-y-4">
                <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Expected Closing Cash</span>
                        <span class="text-xs text-slate-400">Opening + Cash Sales</span>
                    </div>
                    <span class="text-xl font-bold font-mono text-slate-900">
                        ₹{{ number_format($activeShift->calculateExpectedCashPaise() / 100, 2) }}
                    </span>
                </div>

                <div>
                    <label for="closing-cash-counted-input" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Closing Cash Counted (₹)
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-slate-400 font-mono font-bold text-base pointer-events-none">₹</span>
                        <input type="number" id="closing-cash-counted-input" step="10" wire:model="closingCashCounted" autofocus
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-4 py-3 text-slate-900 font-mono font-bold text-base focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-200">
                    <button type="button" @click="showCloseShiftModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                        Cancel [Esc]
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md shadow-rose-600/20 active:scale-95 transition-all cursor-pointer">
                        Confirm Closing Cash & End Shift [Enter] ↵
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- ========================================== -->
    <!-- DOCKED CONTEXTUAL BOTTOM STATUS BAR -->
    <!-- ========================================== -->
    <footer class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 shadow-lg px-4 sm:px-6 lg:px-8 py-2 print:hidden no-print">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2.5 text-xs font-mono">
            <!-- Left Side: Terminal & Shift Status -->
            <div class="flex items-center gap-2 text-slate-700">
                @if ($activeShift)
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>ONLINE</span>
                    </span>
                    <span class="font-sans text-xs hidden sm:inline-flex items-center gap-1.5 text-slate-600">
                        <strong class="text-slate-800">{{ $activeShift->counter->name }}</strong>
                        <span class="text-slate-300">•</span>
                        <span class="font-mono text-[11px] text-slate-500">{{ $activeShift->shift_number }}</span>
                    </span>
                    <span class="hidden md:inline-flex items-center gap-1 text-[11px] text-slate-500 font-sans">
                        <span class="text-slate-300">•</span>
                        <span>Expected Cash:</span>
                        <strong class="font-mono text-slate-800 font-bold">₹{{ number_format($activeShift->calculateExpectedCashPaise() / 100, 2) }}</strong>
                    </span>
                    <button type="button" @click="showCloseShiftModal = true" class="ml-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-700 border border-slate-300 hover:border-rose-300 text-[11px] font-sans font-semibold transition-colors cursor-pointer" title="Reconcile and close counter shift">
                        <span>Close Shift</span>
                    </button>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-300 text-[11px] font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <span>SHIFT CLOSED</span>
                    </span>
                    <span class="text-slate-500 text-xs font-sans hidden sm:inline">Enter opening cash above to begin counter billing</span>
                @endif
            </div>

            <!-- Right Side: Contextual Shortcut Keycaps -->
            <div class="flex items-center flex-wrap gap-1.5 sm:gap-2">
                @if ($errorMessage)
                    <!-- Context: Error Alert Dialog Active -->
                    <span class="inline-flex items-center gap-1.5 text-xs text-rose-700 font-sans font-semibold">
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                        <span>Alert:</span>
                    </span>
                    <button type="button" wire:click="$set('errorMessage', null)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-rose-900 text-white font-mono shadow-2xs">↵ Enter / Esc</kbd>
                        <span>Dismiss</span>
                    </button>
                @elseif (!$activeShift)
                    <!-- Context: Shift Closed -->
                    <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 font-sans">
                        <span>Press</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-white text-slate-900 border border-slate-300 font-mono text-[10px] shadow-2xs font-bold">↵ Enter</kbd>
                        <span>to Open Shift</span>
                    </span>
                @elseif ($showReceiptModal)
                    <!-- Context: Receipt Modal -->
                    <span class="text-[11px] text-slate-500 font-sans mr-1 hidden sm:inline">Receipt:</span>
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-slate-800 text-white border border-slate-700 shadow-2xs">P</kbd>
                        <span>Print</span>
                    </button>
                    <button type="button" wire:click="closeReceiptModal" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-300 cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-900 border border-slate-300 shadow-2xs">Enter / Esc</kbd>
                        <span>Next Customer</span>
                    </button>

                @elseif ($showPaymentConfirmModal)
                    <!-- Context: Payment Confirmation Modal -->
                    <span class="text-[11px] text-amber-800 font-sans font-semibold mr-1 hidden sm:inline">Authorization:</span>
                    <button type="button" wire:click="confirmCheckout" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs border border-amber-600 shadow-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-950 border border-amber-600 font-mono shadow-2xs">Enter ↵</kbd>
                        <span>Confirm Settle</span>
                    </button>
                    <button type="button" wire:click="closePaymentConfirmModal" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Cancel</span>
                    </button>

                @elseif ($showServingModal)
                    <!-- Context: Serving Selection Modal -->
                    <span class="text-[11px] text-amber-800 font-sans font-semibold mr-1 hidden sm:inline">Unit:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-200">
                        <kbd class="px-1 py-0.2 rounded bg-white text-amber-800 border border-slate-300 font-bold">1</kbd>
                        <kbd class="px-1 py-0.2 rounded bg-white text-amber-800 border border-slate-300 font-bold">2</kbd>
                        <kbd class="px-1 py-0.2 rounded bg-white text-amber-800 border border-slate-300 font-bold">3</kbd>
                        <span>Select Serving</span>
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-200 hidden sm:inline-flex">
                        <kbd class="px-1 py-0.2 rounded bg-white text-slate-700 border border-slate-300">↑ ↓</kbd>
                        <kbd class="px-1 py-0.2 rounded bg-white text-slate-700 border border-slate-300">Enter</kbd>
                    </span>
                    <button type="button" wire:click="closeServingModal" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Cancel</span>
                    </button>

                @elseif (!empty($itemSearchResults))
                    <!-- Context: Item Autocomplete Active -->
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-200">
                        <kbd class="px-1 py-0.2 rounded bg-white text-slate-700 border border-slate-300">↑ ↓</kbd>
                        <span>Navigate</span>
                    </span>
                    <button type="button" wire:click="selectHighlightedItem" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-amber-800 border border-amber-300 shadow-2xs">Enter</kbd>
                        <span>Add to Bill</span>
                    </button>
                    <button type="button" wire:click="$set('itemSearchResults', []); $set('itemSearch', '');" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Close</span>
                    </button>

                @elseif (!empty($customerSearchResults))
                    <!-- Context: Customer Autocomplete Active -->
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-200">
                        <kbd class="px-1 py-0.2 rounded bg-white text-slate-700 border border-slate-300">↑ ↓</kbd>
                        <span>Navigate</span>
                    </span>
                    <button type="button" wire:click="selectHighlightedCustomer" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-amber-800 border border-amber-300 shadow-2xs">Enter</kbd>
                        <span>Select Member</span>
                    </button>
                    <button type="button" wire:click="$set('customerSearchResults', [])" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Close</span>
                    </button>

                @else
                    <!-- Context: Standard Ready Counter Billing State -->
                    <button type="button" @click="$refs.customerSearchInput?.focus()" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 hover:bg-amber-50 hover:border-amber-300 text-slate-700 hover:text-slate-900 border border-slate-200 transition-colors cursor-pointer" title="Find Member (F2)">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-amber-700 font-bold text-[10px] border border-slate-300 shadow-2xs">F2</kbd>
                        <span>Customer</span>
                    </button>

                    <button type="button" @click="$refs.itemSearchInput?.focus()" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 hover:bg-amber-50 hover:border-amber-300 text-slate-700 hover:text-slate-900 border border-slate-200 transition-colors cursor-pointer" title="Search / Scan Item (F3)">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-amber-700 font-bold text-[10px] border border-slate-300 shadow-2xs">F3</kbd>
                        <span>Item Search</span>
                    </button>

                    @if (!empty($cart))
                        <button type="button" @click="const el = document.querySelector('.cart-qty-input'); el?.focus(); el?.select();" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors cursor-pointer" title="Focus Cart Quantity (F4)">
                            <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-700 font-bold text-[10px] border border-slate-300 shadow-2xs">F4</kbd>
                            <span>Qty</span>
                        </button>

                        <button type="button" wire:click="initiateCheckout('prepaid')" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold transition-colors cursor-pointer" title="Prepaid Checkout (F8)">
                            <kbd class="px-1.5 py-0.2 rounded bg-white text-amber-800 font-bold text-[10px] border border-amber-300 shadow-2xs">F8</kbd>
                            <span>Prepaid</span>
                        </button>

                        <button type="button" wire:click="initiateCheckout('cash')" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-900 border border-emerald-300 font-bold transition-colors cursor-pointer" title="Cash Checkout (F9)">
                            <kbd class="px-1.5 py-0.2 rounded bg-white text-emerald-800 font-bold text-[10px] border border-emerald-300 shadow-2xs">F9</kbd>
                            <span>Cash</span>
                        </button>

                        <button type="button" wire:click="initiateCheckout('digital')" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-900 border border-blue-300 font-bold transition-colors cursor-pointer" title="Digital Checkout (F10)">
                            <kbd class="px-1.5 py-0.2 rounded bg-white text-blue-800 font-bold text-[10px] border border-blue-300 shadow-2xs">F10</kbd>
                            <span>Digital</span>
                        </button>

                        <button type="button" wire:click="clearCart" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition-colors cursor-pointer" title="Clear Bill">
                            <kbd class="px-1.5 py-0.2 rounded bg-white text-rose-700 font-bold text-[10px] border border-rose-200 shadow-2xs">Esc</kbd>
                            <span>Clear</span>
                        </button>
                    @else
                        <span class="text-slate-400 text-[11px] font-sans hidden md:inline ml-1">Scan barcode or press F3 to add items</span>
                    @endif
                @endif
            </div>
        </div>
    </footer>

</div>
