<x-filament-panels::page>
    <div class="space-y-6">

        <!-- Top Header & Search Bar -->
        <div class="p-6 bg-gradient-to-br from-slate-900 via-slate-800 to-amber-950 text-white border border-amber-500/20 rounded-2xl shadow-lg relative overflow-hidden print:hidden no-print">
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-500/30 text-amber-400 flex items-center justify-center font-bold text-2xl shadow-inner">
                        📖
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-white flex items-center gap-2">
                            <span>Malabar Club Operations Help &amp; Manual</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                Official Standard SOP
                            </span>
                        </h1>
                        <p class="text-xs text-slate-300 mt-0.5">
                            Comprehensive operational guide for Cashiers, Front Desk, Accounts, Store Custodians, and Management.
                        </p>
                    </div>
                </div>

                <!-- Print Reference Button -->
                <div class="flex items-center gap-2 self-start md:self-auto">
                    <button
                        type="button"
                        onclick="window.print()"
                        class="px-3.5 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 text-amber-300 border border-slate-700 text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                    >
                        <span>🖨️</span>
                        <span>Print Desk Reference</span>
                    </button>
                </div>
            </div>

            <!-- In-Page Live Filter / Search Input -->
            <div class="relative mt-5">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.250ms="searchQuery"
                    placeholder="Search operations help: type 'shortcuts', 'F3', 'recharge', 'accommodation deck', 'settlement', 'expense head', 'zero credit'..."
                    class="w-full pl-10 pr-10 py-2.5 bg-slate-950/70 hover:bg-slate-950/90 focus:bg-slate-950 text-white placeholder-slate-400 text-xs rounded-xl border border-slate-700 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all outline-hidden shadow-inner"
                />
                @if($searchQuery)
                    <button
                        type="button"
                        wire:click="$set('searchQuery', '')"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white text-xs cursor-pointer"
                    >
                        ✕
                    </button>
                @endif
            </div>
        </div>

        <!-- Role-Tailored Tab Bar Navigation -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-slate-200 print:hidden no-print">
            <button
                type="button"
                wire:click="setTab('cashier')"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'cashier' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200' }}"
            >
                <span>⚡</span>
                <span>Counter &amp; POS</span>
            </button>

            <button
                type="button"
                wire:click="setTab('reception')"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'reception' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200' }}"
            >
                <span>🏨</span>
                <span>Front Desk &amp; Facilities</span>
            </button>

            <button
                type="button"
                wire:click="setTab('finance')"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'finance' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200' }}"
            >
                <span>💰</span>
                <span>Finance &amp; Accounts</span>
            </button>

            <button
                type="button"
                wire:click="setTab('inventory')"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'inventory' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200' }}"
            >
                <span>📦</span>
                <span>Procurement &amp; Stores</span>
            </button>

            <button
                type="button"
                wire:click="setTab('executive')"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'executive' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200' }}"
            >
                <span>🛡️</span>
                <span>Executive &amp; Admin</span>
            </button>

            <button
                type="button"
                wire:click="setTab('bylaws')"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer {{ $activeTab === 'bylaws' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200' }}"
            >
                <span>📜</span>
                <span>Club Bylaws</span>
            </button>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 1: COUNTER CASHIER & POS OPERATIONS GUIDE -->
        <!-- ========================================================================= -->
        @if($activeTab === 'cashier')
            <div class="space-y-6">
                <!-- Shift Lifecycle Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">⏱️</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. Cashier Shift Lifecycle (Daily Opening &amp; Closing)</h2>
                            <p class="text-xs text-slate-500">Every counter transaction is audited under an active, verified cashier shift.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <span class="font-bold text-amber-700 font-mono text-[11px]">STEP 01</span>
                            <h3 class="font-bold text-slate-900 text-sm">Open Shift</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Enter your physical <strong>Opening Cash Float</strong> (e.g. ₹2,000.00). Press <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-300 font-mono text-[10px] shadow-2xs font-bold">↵ Enter</kbd> to unlock the billing workspace.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <span class="font-bold text-blue-700 font-mono text-[11px]">STEP 02</span>
                            <h3 class="font-bold text-slate-900 text-sm">High-Speed Billing</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Use keyboard shortcuts to scan items, select serving sizes, and tender bills via <strong>Prepaid Card</strong>, <strong>Cash</strong>, or <strong>Digital UPI</strong>.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <span class="font-bold text-emerald-700 font-mono text-[11px]">STEP 03</span>
                            <h3 class="font-bold text-slate-900 text-sm">Monitor Drawer</h3>
                            <p class="text-slate-600 leading-relaxed">
                                The docked bottom status bar computes real-time <strong>Expected Cash</strong>: <span class="font-mono font-semibold text-slate-800">Float + Cash Sales + Cash Recharges</span>. Prepaid redemptions do not affect drawer cash.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <span class="font-bold text-rose-700 font-mono text-[11px]">STEP 04</span>
                            <h3 class="font-bold text-slate-900 text-sm">Count &amp; Close Shift</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Click <strong>Close Shift</strong> in the bottom bar. Physically count and enter your <strong>Closing Cash</strong>. Any drawer discrepancy is automatically audited for management review.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Keyboard Shortcut Cheat Sheet Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">⌨️</span>
                            <div>
                                <h2 class="text-base font-bold text-slate-900">2. High-Speed Counter Keyboard Shortcuts</h2>
                                <p class="text-xs text-slate-500">Operate the counter without reaching for the mouse.</p>
                            </div>
                        </div>
                        <span class="text-[11px] font-mono font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg">
                            Standard Non-Conflicting Function Keys
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 text-xs">
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                            <kbd class="px-2.5 py-1 rounded-lg bg-slate-900 text-amber-300 font-mono font-bold text-xs border border-slate-700 shadow-xs shrink-0">F2</kbd>
                            <div>
                                <h3 class="font-bold text-slate-900">Find Member / Tap Card</h3>
                                <p class="text-slate-600 mt-0.5">Focuses member search. Tap member's RFID card on reader, or type name, membership #, or phone.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                            <kbd class="px-2.5 py-1 rounded-lg bg-slate-900 text-amber-300 font-mono font-bold text-xs border border-slate-700 shadow-xs shrink-0">F3</kbd>
                            <div>
                                <h3 class="font-bold text-slate-900">Item Search / Barcode Scan</h3>
                                <p class="text-slate-600 mt-0.5">Focuses item search input. Scan physical barcode with handheld scanner, or type item name.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                            <kbd class="px-2.5 py-1 rounded-lg bg-slate-900 text-amber-300 font-mono font-bold text-xs border border-slate-700 shadow-xs shrink-0">F4</kbd>
                            <div>
                                <h3 class="font-bold text-slate-900">Cart &amp; Quantity Edit</h3>
                                <p class="text-slate-600 mt-0.5">Jumps focus directly to cart table. Use arrow keys <kbd class="px-1 py-0.5 rounded bg-white border border-slate-300 font-mono text-[9px]">↑</kbd> <kbd class="px-1 py-0.5 rounded bg-white border border-slate-300 font-mono text-[9px]">↓</kbd> and type numbers to change quantity.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-amber-500/10 border border-amber-500/30 rounded-xl flex items-start gap-3">
                            <kbd class="px-2.5 py-1 rounded-lg bg-amber-600 text-slate-950 font-mono font-bold text-xs border border-amber-700 shadow-xs shrink-0">F8</kbd>
                            <div>
                                <h3 class="font-bold text-amber-950">Member Prepaid Tender</h3>
                                <p class="text-amber-900 mt-0.5">Debits customer's verified prepaid wallet account. Enforces atomic locking and strict zero-credit rule.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-emerald-500/10 border border-emerald-500/30 rounded-xl flex items-start gap-3">
                            <kbd class="px-2.5 py-1 rounded-lg bg-emerald-700 text-white font-mono font-bold text-xs border border-emerald-800 shadow-xs shrink-0">F9</kbd>
                            <div>
                                <h3 class="font-bold text-emerald-950">Cash Payment Tender</h3>
                                <p class="text-emerald-900 mt-0.5">Tenders invoice via physical drawer cash. Automatically calculates customer change and adds to till float.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-blue-500/10 border border-blue-500/30 rounded-xl flex items-start gap-3">
                            <kbd class="px-2.5 py-1 rounded-lg bg-blue-700 text-white font-mono font-bold text-xs border border-blue-800 shadow-xs shrink-0">F10</kbd>
                            <div>
                                <h3 class="font-bold text-blue-950">Digital UPI / Card POS</h3>
                                <p class="text-blue-900 mt-0.5">Records electronic digital collection via card EDC machine or dynamic clubhouse UPI QR code.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                            <kbd class="px-2 py-1 rounded-lg bg-slate-200 text-slate-800 font-mono font-bold text-xs border border-slate-300 shadow-xs shrink-0">1, 2, 3</kbd>
                            <div>
                                <h3 class="font-bold text-slate-900">Serving Size Selector</h3>
                                <p class="text-slate-600 mt-0.5">When adding spirits, press numeric keycaps for <strong>1: 30ml Peg</strong>, <strong>2: 60ml Peg</strong>, or <strong>3: Full Bottle (750ml)</strong>.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                            <kbd class="px-2.5 py-1 rounded-lg bg-slate-200 text-slate-800 font-mono font-bold text-xs border border-slate-300 shadow-xs shrink-0">Esc</kbd>
                            <div>
                                <h3 class="font-bold text-slate-900">Dismiss Modal / Close Dialog</h3>
                                <p class="text-slate-600 mt-0.5">Instantly closes any active pop-up, error dialog, or receipt preview without submitting.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                            <kbd class="px-2 py-1 rounded-lg bg-slate-200 text-slate-800 font-mono font-bold text-xs border border-slate-300 shadow-xs shrink-0">↵ Enter</kbd>
                            <div>
                                <h3 class="font-bold text-slate-900">Confirm Action / Acknowledge</h3>
                                <p class="text-slate-600 mt-0.5">Submits payment, confirms active dialog, or dismisses error alert modal.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Special Workflows Card -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Top-Up Workflow -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">💳</span>
                            <h3 class="font-bold text-slate-900 text-sm">How to Recharge Member Wallet at Counter</h3>
                        </div>
                        <ol class="list-decimal list-inside text-xs text-slate-600 space-y-1.5 leading-relaxed">
                            <li>Tap member's RFID card on reader or press <kbd class="px-1 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[10px]">F2</kbd> and search their name.</li>
                            <li>In the Member Card header, click the <strong>⚡ Quick Top-Up</strong> button.</li>
                            <li>Select quick preset (e.g. ₹500, ₹1,000, ₹2,000) or type the exact recharge amount.</li>
                            <li>Select Payment Mode (<strong>Cash</strong> or <strong>UPI / Card POS</strong>).</li>
                            <li>Click <strong>Confirm Recharge</strong>. Balance updates immediately on the server ledger and prints an official top-up receipt.</li>
                        </ol>
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-900">
                            <strong>Policy Note:</strong> Recharges increase member prepaid liabilities. They do not count as restaurant food revenue until food is actually ordered.
                        </div>
                    </div>

                    <!-- Guest Entry Slip Workflow -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🎟️</span>
                            <h3 class="font-bold text-slate-900 text-sm">How to Issue Guest Day Passes</h3>
                        </div>
                        <ol class="list-decimal list-inside text-xs text-slate-600 space-y-1.5 leading-relaxed">
                            <li>On the billing screen, switch customer type from Member to <strong>Visiting Guest</strong>.</li>
                            <li>Select the registered Guest (must be sponsored by an active member or affiliated club).</li>
                            <li>Add the applicable Guest Pass tariff: <strong>Bar &amp; Card Room Day Pass</strong> or <strong>Gym/Sports Guest Pass</strong>.</li>
                            <li>Tender the fee via Cash (<kbd class="px-1 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[10px]">F9</kbd>) or UPI (<kbd class="px-1 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[10px]">F10</kbd>).</li>
                            <li>Issue the printed thermal receipt containing the guest permit barcode for turnstile verification.</li>
                        </ol>
                        <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-[11px] text-rose-900">
                            <strong>Strict Rule:</strong> Unpaid draft entries never generate gate access. Zero-rupee or complimentary guest passes are prohibited by club bylaws.
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 2: FRONT DESK & ACCOMMODATION DECK GUIDE -->
        <!-- ========================================================================= -->
        @if($activeTab === 'reception')
            <div class="space-y-6">
                <!-- Accommodation Deck Operations -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">🏨</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. Accommodation Deck Operations (Rooms 101–105)</h2>
                            <p class="text-xs text-slate-500">Live graphical board for front-desk guest room management, bookings, and housekeeping.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3.5 text-xs">
                        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <h3 class="font-bold text-emerald-950">Vacant Ready</h3>
                            </div>
                            <p class="text-emerald-900 text-[11px]">Room is cleaned, inspected, and ready for immediate walk-in check-in or booking.</p>
                        </div>

                        <div class="p-3.5 bg-teal-50 border border-teal-200 rounded-xl space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-teal-600"></span>
                                <h3 class="font-bold text-teal-950">Occupied</h3>
                            </div>
                            <p class="text-teal-900 text-[11px]">Guest is currently in-house. Checkout date and guest folio details are displayed on the card.</p>
                        </div>

                        <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-xl space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                <h3 class="font-bold text-blue-950">Reserved</h3>
                            </div>
                            <p class="text-blue-900 text-[11px]">Confirmed advance reservation. Fully protected by prepaid deposit interval locking.</p>
                        </div>

                        <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                                <h3 class="font-bold text-amber-950">Needs Cleaning</h3>
                            </div>
                            <p class="text-amber-900 text-[11px]">Guest has checked out. Housekeeping is in progress. Click <strong>Mark Ready</strong> once room is prepared.</p>
                        </div>

                        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                                <h3 class="font-bold text-rose-950">Maintenance</h3>
                            </div>
                            <p class="text-rose-900 text-[11px]">Room taken out of service for electrical, plumbing, or AC repair works.</p>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-xs">
                        <h4 class="font-bold text-slate-900">Standard Check-In &amp; Check-Out Workflow</h4>
                        <ul class="list-disc list-inside text-slate-600 space-y-1 leading-relaxed">
                            <li><strong>New Reservation:</strong> Click <strong>+ New Room Reservation</strong>. Select Room, Guest, Check-in / Check-out dates, and collect advance tariff via Cash, UPI, or Member Wallet.</li>
                            <li><strong>Check-In:</strong> On arrival date, click the reserved room card and press <strong>Check-In</strong>. Room status flips to Occupied.</li>
                            <li><strong>Check-Out:</strong> Click Occupied room card and select <strong>Check-Out</strong>. Room automatically flips to <em>Needs Cleaning</em>.</li>
                            <li><strong>Housekeeping Turnaround:</strong> Once housekeeping staff sanitizes the room and places fresh linen, click <strong>Mark Cleaned &amp; Ready</strong> to restore <em>Vacant Ready</em>.</li>
                        </ul>
                    </div>
                </div>

                <!-- Badminton Court Schedule Matrix -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">🏸</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">2. Badminton Court Daily Schedule (6:00 AM – 10:00 PM)</h2>
                            <p class="text-xs text-slate-500">Hourly slot bookings, subscription plan detection, and court capacity management.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900">Hourly Slots</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Court time is strictly divided into 60-minute slots. Concurrent overlapping bookings for the same slot are blocked by pessimistic database interval locks.
                            </p>
                        </div>

                        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-emerald-950">Active Sports Subscribers</h3>
                            <p class="text-emerald-900 leading-relaxed">
                                When a member has an active Monthly or Annual Badminton Plan, the system automatically detects their entitlement and <strong>waives the hourly fee (₹0.00)</strong>.
                            </p>
                        </div>

                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-amber-950">Hourly Pay-Per-Play</h3>
                            <p class="text-amber-900 leading-relaxed">
                                For members or guests without an active subscription, the hourly court tariff is debited from their prepaid balance or collected via cash/UPI before slot confirmation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 3: FINANCE, ACCOUNTS & CONTRACTOR SETTLEMENTS GUIDE -->
        <!-- ========================================================================= -->
        @if($activeTab === 'finance')
            <div class="space-y-6">
                <!-- Chart of Accounts Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">⚖️</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. General Ledger &amp; 5-Digit Numbering Standard</h2>
                            <p class="text-xs text-slate-500">How accounts are structured in Malabar Club's double-entry accounting engine.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 text-xs">
                        <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-blue-700">1000s SERIES</span>
                            <h3 class="font-bold text-blue-950">Assets</h3>
                            <p class="text-blue-900 text-[11px]">1010 Cash in Hand, 1020 Bank Clearing, 1030 Bar Inventory Stores.</p>
                        </div>

                        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-rose-700">2000s SERIES</span>
                            <h3 class="font-bold text-rose-950">Liabilities</h3>
                            <p class="text-rose-900 text-[11px]">2010 Prepaid Wallet Advances, 2020 Contractor Payable, 2030 GST Liability.</p>
                        </div>

                        <div class="p-3.5 bg-purple-50 border border-purple-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-purple-700">3000s SERIES</span>
                            <h3 class="font-bold text-purple-950">Capital &amp; Equity</h3>
                            <p class="text-purple-900 text-[11px]">3010 Club Capital &amp; General Reserves, Retained Operational Surplus.</p>
                        </div>

                        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-emerald-700">4000s SERIES</span>
                            <h3 class="font-bold text-emerald-950">Revenue / Income</h3>
                            <p class="text-emerald-900 text-[11px]">4010 Guest Passes, 4020 Bar Sales, 4030 Rooms, 4040 Sports Subscriptions.</p>
                        </div>

                        <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl space-y-1">
                            <span class="font-mono font-bold text-amber-700">5000s SERIES</span>
                            <h3 class="font-bold text-amber-950">Expenses</h3>
                            <p class="text-amber-900 text-[11px]">5010 COGS Liquor, 5020 Food Contractor, 5100s Utilities, 5200s Maintenance, 5400s Admin.</p>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-xs">
                        <h4 class="font-bold text-slate-900">Protected System Accounts vs. Custom Expense Heads</h4>
                        <p class="text-slate-600 leading-relaxed">
                            Accounts marked with the <span class="text-rose-600 font-bold">🔒 System Lock</span> icon (e.g. 1010, 1020, 1030, 2010, 2020, 2030) are automated system accounts required for software operations; their codes cannot be changed or deleted. Custom expense heads (e.g. 5110 KSEB, 5120 Diesel, 5220 Court AMC) can be created, edited, and classified freely under the <strong>Chart of Accounts</strong> screen or on the fly using the <kbd class="px-1 py-0.5 rounded bg-white border border-slate-300 font-mono text-[10px]">+</kbd> button on Expense Vouchers.
                        </p>
                    </div>
                </div>

                <!-- Daily Food Contractor Settlement Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">🍲</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">2. External Food Contractor Pass-Through &amp; Daily Settlement</h2>
                            <p class="text-xs text-slate-500">Dining operations are contracted out. All food collections are reconciled and settled next-day.</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs leading-relaxed text-slate-600">
                        <p>
                            1. <strong>Automated Daily Compilation:</strong> Every morning, the system compiles the preceding business day's food billing lines (Cash, UPI, and Prepaid Wallet redemptions) into an unconfirmed Settlement Batch under <em>Procurement &amp; Food Contractor &rarr; Contractor Settlements</em>.
                        </p>
                        <p>
                            2. <strong>Management Audit &amp; Approval:</strong> The General Manager or Club Accountant reviews the batch turnover, refunds, and net payable before clicking <strong>Approve Settlement</strong>.
                        </p>
                        <p>
                            3. <strong>Bank Disbursement:</strong> Click <strong>Record Payment</strong>. Select the disbursing Club Bank Account (e.g. Canara Bank Current A/c), payment mode (NEFT/RTGS, Cheque), and enter the bank UTR transaction number.
                        </p>
                        <p>
                            4. <strong>Automated Double-Entry Posting:</strong> Confirming disbursement debits Contractor Payable Account (2020) and credits Bank Account (1020), synchronizing an official payment voucher into Tally Prime.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 4: PROCUREMENT, INVENTORY & STORES GUIDE -->
        <!-- ========================================================================= -->
        @if($activeTab === 'inventory')
            <div class="space-y-6">
                <!-- Inward Consignments Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">📦</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. Inward Stock Purchases &amp; Consignment Receipts</h2>
                            <p class="text-xs text-slate-500">Record deliveries from KSBC wholesale depots and commercial distributors.</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs leading-relaxed text-slate-600">
                        <p>
                            1. Navigate to <strong>Procurement &amp; Food Contractor &rarr; Purchase Receipts</strong> and click <strong>Create Purchase Receipt</strong>.
                        </p>
                        <p>
                            2. <strong>Supplier Selection:</strong> Select the authorized distributor (e.g. <em>KSBC-FL4 Wholesale Depot</em>). If dealing with a new supplier, click the <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 font-mono text-[10px] font-bold">+</kbd> button in the dropdown to register the vendor with their 15-digit GSTIN on the fly.
                        </p>
                        <p>
                            3. <strong>Invoice Details:</strong> Enter the delivery challan or invoice number, invoice date, and consignment notes.
                        </p>
                        <p>
                            4. <strong>Item Repeater Lines:</strong> Add delivered items. For bar liquor, enter base quantities in milliliters (ml) — e.g. 10 bottles of 750ml = 7,500 ml. Enter unit cost in Rupees.
                        </p>
                        <p>
                            5. <strong>Automated Costing &amp; Ledger Posting:</strong> Clicking <strong>Create</strong> increments physical stock on hand, recalculates the item's <em>Moving Weighted-Average Cost (Paise)</em>, and automatically posts the financial journal: <span class="font-mono font-semibold text-slate-900">Debit 1030 (Inventory Stores) / Credit 1020 (Bank Clearing)</span>.
                        </p>
                    </div>
                </div>

                <!-- Stock Consumption Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">🍸</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">2. Bar Liquor Dispensing &amp; Milliliter (ml) Accuracy</h2>
                            <p class="text-xs text-slate-500">Exact liquor inventory tracking prevents unaccounted dispensing losses.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900">30ml Small Peg</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Dispensing a 30ml peg deducts exactly 30 units from the central ml stock on hand, logging consumption cost to Account 5010 (Liquor COGS).
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900">60ml Large Peg</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Dispensing a 60ml peg deducts exactly 60 units from stock. Bar stock reports display both ml volume and bottle equivalents (750ml).
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900">Full Bottle (750ml)</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Sealed bottle sales deduct 750ml from inventory stores. Barcode scanning instantly detects bottle size variants.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 5: EXECUTIVE GOVERNANCE & ADMIN GUIDE -->
        <!-- ========================================================================= -->
        @if($activeTab === 'executive')
            <div class="space-y-6">
                <!-- Staff Onboarding Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">🛡️</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">1. Staff User Management &amp; Role-Based Access Control (RBAC)</h2>
                            <p class="text-xs text-slate-500">Administered under System &amp; Access Control &rarr; Staff &amp; User Access.</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs leading-relaxed text-slate-600">
                        <p>
                            1. <strong>Onboarding Staff:</strong> Click <strong>Register New Staff User</strong>. Enter Full Name, Staff ID (e.g. EMP-104), Designation, Email, Mobile Phone, and assign a temporary password (min 8 characters).
                        </p>
                        <p>
                            2. <strong>Assigning Roles:</strong> Select one or more preset roles:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs">
                            <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg"><strong>Admin:</strong> Full system, user management, and settings.</div>
                            <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg"><strong>Manager:</strong> Operations, refunds, tariffs, and stock.</div>
                            <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg"><strong>Accountant:</strong> Ledgers, settlements, and P&amp;L reports.</div>
                            <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg"><strong>Auditor:</strong> Read-only financial audit registers.</div>
                            <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg"><strong>Reception:</strong> Accommodation Deck, courts, and KYC.</div>
                            <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg"><strong>Cashier:</strong> Counter POS, wallet recharges, and drawer shifts.</div>
                        </div>
                        <p>
                            3. <strong>Immediate Account Deactivation:</strong> When an employee leaves, toggle their status to <strong>Deactivated</strong>. This immediately cuts off login access to both back-office and POS terminal without deleting historical bills or shifts.
                        </p>
                        <p>
                            4. <strong>Audit Safeguards:</strong> Staff users with historical database records (shifts, bills, purchase receipts, or journal vouchers) cannot be hard-deleted to preserve legal audit integrity.
                        </p>
                    </div>
                </div>

                <!-- Executive Reporting Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">📊</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">2. Executive Reporting Hub (8 Core Statutory Reports)</h2>
                            <p class="text-xs text-slate-500">Accessible under Finance &amp; Audit Ledger &rarr; Executive Reports.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-slate-600">
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong>1. Sales &amp; Collections:</strong> Segregates external cash/UPI inflows from prepaid card redemptions.
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong>2. Income &amp; Expenditure:</strong> Official statement showing operating surplus or deficit.
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong>3. Till Drawer Reconciliation:</strong> Shift-by-shift expected cash vs physically counted cash variances.
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong>4. Food Contractor Audit:</strong> Daily turnover, post-settlement adjustments, and bank payout references.
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong>5. Bar Stock &amp; Consumption:</strong> Weighted-average stock valuation, sales COGS, and bottle reserves.
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong>6. Sports Plan Income:</strong> Gym and badminton subscription revenues and daily court utilization.
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong>7. Zero-Credit &amp; Payables Audit:</strong> Confirms 100% zero-credit compliance (0 member bad debts).
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <strong>8. Departmental P&amp;L:</strong> Segmented profitability for Bar, Rooms, Restaurant, and Sports.
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TAB 6: CLUB BYLAWS & SYSTEM INTEGRITY MANUAL -->
        <!-- ========================================================================= -->
        @if($activeTab === 'bylaws')
            <div class="space-y-6">
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <span class="text-xl">📜</span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Malabar Club Operational Bylaws &amp; System Rules</h2>
                            <p class="text-xs text-slate-500">Approved club policies codified into the core transactional engine.</p>
                        </div>
                    </div>

                    <div class="space-y-4 text-xs leading-relaxed text-slate-600">
                        <div class="p-4 bg-amber-50/50 border border-amber-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-amber-950 text-sm">Rule 1: Strict Zero-Credit Policy (No Overdrafts)</h3>
                            <p class="text-amber-900">
                                No member, dependent, visiting guest, or affiliated reciprocal club member may receive goods, liquor, food, sports access, or room stays on credit or with a negative account balance. No role, including General Manager and Administrator, can override this policy through ordinary billing. Service fulfillment strictly requires confirmed payment or sufficient verified prepaid funds.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900 text-sm">Rule 2: Centralized Server Ledger Ownership</h3>
                            <p>
                                A member's RFID contactless smartcard does not store money. It stores an encrypted, unique identifier that links to the club's central database ledger. Offline prepaid spending is strictly disabled to prevent untraceable negative balances. Monetary values are calculated as exact integers in Indian Paise.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900 text-sm">Rule 3: Immutable Financial Records &amp; Linked Reversals</h3>
                            <p>
                                Posted tax invoices, stock movements, wallet recharges, and general ledger journals cannot be updated, edited, or deleted under any circumstance. Correcting a mistake requires an authorized, linked refund, credit note, or reversing journal. Database triggers permanently enforce append-only financial history.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900 text-sm">Rule 4: Guest Entry &amp; Sponsoring Eligibility</h3>
                            <p>
                                Guest entry for the bar and card room requires formal registration, an eligible sponsoring club member or affiliated reciprocal member, and the applicable paid day pass. Unpaid draft entries never activate gate access.
                            </p>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <h3 class="font-bold text-slate-900 text-sm">Rule 5: Fiscal Period Closing Locks</h3>
                            <p>
                                Audited financial periods are locked by the Club Accountant. Once closed, the system strictly forbids posting backdated transactions into that date range to protect statutory compliance.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- PRINT-ONLY SINGLE-PAGE CASHIER CHEAT SHEET (DECISION D47) -->
        <!-- ========================================================================= -->
        <div class="hidden print:block p-8 bg-white text-slate-900 space-y-6">
            <div class="border-b-2 border-slate-900 pb-4 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black uppercase tracking-tight">Malabar Club</h1>
                    <p class="text-sm font-bold text-slate-700">Counter POS Keyboard Shortcuts &amp; Operational SOP</p>
                </div>
                <div class="text-right text-xs">
                    <p class="font-bold">Laminated Desk Reference</p>
                    <p class="text-slate-500">Palakkad, Kerala</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-xs font-mono">
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F2] MEMBER / CARD TAP</p>
                    <p class="text-slate-700 font-sans">Tap RFID smartcard or type member name / phone.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F3] ITEM / BARCODE</p>
                    <p class="text-slate-700 font-sans">Scan barcode or autocomplete item name.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F4] CART / QUANTITY</p>
                    <p class="text-slate-700 font-sans">Navigate rows with arrows; edit quantities.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F8] PREPAID CARD</p>
                    <p class="text-slate-700 font-sans">Debit member wallet balance (No credit permitted).</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F9] CASH PAYMENT</p>
                    <p class="text-slate-700 font-sans">Settle via physical drawer cash &amp; give change.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[F10] DIGITAL UPI / POS</p>
                    <p class="text-slate-700 font-sans">Settle via card swipe terminal or club UPI QR.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[1, 2, 3] SERVING SIZE</p>
                    <p class="text-slate-700 font-sans">Select 1: 30ml Small, 2: 60ml Large, 3: Bottle.</p>
                </div>
                <div class="border border-slate-400 p-3 rounded space-y-1">
                    <p class="font-bold text-sm">[Esc] DISMISS / CANCEL</p>
                    <p class="text-slate-700 font-sans">Close active modal or dismiss alert dialog.</p>
                </div>
            </div>

            <div class="border-t border-slate-400 pt-3 text-[11px] text-slate-700 space-y-1">
                <p><strong>STRICT CLUB MANDATE:</strong> Zero credit is permitted for any member or guest. Transactions cannot proceed with insufficient funds.</p>
                <p><strong>DRAWER RECONCILIATION:</strong> Expected Cash = Opening Cash Float + Cash Sales + Cash Wallet Recharges. Prepaid card redemptions do not affect drawer cash.</p>
            </div>
        </div>

    </div>
</x-filament-panels::page>

