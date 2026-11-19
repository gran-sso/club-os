<div x-data="{
        isSubmitting: false,
        showCloseShiftModal: false,
        highlightedItemIdx: 0,
        highlightedCustIdx: 0,
        highlightedServingIdx: 0,
        getWire() {
            return this.$wire || (typeof $wire !== 'undefined' ? $wire : null);
        },
        getProp(name) {
            const w = this.getWire();
            if (!w) return null;
            if (typeof w.get === 'function') {
                const val = w.get(name);
                if (val !== undefined) return val;
            }
            return w[name];
        },
        navigateItems(direction) {
            if (this.getProp('showServingModal')) {
                this.navigateServings(direction);
                return;
            }
            const results = this.getProp('itemSearchResults');
            if (!results || results.length === 0) return;
            const count = results.length;
            if (direction === 'down') {
                this.highlightedItemIdx = (this.highlightedItemIdx + 1) % count;
            } else {
                this.highlightedItemIdx = (this.highlightedItemIdx - 1 + count) % count;
            }
            this.getWire()?.set('highlightedItemIndex', this.highlightedItemIdx, false);
            this.scrollActiveIntoView('item-result-' + this.highlightedItemIdx);
        },
        selectItem() {
            if (this.getProp('showServingModal')) {
                this.selectServing();
                return;
            }
            const input = this.$refs.itemSearchInput || document.getElementById('item-search-input');
            const activeEl = document.getElementById('item-result-' + this.highlightedItemIdx);
            if (activeEl) {
                if (input) {
                    input.value = '';
                }
                activeEl.click();
                this.highlightedItemIdx = 0;
                return;
            }
            const results = this.getProp('itemSearchResults');
            if (results && results.length > 0) {
                const item = results[this.highlightedItemIdx] || results[0];
                if (item) {
                    if (input) {
                        input.value = '';
                    }
                    if (item.has_multiple_servings) {
                        this.getWire()?.openServingModalForItem(item);
                    } else {
                        this.getWire()?.addToCart(item.tariff_id);
                        this.getWire()?.set('itemSearch', '');
                        this.getWire()?.set('itemSearchResults', []);
                        this.highlightedItemIdx = 0;
                    }
                    return;
                }
            }
            const query = this.getProp('itemSearch');
            if (query && query.trim().length > 0) {
                if (input) {
                    input.value = '';
                }
                this.getWire()?.selectHighlightedItem();
            }
        },
        navigateCustomers(direction) {
            if (this.getProp('showServingModal')) {
                this.navigateServings(direction);
                return;
            }
            const results = this.getProp('customerSearchResults');
            if (!results || results.length === 0) return;
            const count = results.length;
            if (direction === 'down') {
                this.highlightedCustIdx = (this.highlightedCustIdx + 1) % count;
            } else {
                this.highlightedCustIdx = (this.highlightedCustIdx - 1 + count) % count;
            }
            this.getWire()?.set('highlightedCustomerIndex', this.highlightedCustIdx, false);
            this.scrollActiveIntoView('cust-result-' + this.highlightedCustIdx);
        },
        selectCustomer() {
            if (this.getProp('showServingModal')) {
                this.selectServing();
                return;
            }
            const activeEl = document.getElementById('cust-result-' + this.highlightedCustIdx);
            if (activeEl) {
                activeEl.click();
                this.highlightedCustIdx = 0;
                return;
            }
            const results = this.getProp('customerSearchResults');
            if (results && results.length > 0) {
                const match = results[this.highlightedCustIdx] || results[0];
                if (match) {
                    if ((match.type || 'member') === 'room_guest') {
                        this.getWire()?.selectRoomGuest(match.id);
                    } else {
                        this.getWire()?.selectCustomerById(match.id);
                    }
                    this.getWire()?.set('customerSearchResults', []);
                    this.highlightedCustIdx = 0;
                    return;
                }
            }
            this.getWire()?.searchMember();
        },
        navigateServings(direction) {
            const options = this.getProp('servingItemOptions');
            if (!options || options.length === 0) return;
            const count = options.length;
            if (direction === 'down') {
                this.highlightedServingIdx = (this.highlightedServingIdx + 1) % count;
            } else {
                this.highlightedServingIdx = (this.highlightedServingIdx - 1 + count) % count;
            }
            this.getWire()?.set('highlightedServingIndex', this.highlightedServingIdx, false);
            this.scrollActiveIntoView('serving-option-' + this.highlightedServingIdx);
        },
        selectServing() {
            const options = this.getProp('servingItemOptions');
            if (options && options.length > 0) {
                const opt = options[this.highlightedServingIdx] || options[0];
                if (opt && opt.tariff_id) {
                    this.getWire()?.selectServingAndAddToCart(opt.tariff_id);
                    this.highlightedServingIdx = 0;
                    return;
                }
            }
            const activeEl = document.getElementById('serving-option-' + this.highlightedServingIdx);
            if (activeEl) {
                activeEl.click();
                this.highlightedServingIdx = 0;
            }
        },
        selectServingByIndex(idx) {
            const options = this.getProp('servingItemOptions');
            if (options && options[idx] && options[idx].tariff_id) {
                this.getWire()?.selectServingAndAddToCart(options[idx].tariff_id);
                this.highlightedServingIdx = 0;
            }
        },
        scrollActiveIntoView(id) {
            this.$nextTick(() => {
                const el = document.getElementById(id);
                if (el) {
                    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            });
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

            // 1. RECEIPT PREVIEW MODAL (P to Print, H to Park Tab, Enter / Esc to Close)
            if (this.getProp('showReceiptModal')) {
                if (e.key === 'p' || e.key === 'P') {
                    e.preventDefault();
                    window.print();
                    return;
                }
                if (e.key === 'h' || e.key === 'H') {
                    if (this.getProp('isTableCheckReceipt')) {
                        e.preventDefault();
                        wire.holdCurrentCart();
                        wire.closeReceiptModal();
                        return;
                    }
                }
                if (e.key === 'Enter' || e.key === 'Escape') {
                    e.preventDefault();
                    wire.closeReceiptModal();
                    return;
                }
                return;
            }

            // SHIFT ACTIVITY & SUMMARY MODAL (Esc to close, P to print handover slip)
            if (this.getProp('showShiftSummaryModal')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    wire.closeShiftSummaryModal();
                    return;
                }
                if (e.key === 'p' || e.key === 'P') {
                    e.preventDefault();
                    wire.printCurrentShiftSummarySlip();
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

            // HOLD TAB CONFIRM MODAL (Enter to confirm hold, Esc to cancel)
            if (this.getProp('showHoldTabModal')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    wire.closeHoldTabModal();
                    return;
                }
                if (e.key === 'Enter' && !this.isSubmitting) {
                    e.preventDefault();
                    this.isSubmitting = true;
                    wire.holdCurrentCart().finally(() => { this.isSubmitting = false; });
                    return;
                }
                return;
            }

            // HELD TABS LIST MODAL (Esc to close)
            if (this.getProp('showHeldTabsListModal')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    wire.closeHeldTabsListModal();
                    return;
                }
                return;
            }

            // UNSETTLED WALK-OUT RECORDING MODAL (Esc to cancel)
            if (this.getProp('showUnsettledWalkoutModal')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    wire.closeUnsettledWalkoutModal();
                    return;
                }
                return;
            }

            // OUTSTANDING WALK-OUT DUES SETTLEMENT MODAL (Esc to dismiss)
            if (this.getProp('showOutstandingDuesModal')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    wire.closeOutstandingDuesModal();
                    return;
                }
                return;
            }

            // EXISTING ACTIVE RUNNING TAB DETECTED PROMPT MODAL (Enter to resume, Esc to dismiss/start fresh)
            if (this.getProp('showExistingTabPromptModal')) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    wire.resumePromptTab();
                    return;
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    wire.dismissExistingTabPrompt();
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
                    this.navigateServings('down');
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigateServings('up');
                    return;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.selectServing();
                    return;
                }
                return;
            }

            // 4. ACTIVE ITEM AUTOCOMPLETE (Arrow Keys, Enter)
            const itemResults = this.getProp('itemSearchResults');
            if (itemResults && itemResults.length > 0) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navigateItems('down');
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigateItems('up');
                    return;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.selectItem();
                    return;
                }
            }

            // 5. ACTIVE CUSTOMER AUTOCOMPLETE (Arrow Keys, Enter)
            const custResults = this.getProp('customerSearchResults');
            if (custResults && custResults.length > 0) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navigateCustomers('down');
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigateCustomers('up');
                    return;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.selectCustomer();
                    return;
                }
            }

            // 6. CART QUANTITY ROW NAVIGATION (When focused in a .cart-qty-input)
            if (document.activeElement?.classList?.contains('cart-qty-input')) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const inputs = Array.from(document.querySelectorAll('.cart-qty-input'));
                    const idx = inputs.indexOf(document.activeElement);
                    if (idx !== -1 && idx < inputs.length - 1) {
                        inputs[idx + 1].focus();
                        inputs[idx + 1].select();
                    }
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const inputs = Array.from(document.querySelectorAll('.cart-qty-input'));
                    const idx = inputs.indexOf(document.activeElement);
                    if (idx > 0) {
                        inputs[idx - 1].focus();
                        inputs[idx - 1].select();
                    }
                    return;
                }
            }

            // 7. ESCAPE KEY (Cascading Clear: Quantity Input -> Autocomplete/Search -> Cart Clear -> Messages)
            if (e.key === 'Escape') {
                e.preventDefault();

                // If currently editing a cart quantity input, blur and return focus to item search
                if (document.activeElement?.classList?.contains('cart-qty-input')) {
                    document.activeElement.blur();
                    const itemInput = this.$refs.itemSearchInput || document.getElementById('item-search-input');
                    itemInput?.focus();
                    return;
                }

                // If customer search input is active or focused, clear customer search first
                const isCustFocused = document.activeElement === this.$refs.customerSearchInput || document.activeElement?.id === 'customer-search-input';
                if (isCustFocused) {
                    const custQuery = this.getProp('searchQuery');
                    if ((custResults && custResults.length > 0) || (custQuery && custQuery.length > 0)) {
                        wire.set('customerSearchResults', []);
                        wire.set('searchQuery', '');
                        this.highlightedCustIdx = 0;
                        document.activeElement?.blur();
                        return;
                    }
                }

                // If item search autocomplete or search query has text, clear it
                const itemSearch = this.getProp('itemSearch');
                if ((itemResults && itemResults.length > 0) || (itemSearch && itemSearch.length > 0)) {
                    wire.set('itemSearchResults', []);
                    wire.set('itemSearch', '');
                    this.highlightedItemIdx = 0;
                    return;
                }

                // If customer search autocomplete or search query has text, clear it
                const custQuery = this.getProp('searchQuery');
                if (custResults && custResults.length > 0) {
                    wire.set('customerSearchResults', []);
                    this.highlightedCustIdx = 0;
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

            // 8. FUNCTION KEYS (Standard Ready Billing State)
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
            if (e.key === 'F6') {
                e.preventDefault();
                wire.openHoldTabModal();
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

            // 9. DELETE KEY (Reset selected member when not actively typing in a text field)
            if (e.key === 'Delete' && !['INPUT', 'TEXTAREA'].includes(e.target?.tagName)) {
                if (this.getProp('selectedMember')) {
                    e.preventDefault();
                    wire.resetMember();
                    return;
                }
            }
        }
    }" x-init="
        $watch('$wire.itemSearchResults', () => { highlightedItemIdx = 0; });
        $watch('$wire.customerSearchResults', () => { highlightedCustIdx = 0; });
        $watch('$wire.servingItemOptions', () => { highlightedServingIdx = 0; });
        $watch('$wire.showServingModal', value => {
            if (value) {
                highlightedServingIdx = 0;
                $nextTick(() => {
                    document.activeElement?.blur();
                    const dlg = document.getElementById('serving-modal-dialog');
                    dlg?.focus();
                });
            }
        });
    " x-on:keydown.window="handleGlobalKeydown($event)"
    x-on:focus-item-search.window="$nextTick(() => { $refs.itemSearchInput?.focus(); $refs.itemSearchInput?.select(); })"
    x-on:focus-customer-search.window="$nextTick(() => { $refs.customerSearchInput?.focus(); $refs.customerSearchInput?.select(); })"
    class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 pb-20 w-full space-y-5">

    <!-- Notifications / Alerts -->
    @if ($successMessage)
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 flex items-center justify-between shadow-xs animate-fade-in {{ $showReceiptModal ? 'print:hidden' : '' }}"
            role="alert">
            <div class="flex items-center space-x-3">
                <div
                    class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold">
                    ✓</div>
                <div class="font-medium text-sm">{{ $successMessage }}</div>
            </div>
            <button wire:click="$set('successMessage', null)"
                class="text-emerald-700 hover:text-emerald-900 text-lg font-bold">×</button>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- ERROR ALERT DIALOGUE BOX (MODAL)           -->
    <!-- ========================================== -->
    @if ($errorMessage)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
            role="alertdialog" aria-modal="true" aria-labelledby="error-modal-title"
            @click.self="$wire.set('errorMessage', null)"
            @keydown.escape.window="$wire.set('errorMessage', null)">
            <div
                class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-rose-200 p-6 space-y-4 text-center transform transition-all scale-100 animate-scale-up">
                <!-- Glowing Alert Icon -->
                <div
                    class="w-16 h-16 rounded-2xl bg-rose-100 text-rose-600 border border-rose-200 flex items-center justify-center mx-auto shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <!-- Title & Message -->
                <div class="space-y-1.5">
                    <h3 id="error-modal-title" class="text-lg font-extrabold text-slate-900 tracking-tight">
                        Transaction Alert
                    </h3>
                    <div
                        class="p-3.5 bg-rose-50/80 rounded-2xl border border-rose-200/80 text-rose-950 font-semibold text-sm leading-relaxed">
                        {{ $errorMessage }}
                    </div>
                </div>

                <!-- Dismiss Button -->
                <div class="pt-2">
                    <button type="button" wire:click="$set('errorMessage', null)" @click="$wire.set('errorMessage', null)"
                        autofocus
                        class="w-full py-3 bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-700 hover:to-rose-800 active:scale-[0.98] text-white font-bold text-sm rounded-xl transition shadow-lg shadow-rose-600/25 flex items-center justify-center gap-2 cursor-pointer">
                        <span>Acknowledge & Continue</span>
                        <kbd
                            class="px-2 py-0.5 text-xs font-mono bg-rose-900/60 rounded text-rose-100 border border-rose-500/40">↵
                            Enter or Esc</kbd>
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
            <div
                class="bg-white border border-slate-200/90 rounded-3xl p-8 sm:p-10 shadow-xl text-center max-w-lg w-full space-y-6 animate-fade-in">
                <!-- Icon & Header -->
                <div
                    class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center mx-auto text-amber-700 text-3xl font-bold shadow-2xs">
                    💼
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Open Counter Shift</h2>
                    <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                        Welcome, <strong class="text-slate-800">{{ auth()->user()->name }}</strong>. Select your assigned
                        counter and declare opening cash to begin billing.
                    </p>
                </div>

                <!-- Form -->
                <form wire:submit.prevent="openShift" class="space-y-5 text-left pt-2">
                    <div>
                        <label for="select-counter-input"
                            class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Assigned Billing Counter
                        </label>
                        <select id="select-counter-input" wire:model="selectedCounterId"
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-slate-900 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-colors">
                            @foreach ($counters as $cnt)
                                <option value="{{ $cnt->id }}">{{ $cnt->name }} ({{ $cnt->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="opening-cash-input"
                            class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Opening Cash (₹)
                        </label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-3.5 text-slate-400 font-mono font-bold text-base pointer-events-none">₹</span>
                            <input type="number" id="opening-cash-input" step="10" wire:model="openingFloat" autofocus
                                class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-4 py-3 text-slate-900 font-mono font-bold text-base focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-colors">
                        </div>
                        <span class="text-[11px] text-slate-400 mt-1.5 block">Standard opening cash in drawer:
                            ₹2,000.00</span>
                    </div>

                    <button type="submit"
                        class="w-full py-3.5 px-5 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-sm rounded-xl shadow-md shadow-amber-500/20 active:scale-98 transition-all cursor-pointer flex items-center justify-center gap-2">
                        <span>Open Shift & Start Counter</span>
                        <kbd
                            class="px-1.5 py-0.5 rounded bg-white/80 text-slate-950 border border-amber-600 font-mono text-[11px] shadow-2xs font-bold">↵
                            Enter</kbd>
                    </button>
                </form>
            </div>
        </div>
    @else
        <!-- ========================================== -->
        <!-- RUNNING BAR TABS STRIP (MEMBER-FIRST TABS) -->
        <!-- ========================================== -->
        <div
            class="bg-white border border-slate-200/90 rounded-3xl p-4 shadow-xs {{ $showReceiptModal ? 'print:hidden' : '' }}">
            <div class="flex items-center justify-between gap-3 flex-wrap lg:flex-nowrap">
                <!-- Left: Title & Indicator -->
                <div class="flex items-center gap-2.5 shrink-0">
                    <div
                        class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-base font-bold shadow-2xs">
                        🍸
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-wider text-slate-800">Running Tabs</span>
                            <span
                                class="px-2 py-0.5 rounded-full text-[11px] font-extrabold {{ $heldTabs->count() > 0 ? 'bg-amber-500 text-slate-950 animate-pulse' : 'bg-slate-100 text-slate-500' }}">
                                {{ $heldTabs->count() }} Open
                            </span>
                        </div>
                        <div class="text-[10px] text-slate-400">Parked in-session bills for Bar / Lounge</div>
                    </div>
                </div>

                <!-- Center: Active Tabs Chips & Responsive More Tabs Dropdown (Accessible for Desktop Mouse Users) -->
                @php
                    $primaryTopTabs = $heldTabs->take(4);
                    if ($activeHeldTabId && ! $primaryTopTabs->contains('id', $activeHeldTabId)) {
                        $activeModel = $heldTabs->firstWhere('id', $activeHeldTabId);
                        if ($activeModel) {
                            $primaryTopTabs = $primaryTopTabs->prepend($activeModel)->unique('id')->take(4);
                        }
                    }
                    $secondaryHeldTabs = $heldTabs->filter(fn($t) => ! $primaryTopTabs->contains('id', $t->id));
                    $overflowHeldTabsCount = $secondaryHeldTabs->count();
                @endphp
                <div class="flex items-center gap-2 flex-wrap py-1 max-w-full flex-1">
                    @forelse ($primaryTopTabs as $tab)
                        <div class="inline-flex items-center gap-0.5 shrink-0">
                            <button type="button" wire:click="loadHeldTab({{ $tab->id }})"
                                class="px-3 py-1.5 rounded-l-xl text-xs transition-all flex items-center gap-2 border border-r-0 cursor-pointer {{ $activeHeldTabId === $tab->id ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-sm font-bold scale-[1.01]' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200 hover:border-slate-300 font-semibold' }}">
                                <span class="truncate max-w-[110px]">{{ $tab->customer_display_name }}</span>
                                @if ($tab->table_identifier)
                                    <span
                                        class="px-1.5 py-0.2 rounded text-[10px] font-mono {{ $activeHeldTabId === $tab->id ? 'bg-amber-600 text-white font-bold' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $tab->table_identifier }}
                                    </span>
                                @endif
                                <span
                                    class="font-mono text-[11px] {{ $activeHeldTabId === $tab->id ? 'text-slate-950 font-extrabold' : 'text-amber-800 font-bold' }}">
                                    ₹{{ number_format($tab->total_amount, 2) }}
                                </span>
                            </button>
                            <button type="button" wire:click="printHeldTabTableCheck({{ $tab->id }})"
                                class="px-2 py-1.5 rounded-r-xl text-xs border border-l transition-all cursor-pointer bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border-indigo-200 hover:border-indigo-300"
                                title="Print Table Check for floor bearer to take to the table">
                                <span>📄</span>
                            </button>
                        </div>
                    @empty
                        <span class="text-xs text-slate-400 italic">No open tabs parked. Add drinks for a member and press <kbd
                                class="px-1 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-mono border border-slate-200">F6</kbd>
                            to hold.</span>
                    @endforelse

                    @if ($overflowHeldTabsCount > 0)
                        <!-- More Tabs Dropdown for Desktop Mouse Users -->
                        <div class="relative shrink-0" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                class="px-2.5 py-1.5 rounded-xl bg-slate-200/90 hover:bg-slate-300 text-slate-800 text-xs font-bold shrink-0 transition-colors cursor-pointer flex items-center gap-1 shadow-2xs border border-slate-300/80"
                                title="View more open running tabs">
                                <span>📂 More Tabs (+{{ $overflowHeldTabsCount }})</span>
                                <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div x-show="open" x-cloak
                                @click.outside="open = false"
                                @keydown.escape.window="open = false"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                                class="absolute left-0 mt-2 z-50 w-80 bg-white rounded-2xl border border-slate-200 shadow-xl p-2 space-y-1.5">
                                <div class="flex items-center justify-between px-2.5 py-1 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <span>Other Open Tabs ({{ $overflowHeldTabsCount }})</span>
                                    <span>Amount</span>
                                </div>
                                <div class="max-h-60 overflow-y-auto space-y-1 pr-1">
                                    @foreach ($secondaryHeldTabs as $oTab)
                                        <div class="flex items-center justify-between p-1.5 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-200 transition">
                                            <button type="button" @click="open = false; $wire.loadHeldTab({{ $oTab->id }})"
                                                class="flex-1 text-left min-w-0 pr-2 cursor-pointer">
                                                <div class="font-bold text-xs text-slate-800 truncate">{{ $oTab->customer_display_name }}</div>
                                                <div class="text-[10px] text-slate-500 flex items-center gap-1.5">
                                                    @if ($oTab->table_identifier)
                                                        <span class="px-1 py-0.2 rounded bg-slate-100 text-slate-700 font-mono">{{ $oTab->table_identifier }}</span>
                                                    @endif
                                                    @if ($oTab->accompanying_guest_name)
                                                        <span class="text-amber-700 truncate">👤 {{ $oTab->accompanying_guest_name }}</span>
                                                    @endif
                                                </div>
                                            </button>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <span class="font-mono text-xs font-bold text-amber-900">₹{{ number_format($oTab->total_amount, 2) }}</span>
                                                <button type="button" wire:click="printHeldTabTableCheck({{ $oTab->id }})"
                                                    class="p-1 rounded-lg text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border border-indigo-200 cursor-pointer"
                                                    title="Print Table Check">
                                                    📄
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="pt-1.5 border-t border-slate-100">
                                    <button type="button" @click="open = false; $wire.openHeldTabsListModal()"
                                        class="w-full py-1.5 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-amber-400 font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                                        <span>🔍 Open Searchable Tabs Manager (All {{ $heldTabs->count() }})</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Right: Controls -->
                <div class="flex items-center gap-2 shrink-0">
                    @if ($activeHeldTabId)
                        <button type="button" wire:click="resetWorkspaceToNewBill"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300 transition-colors flex items-center gap-1.5 cursor-pointer">
                            <span>＋ New Bill</span>
                        </button>
                    @endif

                    <button type="button" wire:click="openHoldTabModal"
                        class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs shadow-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>Hold Tab</span>
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white/70 text-slate-950 font-mono text-[10px] font-bold border border-amber-600/40">F6</kbd>
                    </button>

                    @if ($heldTabs->count() > 0)
                        <button type="button" wire:click="openHeldTabsListModal"
                            class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold border border-slate-200 transition-colors cursor-pointer"
                            title="Manage all held tabs">
                            <span>☰ All ({{ $heldTabs->count() }})</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Outstanding Unsettled Walk-Out Dues Warning Alert Banner -->
        @if ($hasOutstandingUnsettledBills)
            <div class="mb-5 p-4 rounded-3xl bg-rose-50 border-2 border-rose-500 text-rose-950 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg animate-fade-in">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center shrink-0 font-bold text-lg shadow-sm">
                        ⚠️
                    </div>
                    <div>
                        <h4 class="font-black text-sm uppercase tracking-wide text-rose-900 flex items-center gap-2">
                            <span>ORDERING LOCKED: Outstanding Walk-Out Dues</span>
                            <span class="px-2 py-0.5 rounded-full bg-rose-200 text-rose-900 font-mono text-xs font-bold">
                                ₹{{ number_format($outstandingTotalPaise / 100, 2) }}
                            </span>
                        </h4>
                        <p class="text-xs text-rose-800 mt-1">
                            <strong>{{ $selectedMember?->person?->full_name ?? ($selectedGuest?->name ?? 'Customer') }}</strong> has {{ count($outstandingBillsList) }} unpaid walk-out bill(s) on record. Under Club By-Laws, this balance must be cleared before placing any new order.
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="openOutstandingDuesModal"
                    class="px-5 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs shadow-md shadow-rose-600/30 cursor-pointer shrink-0 transition flex items-center gap-2 active:scale-95">
                    <span>Review & Settle Dues Now</span>
                    <span>→</span>
                </button>
            </div>
        @endif

        <!-- Outstanding Unsettled Walk-Out Dues Warning Alert Banner -->
        @if ($hasOutstandingUnsettledBills)
            <div class="mb-5 p-4 rounded-3xl bg-rose-50 border-2 border-rose-500 text-rose-950 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg animate-fade-in">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center shrink-0 font-bold text-lg shadow-sm">
                        ⚠️
                    </div>
                    <div>
                        <h4 class="font-black text-sm uppercase tracking-wide text-rose-900 flex items-center gap-2">
                            <span>ORDERING LOCKED: Outstanding Walk-Out Dues</span>
                            <span class="px-2 py-0.5 rounded-full bg-rose-200 text-rose-900 font-mono text-xs font-bold">
                                ₹{{ number_format($outstandingTotalPaise / 100, 2) }}
                            </span>
                        </h4>
                        <p class="text-xs text-rose-800 mt-1">
                            <strong>{{ $selectedMember?->person?->full_name ?? ($selectedGuest?->name ?? 'Customer') }}</strong> has {{ count($outstandingBillsList) }} unpaid walk-out bill(s) on record. Under Club By-Laws, this balance must be cleared before placing any new order.
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="openOutstandingDuesModal"
                    class="px-5 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs shadow-md shadow-rose-600/30 cursor-pointer shrink-0 transition flex items-center gap-2 active:scale-95">
                    <span>Review & Settle Dues Now</span>
                    <span>→</span>
                </button>
            </div>
        @endif

        <!-- MAIN TWO-COLUMN POS WORKSPACE -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start {{ $showReceiptModal ? 'print:hidden' : '' }}">

            <!-- LEFT COLUMN: Customer Selection & Wallet Top-Up (5 cols) -->
            <div class="lg:col-span-5 space-y-5">

                <!-- Customer Search / Identification Card -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs relative">
                    <div class="flex items-center justify-between mb-3">
                        <label for="customer-search-input"
                            class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span>👤</span>
                            <span>Member Identification</span>
                            <kbd
                                class="px-1.5 py-0.2 rounded bg-slate-100 text-amber-700 font-mono text-[10px] border border-slate-300 font-bold">F2</kbd>
                        </label>
                        @if ($selectedMember)
                            <button wire:click="resetMember" type="button"
                                class="text-xs text-rose-600 hover:text-rose-800 font-semibold focus:outline-none">
                                Change [Del]
                            </button>
                        @endif
                    </div>

                    <!-- Live Search Input -->
                    <div class="relative">
                        <input type="text" id="customer-search-input" x-ref="customerSearchInput"
                            wire:model.live.debounce.150ms="searchQuery"
                            @keydown.arrow-down.prevent.stop="navigateCustomers('down')"
                            @keydown.arrow-up.prevent.stop="navigateCustomers('up')"
                            @keydown.enter.prevent.stop="selectCustomer()"
                            placeholder="Scan card, type membership #, room # (e.g. 101), name, or phone..."
                            aria-label="Search members or room guests by card number, membership ID, room number, name, or phone"
                            class="w-full bg-slate-50 border border-slate-300 rounded-2xl pl-11 pr-20 py-3 text-slate-900 placeholder-slate-400 font-mono text-sm focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none">

                        <div class="absolute left-3.5 top-3.5 text-slate-400 pointer-events-none">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        @if ($searchQuery)
                            <button type="button" wire:click="resetMember"
                                class="absolute right-3 top-2.5 px-2 py-1 text-xs text-slate-400 hover:text-slate-700 rounded-lg bg-slate-100">
                                Clear
                            </button>
                        @endif
                    </div>

                    <!-- Floating Customer Autocomplete Results Dropdown -->
                    @if (!empty($customerSearchResults))
                        <div
                            class="absolute left-5 right-5 top-[92px] z-40 bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden divide-y divide-slate-100 max-h-80 overflow-y-auto">
                            @foreach ($customerSearchResults as $idx => $cMatch)
                                @if (($cMatch['type'] ?? 'member') === 'room_guest')
                                    <div id="cust-result-{{ $idx }}" wire:click="selectRoomGuest({{ $cMatch['id'] }})"
                                        @mouseenter="highlightedCustIdx = {{ $idx }}"
                                        class="p-3 cursor-pointer flex items-center justify-between text-xs transition-colors"
                                        :class="highlightedCustIdx === {{ $idx }} ? 'bg-teal-50 text-teal-950 border-l-4 border-teal-500 font-medium' : 'text-slate-700 hover:bg-slate-50'">
                                        <div>
                                            <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                                <span class="text-teal-700 font-extrabold">🏨 {{ $cMatch['room_name'] }}</span>
                                                <span
                                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-teal-100 text-teal-900 border border-teal-200 font-bold">{{ $cMatch['guest_name'] }}</span>
                                            </div>
                                            <div class="text-[10px] text-slate-500 mt-0.5">
                                                Resident Guest • Sponsor: <strong
                                                    class="text-slate-700">{{ $cMatch['sponsor_name'] }}</strong> • Phone:
                                                {{ $cMatch['phone'] }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span
                                                class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Checked-In</span>
                                            <span class="block text-[9px] uppercase font-semibold text-slate-400">Room Occupant</span>
                                        </div>
                                    </div>
                                @else
                                    <div id="cust-result-{{ $idx }}" wire:click="selectCustomerById({{ $cMatch['id'] }})"
                                        @mouseenter="highlightedCustIdx = {{ $idx }}"
                                        class="p-3 cursor-pointer flex items-center justify-between text-xs transition-colors"
                                        :class="highlightedCustIdx === {{ $idx }} ? 'bg-amber-50 text-amber-950 border-l-4 border-amber-500 font-medium' : 'text-slate-700 hover:bg-slate-50'">
                                        <div>
                                            <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                                <span>{{ $cMatch['name'] }}</span>
                                                <span
                                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-amber-800 border border-slate-200 font-semibold">{{ $cMatch['membership_number'] }}</span>
                                            </div>
                                            <div class="text-[10px] text-slate-500 mt-0.5">
                                                Card: <span class="font-mono">{{ $cMatch['card_number'] }}</span> • Phone:
                                                {{ $cMatch['phone'] }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span
                                                class="font-mono font-bold text-amber-700">₹{{ number_format($cMatch['balance_paise'] / 100, 2) }}</span>
                                            <span
                                                class="block text-[9px] uppercase font-semibold text-slate-400">{{ $cMatch['category'] }}</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <!-- Quick Member Shortcut Badges for fast cashier demo testing -->
                    <div class="mt-2.5 flex items-center gap-1.5 flex-wrap">
                        <span class="text-[10px] text-slate-400 font-semibold uppercase">Demo:</span>
                        <button wire:click="$set('searchQuery', 'CARD-VENU-001'); searchMember" type="button"
                            class="text-[11px] px-2 py-0.5 rounded-md bg-slate-100 hover:bg-amber-50 hover:border-amber-300 text-slate-700 border border-slate-200 font-mono transition-colors cursor-pointer">Venu
                            (MC-001)</button>
                        <button wire:click="$set('searchQuery', 'CARD-PRIYA-002'); searchMember" type="button"
                            class="text-[11px] px-2 py-0.5 rounded-md bg-slate-100 hover:bg-amber-50 hover:border-amber-300 text-slate-700 border border-slate-200 font-mono transition-colors cursor-pointer">Priya
                            (MC-002)</button>
                        <button wire:click="$set('searchQuery', 'CARD-RAJESH-003'); searchMember" type="button"
                            class="text-[11px] px-2 py-0.5 rounded-md bg-slate-100 hover:bg-amber-50 hover:border-amber-300 text-slate-700 border border-slate-200 font-mono transition-colors cursor-pointer">Rajesh
                            (MC-003)</button>
                    </div>
                </div>

                <!-- Active Customer Details: Room Guest OR Member -->
                @if ($selectedRoomReservation)
                    <div
                        class="bg-white border border-teal-200 rounded-3xl p-5 shadow-xs animate-fade-in ring-1 ring-teal-500/20">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center space-x-3">
                                <div
                                    class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-teal-600 to-emerald-400 flex items-center justify-center font-bold text-white text-2xl shadow-md shadow-teal-500/20">
                                    🏨
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg text-slate-900 tracking-tight">
                                        {{ $selectedRoomReservation->guest?->name ?? $selectedRoomReservation->sponsorMember?->name ?? 'Resident Guest' }}
                                    </h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span
                                            class="text-xs font-mono font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-md border border-teal-200">{{ $selectedRoomReservation->facility->name }}</span>
                                        <span class="text-xs text-slate-500 font-medium">Room Occupant</span>
                                    </div>
                                </div>
                            </div>

                            <span
                                class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 font-semibold capitalize flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Checked-In
                            </span>
                        </div>

                        <!-- Room Stay & Sponsor Details Box -->
                        <div class="mt-4 p-4 rounded-2xl bg-teal-50/70 border border-teal-200/80 text-xs space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Reservation Reference:</span>
                                <span
                                    class="font-mono font-bold text-slate-800">{{ $selectedRoomReservation->reservation_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Stay Period:</span>
                                <span
                                    class="font-mono font-semibold text-slate-700">{{ $selectedRoomReservation->check_in_date->format('d M') }}
                                    &rarr; {{ $selectedRoomReservation->check_out_date->format('d M Y') }}</span>
                            </div>
                            <div class="flex justify-between border-t border-teal-200/60 pt-1.5">
                                <span class="text-slate-500">Sponsoring Member:</span>
                                <span
                                    class="font-bold text-slate-900">{{ $selectedRoomReservation->sponsorMember ? ($selectedRoomReservation->sponsorMember->name . ' (' . $selectedRoomReservation->sponsorMember->membership_number . ')') : 'Direct Booking' }}</span>
                            </div>
                            @if ($selectedRoomReservation->sponsorMember && $memberWalletBalancePaise > 0)
                                <div
                                    class="flex justify-between items-center bg-white/70 p-2 rounded-xl border border-teal-200/60 mt-1">
                                    <span class="text-amber-900 font-semibold flex items-center gap-1">
                                        <span>💳</span> Sponsor Wallet Available:
                                    </span>
                                    <span
                                        class="font-mono font-extrabold text-amber-950 text-sm">₹{{ number_format($memberWalletBalancePaise / 100, 2) }}</span>
                                </div>
                            @endif
                            <div class="pt-1.5 text-[10px] text-teal-900 font-medium leading-tight border-t border-teal-200/60">
                                ⚠️ <strong>Zero-Credit Policy (Rule 1)</strong>: Room-folio post-payment is prohibited. Settle
                                order immediately via Cash or Digital UPI (or Sponsor Wallet).
                            </div>
                        </div>
                    </div>
                @elseif ($selectedMember)
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs animate-fade-in">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center space-x-3">
                                @php
                                    $posInitials = strtoupper(substr($selectedMember->person->first_name ?? 'M', 0, 1) . substr($selectedMember->person->last_name ?? '', 0, 1));
                                @endphp
                                @if ($selectedMember->photo_url)
                                    <a href="{{ $selectedMember->photo_url }}" target="_blank" title="View full member photo" class="shrink-0">
                                        <img src="{{ $selectedMember->photo_url }}" alt="{{ $selectedMember->name }}"
                                            class="w-14 h-14 rounded-2xl object-cover border border-slate-200 shadow-md"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div style="display: none;"
                                            class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-yellow-400 items-center justify-center font-bold text-slate-950 text-xl shadow-md shadow-amber-500/20">
                                            {{ $posInitials }}
                                        </div>
                                    </a>
                                @else
                                    <div
                                        class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center font-bold text-slate-950 text-xl shadow-md shadow-amber-500/20 shrink-0">
                                        {{ $posInitials }}
                                    </div>
                                @endif
                                <div>
                                    <h3 class="font-bold text-lg text-slate-900 tracking-tight">
                                        {{ $selectedMember->person->first_name }} {{ $selectedMember->person->last_name }}
                                    </h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span
                                            class="text-xs font-mono font-semibold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">{{ $selectedMember->membership_number }}</span>
                                        <span class="text-xs text-slate-500">{{ $selectedMember->category->name }}</span>
                                    </div>
                                </div>
                            </div>

                            <span
                                class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 font-medium capitalize">
                                {{ $selectedMember->status->value }}
                            </span>
                        </div>

                        <!-- Authoritative Wallet Box -->
                        <div
                            class="mt-6 p-4 rounded-2xl bg-gradient-to-br from-amber-50 via-amber-100/40 to-orange-50 border border-amber-300/80 relative">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-semibold uppercase tracking-wider text-amber-900 flex items-center gap-1.5">
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
                                <span>Active Card: <strong
                                        class="text-slate-800 font-mono">{{ $activeCard ? $activeCard->card_number : 'None' }}</strong></span>
                            </div>
                        </div>

                        <!-- Dependents (if any) -->
                        @if ($selectedMember->dependents->isNotEmpty())
                            <div class="mt-4 pt-3 border-t border-slate-200">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Registered
                                    Dependents</span>
                                <div class="mt-2 space-y-1.5">
                                    @foreach ($selectedMember->dependents as $dep)
                                        <div
                                            class="flex items-center justify-between gap-2 text-xs px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200">
                                            <div class="flex items-center gap-2 min-w-0">
                                                @if ($dep->photo_url)
                                                    <a href="{{ $dep->photo_url }}" target="_blank" title="View dependent photo" class="shrink-0">
                                                        <img src="{{ $dep->photo_url }}" alt="{{ $dep->person?->full_name ?? 'Dependent' }}"
                                                            class="w-6 h-6 rounded-md object-cover border border-slate-200 shrink-0"
                                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <span style="display: none;" class="w-6 h-6 rounded-md bg-amber-100 text-amber-800 font-bold text-[10px] items-center justify-center shrink-0">
                                                            {{ strtoupper(substr($dep->person->first_name ?? 'D', 0, 1)) }}
                                                        </span>
                                                    </a>
                                                @else
                                                    <span class="w-6 h-6 rounded-md bg-amber-100 text-amber-800 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                        {{ strtoupper(substr($dep->person->first_name ?? 'D', 0, 1)) }}
                                                    </span>
                                                @endif
                                                <span class="text-slate-700 truncate">{{ $dep->person->first_name }} {{ $dep->person->last_name }}
                                                    ({{ $dep->relationship }})</span>
                                            </div>
                                            <span
                                                class="text-[10px] px-2 py-0.5 rounded shrink-0 {{ $dep->can_spend_wallet ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-500' }}">
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
                            <button type="button" wire:click="$set('rechargeAmount', 500)"
                                class="py-1.5 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer {{ $rechargeAmount == 500 ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">₹500</button>
                            <button type="button" wire:click="$set('rechargeAmount', 1000)"
                                class="py-1.5 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer {{ $rechargeAmount == 1000 ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">₹1,000</button>
                            <button type="button" wire:click="$set('rechargeAmount', 2000)"
                                class="py-1.5 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer {{ $rechargeAmount == 2000 ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">₹2,000</button>
                            <button type="button" wire:click="$set('rechargeAmount', 5000)"
                                class="py-1.5 rounded-xl text-xs font-mono font-bold transition-all cursor-pointer {{ $rechargeAmount == 5000 ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200' }}">₹5,000</button>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Recharge Amount (₹)</label>
                                <input type="number" step="50" wire:model="rechargeAmount"
                                    class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-slate-900 font-mono font-bold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    class="flex items-center space-x-2 p-2.5 rounded-xl border {{ $rechargeTender === 'cash' ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-slate-50 border-slate-200 text-slate-600' }} cursor-pointer">
                                    <input type="radio" value="cash" wire:model="rechargeTender" class="text-amber-500">
                                    <span class="text-xs font-semibold">Cash Payment</span>
                                </label>
                                <label
                                    class="flex items-center space-x-2 p-2.5 rounded-xl border {{ $rechargeTender === 'digital' ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-slate-50 border-slate-200 text-slate-600' }} cursor-pointer">
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

                            <button wire:click="rechargeWallet" wire:loading.attr="disabled" type="button"
                                class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm shadow-md shadow-emerald-500/20 active:scale-98 transition-all flex items-center justify-center space-x-2 cursor-pointer">
                                <span>Confirm Recharge & Issue Receipt</span>
                            </button>
                        </div>
                    </div>
                @else
                    <div
                        class="bg-slate-50/80 border border-dashed border-slate-300 rounded-3xl p-8 text-center text-slate-500">
                        <div
                            class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center mx-auto mb-3 text-slate-500 shadow-2xs">
                            💳
                        </div>
                        <div class="font-bold text-sm text-slate-700">No Customer Selected</div>
                        <p class="text-xs mt-1 text-slate-500">Scan member card or search above to view prepaid balance or
                            sponsor guest passes.</p>
                    </div>
                @endif
            </div>

            <!-- RIGHT COLUMN: Prominent Item Search & Current Order / Bill (7 cols) -->
            <div class="lg:col-span-7 space-y-5">

                <!-- Prominent Keyboard-Driven Item Search Bar -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs relative">
                    <div class="flex items-center justify-between mb-2">
                        <label for="item-search-input"
                            class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span>🔍</span>
                            <span>Item Search & Quick Add</span>
                            <kbd
                                class="px-1.5 py-0.2 rounded bg-slate-100 text-amber-700 font-mono text-[10px] border border-slate-300 font-bold">F3</kbd>
                        </label>
                        <span class="text-[11px] text-slate-500">Arrow Up/Down to navigate • Enter to add</span>
                    </div>

                    <div class="relative">
                        <input type="text" id="item-search-input" x-ref="itemSearchInput"
                            wire:model.live.debounce.150ms="itemSearch"
                            @keydown.arrow-down.prevent.stop="navigateItems('down')"
                            @keydown.arrow-up.prevent.stop="navigateItems('up')" @keydown.enter.prevent.stop="selectItem()"
                            @keydown="if (getProp('showServingModal')) {
                                        if (['1', '2', '3'].includes($event.key)) {
                                            $event.preventDefault();
                                            selectServingByIndex(parseInt($event.key) - 1);
                                        } else if ($event.key === 'Escape') {
                                            $event.preventDefault();
                                            $wire.closeServingModal();
                                        }
                                    }" placeholder="Type item name, code or barcode (e.g. Beer, Meals, 890123400001)..."
                            aria-label="Search saleable items by name, item code and barcode"
                            class="w-full bg-slate-50 border border-slate-300 rounded-2xl pl-11 pr-24 py-3.5 text-slate-900 placeholder-slate-400 font-mono text-sm focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none">

                        <div class="absolute left-3.5 top-4 text-slate-400 pointer-events-none">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        @if ($itemSearch)
                            <button type="button" wire:click="$set('itemSearch', ''); $set('itemSearchResults', []);"
                                class="absolute right-3 top-3 px-2.5 py-1 text-xs text-slate-500 hover:text-slate-800 rounded-lg bg-slate-100 cursor-pointer">
                                Clear
                            </button>
                        @endif
                    </div>

                    <!-- Floating Item Search Autocomplete Results -->
                    @if (!empty($itemSearchResults))
                        <div
                            class="absolute left-5 right-5 top-[95px] z-50 bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden divide-y divide-slate-100 max-h-80 overflow-y-auto">
                            @foreach ($itemSearchResults as $idx => $itemMatch)
                                <div id="item-result-{{ $idx }}"
                                    wire:click="{{ $itemMatch['has_multiple_servings'] ? 'openServingModalForItem(' . json_encode($itemMatch) . ')' : 'addToCart(' . $itemMatch['tariff_id'] . ')' }}"
                                    @click="const inp = document.getElementById('item-search-input'); if (inp) { inp.value = ''; inp.focus(); }"
                                    @mouseenter="highlightedItemIdx = {{ $idx }}"
                                    class="p-3.5 cursor-pointer flex items-center justify-between text-xs transition-colors"
                                    :class="highlightedItemIdx === {{ $idx }} ? 'bg-amber-50 text-amber-950 border-l-4 border-amber-500 font-medium' : 'text-slate-700 hover:bg-slate-50'">
                                    <div class="flex-1 min-w-0 pr-4">
                                        <div class="font-bold flex items-center gap-2 truncate">
                                            <span class="text-sm text-slate-900">{{ $itemMatch['name'] }}</span>
                                            <span
                                                class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 uppercase font-semibold">{{ $itemMatch['code'] }}</span>
                                            @if ($itemMatch['barcode'])
                                                <span
                                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200">🏷
                                                    {{ $itemMatch['barcode'] }}</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $itemMatch['has_multiple_servings'] ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700' }}">
                                                {{ $itemMatch['serving_display'] }}
                                            </span>
                                            @if (!empty($itemMatch['promo_badge']))
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    🎁 {{ $itemMatch['promo_badge'] }}
                                                </span>
                                            @endif
                                            <span
                                                class="{{ !$itemMatch['is_in_stock'] ? 'text-rose-600 font-bold' : (($itemMatch['stock_qty'] !== null && (float) $itemMatch['stock_qty'] <= 0) ? 'text-amber-700 font-semibold' : 'text-emerald-700') }}">
                                                @if ($itemMatch['stock_qty'] !== null)
                                                    ●
                                                    @if ($itemMatch['is_in_stock'])
                                                        Stock: {{ $itemMatch['stock_qty'] }} {{ $itemMatch['base_unit'] }}@if ((float) $itemMatch['stock_qty'] <= 0) (Negative Allowed)@endif
                                                    @else
                                                        OUT OF STOCK
                                                    @endif
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
                            <strong
                                class="text-slate-900">{{ $selectedMember ? ($selectedMember->person->first_name . ' ' . $selectedMember->person->last_name . ' (' . $selectedMember->membership_number . ')') : 'None Selected — Please select a member!' }}</strong>
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
                    <!-- Header Strip: Title, Item Count & Quick Actions -->
                    <div class="flex items-center justify-between mb-3.5 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <span class="text-base font-extrabold text-slate-900 tracking-tight flex items-center gap-2 whitespace-nowrap">
                                <span>🧾</span>
                                <span>Current Order</span>
                            </span>
                            <span
                                class="text-xs px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200/80 font-mono font-bold whitespace-nowrap">
                                {{ count($cart) }} {{ count($cart) === 1 ? 'item' : 'items' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (count($cart) > 0 && ($selectedMember || $selectedRoomReservation))
                                <button wire:click="openUnsettledWalkoutModal" type="button"
                                    class="text-xs text-rose-700 hover:text-rose-900 hover:bg-rose-50 px-2.5 py-1 rounded-xl border border-rose-200/90 font-semibold transition-all focus:outline-none cursor-pointer inline-flex items-center gap-1 shadow-2xs"
                                    title="Record order as an unsettled walk-out if member left without paying">
                                    <span>🚶</span>
                                    <span class="hidden sm:inline">Walk-Out Dues</span>
                                </button>
                            @endif
                            @if (count($cart) > 0)
                                <button wire:click="clearCart" type="button"
                                    class="text-xs text-slate-500 hover:text-rose-600 hover:bg-rose-50/60 px-2.5 py-1 rounded-xl border border-transparent hover:border-rose-200 font-medium transition-all focus:outline-none cursor-pointer inline-flex items-center gap-1">
                                    <span>Clear Bill</span>
                                    <kbd class="px-1 text-[9px] font-mono bg-slate-100 border border-slate-200 rounded text-slate-500">Del</kbd>
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Floor Staff Bearer & Table Selector Strip -->
                    <div class="mb-4 p-2.5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-wrap items-center justify-between gap-2.5 text-xs">
                        <div class="flex items-center gap-2 flex-1 min-w-[200px]">
                            <label for="pos-waiter-select" class="text-[11px] font-bold uppercase text-slate-500 whitespace-nowrap flex items-center gap-1">
                                <span>🤵</span>
                                <span>Serving Bearer:</span>
                            </label>
                            <select id="pos-waiter-select" wire:model.live="selectedWaiterId"
                                class="flex-1 py-1 px-2.5 text-xs rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:ring-1 focus:ring-amber-500">
                                <option value="">-- Direct Counter / Unassigned --</option>
                                @foreach ($this->waiters as $w)
                                    <option value="{{ $w->id }}">{{ $w->code }} • {{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-center gap-2 min-w-[140px]">
                            <label for="pos-table-input" class="text-[11px] font-bold uppercase text-slate-500 whitespace-nowrap">
                                <span>Table / Spot:</span>
                            </label>
                            <input type="text" id="pos-table-input" wire:model.live.debounce.300ms="tableNumber" placeholder="e.g. Table 4 / Bar"
                                class="w-28 py-1 px-2.5 text-xs rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:ring-1 focus:ring-amber-500">
                        </div>

                        <div class="flex items-center gap-2 min-w-[170px] flex-1 sm:flex-initial">
                            <label for="pos-guest-input" class="text-[11px] font-bold uppercase text-slate-500 whitespace-nowrap flex items-center gap-1">
                                <span>👤</span>
                                <span>Accompanying Guest:</span>
                            </label>
                            <input type="text" id="pos-guest-input" wire:model.live.debounce.300ms="accompanyingGuestName" placeholder="e.g. Suresh (Optional)"
                                class="w-36 py-1 px-2.5 text-xs rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:ring-1 focus:ring-amber-500">
                        </div>
                    </div>

                    @if ($activeHeldTabId)
                        <div
                            class="mb-4 p-3 rounded-2xl bg-amber-50 border border-amber-300 text-slate-900 flex items-center justify-between shadow-2xs animate-fade-in">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-amber-500 text-slate-950 font-black text-sm">
                                    🍸
                                </span>
                                <div>
                                    <div
                                        class="text-xs font-black uppercase tracking-wide text-amber-950 flex items-center gap-2">
                                        <span>Active Bar Tab</span>
                                        @if ($tabTableIdentifier)
                                            <span
                                                class="px-1.5 py-0.2 rounded bg-amber-200 text-amber-900 font-mono text-[10px] font-bold">
                                                Table: {{ $tabTableIdentifier }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-600">
                                        Editing parked bill • Adding items will update this tab. Settle when member leaves.
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="resetWorkspaceToNewBill"
                                    class="px-2.5 py-1 rounded-lg bg-white text-slate-700 hover:bg-slate-100 text-xs font-bold border border-slate-300 shadow-2xs cursor-pointer">
                                    ＋ Park & New
                                </button>
                                <button type="button" wire:click="voidActiveHeldTab('Voided by cashier')"
                                    wire:confirm="Are you sure you want to void this open tab?"
                                    class="px-2 py-1 rounded-lg text-rose-600 hover:bg-rose-50 text-xs font-semibold cursor-pointer">
                                    Void Tab
                                </button>
                            </div>
                        </div>
                    @endif

                    @if (empty($cart))
                        <!-- Empty Cart State with Cashier Quick-Guide -->
                        <div class="py-12 px-4 text-center">
                            <div
                                class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center mx-auto mb-4 text-2xl shadow-2xs">
                                🧾
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1">Bill is ready for items</h4>
                            <p class="text-xs text-slate-500 max-w-sm mx-auto mb-6">
                                Press <kbd
                                    class="px-1.5 py-0.5 rounded bg-slate-100 text-amber-800 font-mono text-xs border border-slate-300 font-bold">F3</kbd>
                                or scan a barcode to quickly search and add saleable items.
                            </p>

                            <!-- Keyboard Quick Reference Cards -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-w-lg mx-auto text-left">
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] text-slate-500 font-semibold uppercase">Search / Scan</span>
                                        <kbd
                                            class="px-1.5 py-0.5 rounded bg-white text-amber-800 font-mono font-bold text-[10px] border border-slate-300">F3</kbd>
                                    </div>
                                    <div class="text-[11px] font-medium text-slate-700">Quick add items</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] text-slate-500 font-semibold uppercase">Customer</span>
                                        <kbd
                                            class="px-1.5 py-0.5 rounded bg-white text-amber-800 font-mono font-bold text-[10px] border border-slate-300">F2</kbd>
                                    </div>
                                    <div class="text-[11px] font-medium text-slate-700">Swipe or card find</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] text-slate-500 font-semibold uppercase">Prepaid Pay</span>
                                        <kbd
                                            class="px-1.5 py-0.5 rounded bg-white text-amber-800 font-mono font-bold text-[10px] border border-slate-300">F8</kbd>
                                    </div>
                                    <div class="text-[11px] font-medium text-slate-700">Server ledger debit</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] text-slate-500 font-semibold uppercase">Cash Drawer</span>
                                        <kbd
                                            class="px-1.5 py-0.5 rounded bg-white text-emerald-800 font-mono font-bold text-[10px] border border-slate-300">F9</kbd>
                                    </div>
                                    <div class="text-[11px] font-medium text-slate-700">Physical drawer</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] text-slate-500 font-semibold uppercase">Digital / UPI</span>
                                        <kbd
                                            class="px-1.5 py-0.5 rounded bg-white text-blue-800 font-mono font-bold text-[10px] border border-slate-300">F10</kbd>
                                    </div>
                                    <div class="text-[11px] font-medium text-slate-700">Gateway reference</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] text-slate-500 font-semibold uppercase">Edit Qty</span>
                                        <kbd
                                            class="px-1.5 py-0.5 rounded bg-white text-slate-700 font-mono font-bold text-[10px] border border-slate-300">F4</kbd>
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
                                    <thead
                                        class="bg-slate-50 sticky top-0 z-10 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-600">
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
                                                $lineGrossPaise = (int) ($item['gross_amount_paise'] ?? round($item['quantity'] * $item['unit_price_paise']));
                                                $lineDiscountPaise = (int) ($item['discount_amount_paise'] ?? 0);
                                                $lineTaxPaise = (int) ($item['tax_amount_paise'] ?? round((max(0, $lineGrossPaise - $lineDiscountPaise) * $item['tax_percent']) / 100));
                                                $lineNetPaise = (int) ($item['line_total_paise'] ?? (max(0, $lineGrossPaise - $lineDiscountPaise) + $lineTaxPaise));
                                                $freeQty = (float) ($item['free_quantity'] ?? 0);
                                                $ctaAddQty = (int) ($item['promo_cta_add_qty'] ?? 0);
                                            @endphp
                                            <tr class="hover:bg-slate-50/80 transition-colors">
                                                <td class="py-3 pl-4 pr-2 text-center font-mono text-[11px] text-slate-400">
                                                    {{ $index + 1 }}
                                                </td>
                                                <td class="py-3 px-3">
                                                    <div class="font-bold text-slate-900 text-sm flex items-center gap-2 flex-wrap">
                                                        <span>{{ $item['name'] }}</span>
                                                        @if (!empty($item['serving_name']))
                                                            <span
                                                                class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 font-semibold">
                                                                {{ $item['serving_name'] }}
                                                            </span>
                                                        @endif
                                                        @if ($freeQty > 0 && !empty($item['promotion_badge']))
                                                            <span
                                                                class="text-[10px] px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">
                                                                🎁 {{ $item['promotion_badge'] }} •
                                                                {{ rtrim(rtrim(number_format($freeQty, 2, '.', ''), '0'), '.') }} Free
                                                            </span>
                                                        @elseif ($ctaAddQty > 0)
                                                            <button type="button" wire:click="claimPromoFreeItem({{ $index }})"
                                                                class="text-[10px] px-2 py-0.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-2xs transition-colors cursor-pointer">
                                                                +{{ $ctaAddQty }} Free Offer Available
                                                            </button>
                                                        @elseif (!empty($item['promo_pending_member_label']))
                                                            <span
                                                                class="text-[10px] px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 font-semibold">
                                                                {{ $item['promo_pending_member_label'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                                        Base: ₹{{ number_format($item['unit_price_paise'] / 100, 2) }}
                                                        @if ($lineDiscountPaise > 0)
                                                            <span class="text-emerald-700 font-semibold ml-1.5">
                                                                • Saved ₹{{ number_format($lineDiscountPaise / 100, 2) }}
                                                            </span>
                                                        @endif
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

                                                        <input type="number" min="1" step="1" value="{{ $item['quantity'] }}"
                                                            wire:change="setCartQuantity({{ $index }}, $event.target.value)"
                                                            wire:keydown.enter.prevent="$wire.setCartQuantity({{ $index }}, $event.target.value); $dispatch('focus-item-search');"
                                                            @keydown.arrow-down.prevent="
                                                                                                const inputs = Array.from(document.querySelectorAll('.cart-qty-input'));
                                                                                                const idx = inputs.indexOf($el);
                                                                                                if (idx !== -1 && idx < inputs.length - 1) { inputs[idx + 1].focus(); inputs[idx + 1].select(); }
                                                                                            " @keydown.arrow-up.prevent="
                                                                                                const inputs = Array.from(document.querySelectorAll('.cart-qty-input'));
                                                                                                const idx = inputs.indexOf($el);
                                                                                                if (idx > 0) { inputs[idx - 1].focus(); inputs[idx - 1].select(); }
                                                                                            "
                                                            class="cart-qty-input w-12 text-center bg-slate-50 border border-slate-300 rounded-lg py-1 font-mono text-slate-900 text-xs font-bold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                                            aria-label="Item quantity for {{ $item['name'] }}">

                                                        <button type="button" wire:click="updateQuantity({{ $index }}, 1)"
                                                            class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 flex items-center justify-center font-bold text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"
                                                            aria-label="Increase quantity">+</button>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-3 text-right font-mono font-bold text-slate-900 text-sm">
                                                    @if ($lineDiscountPaise > 0)
                                                        <div class="text-[11px] text-slate-400 line-through font-normal">
                                                            ₹{{ number_format(($lineGrossPaise + (int) round(($lineGrossPaise * $item['tax_percent']) / 100)) / 100, 2) }}
                                                        </div>
                                                    @endif
                                                    <div>₹{{ number_format($lineNetPaise / 100, 2) }}</div>
                                                </td>
                                                <td class="py-3 pl-2 pr-4 text-center">
                                                    <button type="button" wire:click="removeFromCart({{ $index }})"
                                                        class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-colors focus:outline-none cursor-pointer"
                                                        title="Remove item [Del]" aria-label="Remove item">
                                                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
                            @if ($cartDiscount > 0)
                                <div class="flex justify-between text-xs text-emerald-700 font-semibold">
                                    <span>Promotional Discount</span>
                                    <span class="font-mono">-₹{{ number_format($cartDiscount, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-xs text-slate-600">
                                <span>GST &amp; Taxes</span>
                                <span class="font-mono text-slate-900 font-semibold">₹{{ number_format($cartTax, 2) }}</span>
                            </div>
                            <div class="pt-2.5 border-t border-slate-200 flex justify-between items-center">
                                <div>
                                    <span class="text-sm font-bold text-slate-900 block">Net Total Payable</span>
                                    <span class="text-[10px] text-slate-500 font-mono">Inclusive of all taxes</span>
                                </div>
                                <span
                                    class="text-3xl font-black font-mono text-amber-700">₹{{ number_format($cartNet, 2) }}</span>
                            </div>
                        </div>

                        <!-- Floor Table Service Slip Trigger -->
                        @if ($selectedMember || $selectedRoomReservation)
                            <div class="mb-3 p-2.5 rounded-2xl bg-indigo-50/70 border border-indigo-200 flex items-center justify-between gap-3 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🍽️</span>
                                    <div>
                                        <div class="font-bold text-indigo-950">Table Service Check</div>
                                        <div class="text-[10px] text-indigo-700">Need member to verify bill or add tip at their table before payment?</div>
                                    </div>
                                </div>
                                <button type="button" wire:click="printCurrentCartTableCheck"
                                    class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer flex items-center gap-1.5 shrink-0">
                                    <span>📄 Print Table Check</span>
                                </button>
                            </div>
                        @endif

                        <!-- Tender Selection & Checkout Action Bar -->
                        <div>
                            <div
                                class="text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2.5 flex items-center justify-between">
                                <span>Select Payment Method (No Credit)</span>
                                <span class="text-[11px] text-amber-700 font-bold">Press F8, F9, or F10</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                                <!-- Option 1: Prepaid Wallet [F8] -->
                                @php
                                    $totalPaise = (int) round(($cartNet + $tipAmount) * 100);
                                    $hasSufficientPrepaid = $selectedMember && ($memberWalletBalancePaise >= $totalPaise);
                                @endphp
                                <button type="button" wire:click="initiateCheckout('prepaid')" wire:loading.attr="disabled" @if (!$hasSufficientPrepaid) disabled @endif
                                    class="p-3.5 rounded-2xl border text-left flex flex-col justify-between transition-all focus:outline-none focus:ring-2 focus:ring-amber-400 {{ $hasSufficientPrepaid ? 'bg-amber-500 hover:bg-amber-400 border-amber-500 text-slate-950 cursor-pointer active:scale-95 shadow-md shadow-amber-500/20' : 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed opacity-60' }}">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs">Prepaid Wallet</span>
                                            <kbd
                                                class="px-1.5 py-0.5 rounded bg-amber-600/20 text-slate-950 font-bold text-[10px] border border-amber-600/30">F8</kbd>
                                        </div>
                                        <div class="text-[10px] mt-1 font-medium">Avail:
                                            ₹{{ number_format($memberWalletBalancePaise / 100, 2) }}</div>
                                    </div>
                                    <div class="mt-2 text-[11px] font-bold font-mono">
                                        {{ $hasSufficientPrepaid ? '✓ Pay ₹' . number_format($cartNet, 2) : 'Insufficient Funds' }}
                                    </div>
                                </button>

                                <!-- Option 2: Cash Payment [F9] -->
                                <button type="button" wire:click="initiateCheckout('cash')" wire:loading.attr="disabled"
                                    class="p-3.5 rounded-2xl border border-emerald-600 bg-emerald-600 hover:bg-emerald-500 text-white text-left flex flex-col justify-between transition-all cursor-pointer active:scale-95 focus:outline-none focus:ring-2 focus:ring-emerald-400 shadow-md shadow-emerald-600/20">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs">Cash Payment</span>
                                            <kbd
                                                class="px-1.5 py-0.5 rounded bg-emerald-700 text-white font-bold text-[10px] border border-emerald-500">F9</kbd>
                                        </div>
                                        <div class="text-[10px] text-emerald-100 mt-1">Physical Cash</div>
                                    </div>
                                    <div class="mt-2 text-[11px] font-bold font-mono text-white">
                                        Pay ₹{{ number_format($cartNet, 2) }}
                                    </div>
                                </button>

                                <!-- Option 3: UPI / Digital [F10] -->
                                <button type="button" wire:click="initiateCheckout('digital')" wire:loading.attr="disabled"
                                    class="p-3.5 rounded-2xl border border-blue-600 bg-blue-600 hover:bg-blue-500 text-white text-left flex flex-col justify-between transition-all cursor-pointer active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400 shadow-md shadow-blue-600/20">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs">UPI / Digital</span>
                                            <kbd
                                                class="px-1.5 py-0.5 rounded bg-blue-700 text-white font-bold text-[10px] border border-blue-500">F10</kbd>
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
                role="dialog" aria-modal="true" aria-labelledby="serving-modal-title"
                @click.self="$wire.closeServingModal()"
                @keydown.escape.window="$wire.closeServingModal()">
                <div id="serving-modal-dialog" tabindex="-1"
                    class="bg-white border border-slate-200 rounded-3xl p-6 max-w-lg w-full shadow-2xl relative focus:outline-none"
                    x-init="$nextTick(() => $el.focus())" @keydown.arrow-down.prevent.stop="navigateServings('down')"
                    @keydown.arrow-up.prevent.stop="navigateServings('up')" @keydown.enter.prevent.stop="selectServing()"
                    @keydown.escape.prevent.stop="$wire.closeServingModal()" @keydown.1.prevent.stop="selectServingByIndex(0)"
                    @keydown.2.prevent.stop="selectServingByIndex(1)" @keydown.3.prevent.stop="selectServingByIndex(2)">

                    <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4">
                        <div>
                            <span class="text-[11px] uppercase font-bold tracking-wider text-amber-700">Serving Size
                                Selection</span>
                            <h3 id="serving-modal-title" class="text-lg font-bold text-slate-900 mt-0.5">{{ $servingItemName }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $servingItemStockText }}</p>
                        </div>
                        <button wire:click="closeServingModal" type="button"
                            class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer"
                            aria-label="Close serving dialog">×</button>
                    </div>

                    <p class="text-xs text-slate-600 mb-4">
                        This item has multiple configured serving sizes. Select unit with number key [1, 2, 3] or navigate with
                        Arrow keys and press <kbd
                            class="px-1 py-0.5 rounded bg-slate-100 text-amber-800 font-bold border border-slate-300">Enter</kbd>:
                    </p>

                    <div class="space-y-2.5 mb-6">
                        @foreach ($servingItemOptions as $sIdx => $sOpt)
                            <div id="serving-option-{{ $sIdx }}" wire:click="selectServingAndAddToCart({{ $sOpt['tariff_id'] }})"
                                @mouseenter="highlightedServingIdx = {{ $sIdx }}"
                                class="p-3.5 rounded-2xl border cursor-pointer flex items-center justify-between text-xs transition-all"
                                :class="highlightedServingIdx === {{ $sIdx }} ? 'bg-amber-50 border-amber-500 ring-2 ring-amber-400 text-slate-900 shadow-md font-semibold' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'">
                                <div class="flex items-center space-x-3">
                                    <span
                                        class="w-6 h-6 rounded-lg font-mono font-bold flex items-center justify-center text-xs border shadow-2xs transition-colors"
                                        :class="highlightedServingIdx === {{ $sIdx }} ? 'bg-amber-500 text-slate-950 border-amber-600 font-extrabold' : 'bg-white text-amber-700 border-slate-300'">
                                        {{ $sIdx + 1 }}
                                    </span>
                                    <div>
                                        <div class="font-bold text-slate-900 text-sm flex items-center gap-2 flex-wrap">
                                            <span>{{ $sOpt['serving_name'] }}</span>
                                            @if (!empty($sOpt['promo_badge']))
                                                <span
                                                    class="text-[10px] px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">
                                                    🎁 {{ $sOpt['promo_badge'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                            Rate: ₹{{ number_format($sOpt['rate_paise'] / 100, 2) }} + {{ $sOpt['tax_percent'] }}%
                                            GST
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
                        <button wire:click="closeServingModal" type="button"
                            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
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
                $tenderLabel = match ($pendingTenderType) {
                    'prepaid' => 'Prepaid Wallet',
                    'cash' => 'Cash Payment',
                    'digital' => 'UPI / Card Digital',
                    default => strtoupper($pendingTenderType ?? '')
                };
            @endphp
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 animate-fade-in"
                role="dialog" aria-modal="true" aria-labelledby="payment-confirm-title"
                @click.self="$wire.closePaymentConfirmModal()"
                @keydown.escape.window="$wire.closePaymentConfirmModal()">
                <div
                    class="bg-white border border-slate-200 rounded-3xl p-6 max-w-md w-full shadow-2xl relative text-slate-900">

                    <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4">
                        <div>
                            <span class="text-[11px] uppercase font-bold tracking-wider text-amber-700">Payment
                                Authorization</span>
                            <h3 id="payment-confirm-title" class="text-lg font-bold text-slate-900 mt-0.5">Confirm Bill
                                Settlement</h3>
                        </div>
                        <button wire:click="closePaymentConfirmModal" type="button"
                            class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer"
                            aria-label="Cancel checkout">×</button>
                    </div>

                    <div class="space-y-4 mb-6 text-xs">
                        <!-- Customer Details -->
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Customer</div>
                            <div class="font-bold text-sm text-slate-900 mt-0.5">
                                @if ($selectedMember)
                                    {{ $selectedMember->person->first_name }} {{ $selectedMember->person->last_name }}
                                    ({{ $selectedMember->membership_number }})
                                @elseif ($isGuestSale && !empty($guestFirstName))
                                    Guest: {{ $guestFirstName }} {{ $guestLastName }}
                                @else
                                    Counter Guest
                                @endif
                            </div>
                        </div>

                        <!-- Floor Staff Bearer, Spot & Accompanying Guest Info -->
                        @if ($selectedWaiterId || $tableNumber || $accompanyingGuestName)
                            <div class="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 flex flex-wrap items-center justify-between gap-2 text-slate-600">
                                @if ($selectedWaiterId)
                                    @php $b = $this->waiters->firstWhere('id', $selectedWaiterId); @endphp
                                    <div>
                                        <span class="text-[10px] uppercase font-bold text-slate-400">Bearer:</span>
                                        <span class="font-bold text-slate-800 ml-1">{{ $b?->name ?? 'Unassigned' }} ({{ $b?->code }})</span>
                                    </div>
                                @endif
                                @if ($tableNumber)
                                    <div>
                                        <span class="text-[10px] uppercase font-bold text-slate-400">Spot:</span>
                                        <span class="font-bold text-slate-800 ml-1">{{ $tableNumber }}</span>
                                    </div>
                                @endif
                                @if ($accompanyingGuestName)
                                    <div>
                                        <span class="text-[10px] uppercase font-bold text-slate-400">Guest:</span>
                                        <span class="font-bold text-indigo-900 ml-1">👤 {{ $accompanyingGuestName }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Optional Tip / Gratuity Section -->
                        <div class="p-3.5 rounded-2xl bg-amber-50/50 border border-amber-200 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-900 flex items-center gap-1">
                                    <span>🎁</span>
                                    <span>Staff Tip / Gratuity (Optional)</span>
                                </span>
                                <span class="text-xs font-mono font-bold text-amber-700">+₹{{ number_format($tipAmount, 2) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="setTip(0)" class="px-2.5 py-1 text-xs rounded-xl border cursor-pointer font-bold {{ $tipAmount == 0 ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">₹0</button>
                                <button type="button" wire:click="setTip(20)" class="px-2.5 py-1 text-xs rounded-xl border cursor-pointer font-bold {{ $tipAmount == 20 ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">₹20</button>
                                <button type="button" wire:click="setTip(50)" class="px-2.5 py-1 text-xs rounded-xl border cursor-pointer font-bold {{ $tipAmount == 50 ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">₹50</button>
                                <button type="button" wire:click="setTip(100)" class="px-2.5 py-1 text-xs rounded-xl border cursor-pointer font-bold {{ $tipAmount == 100 ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">₹100</button>
                                <div class="relative flex-1">
                                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 text-xs font-bold">₹</span>
                                    <input type="number" step="1" min="0" wire:model.live.debounce.300ms="tipAmount" placeholder="Custom"
                                        class="w-full pl-6 pr-2 py-1 text-xs rounded-xl border border-slate-300 bg-white font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                </div>
                            </div>
                        </div>

                        <!-- Tender Details & Amount -->
                        <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-300">
                            <div class="flex justify-between items-center text-slate-600 mb-1">
                                <span>Payment Method</span>
                                <span class="font-bold text-amber-800 font-mono text-sm">{{ $tenderLabel }}</span>
                            </div>
                            @if ($cartDiscount > 0)
                                <div class="flex justify-between items-center text-emerald-700 font-semibold mb-1.5">
                                    <span>Promo Savings Applied</span>
                                    <span class="font-mono">-₹{{ number_format($cartDiscount, 2) }}</span>
                                </div>
                            @endif
                            @if ($tipAmount > 0)
                                <div class="flex justify-between items-center text-amber-800 font-semibold mb-1.5">
                                    <span>Staff Tip Added</span>
                                    <span class="font-mono">+₹{{ number_format($tipAmount, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between items-center pt-2 border-t border-amber-200">
                                <span class="font-bold text-slate-900 text-sm">Total Amount Payable</span>
                                <span
                                    class="text-2xl font-extrabold font-mono text-amber-700">₹{{ number_format($cartNet + $tipAmount, 2) }}</span>
                            </div>
                        </div>

                        <!-- Prepaid Specific Checks -->
                        @if ($isPrepaid)
                            <div
                                class="p-3.5 rounded-2xl {{ $hasSufficient ? 'bg-emerald-50 border border-emerald-300 text-emerald-900' : 'bg-rose-50 border border-rose-300 text-rose-900' }}">
                                <div class="flex justify-between">
                                    <span>Available Prepaid Balance:</span>
                                    <span
                                        class="font-mono font-bold">₹{{ number_format($memberWalletBalancePaise / 100, 2) }}</span>
                                </div>
                                @if ($hasSufficient)
                                    <div class="flex justify-between mt-1 text-[11px] text-slate-600">
                                        <span>Remaining Balance After Sale:</span>
                                        <span
                                            class="font-mono text-emerald-700 font-bold">₹{{ number_format(($memberWalletBalancePaise - $totalPaise) / 100, 2) }}</span>
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
                        <button wire:click="closePaymentConfirmModal" type="button"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                            Cancel [Esc]
                        </button>

                        <button wire:click="confirmCheckout" wire:loading.attr="disabled" @if (!$hasSufficient || $isProcessingPayment) disabled @endif type="button"
                            class="flex-1 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs shadow-md shadow-amber-500/20 active:scale-95 transition-all flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer {{ !$hasSufficient ? 'opacity-50 cursor-not-allowed' : '' }}">
                            <span wire:loading.remove wire:target="confirmCheckout">Confirm & Print Receipt [Enter] ↵</span>
                            <span wire:loading wire:target="confirmCheckout">Settling Bill...</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- MODAL 4: Reconcile & Close Counter Dialog -->
        <!-- ========================================== -->
        <div x-show="showCloseShiftModal" x-cloak
            @shift-closed.window="showCloseShiftModal = false"
            @click.self="showCloseShiftModal = false"
            @keydown.escape.window="showCloseShiftModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog" aria-modal="true" aria-labelledby="close-counter-dialog-title" style="display: none;">
            <div @click.away="showCloseShiftModal = false"
                class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl relative text-slate-900">
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4">
                    <div>
                        <span class="text-[11px] uppercase font-bold tracking-wider text-rose-600">Shift
                            Reconciliation</span>
                        <h3 id="close-counter-dialog-title" class="text-lg font-bold text-slate-900 mt-0.5">Reconcile &amp;
                            Close Counter</h3>
                    </div>
                    <button @click="showCloseShiftModal = false" type="button"
                        class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer"
                        aria-label="Close dialog">×</button>
                </div>

                <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                    Count the physical currency notes and coins in the cash drawer. Prepaids and digital card/UPI tenders
                    reconcile automatically.
                </p>

                @php
                    $quickSummary = app(\App\Domain\Billing\Services\ShiftService::class)->getShiftTransactionDetails($activeShift);
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Billed Sales</span>
                        <span class="text-sm font-extrabold font-mono text-slate-900 block">
                            ₹{{ number_format(($quickSummary['totals']['billed_turnover_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[10px] text-slate-400 block">{{ $quickSummary['shift']['total_bills'] ?? 0 }} paid bills</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-emerald-50/70 border border-emerald-200">
                        <span class="text-[10px] uppercase font-bold text-emerald-800 block">Cash Sales</span>
                        <span class="text-sm font-extrabold font-mono text-emerald-900 block">
                            ₹{{ number_format(($quickSummary['tenders']['cash']['amount_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[10px] text-emerald-700 block">{{ $quickSummary['tenders']['cash']['count'] ?? 0 }} orders</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-blue-50/70 border border-blue-200">
                        <span class="text-[10px] uppercase font-bold text-blue-800 block">UPI / Card</span>
                        <span class="text-sm font-extrabold font-mono text-blue-900 block">
                            ₹{{ number_format(($quickSummary['tenders']['digital']['amount_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[10px] text-blue-700 block">{{ $quickSummary['tenders']['digital']['count'] ?? 0 }} orders</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-amber-50/70 border border-amber-200">
                        <span class="text-[10px] uppercase font-bold text-amber-800 block">Prepaid Wallet</span>
                        <span class="text-sm font-extrabold font-mono text-amber-900 block">
                            ₹{{ number_format(($quickSummary['tenders']['prepaid']['amount_paise'] ?? 0) / 100, 2) }}
                        </span>
                        <span class="text-[10px] text-amber-700 block">{{ $quickSummary['tenders']['prepaid']['count'] ?? 0 }} orders</span>
                    </div>
                </div>

                <form wire:submit.prevent="closeShift" class="space-y-4">
                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-500 block">Expected Closing Cash</span>
                            <span class="text-xs text-slate-400">Opening Float + Cash Sales</span>
                        </div>
                        <div class="text-right">
                            <span class="text-xl font-bold font-mono text-slate-900 block">
                                ₹{{ number_format($activeShift->calculateExpectedCashPaise() / 100, 2) }}
                            </span>
                            <button type="button" wire:click="fillExpectedCash"
                                class="text-[11px] font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-2.5 py-1 rounded-lg mt-1 inline-flex items-center gap-1 transition-colors cursor-pointer">
                                <span>⚡ Match Expected</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="closing-cash-counted-input"
                                class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Closing Cash Counted (₹)
                            </label>
                            @if ($closingCashCounted !== null && $closingCashCounted !== '')
                                @php
                                    $countedP = (int) round(((float) $closingCashCounted) * 100);
                                    $diffP = $countedP - $activeShift->calculateExpectedCashPaise();
                                @endphp
                                @if ($diffP === 0)
                                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md flex items-center gap-1">
                                        ✓ Drawer Balanced
                                    </span>
                                @elseif ($diffP < 0)
                                    <span class="text-[10px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-md flex items-center gap-1">
                                        ⚠️ Shortage: -₹{{ number_format(abs($diffP) / 100, 2) }}
                                    </span>
                                @else
                                    <span class="text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md flex items-center gap-1">
                                        ⚠️ Surplus: +₹{{ number_format($diffP / 100, 2) }}
                                    </span>
                                @endif
                            @endif
                        </div>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-3 text-slate-400 font-mono font-bold text-base pointer-events-none">₹</span>
                            <input type="number" id="closing-cash-counted-input" step="any" min="0" wire:model.live="closingCashCounted"
                                placeholder="Enter physical counted cash..."
                                autofocus
                                class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-4 py-3 text-slate-900 font-mono font-bold text-base focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                        <span class="text-[11px] text-slate-400 mt-1 block">
                            Type the physically counted drawer cash, or click "⚡ Match Expected" if drawer balances.
                        </span>
                    </div>

                    <div>
                        <label for="shift-notes-input"
                            class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Handover &amp; Variance Remarks
                        </label>
                        <input type="text" id="shift-notes-input" wire:model="shiftNotes"
                            placeholder="e.g. Exact cash balanced, or reason for shortage/surplus..."
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <span class="text-[10px] text-slate-400 mt-1 block">
                            Mandatory if closing cash does not match the expected drawer amount.
                        </span>
                    </div>

                    <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="showCloseShiftModal = false"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                            Cancel [Esc]
                        </button>
                        <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md shadow-rose-600/20 active:scale-95 transition-all cursor-pointer">
                            Confirm Closing Cash &amp; End Shift [Enter] ↵
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 3: 80mm Thermal Receipt Preview Modal -->
    <!-- ========================================== -->
    @if ($showReceiptModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4 animate-fade-in print:p-0 print:static print:bg-transparent print:backdrop-blur-none"
            role="dialog" aria-modal="true" aria-labelledby="receipt-modal-title"
            @click.self="$wire.closeReceiptModal()"
            @keydown.escape.window="$wire.closeReceiptModal()">
            <div
                class="bg-white text-slate-900 rounded-3xl p-6 max-w-sm w-full shadow-2xl relative border border-slate-200 print:shadow-none print:border-none print:p-0 print:max-w-none">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-4 print:hidden no-print">
                    <div>
                        <span id="receipt-modal-title"
                            class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            @if ($isShiftHandoverSlip)
                                📋 Shift Handover Summary Slip
                            @elseif ($isTableCheckReceipt)
                                🍽️ Table Check / Pre-Bill
                            @else
                                80mm Thermal Receipt
                            @endif
                        </span>
                        @if ($isShiftHandoverSlip)
                            <div class="text-[11px] text-amber-700 font-semibold mt-0.5">
                                Official cash handover summary. Attach slip to physical cash deposit.
                            </div>
                        @elseif ($isTableCheckReceipt)
                            <div class="text-[11px] text-indigo-700 font-semibold mt-0.5">
                                Deliver slip to table. Settle payment upon bearer's return.
                            </div>
                        @elseif ($lastReceiptNumber)
                            <div class="text-[11px] font-mono text-slate-800 font-semibold mt-0.5">Reference:
                                {{ $lastReceiptNumber }}
                            </div>
                        @endif
                    </div>
                    <button wire:click="closeReceiptModal" type="button"
                        class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer print:hidden no-print"
                        aria-label="Close receipt">×</button>
                </div>

                <!-- Receipt Paper Simulation -->
                <pre id="printable-pos-receipt" data-print-document="modal"
                    class="m-0 bg-amber-50/40 p-4 rounded-xl border border-dashed border-slate-300 font-mono text-[11px] leading-relaxed whitespace-pre overflow-x-auto shadow-inner text-slate-800 max-h-96 print:max-h-none print:overflow-visible print:border-none print:bg-white print:text-black print:p-0 print:w-fit print:max-w-full print:mx-auto print:text-[9.5pt] print:leading-snug">{{ $receiptContent }}</pre>

                <div class="mt-5 flex flex-col sm:flex-row items-center justify-between gap-2.5 print:hidden no-print">
                    <button onclick="window.print()" type="button"
                        class="w-full sm:flex-1 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-slate-900 cursor-pointer print:hidden no-print">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>{{ $isShiftHandoverSlip ? 'Print Handover Slip [P]' : ($isTableCheckReceipt ? 'Print Table Check [P]' : 'Print Receipt [P]') }}</span>
                    </button>
                    
                    @if ($isTableCheckReceipt && !empty($cart) && !$activeHeldTabId)
                        <button wire:click="holdCurrentCart" type="button"
                            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer print:hidden no-print"
                            title="Park this order as a running tab while bearer is at the table">
                            <span>🍸 Park Tab</span>
                        </button>
                    @endif

                    <button wire:click="closeReceiptModal" type="button"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-colors focus:outline-none focus:ring-2 focus:ring-slate-300 cursor-pointer print:hidden no-print">
                        {{ $isShiftHandoverSlip ? 'Close & Finish [Esc]' : ($isTableCheckReceipt ? 'Back to POS [Esc]' : 'Done / Next [Esc]') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 5: Hold / Park Bar Tab Modal         -->
    <!-- ========================================== -->
    @if ($showHoldTabModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog" aria-modal="true" aria-labelledby="hold-tab-dialog-title"
            @click.self="$wire.closeHoldTabModal()"
            @keydown.escape.window="$wire.closeHoldTabModal()">
            <div
                class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl relative text-slate-900">
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-base font-bold shadow-2xs">
                            🍸
                        </span>
                        <div>
                            <span class="text-[11px] uppercase font-bold tracking-wider text-amber-700">Bar / Lounge
                                Order</span>
                            <h3 id="hold-tab-dialog-title" class="text-lg font-bold text-slate-900 mt-0.5">
                                {{ $activeHeldTabId ? 'Update Held Tab' : 'Hold / Park Running Tab' }}
                            </h3>
                        </div>
                    </div>
                    <button wire:click="closeHoldTabModal" type="button"
                        class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer"
                        aria-label="Close dialog">×</button>
                </div>

                <div class="space-y-4">
                    <!-- Member / Guest Summary -->
                    <div
                        class="p-3.5 bg-amber-50/60 rounded-2xl border border-amber-200 text-xs text-slate-700 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-amber-800 block">Customer</span>
                            <span class="font-bold text-slate-900 text-sm">
                                @if ($selectedMember)
                                    {{ $selectedMember->person->first_name }} {{ $selectedMember->person->last_name }}
                                    ({{ $selectedMember->membership_number }})
                                @elseif ($selectedRoomReservation)
                                    🏨 {{ $selectedRoomReservation->room->room_number }} •
                                    {{ $selectedRoomReservation->primaryGuest?->full_name }}
                                @endif
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-amber-800 block">Current Amount</span>
                            <span class="text-base font-extrabold font-mono text-amber-900">
                                ₹{{ number_format($totalPaise / 100, 2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Table / Stool Identifier -->
                    <div>
                        <label for="tab-table-identifier-input"
                            class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Table / Bar Stool / Area <span class="text-slate-400 font-normal">(Optional)</span>
                        </label>
                        <input type="text" id="tab-table-identifier-input" wire:model="tabTableIdentifier"
                            placeholder="e.g. Table 4, Bar Stool 3, Poolside" autofocus
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-slate-900 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-colors">
                    </div>

                    <!-- Accompanying Guest Name -->
                    <div>
                        <label for="tab-guest-name-input"
                            class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Accompanying Guest Name <span class="text-slate-400 font-normal">(Optional)</span>
                        </label>
                        <input type="text" id="tab-guest-name-input" wire:model="accompanyingGuestName"
                            placeholder="e.g. Suresh Menon"
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-slate-900 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-colors">
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="tab-notes-input"
                            class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Staff Remark / Note <span class="text-slate-400 font-normal">(Optional)</span>
                        </label>
                        <input type="text" id="tab-notes-input" wire:model="tabNotes"
                            placeholder="e.g. Round 1 drinks, waiting for starters"
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-slate-900 text-sm focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-colors">
                    </div>

                    <p
                        class="text-[11px] text-slate-500 leading-relaxed bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                        🔒 <strong>Zero-Credit Policy:</strong> Held tabs are in-session draft orders. Stock and journals
                        update only when settled by card, cash, or digital tender before the member leaves.
                    </p>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-200">
                        <button type="button" wire:click="closeHoldTabModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                            Cancel [Esc]
                        </button>
                        <button type="button" wire:click="holdCurrentCart"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs shadow-md shadow-amber-500/20 active:scale-95 transition-all flex items-center gap-1.5 cursor-pointer">
                            <span>Hold Tab & Clear Workspace [Enter]</span>
                            <kbd
                                class="px-1.5 py-0.2 rounded bg-white/80 text-slate-950 border border-amber-600 font-mono text-[10px] font-bold">↵</kbd>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 6: All Held Tabs Manager Modal       -->
    <!-- ========================================== -->
    @if ($showHeldTabsListModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog" aria-modal="true" aria-labelledby="held-tabs-manager-dialog-title"
            @click.self="$wire.closeHeldTabsListModal()"
            @keydown.escape.window="$wire.closeHeldTabsListModal()">
            <div
                class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-7 max-w-3xl w-full shadow-2xl relative text-slate-900 max-h-[85vh] flex flex-col">
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-base font-bold shadow-2xs">
                            🍸
                        </span>
                        <div>
                            <span class="text-[11px] uppercase font-bold tracking-wider text-amber-700">Bar Tabs
                                Manager</span>
                            <h3 id="held-tabs-manager-dialog-title" class="text-lg font-bold text-slate-900 mt-0.5">
                                Active Running Tabs ({{ $heldTabs->count() }})
                            </h3>
                        </div>
                    </div>
                    <button wire:click="closeHeldTabsListModal" type="button"
                        class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer"
                        aria-label="Close dialog">×</button>
                </div>

                <!-- Search Input for tabs -->
                <div class="mb-4 shrink-0">
                    <input type="text" wire:model.live.debounce.150ms="heldTabSearch"
                        placeholder="Filter by member name, membership #, table #, tab #..."
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-slate-900 text-xs font-semibold focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-colors">
                </div>

                <!-- Scrollable Grid of Tabs -->
                <div class="overflow-y-auto flex-1 space-y-3 pr-1">
                    @forelse ($heldTabs as $tab)
                        <div
                            class="p-4 rounded-2xl border transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3 {{ $activeHeldTabId === $tab->id ? 'bg-amber-50/70 border-amber-400 ring-2 ring-amber-400/40' : 'bg-slate-50 hover:bg-slate-100/70 border-slate-200' }}">
                            <div class="space-y-1 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-extrabold text-sm text-slate-900">{{ $tab->customer_display_name }}</span>
                                    <span
                                        class="px-2 py-0.5 rounded bg-slate-200 text-slate-700 font-mono text-[10px] font-bold">
                                        {{ $tab->tab_number }}
                                    </span>
                                    @if ($tab->table_identifier)
                                        <span
                                            class="px-2 py-0.5 rounded bg-amber-200 text-amber-950 font-mono text-[10px] font-bold">
                                            📍 {{ $tab->table_identifier }}
                                        </span>
                                    @endif
                                    @if ($tab->accompanying_guest_name)
                                        <span
                                            class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-900 font-sans text-[10px] font-bold">
                                            👤 Guest: {{ $tab->accompanying_guest_name }}
                                        </span>
                                    @endif
                                    @if ($activeHeldTabId === $tab->id)
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-amber-500 text-slate-950 text-[10px] font-extrabold uppercase tracking-wide">
                                            Currently Active
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 flex items-center gap-2 flex-wrap">
                                    <span>{{ $tab->items_count }} items</span>
                                    <span>•</span>
                                    <span>Parked {{ $tab->held_at ? $tab->held_at->diffForHumans() : 'recently' }}</span>
                                    @if ($tab->notes)
                                        <span>•</span>
                                        <span class="text-slate-600 italic">"{{ $tab->notes }}"</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                                <div class="text-right">
                                    <div class="text-base font-mono font-black text-amber-900">
                                        ₹{{ number_format($tab->total_amount, 2) }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" wire:click="printHeldTabTableCheck({{ $tab->id }})"
                                        class="px-2.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border border-indigo-200 font-bold text-xs shadow-2xs transition-all cursor-pointer flex items-center gap-1"
                                        title="Print Table Check slip for bearer">
                                        <span>📄 Check</span>
                                    </button>
                                    <button type="button" wire:click="loadHeldTab({{ $tab->id }})"
                                        class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs shadow-xs transition-all cursor-pointer">
                                        Open Tab ↵
                                    </button>
                                    <button type="button" wire:click="voidActiveHeldTab('Voided from manager')"
                                        wire:confirm="Are you sure you want to void this open tab?"
                                        class="px-2 py-1.5 rounded-xl text-rose-600 hover:bg-rose-50 text-xs font-semibold cursor-pointer">
                                        Void
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-slate-400">
                            <span class="text-3xl block mb-2">🍸</span>
                            <span class="text-xs font-medium">No held tabs found.</span>
                        </div>
                    @endforelse
                </div>

                <div class="pt-4 border-t border-slate-200 mt-4 flex items-center justify-between shrink-0">
                    <span class="text-xs text-slate-500">
                        Max capacity: {{ $maxHeldTabs }} concurrent tabs
                    </span>
                    <button type="button" wire:click="closeHeldTabsListModal"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer">
                        Close [Esc]
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 7: Unsettled Walk-Out Recording Dialog -->
    <!-- ========================================== -->
    @if ($showUnsettledWalkoutModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog" aria-modal="true" aria-labelledby="unsettled-modal-title"
            @click.self="$wire.closeUnsettledWalkoutModal()"
            @keydown.escape.window="$wire.closeUnsettledWalkoutModal()">
            <div class="bg-white border-2 border-rose-500 rounded-3xl p-6 max-w-lg w-full shadow-2xl relative text-slate-900">
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center text-lg font-bold">
                            🚶
                        </span>
                        <div>
                            <span class="text-[11px] uppercase font-bold tracking-wider text-rose-700">Immediate Action Required</span>
                            <h3 id="unsettled-modal-title" class="text-lg font-black text-slate-900 mt-0.5">Record Unsettled Walk-Out Bill</h3>
                        </div>
                    </div>
                    <button wire:click="closeUnsettledWalkoutModal" type="button"
                        class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer">×</button>
                </div>

                <div class="space-y-4 text-xs">
                    <!-- Member Summary -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex justify-between items-center">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Member Account</span>
                            <span class="font-bold text-sm text-slate-900">
                                {{ $selectedMember?->person?->first_name }} {{ $selectedMember?->person?->last_name }}
                                ({{ $selectedMember?->membership_number }})
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Bill Amount</span>
                            <span class="text-lg font-extrabold font-mono text-rose-700">₹{{ number_format($cartNet, 2) }}</span>
                        </div>
                    </div>

                    <!-- Serving Bearer & Table Confirmation -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="walkout-waiter-select" class="block text-[11px] font-bold uppercase text-slate-700 mb-1">
                                Serving Bearer / Waiter
                            </label>
                            <select id="walkout-waiter-select" wire:model.live="selectedWaiterId"
                                class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 border border-slate-300 font-semibold focus:outline-none focus:ring-1 focus:ring-rose-500">
                                <option value="">-- Direct Counter / Unassigned --</option>
                                @foreach ($this->waiters as $w)
                                    <option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="walkout-table-input" class="block text-[11px] font-bold uppercase text-slate-700 mb-1">
                                Table / Area Spot
                            </label>
                            <input type="text" id="walkout-table-input" wire:model.live="tableNumber" placeholder="e.g. Table 4 / Bar Stool"
                                class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 border border-slate-300 font-semibold focus:outline-none focus:ring-1 focus:ring-rose-500">
                        </div>
                    </div>

                    <!-- Reason Description -->
                    <div>
                        <label for="walkout-reason-input" class="block text-[11px] font-bold uppercase text-slate-700 mb-1">
                            Walk-Out Circumstances / Remarks
                        </label>
                        <textarea id="walkout-reason-input" wire:model="unsettledWalkoutReason" rows="2"
                            placeholder="e.g. Member consumed peg at counter and left premises before paying..."
                            class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 border border-slate-300 text-slate-900 focus:outline-none focus:ring-1 focus:ring-rose-500"></textarea>
                    </div>

                    <!-- Crucial Accounting & Inventory Notice -->
                    <div class="p-3 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 text-[11px] space-y-1.5 leading-relaxed">
                        <div class="font-bold flex items-center gap-1 text-amber-900">
                            <span>🔒</span>
                            <span>Inventory & Enforced Settlement Protocol</span>
                        </div>
                        <div>
                            1. <strong>Cellar Stock Deducted Now:</strong> Physical alcohol and food inventory will be immediately deducted so cellar books have zero stock discrepancy.
                        </div>
                        <div>
                            2. <strong>Account Ordering Lockout:</strong> This member will be immediately blocked from placing future orders at any counter until this bill is settled.
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-between gap-3 pt-3 border-t border-slate-200">
                    <button wire:click="closeUnsettledWalkoutModal" type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                        Cancel
                    </button>
                    <button wire:click="recordUnsettledWalkout" type="button"
                        class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs shadow-md shadow-rose-600/30 active:scale-95 transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <span>Confirm & Record Unsettled Walk-Out</span>
                        <span>✓</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 8: Outstanding Walk-Out Dues Settlement -->
    <!-- ========================================== -->
    @if ($showOutstandingDuesModal && !empty($outstandingBillsList))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog" aria-modal="true" aria-labelledby="outstanding-dues-title"
            @click.self="$wire.closeOutstandingDuesModal()"
            @keydown.escape.window="$wire.closeOutstandingDuesModal()">
            <div class="bg-white border-2 border-rose-500 rounded-3xl p-6 sm:p-7 max-w-xl w-full shadow-2xl relative text-slate-900 max-h-[90vh] flex flex-col">
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-xl font-bold shadow-sm">
                            ⚠️
                        </span>
                        <div>
                            <span class="text-[11px] uppercase font-bold tracking-wider text-rose-700">Enforced By-Law Policy</span>
                            <h3 id="outstanding-dues-title" class="text-lg font-black text-slate-900 mt-0.5">
                                Outstanding Walk-Out Dues ({{ count($outstandingBillsList) }})
                            </h3>
                        </div>
                    </div>
                    <button wire:click="closeOutstandingDuesModal" type="button"
                        class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer">×</button>
                </div>

                <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs mb-4 shrink-0 leading-relaxed">
                    <strong>{{ $selectedMember?->person?->first_name }} {{ $selectedMember?->person?->last_name }} ({{ $selectedMember?->membership_number }})</strong> has unpaid walk-out bill(s) from a previous visit. Under Club Rules, outstanding dues must be settled in full before placing any new order.
                </div>

                <!-- Scrollable Bills List -->
                <div class="space-y-3 overflow-y-auto pr-1 flex-1 mb-4 text-xs">
                    @foreach ($outstandingBillsList as $b)
                        <div class="p-4 rounded-2xl border transition-all cursor-pointer {{ $settlingBillId === $b['id'] ? 'bg-amber-50/70 border-amber-500 ring-2 ring-amber-400' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/70' }}"
                            wire:click="$set('settlingBillId', {{ $b['id'] }})">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-slate-900">{{ $b['bill_number'] }}</span>
                                        <span class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-800 text-[10px] font-bold uppercase">Unsettled</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        {{ $b['created_at'] }} • {{ $b['table_number'] }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold font-mono text-slate-900">₹{{ number_format($b['net_amount'], 2) }}</span>
                                </div>
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex flex-wrap items-center justify-between text-[11px] text-slate-600 gap-1">
                                <div>
                                    <span class="font-semibold text-slate-500">Items:</span>
                                    <span>{{ $b['lines'] }}</span>
                                </div>
                                <div>
                                    <span class="font-semibold text-slate-500">Bearer:</span>
                                    <span class="font-bold text-amber-800">{{ $b['waiter_name'] }}</span>
                                </div>
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400 italic">
                                Reason: {{ $b['unsettled_reason'] }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Settlement Tender & Tip Box for Selected Bill -->
                @php
                    $activeSettlingBill = collect($outstandingBillsList)->firstWhere('id', $settlingBillId);
                    $billAmount = (float) ($activeSettlingBill['net_amount'] ?? 0);
                    $totalSettlingPayable = $billAmount + $settlingBillTipAmount;
                    $hasSufficientPrepaid = ($selectedMember && $memberWalletBalancePaise >= (int) round($totalSettlingPayable * 100));
                @endphp
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3 shrink-0 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-700">Settling: {{ $activeSettlingBill['bill_number'] ?? 'Bill' }}</span>
                        <span class="font-mono text-base font-extrabold text-amber-700">
                            Total: ₹{{ number_format($totalSettlingPayable, 2) }}
                        </span>
                    </div>

                    <!-- Optional Gratuity / Tip for Serving Bearer -->
                    <div class="p-2.5 rounded-xl bg-white border border-slate-200 space-y-1.5">
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="font-semibold text-slate-600">Bearer Tip / Gratuity (Optional)</span>
                            <span class="font-mono font-bold text-amber-700">+₹{{ number_format($settlingBillTipAmount, 2) }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="$set('settlingBillTipAmount', 0)" class="px-2 py-0.5 text-xs rounded-lg border cursor-pointer font-bold {{ $settlingBillTipAmount == 0 ? 'bg-amber-500 text-slate-950 border-amber-600' : 'bg-slate-50 text-slate-600 border-slate-200' }}">₹0</button>
                            <button type="button" wire:click="$set('settlingBillTipAmount', 20)" class="px-2 py-0.5 text-xs rounded-lg border cursor-pointer font-bold {{ $settlingBillTipAmount == 20 ? 'bg-amber-500 text-slate-950 border-amber-600' : 'bg-slate-50 text-slate-600 border-slate-200' }}">₹20</button>
                            <button type="button" wire:click="$set('settlingBillTipAmount', 50)" class="px-2 py-0.5 text-xs rounded-lg border cursor-pointer font-bold {{ $settlingBillTipAmount == 50 ? 'bg-amber-500 text-slate-950 border-amber-600' : 'bg-slate-50 text-slate-600 border-slate-200' }}">₹50</button>
                            <button type="button" wire:click="$set('settlingBillTipAmount', 100)" class="px-2 py-0.5 text-xs rounded-lg border cursor-pointer font-bold {{ $settlingBillTipAmount == 100 ? 'bg-amber-500 text-slate-950 border-amber-600' : 'bg-slate-50 text-slate-600 border-slate-200' }}">₹100</button>
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-slate-400 text-xs">₹</span>
                                <input type="number" step="1" min="0" wire:model.live="settlingBillTipAmount" placeholder="Custom"
                                    class="w-full pl-5 pr-2 py-0.5 text-xs rounded-lg border border-slate-200 bg-white font-mono focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- Tender Selector Buttons -->
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" wire:click="$set('settlingTenderType', 'prepaid')"
                            class="py-2 px-2.5 rounded-xl border text-center font-bold transition cursor-pointer {{ $settlingTenderType === 'prepaid' ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100' }}">
                            <span>Prepaid Wallet</span>
                            <div class="text-[10px] font-normal {{ $hasSufficientPrepaid ? 'text-slate-800' : 'text-rose-600 font-bold' }}">
                                ₹{{ number_format($memberWalletBalancePaise / 100, 2) }}
                            </div>
                        </button>
                        <button type="button" wire:click="$set('settlingTenderType', 'cash')"
                            class="py-2 px-2.5 rounded-xl border text-center font-bold transition cursor-pointer {{ $settlingTenderType === 'cash' ? 'bg-emerald-600 text-white border-emerald-700 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100' }}">
                            <span>Cash</span>
                            <div class="text-[10px] font-normal opacity-80">Physical Drawer</div>
                        </button>
                        <button type="button" wire:click="$set('settlingTenderType', 'digital')"
                            class="py-2 px-2.5 rounded-xl border text-center font-bold transition cursor-pointer {{ $settlingTenderType === 'digital' ? 'bg-blue-600 text-white border-blue-700 shadow-2xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100' }}">
                            <span>UPI / Digital</span>
                            <div class="text-[10px] font-normal opacity-80">Bank Clearing</div>
                        </button>
                    </div>
                </div>

                <!-- Settlement Action Button -->
                <div class="mt-4 flex items-center justify-between gap-3 pt-3 border-t border-slate-200 shrink-0">
                    <button wire:click="closeOutstandingDuesModal" type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                        Close
                    </button>
                    <button wire:click="settleOutstandingBill" wire:loading.attr="disabled"
                        @if ($settlingTenderType === 'prepaid' && !$hasSufficientPrepaid) disabled @endif
                        class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs shadow-md shadow-emerald-600/20 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer {{ ($settlingTenderType === 'prepaid' && !$hasSufficientPrepaid) ? 'opacity-50 cursor-not-allowed' : '' }}">
                        <span wire:loading.remove wire:target="settleOutstandingBill">
                            Settle Bill & Print Receipt (₹{{ number_format($totalSettlingPayable, 2) }}) ↵
                        </span>
                        <span wire:loading wire:target="settleOutstandingBill">Settling Bill...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 9: Existing Active Running Tab Prompt -->
    <!-- ========================================== -->
    @if ($showExistingTabPromptModal && !empty($existingOpenTabsList))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog" aria-modal="true" aria-labelledby="existing-tab-prompt-title"
            @click.self="$wire.dismissExistingTabPrompt()"
            @keydown.escape.window="$wire.dismissExistingTabPrompt()">
            <div class="bg-white border-2 border-amber-500 rounded-3xl p-6 sm:p-7 max-w-lg w-full shadow-2xl relative text-slate-900 max-h-[90vh] flex flex-col">
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="w-10 h-10 rounded-2xl bg-amber-500 text-slate-950 flex items-center justify-center text-xl font-bold shadow-sm">
                            🍸
                        </span>
                        <div>
                            <span class="text-[11px] uppercase font-bold tracking-wider text-amber-700">Active Order Detected</span>
                            <h3 id="existing-tab-prompt-title" class="text-lg font-black text-slate-900 mt-0.5">
                                Resume Existing Tab?
                            </h3>
                        </div>
                    </div>
                    <button wire:click="dismissExistingTabPrompt" type="button"
                        class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer">×</button>
                </div>

                <div class="p-3.5 rounded-2xl bg-amber-50/80 border border-amber-300 text-amber-950 text-xs mb-4 shrink-0 leading-relaxed">
                    <strong>{{ $selectedMember?->person?->first_name }} {{ $selectedMember?->person?->last_name }} ({{ $selectedMember?->membership_number }})</strong> already has an active running tab parked at the counter. Would you like to resume this tab to add items to it, or start a separate new bill?
                </div>

                <!-- Tab Cards List -->
                <div class="space-y-3 overflow-y-auto pr-1 flex-1 mb-4 text-xs">
                    @foreach ($existingOpenTabsList as $tab)
                        <div class="p-4 rounded-2xl border bg-slate-50 border-amber-300/80 space-y-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-slate-900 text-sm">{{ $tab['tab_number'] }}</span>
                                        <span class="px-2 py-0.5 rounded-md bg-amber-200 text-amber-900 text-[10px] font-bold">📍 {{ $tab['table_identifier'] }}</span>
                                        @if (!empty($tab['accompanying_guest_name']))
                                            <span class="px-2 py-0.5 rounded-md bg-indigo-100 text-indigo-900 text-[10px] font-bold">👤 {{ $tab['accompanying_guest_name'] }}</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        Parked {{ $tab['held_time'] }} • Bearer: <strong class="text-slate-800">{{ $tab['waiter_name'] }}</strong>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-base font-extrabold font-mono text-amber-900">₹{{ number_format($tab['net_amount'], 2) }}</span>
                                    <span class="block text-[10px] text-slate-500">{{ $tab['items_count'] }} {{ $tab['items_count'] === 1 ? 'item' : 'items' }}</span>
                                </div>
                            </div>
                            <div class="pt-2 border-t border-slate-200 text-[11px] text-slate-600">
                                <span class="font-semibold text-slate-500">Items:</span>
                                <span>{{ $tab['items_summary'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Actions Strip -->
                <div class="mt-2 flex items-center justify-between gap-3 pt-3 border-t border-slate-200 shrink-0">
                    <button wire:click="dismissExistingTabPrompt" type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                        Start Fresh Bill [Esc]
                    </button>
                    <button wire:click="resumePromptTab" type="button"
                        class="flex-1 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs shadow-md shadow-amber-500/20 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <span>✓ Add to Existing Tab [Enter]</span>
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-950 border border-amber-600 font-mono text-[10px] font-bold">↵</kbd>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 10: Shift Activity & Summary Modal   -->
    <!-- ========================================== -->
    @if ($showShiftSummaryModal && $currentShiftDetails)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
            role="dialog" aria-modal="true" aria-labelledby="shift-summary-dialog-title"
            @click.self="$wire.closeShiftSummaryModal()"
            @keydown.escape.window="$wire.closeShiftSummaryModal()">
            <div class="bg-white border-2 border-amber-500 rounded-3xl p-6 sm:p-7 max-w-2xl w-full shadow-2xl relative text-slate-900 max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-start justify-between border-b border-slate-200 pb-3 mb-4 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="w-10 h-10 rounded-2xl bg-amber-500 text-slate-950 flex items-center justify-center text-xl font-bold shadow-sm">
                            📊
                        </span>
                        <div>
                            <span class="text-[11px] uppercase font-bold tracking-wider text-amber-700">Official Cash Handover &amp; Performance</span>
                            <h3 id="shift-summary-dialog-title" class="text-lg font-black text-slate-900 mt-0.5">
                                Shift Activity &amp; Summary
                            </h3>
                            <div class="text-xs text-slate-500 flex flex-wrap items-center gap-2 mt-0.5 font-sans">
                                <span class="font-mono font-bold text-slate-800">{{ $currentShiftDetails['shift']['shift_number'] }}</span>
                                <span>•</span>
                                <span>{{ $currentShiftDetails['shift']['counter_name'] }}</span>
                                <span>•</span>
                                <span>Cashier: <strong class="text-slate-700">{{ $currentShiftDetails['shift']['cashier_name'] }}</strong></span>
                            </div>
                        </div>
                    </div>
                    <button wire:click="closeShiftSummaryModal" type="button"
                        class="text-slate-400 hover:text-slate-700 text-xl font-bold px-2 py-1 focus:outline-none cursor-pointer">×</button>
                </div>

                <!-- Scrollable Content -->
                <div class="overflow-y-auto space-y-4 pr-1 flex-1 text-xs">
                    <!-- KPI Cards Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                            <span class="text-[10px] uppercase font-bold text-slate-500 block">Billed Turnover</span>
                            <span class="text-base font-extrabold font-mono text-slate-900 block mt-0.5">
                                ₹{{ number_format($currentShiftDetails['totals']['billed_turnover_paise'] / 100, 2) }}
                            </span>
                            <span class="text-[10px] text-slate-400 block">{{ $currentShiftDetails['shift']['total_bills'] }} paid bills</span>
                        </div>

                        <div class="p-3 rounded-2xl bg-emerald-50/70 border border-emerald-200">
                            <span class="text-[10px] uppercase font-bold text-emerald-800 block">Expected Cash</span>
                            <span class="text-base font-extrabold font-mono text-emerald-900 block mt-0.5">
                                ₹{{ number_format($currentShiftDetails['drawer']['expected_cash_paise'] / 100, 2) }}
                            </span>
                            <span class="text-[10px] text-emerald-700 block">Float + Cash Sales</span>
                        </div>

                        <div class="p-3 rounded-2xl bg-blue-50/70 border border-blue-200">
                            <span class="text-[10px] uppercase font-bold text-blue-800 block">UPI / Digital</span>
                            <span class="text-base font-extrabold font-mono text-blue-900 block mt-0.5">
                                ₹{{ number_format($currentShiftDetails['tenders']['digital']['amount_paise'] / 100, 2) }}
                            </span>
                            <span class="text-[10px] text-blue-700 block">{{ $currentShiftDetails['tenders']['digital']['count'] }} transactions</span>
                        </div>

                        <div class="p-3 rounded-2xl bg-amber-50/70 border border-amber-200">
                            <span class="text-[10px] uppercase font-bold text-amber-800 block">Prepaid Wallet</span>
                            <span class="text-base font-extrabold font-mono text-amber-900 block mt-0.5">
                                ₹{{ number_format($currentShiftDetails['tenders']['prepaid']['amount_paise'] / 100, 2) }}
                            </span>
                            <span class="text-[10px] text-amber-700 block">{{ $currentShiftDetails['tenders']['prepaid']['count'] }} debits</span>
                        </div>
                    </div>

                    <!-- Drawer Lifecycle & Reconciliation Box -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Physical Cash Drawer Lifecycle</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $currentShiftDetails['drawer']['discrepancy_status'] === 'balanced' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300' }}">
                                {{ ucfirst($currentShiftDetails['drawer']['discrepancy_status']) }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs pt-1 border-t border-slate-200/80">
                            <div>
                                <span class="text-[10px] text-slate-500 block">Opening Float:</span>
                                <span class="font-mono font-bold text-slate-800">₹{{ number_format($currentShiftDetails['drawer']['opening_float_paise'] / 100, 2) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 block">+ Cash Sales:</span>
                                <span class="font-mono font-bold text-emerald-700">+₹{{ number_format($currentShiftDetails['drawer']['cash_sales_paise'] / 100, 2) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 block">+ Wallet Cash Top-ups:</span>
                                <span class="font-mono font-bold text-emerald-700">+₹{{ number_format($currentShiftDetails['drawer']['cash_topups_paise'] / 100, 2) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 block">- Cash Drops / Safe:</span>
                                <span class="font-mono font-bold text-rose-700">-₹{{ number_format($currentShiftDetails['drawer']['cash_drops_paise'] / 100, 2) }}</span>
                            </div>
                        </div>
                        @if ($currentShiftDetails['drawer']['closing_cash_counted_paise'] !== null)
                            <div class="pt-2 border-t border-slate-200 flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-600">Counted Cash at Closing:</span>
                                <span class="font-mono font-bold text-slate-900">₹{{ number_format($currentShiftDetails['drawer']['closing_cash_counted_paise'] / 100, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Department Splits & Waiter Tips -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200 space-y-1.5">
                            <span class="text-[10px] uppercase font-bold text-slate-500 block">Contractor Food vs Bar Split</span>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600">Contractor Food Turnover:</span>
                                <span class="font-mono font-bold text-slate-900">₹{{ number_format($currentShiftDetails['contractor_turnover']['food_contractor_paise'] / 100, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600">Club Bar Turnover:</span>
                                <span class="font-mono font-bold text-slate-900">₹{{ number_format($currentShiftDetails['contractor_turnover']['bar_paise'] / 100, 2) }}</span>
                            </div>
                        </div>

                        <div class="p-3 rounded-2xl bg-amber-50/60 border border-amber-200 space-y-1.5">
                            <span class="text-[10px] uppercase font-bold text-amber-800 block">Duty Gratuity &amp; Top-ups</span>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600">Waiter &amp; Bearer Tips:</span>
                                <span class="font-mono font-bold text-amber-900">
                                    ₹{{ number_format($currentShiftDetails['tips']['total_tip_paise'] / 100, 2) }}
                                    <span class="text-[10px] font-normal text-slate-500">({{ $currentShiftDetails['tips']['count'] }})</span>
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600">Member Wallet Recharges:</span>
                                <span class="font-mono font-bold text-slate-900">
                                    ₹{{ number_format($currentShiftDetails['wallet_topups']['total_amount_paise'] / 100, 2) }}
                                    <span class="text-[10px] font-normal text-slate-500">({{ $currentShiftDetails['wallet_topups']['count'] }})</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Itemized Recent Bills in Shift -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800">Recent Shift Bills ({{ $currentShiftDetails['bills']->count() }})</span>
                            <span class="text-[10px] text-slate-400">Chronological activity</span>
                        </div>
                        @if ($currentShiftDetails['bills']->isEmpty())
                            <div class="p-4 rounded-xl bg-slate-50 text-center text-slate-400 text-xs italic border border-slate-200">
                                No bills have been finalized during this shift yet.
                            </div>
                        @else
                            <div class="border border-slate-200 rounded-xl overflow-hidden max-h-48 overflow-y-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100 text-slate-600 font-bold border-b border-slate-200 text-[10px] uppercase">
                                        <tr>
                                            <th class="p-2">Bill #</th>
                                            <th class="p-2">Customer</th>
                                            <th class="p-2">Tender</th>
                                            <th class="p-2 text-right">Net Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-sans">
                                        @foreach ($currentShiftDetails['bills']->take(15) as $b)
                                            <tr class="hover:bg-slate-50">
                                                <td class="p-2 font-mono font-bold text-slate-800">{{ $b->bill_number }}</td>
                                                <td class="p-2 text-slate-700 truncate max-w-[120px]">{{ $b->customer_display_name }}</td>
                                                <td class="p-2">
                                                    @php
                                                        $tType = $b->tender_type?->value ?? ($b->tender_type ?: 'paid');
                                                    @endphp
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $tType === 'cash' ? 'bg-emerald-100 text-emerald-800' : ($tType === 'prepaid' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800') }}">
                                                        {{ $tType }}
                                                    </span>
                                                </td>
                                                <td class="p-2 font-mono font-bold text-right text-slate-900">₹{{ number_format($b->net_amount_paise / 100, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="mt-4 flex items-center justify-between gap-3 pt-3 border-t border-slate-200 shrink-0">
                    <button wire:click="closeShiftSummaryModal" type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold focus:outline-none cursor-pointer">
                        Close [Esc]
                    </button>
                    <button wire:click="printCurrentShiftSummarySlip" type="button"
                        class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-md shadow-slate-900/20 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Print Handover Slip [P]</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- DOCKED CONTEXTUAL BOTTOM STATUS BAR -->
    <!-- ========================================== -->
    <footer
        class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 shadow-lg px-4 sm:px-6 lg:px-8 py-2 print:hidden no-print">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2.5 text-xs font-mono">
            <!-- Left Side: Terminal & Shift Status -->
            <div class="flex items-center gap-2 text-slate-700">
                @if ($activeShift)
                    <span
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-semibold">
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
                        <strong
                            class="font-mono text-slate-800 font-bold">₹{{ number_format($activeShift->calculateExpectedCashPaise() / 100, 2) }}</strong>
                    </span>
                    <button type="button" wire:click="openShiftSummaryModal"
                        class="ml-1 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 text-[11px] font-sans font-semibold transition-colors cursor-pointer"
                        title="View Shift Activity & Summary">
                        <span>📊 Shift Summary</span>
                    </button>
                    <button type="button" @click="showCloseShiftModal = true"
                        class="ml-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-700 border border-slate-300 hover:border-rose-300 text-[11px] font-sans font-semibold transition-colors cursor-pointer"
                        title="Reconcile and close counter shift">
                        <span>Close Shift</span>
                    </button>
                @else
                    <span
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-300 text-[11px] font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <span>SHIFT CLOSED</span>
                    </span>
                    <span class="text-slate-500 text-xs font-sans hidden sm:inline">Enter opening cash above to begin
                        counter billing</span>
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
                    <button type="button" wire:click="$set('errorMessage', null)"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-rose-900 text-white font-mono shadow-2xs">↵ Enter / Esc</kbd>
                        <span>Dismiss</span>
                    </button>
                @elseif (!$activeShift)
                    <!-- Context: Shift Closed -->
                    <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 font-sans">
                        <span>Press</span>
                        <kbd
                            class="px-1.5 py-0.5 rounded bg-white text-slate-900 border border-slate-300 font-mono text-[10px] shadow-2xs font-bold">↵
                            Enter</kbd>
                        <span>to Open Shift</span>
                    </span>
                @elseif ($showReceiptModal)
                    <!-- Context: Receipt Modal -->
                    <span class="text-[11px] text-slate-500 font-sans mr-1 hidden sm:inline">Receipt:</span>
                    <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-slate-800 text-white border border-slate-700 shadow-2xs">P</kbd>
                        <span>Print</span>
                    </button>
                    <button type="button" wire:click="closeReceiptModal"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-300 cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-900 border border-slate-300 shadow-2xs">Enter
                            / Esc</kbd>
                        <span>Next Customer</span>
                    </button>

                @elseif ($showPaymentConfirmModal)
                    <!-- Context: Payment Confirmation Modal -->
                    <span
                        class="text-[11px] text-amber-800 font-sans font-semibold mr-1 hidden sm:inline">Authorization:</span>
                    <button type="button" wire:click="confirmCheckout"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs border border-amber-600 shadow-xs cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-slate-950 border border-amber-600 font-mono shadow-2xs">Enter
                            ↵</kbd>
                        <span>Confirm Settle</span>
                    </button>
                    <button type="button" wire:click="closePaymentConfirmModal"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Cancel</span>
                    </button>

                @elseif ($showHoldTabModal)
                    <!-- Context: Hold Tab Modal -->
                    <span class="text-[11px] text-amber-800 font-sans font-semibold mr-1 hidden sm:inline">Hold Tab:</span>
                    <button type="button" wire:click="holdCurrentCart"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs border border-amber-600 shadow-xs cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-slate-950 border border-amber-600 font-mono shadow-2xs">Enter
                            ↵</kbd>
                        <span>Confirm Hold</span>
                    </button>
                    <button type="button" wire:click="closeHoldTabModal"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Cancel</span>
                    </button>

                @elseif ($showHeldTabsListModal)
                    <!-- Context: Held Tabs Manager Modal -->
                    <span class="text-[11px] text-amber-800 font-sans font-semibold mr-1 hidden sm:inline">Tabs
                        Manager:</span>
                    <button type="button" wire:click="closeHeldTabsListModal"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Close</span>
                    </button>

                @elseif ($showUnsettledWalkoutModal)
                    <!-- Context: Unsettled Walkout Recording Modal -->
                    <span class="text-[11px] text-rose-800 font-sans font-semibold mr-1 hidden sm:inline">Walk-Out Record:</span>
                    <button type="button" wire:click="closeUnsettledWalkoutModal"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Cancel</span>
                    </button>

                @elseif ($showOutstandingDuesModal && !empty($outstandingBillsList))
                    <!-- Context: Outstanding Walk-Out Dues Modal -->
                    <span class="text-[11px] text-rose-800 font-sans font-semibold mr-1 hidden sm:inline">Outstanding Dues:</span>
                    <button type="button" wire:click="closeOutstandingDuesModal"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Close</span>
                    </button>

                @elseif ($showExistingTabPromptModal)
                    <!-- Context: Existing Running Tab Detected Prompt -->
                    <span class="text-[11px] text-amber-800 font-sans font-semibold mr-1 hidden sm:inline">Active Tab:</span>
                    <button type="button" wire:click="resumePromptTab"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs border border-amber-600 shadow-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-950 border border-amber-600 font-mono shadow-2xs">Enter ↵</kbd>
                        <span>Add to Existing Tab</span>
                    </button>
                    <button type="button" wire:click="dismissExistingTabPrompt"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs cursor-pointer">
                        <kbd class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Start Fresh Bill</span>
                    </button>

                @elseif ($showServingModal)
                    <!-- Context: Serving Selection Modal -->
                    <span class="text-[11px] text-amber-800 font-sans font-semibold mr-1 hidden sm:inline">Unit:</span>
                    <span
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-200">
                        <kbd class="px-1 py-0.2 rounded bg-white text-amber-800 border border-slate-300 font-bold">1</kbd>
                        <kbd class="px-1 py-0.2 rounded bg-white text-amber-800 border border-slate-300 font-bold">2</kbd>
                        <kbd class="px-1 py-0.2 rounded bg-white text-amber-800 border border-slate-300 font-bold">3</kbd>
                        <span>Select Serving</span>
                    </span>
                    <span
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-200 hidden sm:inline-flex">
                        <kbd class="px-1 py-0.2 rounded bg-white text-slate-700 border border-slate-300">↑ ↓</kbd>
                        <kbd class="px-1 py-0.2 rounded bg-white text-slate-700 border border-slate-300">Enter</kbd>
                    </span>
                    <button type="button" wire:click="closeServingModal"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Cancel</span>
                    </button>

                @elseif (!empty($itemSearchResults))
                    <!-- Context: Item Autocomplete Active -->
                    <span
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-200">
                        <kbd class="px-1 py-0.2 rounded bg-white text-slate-700 border border-slate-300">↑ ↓</kbd>
                        <span>Navigate</span>
                    </span>
                    <button type="button" wire:click="selectHighlightedItem"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-amber-800 border border-amber-300 shadow-2xs">Enter</kbd>
                        <span>Add to Bill</span>
                    </button>
                    <button type="button" wire:click="$set('itemSearchResults', []); $set('itemSearch', '');"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Close</span>
                    </button>

                @elseif (!empty($customerSearchResults))
                    <!-- Context: Customer Autocomplete Active -->
                    <span
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-200">
                        <kbd class="px-1 py-0.2 rounded bg-white text-slate-700 border border-slate-300">↑ ↓</kbd>
                        <span>Navigate</span>
                    </span>
                    <button type="button" wire:click="selectHighlightedCustomer"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-amber-800 border border-amber-300 shadow-2xs">Enter</kbd>
                        <span>Select Member</span>
                    </button>
                    <button type="button" wire:click="$set('customerSearchResults', [])"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 cursor-pointer">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-slate-700 border border-slate-300 shadow-2xs">Esc</kbd>
                        <span>Close</span>
                    </button>

                @else
                    <!-- Context: Standard Ready Counter Billing State -->
                    <button type="button" @click="$refs.customerSearchInput?.focus()"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 hover:bg-amber-50 hover:border-amber-300 text-slate-700 hover:text-slate-900 border border-slate-200 transition-colors cursor-pointer"
                        title="Find Member (F2)">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-amber-700 font-bold text-[10px] border border-slate-300 shadow-2xs">F2</kbd>
                        <span>Customer</span>
                    </button>

                    <button type="button" @click="$refs.itemSearchInput?.focus()"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 hover:bg-amber-50 hover:border-amber-300 text-slate-700 hover:text-slate-900 border border-slate-200 transition-colors cursor-pointer"
                        title="Search / Scan Item (F3)">
                        <kbd
                            class="px-1.5 py-0.2 rounded bg-white text-amber-700 font-bold text-[10px] border border-slate-300 shadow-2xs">F3</kbd>
                        <span>Item Search</span>
                    </button>

                    @if (!empty($cart))
                        <button type="button"
                            @click="const el = document.querySelector('.cart-qty-input'); el?.focus(); el?.select();"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors cursor-pointer"
                            title="Focus Cart Quantity (F4)">
                            <kbd
                                class="px-1.5 py-0.2 rounded bg-white text-slate-700 font-bold text-[10px] border border-slate-300 shadow-2xs">F4</kbd>
                            <span>Qty</span>
                        </button>

                        @if ($selectedMember || $selectedRoomReservation)
                            <button type="button" wire:click="printCurrentCartTableCheck"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border border-indigo-200 font-bold transition-colors cursor-pointer"
                                title="Print Table Check for Bearer">
                                <span>📄 Check</span>
                            </button>
                            <button type="button" wire:click="openHoldTabModal"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold transition-colors cursor-pointer"
                                title="Hold Tab (F6)">
                                <kbd
                                    class="px-1.5 py-0.2 rounded bg-white text-amber-800 font-bold text-[10px] border border-amber-300 shadow-2xs">F6</kbd>
                                <span>Hold Tab</span>
                            </button>
                        @endif

                        <button type="button" wire:click="initiateCheckout('prepaid')"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold transition-colors cursor-pointer"
                            title="Prepaid Checkout (F8)">
                            <kbd
                                class="px-1.5 py-0.2 rounded bg-white text-amber-800 font-bold text-[10px] border border-amber-300 shadow-2xs">F8</kbd>
                            <span>Prepaid</span>
                        </button>

                        <button type="button" wire:click="initiateCheckout('cash')"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-900 border border-emerald-300 font-bold transition-colors cursor-pointer"
                            title="Cash Checkout (F9)">
                            <kbd
                                class="px-1.5 py-0.2 rounded bg-white text-emerald-800 font-bold text-[10px] border border-emerald-300 shadow-2xs">F9</kbd>
                            <span>Cash</span>
                        </button>

                        <button type="button" wire:click="initiateCheckout('digital')"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-900 border border-blue-300 font-bold transition-colors cursor-pointer"
                            title="Digital Checkout (F10)">
                            <kbd
                                class="px-1.5 py-0.2 rounded bg-white text-blue-800 font-bold text-[10px] border border-blue-300 shadow-2xs">F10</kbd>
                            <span>Digital</span>
                        </button>

                        <button type="button" wire:click="clearCart"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition-colors cursor-pointer"
                            title="Clear Bill">
                            <kbd
                                class="px-1.5 py-0.2 rounded bg-white text-rose-700 font-bold text-[10px] border border-rose-200 shadow-2xs">Esc</kbd>
                            <span>Clear</span>
                        </button>
                    @else
                        <span class="text-slate-400 text-[11px] font-sans hidden md:inline ml-1">Scan barcode or press F3 to add
                            items</span>
                    @endif
                @endif
            </div>
        </div>
    </footer>

</div>