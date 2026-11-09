<div class="space-y-6">
    <!-- Welcome Header & Member Status Pill -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-900 to-slate-800 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-yellow-400 p-0.5 shadow-md shadow-amber-500/20 shrink-0">
                <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center font-bold text-amber-400 text-lg">
                    {{ substr($member->name, 0, 1) }}
                </div>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-extrabold text-white tracking-tight">{{ $member->name }}</h2>
                    @php
                        $statusVal = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
                        $statusBadgeColor = match ($statusVal) {
                            'active' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                            'suspended' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                            'resigned' => 'bg-slate-500/10 text-slate-400 border-slate-500/30',
                            default => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                        };
                        $statusDotColor = match ($statusVal) {
                            'active' => 'bg-emerald-400',
                            'suspended' => 'bg-rose-400',
                            'resigned' => 'bg-slate-400',
                            default => 'bg-amber-400',
                        };
                    @endphp
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold tracking-wider uppercase {{ $statusBadgeColor }} border">
                        <span class="w-1.5 h-1.5 rounded-full {{ $statusDotColor }} inline-block"></span> {{ ucfirst($statusVal) }}
                    </span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-400 mt-0.5 font-medium">
                    <span class="font-mono text-amber-400 font-bold">{{ $member->membership_number }}</span>
                    <span>&bull;</span>
                    <span>{{ $member->category?->name ?? 'Ordinary Member' }}</span>
                    <span>&bull;</span>
                    <span class="text-slate-500">Member since {{ $member->joined_at ? $member->joined_at->format('M Y') : '—' }}</span>
                </div>
            </div>
        </div>

        <!-- Quick Balance Display Header -->
        <div class="text-right pl-4 sm:border-l border-slate-800">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Available Wallet Balance</span>
            <span class="text-2xl font-extrabold font-mono text-emerald-400 tracking-tight">
                ₹{{ number_format($balanceRupees, 2) }}
            </span>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- 1. Wallet Balance -->
        <div wire:click="setTab('wallet')" class="bg-slate-900/90 border border-slate-800/90 hover:border-amber-500/40 rounded-2xl p-4 transition-all cursor-pointer group shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Prepaid Wallet</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-sm">₹</span>
            </div>
            <div class="text-xl font-extrabold font-mono text-white group-hover:text-amber-400 transition-colors">
                ₹{{ number_format($balanceRupees, 2) }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                <span>Backed by Central Ledger</span>
                <span class="text-amber-400 font-bold group-hover:translate-x-0.5 transition-transform">&rarr;</span>
            </div>
        </div>

        <!-- 2. Active Sports Plans -->
        <div wire:click="setTab('plans')" class="bg-slate-900/90 border border-slate-800/90 hover:border-amber-500/40 rounded-2xl p-4 transition-all cursor-pointer group shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sports &amp; Gym Plans</span>
                <span class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center text-sm">🏸</span>
            </div>
            <div class="text-xl font-extrabold text-white group-hover:text-amber-400 transition-colors">
                {{ $activeSubscriptions->count() }} Active
            </div>
            <div class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                <span>Badminton &amp; Health Club</span>
                <span class="text-amber-400 font-bold group-hover:translate-x-0.5 transition-transform">&rarr;</span>
            </div>
        </div>

        <!-- 3. Room & Court Bookings -->
        <div wire:click="setTab('bookings')" class="bg-slate-900/90 border border-slate-800/90 hover:border-amber-500/40 rounded-2xl p-4 transition-all cursor-pointer group shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bookings &amp; Stays</span>
                <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-sm">🏨</span>
            </div>
            <div class="text-xl font-extrabold text-white group-hover:text-amber-400 transition-colors">
                {{ $upcomingCourtBookings->count() + $roomReservations->where('status', 'reserved')->count() }} Upcoming
            </div>
            <div class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                <span>Guest Rooms &amp; Badminton</span>
                <span class="text-amber-400 font-bold group-hover:translate-x-0.5 transition-transform">&rarr;</span>
            </div>
        </div>
    </div>

    <!-- Navigation Tab Bar -->
    <div class="bg-slate-900/90 border border-slate-800/90 rounded-2xl p-1.5 flex items-center overflow-x-auto gap-1 text-xs font-bold scrollbar-none shadow-md">
        <button wire:click="setTab('card')" type="button"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer {{ $activeTab === 'card' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span>🪪</span> Digital Card
        </button>
        <button wire:click="setTab('wallet')" type="button"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer {{ $activeTab === 'wallet' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span>💰</span> Wallet &amp; Ledger
        </button>
        <button wire:click="setTab('plans')" type="button"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer {{ $activeTab === 'plans' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span>🏸</span> Sports Plans
        </button>
        <button wire:click="setTab('bookings')" type="button"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer {{ $activeTab === 'bookings' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span>🏨</span> My Bookings
        </button>
        <button wire:click="setTab('bills')" type="button"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer {{ $activeTab === 'bills' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span>🧾</span> Itemized Bills
        </button>
        <button wire:click="setTab('family')" type="button"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer {{ $activeTab === 'family' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span>👨‍👩‍👦</span> Family
        </button>
    </div>

    <!-- ============================================================= -->
    <!-- TAB 1: DIGITAL CARD                                           -->
    <!-- ============================================================= -->
    @if ($activeTab === 'card')
        <div class="space-y-6">
            <!-- Simulated Luxury Physical Club Card -->
            <div class="max-w-md mx-auto">
                <div class="relative bg-gradient-to-tr from-slate-950 via-slate-900 to-amber-950 border border-amber-500/50 rounded-3xl p-6 sm:p-7 shadow-2xl shadow-amber-500/10 overflow-hidden text-white flex flex-col justify-between h-[230px] sm:h-[250px]">
                    <!-- Background Watermark Crest -->
                    <div class="absolute -right-6 -bottom-10 text-[160px] font-serif font-black text-amber-500/5 select-none pointer-events-none">
                        MC
                    </div>

                    <!-- Top Card Header -->
                    <div class="flex items-start justify-between relative z-10">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-500 text-slate-950 font-serif font-black flex items-center justify-center text-sm shadow-sm">
                                {{ config('club.short_name', 'MC') }}
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold tracking-tight text-white leading-tight uppercase">{{ config('club.name', 'MALABAR CLUB') }}</h3>
                                <p class="text-[9px] text-amber-400 tracking-widest uppercase font-medium">{{ config('club.location', 'Vadakkencherry • Kerala') }}</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            {{ $member->category?->name ?? 'Ordinary Member' }}
                        </span>
                    </div>

                    <!-- Card Chip & RFID Token -->
                    <div class="flex items-center justify-between relative z-10 pt-2">
                        <div class="flex items-center gap-2 text-[10px] text-slate-400 font-mono">
                            <span class="w-6 h-5 rounded-md bg-amber-400/20 border border-amber-400/40 inline-block"></span>
                            <span>RFID: {{ $member->activeCard?->card_number ?? 'CARD-PENDING' }}</span>
                        </div>
                        @php
                            $standingLabel = match ($statusVal) {
                                'active' => 'Valid Standing',
                                'suspended' => 'Suspended',
                                'expired' => 'Expired Standing',
                                'resigned' => 'Resigned',
                                default => 'Inactive',
                            };
                            $standingColor = match ($statusVal) {
                                'active' => 'text-emerald-400',
                                'suspended' => 'text-rose-400',
                                default => 'text-amber-400',
                            };
                            $standingPulse = match ($statusVal) {
                                'active' => 'bg-emerald-400 animate-pulse',
                                'suspended' => 'bg-rose-400',
                                default => 'bg-amber-400',
                            };
                        @endphp
                        <span class="text-[10px] {{ $standingColor }} font-bold uppercase tracking-wider flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full {{ $standingPulse }}"></span> {{ $standingLabel }}
                        </span>
                    </div>

                    <!-- Bottom Card Footer: Name & Membership Number -->
                    <div class="flex items-end justify-between relative z-10 pt-4 border-t border-slate-800">
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 block">Member Name</span>
                            <h4 class="text-base font-extrabold text-white tracking-wide uppercase leading-tight">{{ $member->name }}</h4>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 block">Membership No</span>
                            <span class="text-base font-extrabold font-mono text-amber-400 tracking-wider block">{{ $member->membership_number }}</span>
                        </div>
                    </div>
                </div>

                <!-- Digital Verification Token / QR Strip -->
                <div class="mt-4 p-4 bg-slate-900/90 border border-slate-800 rounded-2xl flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white rounded-xl p-1 shrink-0 flex items-center justify-center">
                            <!-- Simulated Dynamic QR Code Matrix -->
                            <svg class="w-10 h-10 text-slate-950" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm13-2h3v2h-3v-2zm-5 0h2v3h-2v-3zm2 3h3v2h-3v-2zm3 2h3v3h-3v-3zm-5 0h2v3h-2v-3zm2 3h3v2h-3v-2z"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white tracking-tight">Security Gate Token</h4>
                            <p class="text-[11px] text-slate-400">Present at club reception &amp; turnstile gates</p>
                        </div>
                    </div>
                    <span class="font-mono text-xs font-bold text-amber-400 bg-amber-500/10 px-3 py-1.5 rounded-xl border border-amber-500/30">
                        {{ $member->activeCard?->card_number ?? $member->membership_number }}
                    </span>
                </div>
            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- TAB 2: PREPAID WALLET & CENTRAL LEDGER                        -->
    <!-- ============================================================= -->
    @if ($activeTab === 'wallet')
        <div class="space-y-6">
            <!-- Balance Card -->
            <div class="bg-gradient-to-tr from-slate-900 via-slate-900 to-emerald-950/40 border border-emerald-500/30 rounded-3xl p-6 sm:p-7 shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">Club Prepaid Account Ledger</span>
                        <h3 class="text-3xl sm:text-4xl font-extrabold font-mono text-white mt-1">₹{{ number_format($balanceRupees, 2) }}</h3>
                        <p class="text-xs text-slate-400 mt-1 max-w-lg">
                            Server-authoritative central balance. Redeemable across club counters for bar, restaurant food, and sports sessions. No credit is extended under club bylaws.
                        </p>
                    </div>
                    <button type="button" onclick="window.print()"
                        class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs border border-slate-700 shadow-sm transition flex items-center gap-2 cursor-pointer shrink-0 print:hidden no-print">
                        <svg width="16" height="16" class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print Official Statement</span>
                    </button>
                </div>
            </div>

            <!-- Recent Ledger Transactions Stream -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                <h3 class="text-sm font-extrabold text-white tracking-tight flex items-center gap-2">
                    <span>Recent Account Transactions</span>
                    <span class="text-xs text-slate-500 font-normal">({{ $recentTransactions->count() }} records)</span>
                </h3>

                @if ($recentTransactions->isEmpty())
                    <div class="p-8 text-center text-slate-500 text-xs">
                        No transactions recorded on this member account yet.
                    </div>
                @else
                    <div class="divide-y divide-slate-800/80">
                        @foreach ($recentTransactions as $tx)
                            @php
                                $isCredit = $tx->type === 'recharge' || $tx->type === 'credit' || $tx->amount_paise > 0;
                                $amountRupees = abs($tx->amount_paise) / 100;
                                $balRupees = ($tx->balance_after_paise ?? 0) / 100;
                            @endphp
                            <div class="py-3.5 flex items-center justify-between gap-3 text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 {{ $isCredit ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-300 border border-slate-700' }}">
                                        {{ $isCredit ? '↓' : '↑' }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-white tracking-tight">
                                            {{ $tx->notes ?? ucfirst($tx->type) }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                            {{ $tx->created_at ? $tx->created_at->format('d M Y, h:i A') : '—' }}
                                            @if($tx->reference_id) &bull; Ref #{{ $tx->reference_id }} @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-extrabold {{ $isCredit ? 'text-emerald-400' : 'text-slate-200' }}">
                                        {{ $isCredit ? '+' : '-' }}₹{{ number_format($amountRupees, 2) }}
                                    </div>
                                    <div class="text-[10px] font-mono text-slate-500">
                                        Bal: ₹{{ number_format($balRupees, 2) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- TAB 3: SPORTS & FACILITY PLANS                                -->
    <!-- ============================================================= -->
    @if ($activeTab === 'plans')
        <div class="space-y-6">
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-white tracking-tight">Active Sports Subscriptions</h3>
                        <p class="text-xs text-slate-400">Indoor Wooden Badminton Court &amp; Health Club Gym</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        {{ $activeSubscriptions->count() }} Active
                    </span>
                </div>

                @if ($activeSubscriptions->isEmpty())
                    <div class="p-8 text-center bg-slate-950/60 rounded-2xl border border-slate-800/80 space-y-2">
                        <div class="text-2xl">🏸</div>
                        <h4 class="text-sm font-bold text-white">No Active Sports Plan</h4>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                            You currently pay standard daily session tariffs for badminton and gym. Visit reception to subscribe to monthly or annual court plans.
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($activeSubscriptions as $sub)
                            @php
                                $daysLeft = $sub->valid_until ? now()->diffInDays($sub->valid_until, false) : null;
                            @endphp
                            <div class="bg-slate-950/80 border border-slate-800 hover:border-amber-500/40 rounded-2xl p-5 space-y-3 shadow-md">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-[10px] font-mono font-bold text-amber-400 uppercase tracking-wider block">
                                            {{ $sub->facility_code ? ucfirst($sub->facility_code) : 'Sports Facility' }}
                                        </span>
                                        <h4 class="text-base font-extrabold text-white tracking-tight mt-0.5">
                                            {{ $sub->tariff?->name ?? 'Standard Subscription' }}
                                        </h4>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                        Active
                                    </span>
                                </div>

                                <div class="p-3 bg-slate-900 rounded-xl border border-slate-800 text-xs space-y-1.5">
                                    <div class="flex justify-between text-slate-400">
                                        <span>Start Date:</span>
                                        <span class="text-white font-mono">{{ $sub->valid_from ? $sub->valid_from->format('d M Y') : '—' }}</span>
                                    </div>
                                    <div class="flex justify-between text-slate-400">
                                        <span>Valid Until:</span>
                                        <span class="text-white font-mono">{{ $sub->valid_until ? $sub->valid_until->format('d M Y') : 'Unlimited' }}</span>
                                    </div>
                                    @if($daysLeft !== null)
                                        <div class="pt-1.5 border-t border-slate-800 flex justify-between font-bold">
                                            <span class="text-slate-400">Days Remaining:</span>
                                            <span class="{{ $daysLeft > 15 ? 'text-emerald-400' : 'text-amber-400' }} font-mono">
                                                {{ max(0, $daysLeft) }} Days
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- TAB 4: MY ROOM & COURT BOOKINGS                               -->
    <!-- ============================================================= -->
    @if ($activeTab === 'bookings')
        <div class="space-y-6">
            <!-- Badminton Court Sessions -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-white tracking-tight">Upcoming Badminton Court Sessions</h3>
                        <p class="text-xs text-slate-400">Indoor Wooden Court (Court 1)</p>
                    </div>
                    <span class="text-xs font-mono font-bold text-amber-400">Max 4 Players</span>
                </div>

                @if ($upcomingCourtBookings->isEmpty())
                    <div class="p-6 text-center text-slate-500 text-xs bg-slate-950/60 rounded-2xl border border-slate-800/80">
                        No upcoming badminton court sessions booked. Contact reception or counter staff to reserve a slot.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        @foreach ($upcomingCourtBookings as $cb)
                            <div class="p-4 bg-slate-950 border border-slate-800 rounded-2xl space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-amber-400">{{ $cb->booking_number }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        Confirmed
                                    </span>
                                </div>
                                <div class="text-xs font-bold text-white">
                                    {{ \Carbon\Carbon::parse($cb->booking_date)->format('D, d M Y') }}
                                </div>
                                <div class="text-xs font-mono text-slate-300">
                                    {{ $cb->start_time }} – {{ $cb->end_time }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Accommodation Room Reservations -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                <div>
                    <h3 class="text-sm font-extrabold text-white tracking-tight">Accommodation Room Stays</h3>
                    <p class="text-xs text-slate-400">Club Guest Rooms for self or sponsored guests</p>
                </div>

                @if ($roomReservations->isEmpty())
                    <div class="p-6 text-center text-slate-500 text-xs bg-slate-950/60 rounded-2xl border border-slate-800/80">
                        No room reservations found under your sponsorship.
                    </div>
                @else
                    <div class="divide-y divide-slate-800/80">
                        @foreach ($roomReservations as $rr)
                            @php
                                $totalRupees = ($rr->total_charge_paise ?? 0) / 100;
                                $paidRupees = ($rr->paid_amount_paise ?? 0) / 100;
                                $isPaid = $paidRupees >= $totalRupees && $totalRupees > 0;
                            @endphp
                            <div class="py-4 flex flex-wrap items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-white text-sm">{{ $rr->facility?->name ?? 'Guest Room' }}</h4>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $rr->status === 'checked_in' ? 'bg-teal-500/20 text-teal-300 border border-teal-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30' }}">
                                            {{ $rr->status }}
                                        </span>
                                    </div>
                                    <div class="text-slate-400 mt-0.5 font-mono text-[11px]">
                                        Stay: {{ $rr->check_in_date ? $rr->check_in_date->format('d M Y') : '—' }} &rarr; {{ $rr->check_out_date ? $rr->check_out_date->format('d M Y') : '—' }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-bold text-white">₹{{ number_format($totalRupees, 2) }}</div>
                                    <span class="text-[10px] font-bold {{ $isPaid ? 'text-emerald-400' : 'text-amber-400' }}">
                                        {{ $isPaid ? '✓ Fully Settled' : 'Advance Paid: ₹' . number_format($paidRupees, 2) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- TAB 5: ITEMIZED BILLS & INVOICES                              -->
    <!-- ============================================================= -->
    @if ($activeTab === 'bills')
        <div class="space-y-6">
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-white tracking-tight">Itemized Invoices &amp; Receipts</h3>
                        <p class="text-xs text-slate-400">Official tax invoices for bar, restaurant food, and room stays</p>
                    </div>
                    <span class="text-xs text-slate-500 font-mono">{{ $bills->count() }} Invoices</span>
                </div>

                @if ($bills->isEmpty())
                    <div class="p-8 text-center text-slate-500 text-xs">
                        No bill records found for this member account.
                    </div>
                @else
                    <div class="divide-y divide-slate-800/80">
                        @foreach ($bills as $b)
                            @php
                                $netRupees = $b->net_amount_paise / 100;
                            @endphp
                            <div class="py-3.5 flex items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-amber-400">{{ $b->bill_number }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300">
                                            {{ ucfirst($b->department_code) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                        {{ $b->created_at ? $b->created_at->format('d M Y, h:i A') : '—' }} &bull; {{ $b->items->count() }} items
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono font-extrabold text-white text-sm">₹{{ number_format($netRupees, 2) }}</span>
                                    <button wire:click="openBillModal({{ $b->id }})" type="button"
                                        class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition cursor-pointer">
                                        Receipt
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- TAB 6: FAMILY & DEPENDENTS                                    -->
    <!-- ============================================================= -->
    @if ($activeTab === 'family')
        <div class="space-y-6">
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                <div>
                    <h3 class="text-sm font-extrabold text-white tracking-tight">Registered Family Dependents</h3>
                    <p class="text-xs text-slate-400">Club access and wallet debit privileges for registered family members</p>
                </div>

                @if ($dependents->isEmpty())
                    <div class="p-8 text-center text-slate-500 text-xs bg-slate-950/60 rounded-2xl border border-slate-800/80">
                        No family dependents are currently registered on your membership account. Contact the club office to add your spouse or children.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($dependents as $dep)
                            <div class="p-5 bg-slate-950 border border-slate-800 rounded-2xl space-y-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 block">{{ $dep->relationship }}</span>
                                        <h4 class="text-sm font-extrabold text-white tracking-tight mt-0.5">{{ $dep->person?->full_name }}</h4>
                                        <span class="text-[11px] text-slate-500 font-mono">{{ $dep->person?->phone ?? 'No Phone' }}</span>
                                    </div>
                                    <div class="w-8 h-8 rounded-xl bg-slate-900 text-slate-300 flex items-center justify-center font-bold text-xs">
                                        {{ substr($dep->person?->first_name ?? 'D', 0, 1) }}
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-800/80">
                                    @if ($dep->can_spend_wallet)
                                        <span class="inline-flex items-center gap-1.5 text-xs text-emerald-400 font-bold">
                                            <span>✓</span> Authorized to Spend Prepaid Wallet
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-400 font-medium">
                                            <span>🔒</span> Identification &amp; Entry Only (No Wallet Debit)
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- MODAL: Itemized Bill Receipt                                  -->
    <!-- ============================================================= -->
    @if ($showBillModal && $selectedBill)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-xs p-4 animate-fade-in print:p-0 print:static print:bg-transparent print:backdrop-blur-none"
            role="dialog" aria-modal="true">
            <div class="bg-slate-900 rounded-3xl max-w-md w-full border border-slate-800 p-6 space-y-4 shadow-2xl print:bg-white print:text-slate-900 print:border-none print:shadow-none print:p-0 print:max-w-none">
                <div class="flex items-start justify-between border-b border-slate-800 print:border-slate-300 pb-3">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 print:text-amber-800">Official Club Invoice</span>
                        <h3 class="text-base font-extrabold text-white print:text-slate-900 font-mono">{{ $selectedBill->bill_number }}</h3>
                        <p class="text-[11px] text-slate-400 print:text-slate-600">{{ $selectedBill->created_at?->format('d M Y, h:i A') }}</p>
                    </div>
                    <button wire:click="closeBillModal" type="button" class="text-slate-400 hover:text-white text-xl font-bold p-1 cursor-pointer print:hidden no-print">&times;</button>
                </div>

                <div class="divide-y divide-slate-800/80 print:divide-slate-200 max-h-60 overflow-y-auto print:max-h-none text-xs">
                    @foreach ($selectedBill->items as $item)
                        <div class="py-2 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-white print:text-slate-900">{{ $item->item_name }}</span>
                                <span class="text-slate-400 print:text-slate-600 text-[11px] block">{{ $item->quantity }} &times; ₹{{ number_format($item->unit_price_paise / 100, 2) }}</span>
                            </div>
                            <span class="font-mono font-bold text-white print:text-slate-900">₹{{ number_format($item->total_price_paise / 100, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="p-3 bg-slate-950 print:bg-slate-50 rounded-xl border border-slate-800 print:border-slate-200 text-xs space-y-1">
                    <div class="flex justify-between text-slate-400 print:text-slate-600">
                        <span>Tax / GST:</span>
                        <span class="font-mono">₹{{ number_format($selectedBill->tax_amount_paise / 100, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-extrabold text-white print:text-slate-900 text-sm pt-1 border-t border-slate-800 print:border-slate-200">
                        <span>Total Paid:</span>
                        <span class="font-mono text-emerald-400 print:text-emerald-700">₹{{ number_format($selectedBill->net_amount_paise / 100, 2) }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 print:hidden no-print">
                    <button wire:click="closeBillModal" type="button" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition cursor-pointer print:hidden no-print">
                        Close
                    </button>
                    <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition cursor-pointer print:hidden no-print">
                        Print Invoice
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
