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
    $upcomingBookingsCount = $upcomingCourtBookings->count() + $roomReservations->where('status', 'reserved')->count();
    $isMoreTabActive = in_array($activeTab, ['plans', 'family', 'notices'], true);
    $firstName = strtok(trim((string) $member->name), ' ') ?: $member->name;
@endphp

<div x-data="{
        scrollToTop() {
            const scroller = document.getElementById('member-main');
            if (scroller) {
                scroller.scrollTo({ top: 0, behavior: 'instant' });
            }
        },
        openMoreSheet() {
            const sheet = this.$refs.moreSheet;
            if (sheet && !sheet.open) {
                sheet.showModal();
            }
        },
        closeMoreSheet() {
            const sheet = this.$refs.moreSheet;
            if (sheet && sheet.open) {
                sheet.close();
            }
        },
        init() {
            const sheet = this.$refs.moreSheet;
            if (sheet && !('closedBy' in HTMLDialogElement.prototype)) {
                let pressedOutside = false;
                const outside = (e) => {
                    const r = sheet.getBoundingClientRect();
                    return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
                };
                sheet.addEventListener('pointerdown', (e) => { pressedOutside = outside(e); });
                sheet.addEventListener('pointerup', (e) => { if (pressedOutside && outside(e)) sheet.close(); });
            }
        }
    }">

    <div class="space-y-4 md:space-y-6">
    <!-- ============================================================= -->
    <!-- PROMINENT STICKY ALERT BANNERS (DUES & URGENT CLUB NOTICES)   -->
    <!-- ============================================================= -->
    @if ($activeBanners->isNotEmpty())
        <!-- Mobile: Compact Collapsible Alert Pills (Keeps Home Card Above the Fold) -->
        <div class="md:hidden space-y-2 {{ ($activeTab === 'wallet' || $showBillModal) ? 'print:hidden' : '' }}">
            @foreach ($activeBanners as $banner)
                @php
                    $isUrgent = $banner->priority === 'urgent';
                    $isReminder = $banner->priority === 'reminder';
                    $isEvent = $banner->priority === 'event';
                    $bannerBorder = $isUrgent ? 'border-rose-500/60' : ($isReminder ? 'border-amber-500/60' : ($isEvent ? 'border-emerald-500/60' : 'border-blue-500/60'));
                    $bannerBg = $isUrgent ? 'from-rose-950/80 via-slate-900 to-slate-950' : ($isReminder ? 'from-amber-950/80 via-slate-900 to-slate-950' : ($isEvent ? 'from-emerald-950/80 via-slate-900 to-slate-950' : 'from-blue-950/80 via-slate-900 to-slate-950'));
                    $bannerIcon = $isUrgent ? '🚨' : ($isReminder ? '⚠️' : ($isEvent ? '🎉' : '📢'));
                    $bannerTextColor = $isUrgent ? 'text-rose-400' : ($isReminder ? 'text-amber-400' : ($isEvent ? 'text-emerald-400' : 'text-blue-400'));
                @endphp
                <div x-data="{ expanded: false }" class="bg-gradient-to-r {{ $bannerBg }} border {{ $bannerBorder }} rounded-2xl p-3 shadow-md ring-1 ring-white/5">
                    <div class="flex items-center justify-between gap-2">
                        <button type="button" @click="expanded = !expanded" :aria-expanded="expanded ? 'true' : 'false'"
                            class="flex items-center gap-2.5 min-w-0 flex-1 text-left cursor-pointer py-0.5">
                            <span class="text-base shrink-0" aria-hidden="true">{{ $bannerIcon }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-xs text-white truncate">{{ $banner->title }}</span>
                                    <span class="text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded-full bg-white/10 {{ $bannerTextColor }} shrink-0">
                                        {{ ucfirst($banner->priority) }}
                                    </span>
                                </div>
                            </div>
                            <svg aria-hidden="true" class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <button wire:click="dismissBanner({{ $banner->id }})"
                            type="button"
                            aria-label="Dismiss {{ $banner->title }}"
                            class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition shrink-0 cursor-pointer">
                            <svg aria-hidden="true" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div x-cloak x-show="expanded" class="mt-2.5 pt-2.5 border-t border-white/10 space-y-2">
                        <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line text-pretty">{{ $banner->content }}</p>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] text-slate-400">{{ $banner->published_at->format('d M Y') }}</span>
                            @if ($banner->action_url)
                                <a href="{{ $banner->action_url }}"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-xs transition cursor-pointer">
                                    <span>{{ $banner->action_label ?: 'Take Action' }}</span>
                                    <span aria-hidden="true">&rarr;</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Desktop: Full Prominent Banners -->
        <div class="hidden md:block space-y-3 {{ ($activeTab === 'wallet' || $showBillModal) ? 'print:hidden' : '' }}">
            @foreach ($activeBanners as $banner)
                @php
                    $isUrgent = $banner->priority === 'urgent';
                    $isReminder = $banner->priority === 'reminder';
                    $isEvent = $banner->priority === 'event';
                    $bannerBorder = $isUrgent ? 'border-rose-500/60' : ($isReminder ? 'border-amber-500/60' : ($isEvent ? 'border-emerald-500/60' : 'border-blue-500/60'));
                    $bannerBg = $isUrgent ? 'from-rose-950/80 via-slate-900 to-slate-950' : ($isReminder ? 'from-amber-950/80 via-slate-900 to-slate-950' : ($isEvent ? 'from-emerald-950/80 via-slate-900 to-slate-950' : 'from-blue-950/80 via-slate-900 to-slate-950'));
                    $bannerIcon = $isUrgent ? '🚨' : ($isReminder ? '⚠️' : ($isEvent ? '🎉' : '📢'));
                    $bannerTextColor = $isUrgent ? 'text-rose-400' : ($isReminder ? 'text-amber-400' : ($isEvent ? 'text-emerald-400' : 'text-blue-400'));
                @endphp
                <div class="relative bg-gradient-to-r {{ $bannerBg }} border {{ $bannerBorder }} rounded-3xl p-4 sm:p-5 shadow-xl flex items-start justify-between gap-4 animate-fade-in ring-1 ring-white/5">
                    <div class="flex items-start gap-3.5">
                        <span class="text-2xl shrink-0 mt-0.5" aria-hidden="true">{{ $bannerIcon }}</span>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-extrabold text-sm sm:text-base text-white tracking-tight text-balance">
                                    {{ $banner->title }}
                                </h3>
                                <span class="text-[9px] uppercase font-bold tracking-widest px-2 py-0.5 rounded-full bg-white/10 {{ $bannerTextColor }} border border-white/10">
                                    {{ ucfirst($banner->priority) }}
                                </span>
                                <span class="text-[10px] text-slate-400 font-medium">
                                    &bull; {{ $banner->published_at->format('d M Y') }}
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-2xl whitespace-pre-line text-pretty">
                                {{ $banner->content }}
                            </p>
                            @if ($banner->action_url)
                                <div class="pt-2">
                                    <a href="{{ $banner->action_url }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-xs transition cursor-pointer">
                                        <span>{{ $banner->action_label ?: 'Take Action' }}</span>
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                    <button wire:click="dismissBanner({{ $banner->id }})"
                        type="button"
                        aria-label="Dismiss {{ $banner->title }}"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition shrink-0 cursor-pointer"
                        title="Dismiss banner from dashboard">
                        <svg aria-hidden="true" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Desktop Welcome Header & Member Status Pill -->
    <div class="hidden md:flex bg-gradient-to-r from-slate-900 via-slate-900 to-slate-800 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl flex-wrap items-center justify-between gap-4 {{ ($activeTab === 'wallet' || $showBillModal) ? 'print:hidden' : '' }}">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-yellow-400 p-0.5 shadow-md shadow-amber-500/20 shrink-0">
                @if ($member->portal_photo_url)
                    <img src="{{ $member->portal_photo_url }}" alt="{{ $member->name }}"
                        class="w-full h-full object-cover rounded-[14px]"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="display: none;" class="w-full h-full bg-slate-950 rounded-[14px] items-center justify-center font-bold text-amber-400 text-lg">
                        {{ substr($member->name, 0, 1) }}
                    </div>
                @else
                    <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center font-bold text-amber-400 text-lg">
                        {{ substr($member->name, 0, 1) }}
                    </div>
                @endif
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-extrabold text-white tracking-tight">{{ $member->name }}</h2>
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

    <!-- Desktop Quick Stats Cards -->
    <div class="hidden md:grid md:grid-cols-3 gap-4 {{ ($activeTab === 'wallet' || $showBillModal) ? 'print:hidden' : '' }}">
        <!-- 1. Wallet Balance -->
        <button type="button" wire:click="setTab('wallet')" @click="scrollToTop()" class="text-left bg-slate-900/90 border border-slate-800/90 hover:border-amber-500/40 rounded-2xl p-4 transition-all cursor-pointer group shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Prepaid Wallet</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-sm" aria-hidden="true">₹</span>
            </div>
            <div class="text-xl font-extrabold font-mono text-white group-hover:text-amber-400 transition-colors">
                ₹{{ number_format($balanceRupees, 2) }}
            </div>
            <div class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                <span>Backed by Central Ledger</span>
                <span class="text-amber-400 font-bold group-hover:translate-x-0.5 transition-transform" aria-hidden="true">&rarr;</span>
            </div>
        </button>

        <!-- 2. Active Sports Plans -->
        <button type="button" wire:click="setTab('plans')" @click="scrollToTop()" class="text-left bg-slate-900/90 border border-slate-800/90 hover:border-amber-500/40 rounded-2xl p-4 transition-all cursor-pointer group shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sports &amp; Gym Plans</span>
                <span class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center text-sm" aria-hidden="true">🏸</span>
            </div>
            <div class="text-xl font-extrabold text-white group-hover:text-amber-400 transition-colors">
                {{ $activeSubscriptions->count() }} Active
            </div>
            <div class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                <span>Badminton &amp; Health Club</span>
                <span class="text-amber-400 font-bold group-hover:translate-x-0.5 transition-transform" aria-hidden="true">&rarr;</span>
            </div>
        </button>

        <!-- 3. Room & Court Bookings -->
        <button type="button" wire:click="setTab('bookings')" @click="scrollToTop()" class="text-left bg-slate-900/90 border border-slate-800/90 hover:border-amber-500/40 rounded-2xl p-4 transition-all cursor-pointer group shadow-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bookings &amp; Stays</span>
                <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-sm" aria-hidden="true">🏨</span>
            </div>
            <div class="text-xl font-extrabold text-white group-hover:text-amber-400 transition-colors">
                {{ $upcomingBookingsCount }} Upcoming
            </div>
            <div class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                <span>Guest Rooms &amp; Badminton</span>
                <span class="text-amber-400 font-bold group-hover:translate-x-0.5 transition-transform" aria-hidden="true">&rarr;</span>
            </div>
        </button>
    </div>

    <!-- Desktop Navigation Tab Bar (Mobile uses Sticky Bottom Navigation Dock) -->
    <nav aria-label="Member Portal Sections" class="hidden md:flex bg-slate-900/90 border border-slate-800/90 rounded-2xl p-1.5 items-center overflow-x-auto gap-1 text-xs font-bold scrollbar-none shadow-md {{ ($activeTab === 'wallet' || $showBillModal) ? 'print:hidden' : '' }}">
        <button wire:click="setTab('card')" @click="scrollToTop()" type="button" aria-current="{{ $activeTab === 'card' ? 'page' : 'false' }}"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer border {{ $activeTab === 'card' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm border-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-transparent' }}">
            <span aria-hidden="true">🪪</span> Digital Card
        </button>
        <button wire:click="setTab('wallet')" @click="scrollToTop()" type="button" aria-current="{{ $activeTab === 'wallet' ? 'page' : 'false' }}"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer border {{ $activeTab === 'wallet' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm border-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-transparent' }}">
            <span aria-hidden="true">💰</span> Wallet &amp; Ledger
        </button>
        <button wire:click="setTab('plans')" @click="scrollToTop()" type="button" aria-current="{{ $activeTab === 'plans' ? 'page' : 'false' }}"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer border {{ $activeTab === 'plans' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm border-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-transparent' }}">
            <span aria-hidden="true">🏸</span> Sports Plans
        </button>
        <button wire:click="setTab('bookings')" @click="scrollToTop()" type="button" aria-current="{{ $activeTab === 'bookings' ? 'page' : 'false' }}"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer border {{ $activeTab === 'bookings' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm border-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-transparent' }}">
            <span aria-hidden="true">🏨</span> My Bookings
        </button>
        <button wire:click="setTab('bills')" @click="scrollToTop()" type="button" aria-current="{{ $activeTab === 'bills' ? 'page' : 'false' }}"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer border {{ $activeTab === 'bills' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm border-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-transparent' }}">
            <span aria-hidden="true">🧾</span> Itemized Bills
        </button>
        <button wire:click="setTab('family')" @click="scrollToTop()" type="button" aria-current="{{ $activeTab === 'family' ? 'page' : 'false' }}"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer border {{ $activeTab === 'family' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm border-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-transparent' }}">
            <span aria-hidden="true">👨‍👩‍👦</span> Family
        </button>
        <button wire:click="setTab('notices')" @click="scrollToTop()" type="button" aria-current="{{ $activeTab === 'notices' ? 'page' : 'false' }}"
            class="px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer border {{ $activeTab === 'notices' ? 'bg-amber-500 text-slate-950 font-extrabold shadow-sm border-amber-400' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-transparent' }}">
            <span aria-hidden="true">🔔</span> Club Notices
            @if($unreadNoticesCount > 0)
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-extrabold {{ $activeTab === 'notices' ? 'bg-slate-950 text-amber-400' : 'bg-rose-500 text-white animate-pulse' }}">
                    {{ $unreadNoticesCount }}
                </span>
            @endif
        </button>
    </nav>

    <div wire:loading.class="opacity-65 transition-opacity" wire:target="setTab,openBillModal">
    <!-- ============================================================= -->
    <!-- TAB 1: DIGITAL CARD (Zero-Scroll Mobile Home Screen)          -->
    <!-- ============================================================= -->
    @if ($activeTab === 'card')
        <div class="space-y-3 md:space-y-6">
            <!-- Mobile Compact Greeting & 3-Chip Summary Bar (Fits Card Above the Fold) -->
            <div class="md:hidden space-y-2.5 max-w-md mx-auto">
                <div class="flex items-center justify-between gap-2 px-0.5">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-7 h-7 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 p-0.5 shrink-0">
                            @if ($member->portal_photo_url)
                                <img src="{{ $member->portal_photo_url }}" alt="{{ $member->name }}"
                                    class="w-full h-full object-cover rounded-[10px]"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <span style="display: none;" class="w-full h-full bg-slate-950 rounded-[10px] items-center justify-center font-bold text-amber-400 text-xs">
                                    {{ substr($member->name, 0, 1) }}
                                </span>
                            @else
                                <span class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center font-bold text-amber-400 text-xs">
                                    {{ substr($member->name, 0, 1) }}
                                </span>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-extrabold text-white tracking-tight truncate">
                                Hi, {{ $firstName }}
                                <span class="text-slate-500 font-normal">&bull;</span>
                                <span class="font-mono text-amber-400">{{ $member->membership_number }}</span>
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold tracking-wider uppercase shrink-0 {{ $statusBadgeColor }} border">
                        <span class="w-1.5 h-1.5 rounded-full {{ $statusDotColor }} inline-block"></span> {{ ucfirst($statusVal) }}
                    </span>
                </div>

                <!-- 3-Across Compact Quick-Jump Strip -->
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" wire:click="setTab('wallet')" @click="scrollToTop()"
                        class="min-h-11 text-left bg-slate-900/95 border border-slate-800/90 active:scale-95 hover:border-amber-500/40 rounded-2xl px-2.5 py-2 transition-all cursor-pointer min-w-0">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block truncate">Wallet</span>
                        <span class="text-xs font-extrabold font-mono text-emerald-400 block truncate mt-0.5">₹{{ number_format($balanceRupees, 0) }}</span>
                    </button>
                    <button type="button" wire:click="setTab('plans')" @click="scrollToTop()"
                        class="min-h-11 text-left bg-slate-900/95 border border-slate-800/90 active:scale-95 hover:border-amber-500/40 rounded-2xl px-2.5 py-2 transition-all cursor-pointer min-w-0">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block truncate">Sports Plans</span>
                        <span class="text-xs font-extrabold text-white block truncate mt-0.5">{{ $activeSubscriptions->count() }} Active</span>
                    </button>
                    <button type="button" wire:click="setTab('bookings')" @click="scrollToTop()"
                        class="min-h-11 text-left bg-slate-900/95 border border-slate-800/90 active:scale-95 hover:border-amber-500/40 rounded-2xl px-2.5 py-2 transition-all cursor-pointer min-w-0">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block truncate">Bookings</span>
                        <span class="text-xs font-extrabold text-white block truncate mt-0.5">{{ $upcomingBookingsCount }} Upcoming</span>
                    </button>
                </div>
            </div>

            <!-- Simulated Luxury Physical Club Card -->
            <div class="max-w-md mx-auto">
                <div class="relative bg-gradient-to-tr from-slate-950 via-slate-900 to-amber-950 border border-amber-500/50 rounded-3xl p-5 sm:p-7 shadow-2xl shadow-amber-500/10 overflow-hidden text-white flex flex-col justify-between min-h-[196px] sm:min-h-[250px]">
                    <!-- Background Watermark Crest -->
                    <div aria-hidden="true" class="absolute -right-5 -bottom-9 text-[125px] sm:text-[160px] font-serif font-black text-amber-500/5 select-none pointer-events-none">
                        MC
                    </div>

                    <!-- Top Card Header -->
                    <div class="flex items-start justify-between gap-2 relative z-10">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-amber-500 text-slate-950 font-serif font-black flex items-center justify-center text-sm shadow-sm shrink-0">
                                {{ config('club.short_name', 'MC') }}
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-xs sm:text-sm font-extrabold tracking-tight text-white leading-tight uppercase truncate">{{ config('club.name', 'MALABAR CLUB') }}</h3>
                                <p class="text-[9px] text-amber-400 tracking-widest uppercase font-medium truncate">{{ config('club.location', 'Vadakkencherry • Kerala') }}</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30 shrink-0">
                            {{ $member->category?->name ?? 'Ordinary Member' }}
                        </span>
                    </div>

                    <!-- Card Chip & RFID Token -->
                    <div class="flex items-center justify-between gap-2 relative z-10 pt-2">
                        <div class="flex items-center gap-2 text-[10px] text-slate-400 font-mono min-w-0">
                            <span aria-hidden="true" class="w-6 h-5 rounded-md bg-amber-400/20 border border-amber-400/40 inline-block shrink-0"></span>
                            <span class="truncate">RFID: {{ $member->activeCard?->card_number ?? 'CARD-PENDING' }}</span>
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
                        <span class="text-[10px] {{ $standingColor }} font-bold uppercase tracking-wider flex items-center gap-1 shrink-0">
                            <span class="w-2 h-2 rounded-full {{ $standingPulse }}"></span> {{ $standingLabel }}
                        </span>
                    </div>

                    <!-- Bottom Card Footer: Name & Membership Number -->
                    <div class="flex items-end justify-between gap-2 relative z-10 pt-3 sm:pt-4 border-t border-slate-800">
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 block">Member Name</span>
                            <h4 class="text-sm sm:text-base font-extrabold text-white tracking-wide uppercase leading-tight truncate">{{ $member->name }}</h4>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 block">Membership No</span>
                            <span class="text-sm sm:text-base font-extrabold font-mono text-amber-400 tracking-wider block">{{ $member->membership_number }}</span>
                        </div>
                    </div>
                </div>

                <!-- Digital Verification Token / QR Strip -->
                <div class="mt-3 sm:mt-4 p-3.5 sm:p-4 bg-slate-900/90 border border-slate-800 rounded-2xl flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-11 h-11 sm:w-12 sm:h-12 bg-white rounded-xl p-1 shrink-0 flex items-center justify-center">
                            <!-- Simulated Dynamic QR Code Matrix -->
                            <svg aria-hidden="true" class="w-9 h-9 sm:w-10 sm:h-10 text-slate-950" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm13-2h3v2h-3v-2zm-5 0h2v3h-2v-3zm2 3h3v2h-3v-2zm3 2h3v3h-3v-3zm-5 0h2v3h-2v-3zm2 3h3v2h-3v-2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-white tracking-tight">Security Gate Token</h4>
                            <p class="text-[11px] text-slate-400 truncate">Present at reception &amp; turnstile gates</p>
                        </div>
                    </div>
                    <span class="font-mono text-[11px] sm:text-xs font-bold text-amber-400 bg-amber-500/10 px-2.5 sm:px-3 py-1.5 rounded-xl border border-amber-500/30 shrink-0">
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
        <div id="printable-member-wallet-statement" class="space-y-6 print:bg-white print:text-slate-900">
            <!-- Official Member Wallet Statement Letterhead (Print Only) -->
            <div class="hidden print:block border-b-2 border-slate-900 pb-4 text-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-black uppercase tracking-wider text-slate-950">{{ \App\Domain\Shared\Models\SystemSetting::get('club_name', config('club.name', 'Malabar Club')) }}</h1>
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-600 mt-0.5">
                            {{ \App\Domain\Shared\Models\SystemSetting::get('club_address', config('club.location', 'Vadakkencherry, Palakkad, Kerala')) }} &bull; Official Member Prepaid Wallet Statement
                        </p>
                    </div>
                    <div class="text-right text-[11px] text-slate-600">
                        <div>Statement Date: <strong class="text-slate-900 font-mono">{{ now()->format('d M Y, h:i A') }} IST</strong></div>
                        <div>Status: <strong class="text-slate-900 uppercase">Zero-Credit Enforced</strong></div>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-300 grid grid-cols-4 gap-3 text-xs">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Member Name</span>
                        <strong class="text-slate-950">{{ $member->name }}</strong>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Membership #</span>
                        <strong class="font-mono text-slate-950">{{ $member->membership_number }}</strong>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Category / Card</span>
                        <strong class="text-slate-950">{{ $member->category?->name ?? 'Member' }} ({{ $member->activeCard?->card_number ?? 'No Card' }})</strong>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Available Balance</span>
                        <strong class="font-mono text-emerald-800 text-sm">₹{{ number_format($balanceRupees, 2) }}</strong>
                    </div>
                </div>
            </div>

            <!-- Balance Card -->
            <div class="bg-gradient-to-tr from-slate-900 via-slate-900 to-emerald-950/40 border border-emerald-500/30 rounded-2xl sm:rounded-3xl p-4 sm:p-7 shadow-xl print:bg-none print:bg-white print:text-slate-900 print:border-slate-300 print:shadow-none print:rounded-2xl print:p-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 print:text-emerald-800">Club Prepaid Account Ledger</span>
                        <h3 class="text-3xl sm:text-4xl font-extrabold font-mono text-white print:text-slate-950 mt-1">₹{{ number_format($balanceRupees, 2) }}</h3>
                        <p class="text-xs text-slate-400 print:text-slate-600 mt-1 max-w-lg text-pretty">
                            Server-authoritative central balance. Redeemable across club counters for bar, restaurant food, and sports sessions. No credit is extended under club bylaws.
                        </p>
                    </div>
                    <button type="button" onclick="window.print()"
                        class="min-h-10 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs border border-slate-700 shadow-sm transition flex items-center gap-2 cursor-pointer shrink-0 active:scale-95 print:hidden no-print">
                        <svg aria-hidden="true" width="16" height="16" class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Print Official Statement</span>
                    </button>
                </div>
            </div>

            <!-- Recent Ledger Transactions Stream -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-xl space-y-3 sm:space-y-4 print:bg-white print:text-slate-900 print:border-slate-300 print:shadow-none print:rounded-2xl print:p-5">
                <h3 class="text-sm font-extrabold text-white print:text-slate-950 tracking-tight flex items-center gap-2">
                    <span>Recent Account Transactions</span>
                    <span class="text-xs text-slate-500 print:text-slate-600 font-normal">({{ $recentTransactions->count() }} records)</span>
                </h3>

                @if ($recentTransactions->isEmpty())
                    <div class="p-8 text-center text-slate-500 print:text-slate-600 text-xs">
                        No transactions recorded on this member account yet.
                    </div>
                @else
                    <div class="divide-y divide-slate-800/80 print:divide-slate-200">
                        @foreach ($recentTransactions as $tx)
                            @php
                                $isCredit = $tx->type === 'recharge' || $tx->type === 'credit' || $tx->amount_paise > 0;
                                $amountRupees = abs($tx->amount_paise) / 100;
                                $balRupees = ($tx->balance_after_paise ?? 0) / 100;
                            @endphp
                            <div class="py-3.5 flex items-center justify-between gap-3 text-xs break-inside-avoid">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 {{ $isCredit ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 print:bg-emerald-50 print:text-emerald-800 print:border-emerald-300' : 'bg-slate-800 text-slate-300 border border-slate-700 print:bg-slate-100 print:text-slate-900 print:border-slate-300' }}">
                                        {{ $isCredit ? '↓' : '↑' }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-white print:text-slate-950 tracking-tight">
                                            {{ $tx->notes ?? ucfirst($tx->type) }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 print:text-slate-600 font-mono mt-0.5">
                                            {{ $tx->created_at ? $tx->created_at->format('d M Y, h:i A') : '—' }}
                                            @if($tx->reference_id) &bull; Ref #{{ $tx->reference_id }} @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-extrabold {{ $isCredit ? 'text-emerald-400 print:text-emerald-800' : 'text-slate-200 print:text-slate-900' }}">
                                        {{ $isCredit ? '+' : '-' }}₹{{ number_format($amountRupees, 2) }}
                                    </div>
                                    <div class="text-[10px] font-mono text-slate-500 print:text-slate-600">
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
        <div class="space-y-4 sm:space-y-6">
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-xl space-y-4">
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
        <div class="space-y-4 sm:space-y-6">
            <!-- Badminton Court Sessions -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-xl space-y-4">
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
        <div class="space-y-4 sm:space-y-6 {{ $showBillModal ? 'print:hidden' : '' }}">
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="text-sm font-extrabold text-white tracking-tight">Itemized Invoices &amp; Receipts</h3>
                        <p class="text-xs text-slate-400">Official tax invoices for bar, restaurant food, and room stays</p>
                    </div>
                    <span class="text-xs text-slate-500 font-mono shrink-0">{{ $bills->count() }} Invoices</span>
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
                            <div class="py-3.5 flex items-center justify-between gap-2 sm:gap-3 text-xs">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                        <span class="font-mono font-bold text-amber-400">{{ $b->bill_number }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-300">
                                            {{ ucfirst($b->department_code) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5 truncate">
                                        {{ $b->created_at ? $b->created_at->format('d M Y, h:i A') : '—' }} &bull; {{ $b->items->count() }} items
                                    </div>
                                </div>
                                <div class="flex items-center gap-2.5 sm:gap-3 shrink-0">
                                    <span class="font-mono font-extrabold text-white text-sm">₹{{ number_format($netRupees, 2) }}</span>
                                    <button wire:click="openBillModal({{ $b->id }})" type="button"
                                        class="min-h-10 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition active:scale-95 cursor-pointer">
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
        <div class="space-y-4 sm:space-y-6">
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-xl space-y-4">
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
                                    @if ($dep->portal_photo_url)
                                        <img src="{{ $dep->portal_photo_url }}" alt="{{ $dep->person?->full_name }}"
                                            class="w-10 h-10 rounded-xl object-cover border border-slate-800 shrink-0"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div style="display: none;" class="w-10 h-10 rounded-xl bg-slate-900 text-slate-300 items-center justify-center font-bold text-xs shrink-0">
                                            {{ substr($dep->person?->first_name ?? 'D', 0, 1) }}
                                        </div>
                                    @else
                                        <div class="w-10 h-10 rounded-xl bg-slate-900 text-slate-300 flex items-center justify-center font-bold text-xs shrink-0">
                                            {{ substr($dep->person?->first_name ?? 'D', 0, 1) }}
                                        </div>
                                    @endif
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
    <!-- TAB 7: CLUB BULLETINS & NOTICES                               -->
    <!-- ============================================================= -->
    @if ($activeTab === 'notices')
        <div class="space-y-4 sm:space-y-6 animate-fade-in">
            <!-- Header with Title & Mark All as Read Action -->
            <div class="bg-slate-900/90 border border-slate-800/90 rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-xl flex flex-wrap items-center justify-between gap-3 sm:gap-4">
                <div>
                    <h3 class="text-base font-extrabold text-white tracking-tight flex items-center gap-2">
                        <span aria-hidden="true">🔔</span> Club Notices &amp; Bulletins
                        @if($unreadNoticesCount > 0)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-extrabold bg-rose-500 text-white animate-pulse">
                                {{ $unreadNoticesCount }} unread
                            </span>
                        @endif
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">Official announcements, payment reminders, facility maintenance notices, and club events.</p>
                </div>

                @if ($unreadNoticesCount > 0)
                    <button wire:click="markAllNoticesAsRead"
                        type="button"
                        class="min-h-10 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95">
                        <span aria-hidden="true">✓✓</span> Mark All as Read
                    </button>
                @endif
            </div>

            <!-- Notices List -->
            @if ($notices->isEmpty())
                <div class="bg-slate-900/50 border border-slate-800/60 rounded-3xl p-12 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-800/80 text-slate-400 flex items-center justify-center text-xl mx-auto mb-3">
                        📭
                    </div>
                    <h4 class="text-sm font-bold text-white">No Notices at Present</h4>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">There are no active notices or announcements targeted to your membership account right now.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($notices as $notice)
                        @php
                            $isRead = $notice->reads->isNotEmpty();
                            $isUrgent = $notice->priority === 'urgent';
                            $isReminder = $notice->priority === 'reminder';
                            $isEvent = $notice->priority === 'event';

                            $deptBadge = match ($notice->department) {
                                'accounts' => ['label' => '💰 Accounts & Dues', 'color' => 'bg-amber-500/10 text-amber-400 border-amber-500/30'],
                                'reception' => ['label' => '🏨 Reception Desk', 'color' => 'bg-blue-500/10 text-blue-400 border-blue-500/30'],
                                'sports' => ['label' => '🏸 Sports & Fitness', 'color' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'],
                                'food' => ['label' => '🍽️ Dining & F&B', 'color' => 'bg-rose-500/10 text-rose-400 border-rose-500/30'],
                                default => ['label' => '🏛️ Administration', 'color' => 'bg-slate-500/10 text-slate-400 border-slate-500/30'],
                            };

                            $priorityBadge = match ($notice->priority) {
                                'urgent' => ['label' => '🚨 Urgent Alert', 'color' => 'bg-rose-500/10 text-rose-400 border-rose-500/30'],
                                'reminder' => ['label' => '⚠️ Due Reminder', 'color' => 'bg-amber-500/10 text-amber-400 border-amber-500/30'],
                                'event' => ['label' => '🎉 Club Event', 'color' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'],
                                default => ['label' => '📢 Notice', 'color' => 'bg-blue-500/10 text-blue-400 border-blue-500/30'],
                            };
                        @endphp
                        <div class="bg-slate-900/90 border {{ $isRead ? 'border-slate-800/80 opacity-90' : 'border-amber-500/50 shadow-lg shadow-amber-500/5 ring-1 ring-amber-500/20' }} rounded-3xl p-5 sm:p-6 transition-all space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold tracking-wider border {{ $deptBadge['color'] }}">
                                        {{ $deptBadge['label'] }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold tracking-wider border {{ $priorityBadge['color'] }}">
                                        {{ $priorityBadge['label'] }}
                                    </span>
                                    @if (!$isRead)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold tracking-wider bg-rose-500/20 text-rose-400 border border-rose-500/40">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-pulse"></span> NEW
                                        </span>
                                    @else
                                        <span class="text-[10px] text-slate-500 font-medium">✓ Read</span>
                                    @endif
                                </div>

                                <span class="text-[11px] font-mono text-slate-400">
                                    {{ $notice->published_at->format('d M Y, h:i A') }}
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <h4 class="text-base font-extrabold text-white tracking-tight">
                                    {{ $notice->title }}
                                </h4>
                                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed whitespace-pre-line">
                                    {{ $notice->content }}
                                </p>
                            </div>

                            <div class="pt-2 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    @if ($notice->action_url)
                                        <a href="{{ $notice->action_url }}"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-xs transition cursor-pointer">
                                            <span>{{ $notice->action_label ?: 'Open Link' }}</span>
                                            <span>&rarr;</span>
                                        </a>
                                    @endif
                                </div>

                                @if (!$isRead)
                                    <button wire:click="markNoticeAsRead({{ $notice->id }})"
                                        type="button"
                                        class="text-xs font-bold text-slate-400 hover:text-white transition flex items-center gap-1 cursor-pointer">
                                        <span>✓</span> Mark as Read
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
    </div><!-- End Livewire Loading Region -->
    </div><!-- End Scrollable Content Stack -->

    <!-- ============================================================= -->
    <!-- MODAL: Itemized Bill Receipt (Bottom Sheet on Mobile)         -->
    <!-- ============================================================= -->
    @if ($showBillModal && $selectedBill)
        <div wire:click.self="closeBillModal"
            @keydown.escape.window="if ($wire.showBillModal) $wire.closeBillModal()"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-950/80 backdrop-blur-xs p-0 sm:p-4 overscroll-contain animate-fade-in print:p-0 print:static print:bg-transparent print:backdrop-blur-none"
            role="dialog" aria-modal="true" aria-labelledby="member-bill-modal-title">
            <div id="printable-member-invoice" data-print-document="modal"
                class="bg-slate-900 rounded-t-3xl sm:rounded-3xl max-w-md w-full max-h-[88dvh] flex flex-col border border-b-0 sm:border-b border-slate-800 p-5 sm:p-6 pb-[max(1.25rem,env(safe-area-inset-bottom,0px))] sm:pb-6 space-y-3.5 sm:space-y-4 shadow-2xl print:bg-white print:text-slate-900 print:border-none print:shadow-none print:p-0 print:max-w-none print:w-full print:max-h-none print:block">
                <!-- Mobile Sheet Drag Pill -->
                <div aria-hidden="true" class="w-10 h-1 rounded-full bg-slate-700 mx-auto -mt-1 sm:hidden print:hidden no-print"></div>

                <!-- Official Club Tax Invoice Letterhead (Print Only) -->
                <div class="hidden print:block border-b-2 border-slate-900 pb-3 text-slate-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-lg font-black uppercase tracking-wider text-slate-950">{{ \App\Domain\Shared\Models\SystemSetting::get('club_name', config('club.name', 'Malabar Club')) }}</h1>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-600">
                                {{ \App\Domain\Shared\Models\SystemSetting::get('club_address', config('club.location', 'Vadakkencherry, Palakkad, Kerala')) }} &bull; GSTIN: {{ \App\Domain\Shared\Models\SystemSetting::get('club_gstin', '32AABCM1234F1Z5') }}
                            </p>
                        </div>
                        <div class="text-right text-[10px] text-slate-600">
                            <div>Member: <strong class="text-slate-950">{{ $member->name }}</strong></div>
                            <div>ID: <strong class="font-mono text-slate-950">#{{ $member->membership_number }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="flex items-start justify-between border-b border-slate-800 print:border-slate-300 pb-3 shrink-0">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 print:text-amber-800">Official Club Invoice</span>
                        <h3 id="member-bill-modal-title" class="text-base font-extrabold text-white print:text-slate-900 font-mono">{{ $selectedBill->bill_number }}</h3>
                        <p class="text-[11px] text-slate-400 print:text-slate-600">{{ $selectedBill->created_at?->format('d M Y, h:i A') }} &bull; {{ ucfirst(str_replace('_', ' ', $selectedBill->department_code)) }}</p>
                    </div>
                    <button wire:click="closeBillModal" type="button" aria-label="Close invoice" class="min-w-10 min-h-10 inline-flex items-center justify-center rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 text-xl font-bold cursor-pointer print:hidden no-print">&times;</button>
                </div>

                <div class="divide-y divide-slate-800/80 print:divide-slate-200 max-h-[42dvh] sm:max-h-60 overflow-y-auto overscroll-contain print:max-h-none print:overflow-visible text-xs">
                    @foreach ($selectedBill->items as $item)
                        <div class="py-2.5 flex items-center justify-between gap-3 break-inside-avoid">
                            <div class="min-w-0">
                                <span class="font-semibold text-white print:text-slate-900 block">{{ $item->item_name }}</span>
                                <span class="text-slate-400 print:text-slate-600 text-[11px] block">{{ $item->quantity }} &times; ₹{{ number_format($item->unit_price_paise / 100, 2) }}</span>
                            </div>
                            <span class="font-mono font-bold text-white print:text-slate-900 shrink-0">₹{{ number_format($item->total_price_paise / 100, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="p-3 bg-slate-950 print:bg-slate-50 rounded-xl border border-slate-800 print:border-slate-200 text-xs space-y-1 shrink-0">
                    <div class="flex justify-between text-slate-400 print:text-slate-600">
                        <span>Tax / GST:</span>
                        <span class="font-mono text-slate-200 print:text-slate-900">₹{{ number_format($selectedBill->tax_amount_paise / 100, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-extrabold text-white print:text-slate-900 text-sm pt-1 border-t border-slate-800 print:border-slate-200">
                        <span>Total Paid:</span>
                        <span class="font-mono text-emerald-400 print:text-emerald-800">₹{{ number_format($selectedBill->net_amount_paise / 100, 2) }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1 shrink-0 print:hidden no-print">
                    <button wire:click="closeBillModal" type="button" class="min-h-11 flex-1 sm:flex-none px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition active:scale-95 cursor-pointer print:hidden no-print">
                        Close
                    </button>
                    <button type="button" onclick="window.print()" class="min-h-11 flex-1 sm:flex-none px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition active:scale-95 cursor-pointer print:hidden no-print">
                        Print Invoice
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- MOBILE STICKY BOTTOM NAVIGATION DOCK (< 768px)                -->
    <!-- ============================================================= -->
    <nav aria-label="Member Mobile Navigation"
        class="fixed bottom-0 left-0 right-0 z-40 md:hidden bg-slate-900/95 backdrop-blur-md border-t border-slate-800/90 text-slate-400 shadow-2xl select-none print:hidden no-print"
        style="padding-bottom: max(0.35rem, env(safe-area-inset-bottom, 0px)); padding-left: env(safe-area-inset-left, 0px); padding-right: env(safe-area-inset-right, 0px);">
        <div class="grid grid-cols-5 h-15 items-center px-1">
            <!-- 1. Card -->
            <button type="button" wire:click="setTab('card')" @click="scrollToTop()" aria-current="{{ $activeTab === 'card' ? 'page' : 'false' }}"
                class="relative flex flex-col items-center justify-center py-1 transition-all active:scale-95 cursor-pointer {{ $activeTab === 'card' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                @if($activeTab === 'card')
                    <span aria-hidden="true" class="absolute top-0 w-6 h-0.5 rounded-full bg-amber-400"></span>
                @endif
                <span class="p-1.5 rounded-xl border transition-colors {{ $activeTab === 'card' ? 'bg-amber-500/15 border-amber-500/30 text-amber-400' : 'border-transparent' }}">
                    <svg aria-hidden="true" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </span>
                <span class="text-[10px] tracking-tight mt-0.5 leading-none">Card</span>
            </button>

            <!-- 2. Wallet -->
            <button type="button" wire:click="setTab('wallet')" @click="scrollToTop()" aria-current="{{ $activeTab === 'wallet' ? 'page' : 'false' }}"
                class="relative flex flex-col items-center justify-center py-1 transition-all active:scale-95 cursor-pointer {{ $activeTab === 'wallet' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                @if($activeTab === 'wallet')
                    <span aria-hidden="true" class="absolute top-0 w-6 h-0.5 rounded-full bg-amber-400"></span>
                @endif
                <span class="p-1.5 rounded-xl border transition-colors {{ $activeTab === 'wallet' ? 'bg-amber-500/15 border-amber-500/30 text-amber-400' : 'border-transparent' }}">
                    <svg aria-hidden="true" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
                <span class="text-[10px] tracking-tight mt-0.5 leading-none">Wallet</span>
            </button>

            <!-- 3. Bookings -->
            <button type="button" wire:click="setTab('bookings')" @click="scrollToTop()" aria-current="{{ $activeTab === 'bookings' ? 'page' : 'false' }}"
                class="relative flex flex-col items-center justify-center py-1 transition-all active:scale-95 cursor-pointer {{ $activeTab === 'bookings' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                @if($activeTab === 'bookings')
                    <span aria-hidden="true" class="absolute top-0 w-6 h-0.5 rounded-full bg-amber-400"></span>
                @endif
                <span class="relative p-1.5 rounded-xl border transition-colors {{ $activeTab === 'bookings' ? 'bg-amber-500/15 border-amber-500/30 text-amber-400' : 'border-transparent' }}">
                    <svg aria-hidden="true" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    @if($upcomingBookingsCount > 0)
                        <span class="absolute -top-0.5 -right-0.5 h-3.5 min-w-3.5 px-1 rounded-full bg-amber-500 text-slate-950 text-[8px] font-extrabold font-mono flex items-center justify-center ring-2 ring-slate-900">
                            {{ $upcomingBookingsCount > 9 ? '9+' : $upcomingBookingsCount }}
                        </span>
                    @endif
                </span>
                <span class="text-[10px] tracking-tight mt-0.5 leading-none">Bookings</span>
            </button>

            <!-- 4. Bills -->
            <button type="button" wire:click="setTab('bills')" @click="scrollToTop()" aria-current="{{ $activeTab === 'bills' ? 'page' : 'false' }}"
                class="relative flex flex-col items-center justify-center py-1 transition-all active:scale-95 cursor-pointer {{ $activeTab === 'bills' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                @if($activeTab === 'bills')
                    <span aria-hidden="true" class="absolute top-0 w-6 h-0.5 rounded-full bg-amber-400"></span>
                @endif
                <span class="p-1.5 rounded-xl border transition-colors {{ $activeTab === 'bills' ? 'bg-amber-500/15 border-amber-500/30 text-amber-400' : 'border-transparent' }}">
                    <svg aria-hidden="true" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
                <span class="text-[10px] tracking-tight mt-0.5 leading-none">Bills</span>
            </button>

            <!-- 5. More (Native Modal Sheet Trigger) -->
            <button type="button" @click="openMoreSheet()" aria-haspopup="dialog" aria-controls="member-more-sheet"
                class="relative flex flex-col items-center justify-center py-1 transition-all active:scale-95 cursor-pointer {{ $isMoreTabActive ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
                @if($isMoreTabActive)
                    <span aria-hidden="true" class="absolute top-0 w-6 h-0.5 rounded-full bg-amber-400"></span>
                @endif
                <span class="relative p-1.5 rounded-xl border transition-colors {{ $isMoreTabActive ? 'bg-amber-500/15 border-amber-500/30 text-amber-400' : 'border-transparent' }}">
                    <svg aria-hidden="true" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    @if($unreadNoticesCount > 0)
                        <span class="absolute -top-0.5 -right-0.5 flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-rose-500 px-1 text-[8px] font-extrabold text-white ring-2 ring-slate-900">
                            {{ $unreadNoticesCount > 9 ? '9+' : $unreadNoticesCount }}
                        </span>
                    @endif
                </span>
                <span class="text-[10px] tracking-tight mt-0.5 leading-none">More</span>
            </button>
        </div>
    </nav>

    <!-- ============================================================= -->
    <!-- NATIVE DIALOG BOTTOM SHEET: Secondary Tabs & Account          -->
    <!-- ============================================================= -->
    <dialog id="member-more-sheet" x-ref="moreSheet" closedby="any" aria-labelledby="member-more-sheet-title" class="print:hidden no-print">
        <div class="bg-slate-900 border-t sm:border border-slate-800 rounded-t-3xl sm:rounded-3xl p-5 shadow-2xl space-y-4 text-slate-100 max-h-[86dvh] overflow-y-auto overscroll-contain"
            style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom, 0px));">
            <!-- Grab Handle & Header -->
            <div aria-hidden="true" class="w-10 h-1 rounded-full bg-slate-700 mx-auto -mt-1 sm:hidden"></div>
            <div class="flex items-center justify-between gap-2">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 block">Member Navigation</span>
                    <h3 id="member-more-sheet-title" class="text-base font-extrabold text-white tracking-tight">More &amp; Account</h3>
                </div>
                <button type="button" autofocus @click="closeMoreSheet()" aria-label="Close member menu"
                    class="min-w-11 min-h-11 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white inline-flex items-center justify-center text-lg font-bold cursor-pointer">
                    &times;
                </button>
            </div>

            <!-- Compact Profile Summary Card -->
            <div class="p-3.5 rounded-2xl bg-slate-950/90 border border-slate-800 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 p-0.5 shrink-0">
                        <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center font-bold text-amber-400 text-sm">
                            {{ substr($member->name, 0, 1) }}
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-extrabold text-white truncate">{{ $member->name }}</div>
                        <div class="text-[11px] text-slate-400 truncate">
                            <span class="font-mono text-amber-400 font-bold">{{ $member->membership_number }}</span>
                            &bull; {{ $member->category?->name ?? 'Ordinary Member' }}
                        </div>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold tracking-wider uppercase shrink-0 {{ $statusBadgeColor }} border">
                    <span class="w-1.5 h-1.5 rounded-full {{ $statusDotColor }} inline-block"></span> {{ ucfirst($statusVal) }}
                </span>
            </div>

            <!-- Secondary Destinations -->
            <div class="space-y-2">
                <button type="button" wire:click="setTab('plans')" @click="closeMoreSheet(); scrollToTop()"
                    aria-current="{{ $activeTab === 'plans' ? 'page' : 'false' }}"
                    class="w-full min-h-12 px-4 py-3 rounded-2xl border flex items-center justify-between gap-3 text-left transition active:scale-98 cursor-pointer {{ $activeTab === 'plans' ? 'bg-amber-500/15 border-amber-500/40 text-amber-300' : 'bg-slate-950/70 border-slate-800 text-slate-200 hover:bg-slate-800/80' }}">
                    <div class="flex items-center gap-3">
                        <span class="text-lg" aria-hidden="true">🏸</span>
                        <div>
                            <div class="text-xs font-extrabold">Sports &amp; Gym Plans</div>
                            <div class="text-[11px] text-slate-400">Badminton &amp; health club subscriptions</div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-amber-400 font-mono">
                        {{ $activeSubscriptions->count() }} Active
                    </span>
                </button>

                <button type="button" wire:click="setTab('family')" @click="closeMoreSheet(); scrollToTop()"
                    aria-current="{{ $activeTab === 'family' ? 'page' : 'false' }}"
                    class="w-full min-h-12 px-4 py-3 rounded-2xl border flex items-center justify-between gap-3 text-left transition active:scale-98 cursor-pointer {{ $activeTab === 'family' ? 'bg-amber-500/15 border-amber-500/40 text-amber-300' : 'bg-slate-950/70 border-slate-800 text-slate-200 hover:bg-slate-800/80' }}">
                    <div class="flex items-center gap-3">
                        <span class="text-lg" aria-hidden="true">👨‍👩‍👦</span>
                        <div>
                            <div class="text-xs font-extrabold">Family &amp; Dependents</div>
                            <div class="text-[11px] text-slate-400">Club access &amp; wallet privileges</div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-300 font-mono">
                        {{ $dependents->count() }}
                    </span>
                </button>

                <button type="button" wire:click="setTab('notices')" @click="closeMoreSheet(); scrollToTop()"
                    aria-current="{{ $activeTab === 'notices' ? 'page' : 'false' }}"
                    class="w-full min-h-12 px-4 py-3 rounded-2xl border flex items-center justify-between gap-3 text-left transition active:scale-98 cursor-pointer {{ $activeTab === 'notices' ? 'bg-amber-500/15 border-amber-500/40 text-amber-300' : 'bg-slate-950/70 border-slate-800 text-slate-200 hover:bg-slate-800/80' }}">
                    <div class="flex items-center gap-3">
                        <span class="text-lg" aria-hidden="true">🔔</span>
                        <div>
                            <div class="text-xs font-extrabold">Club Notices &amp; Bulletins</div>
                            <div class="text-[11px] text-slate-400">Announcements &amp; due reminders</div>
                        </div>
                    </div>
                    @if($unreadNoticesCount > 0)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500 text-white font-mono">
                            {{ $unreadNoticesCount }} unread
                        </span>
                    @else
                        <span class="text-[11px] text-slate-400">&rarr;</span>
                    @endif
                </button>
            </div>

            <!-- Install App Option (Only shown when browser confirms installability) -->
            <button x-cloak x-show="canInstall && !isStandalone" type="button"
                @click="closeMoreSheet(); installApp()"
                :aria-disabled="installing ? 'true' : null"
                class="w-full min-h-11 px-4 py-2.5 rounded-2xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/40 text-amber-300 text-xs font-extrabold flex items-center justify-center gap-2 transition active:scale-98 cursor-pointer">
                <span aria-hidden="true">📲</span> Install Malabar Club App
            </button>

            <!-- Sign Out & Ledger Attribution -->
            <div class="pt-3 border-t border-slate-800 space-y-3">
                <form method="POST" action="{{ route('member.logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full min-h-11 px-4 py-2.5 rounded-2xl bg-rose-950/40 hover:bg-rose-900/50 border border-rose-800/60 text-rose-300 text-xs font-extrabold flex items-center justify-center gap-2 transition active:scale-98 cursor-pointer">
                        <svg aria-hidden="true" width="15" height="15" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>Sign Out of Member Portal</span>
                    </button>
                </form>
                <p class="text-[10px] text-center text-slate-500 font-mono">
                    {{ config('club.name', 'Malabar Club') }} &bull; Server-Authoritative Ledger System
                </p>
            </div>
        </div>
    </dialog>
</div>
