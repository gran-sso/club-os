<x-filament-panels::page>
    <div class="space-y-4 text-slate-800 dark:text-slate-100 {{ ($showLetterViewModal || $showInvoiceModal) ? 'print:hidden' : '' }}">
        
        <!-- ========================================== -->
        <!-- 1. TOP HERO HEADER CARD                    -->
        <!-- ========================================== -->
        <div class="p-4 sm:p-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                
                <!-- Left: Avatar, Name & Identifiers -->
                <div class="flex items-start sm:items-center gap-3 sm:gap-4">
                    @php
                        $nameParts = explode(' ', trim($member->name));
                        $initials = count($nameParts) >= 2 
                            ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
                            : strtoupper(substr($nameParts[0] ?? 'M', 0, 2));
                    @endphp
                    <div class="w-12 h-12 rounded-xl bg-slate-900 dark:bg-slate-100 flex items-center justify-center font-bold text-white dark:text-slate-900 text-base shrink-0 select-none shadow-2xs">
                        {{ $initials }}
                    </div>

                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                # {{ $member->membership_number }}
                            </span>

                            <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                {{ $member->category?->name ?? 'Standard Category' }}
                            </span>

                            @php
                                $statusVal = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
                                $statusClasses = match ($statusVal) {
                                    'active' => 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                                    'suspended' => 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
                                    'resigned' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
                                    default => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
                                };
                            @endphp
                            <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-md border {{ $statusClasses }}">
                                {{ ucfirst($statusVal) }}
                            </span>

                            @if (($member->fee_status ?? 'pending') === 'pending')
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-700 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Fee Pending</span>
                                </span>
                            @endif
                        </div>

                        <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                            {{ $member->name }}
                        </h1>

                        <!-- Contact & Chips -->
                        <div class="flex flex-wrap items-center gap-y-1 gap-x-3 text-xs text-slate-500 dark:text-slate-400">
                            @if ($member->phone)
                                <a href="tel:{{ $member->phone }}" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-slate-200 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    <span>{{ $member->phone }}</span>
                                </a>
                            @endif

                            @if ($member->email)
                                <a href="mailto:{{ $member->email }}" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-slate-200 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="truncate max-w-[200px]">{{ $member->email }}</span>
                                </a>
                            @endif

                            <div class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span>Joined: <strong class="text-slate-700 dark:text-slate-300">{{ $member->joined_at ? $member->joined_at->format('d M Y') : 'Founding' }}</strong></span>
                            </div>

                            <div class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                <span>Card: <strong class="font-mono text-slate-900 dark:text-white">{{ $member->activeCard?->card_number ?? 'No active card' }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Authoritative Live Wallet Balance Widget -->
                <div class="flex flex-row lg:flex-col items-center lg:items-end justify-between lg:justify-center px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950/80 rounded-xl border border-slate-200/90 dark:border-slate-800 min-w-[200px]">
                    <div class="text-left lg:text-right">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 block">Prepaid Wallet</span>
                        <div class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono tracking-tight">
                            ₹{{ number_format($balanceRupees, 2) }}
                        </div>
                        <span class="text-[9px] text-slate-500 dark:text-slate-400 block uppercase font-medium tracking-tight">Strict No-Credit</span>
                    </div>

                    @if ($reservedRupees > 0)
                        <div class="mt-1.5 text-left lg:text-right text-[11px] font-medium text-slate-600 dark:text-slate-400 border-t border-slate-200 dark:border-slate-800 pt-1 w-full">
                            <span>Reserved: ₹{{ number_format($reservedRupees, 2) }}</span>
                            <span class="block text-slate-500 font-mono">Available: ₹{{ number_format($availableRupees, 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Action Bar (Compact, Monochrome Enterprise Styling) -->
            <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 print:hidden no-print">
                
                <!-- Left: Core Actions & Categorized Menu -->
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    
                    <!-- 1. Top-Up Wallet (Primary Monochrome Action) -->
                    <button wire:click="openTopUpModal" type="button"
                        class="h-8.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:active:bg-slate-200 text-white dark:text-slate-950 font-semibold text-xs transition-colors shadow-2xs inline-flex items-center justify-center gap-1.5 cursor-pointer flex-1 sm:flex-initial">
                        <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span>Top-Up Wallet</span>
                    </button>

                    <!-- 2. Unified Member Actions Menu (Alpine.js Popover / Bottom Sheet) -->
                    <div x-data="{ open: false }" class="relative inline-block text-left flex-1 sm:flex-initial">
                        <button @click="open = !open" type="button"
                            class="h-8.5 w-full sm:w-auto px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 active:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors border border-slate-300 dark:border-slate-700 shadow-2xs inline-flex items-center justify-center gap-1.5 cursor-pointer select-none">
                            <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                            </svg>
                            <span>Member Actions</span>
                            <svg class="w-3 h-3 text-slate-400 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Mobile Backdrop (click to close on mobile) -->
                        <div x-show="open" x-cloak
                            @click="open = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs sm:hidden"></div>

                        <!-- Dropdown Menu / Mobile Bottom Sheet -->
                        <div x-show="open" x-cloak
                            @click.outside="open = false"
                            @keydown.escape.window="open = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-2 sm:scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-2 sm:scale-95"
                            class="fixed sm:absolute bottom-0 sm:bottom-auto sm:top-full left-0 sm:left-0 right-0 sm:right-auto z-50 w-full sm:w-[380px] max-h-[85vh] sm:max-h-[540px] overflow-y-auto bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl p-3 sm:p-3 divide-y divide-slate-100 dark:divide-slate-800 space-y-2.5">
                            
                            <!-- Header (Mobile Sheet title) -->
                            <div class="flex items-center justify-between pb-1.5">
                                <div>
                                    <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Member Services &amp; Actions</h4>
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">{{ $member->name }} &bull; #{{ $member->membership_number }}</p>
                                </div>
                                <button @click="open = false" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 sm:hidden cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Group 1: Front-Desk Facilities & Bookings -->
                            <div class="pt-2 space-y-0.5">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block px-1.5 mb-1">Front-Desk &amp; Sports Bookings</span>
                                
                                <button type="button" @click="open = false; $wire.openBookCourtModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Book Badminton Court</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Reserve 60-min slot with plan entitlement check</span>
                                    </div>
                                </button>

                                <button type="button" @click="open = false; $wire.openBookRoomModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Reserve Guest Room</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Daily accommodation booking &amp; deposits</span>
                                    </div>
                                </button>

                                <button type="button" @click="open = false; $wire.openAssignLockerModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Assign Club Locker</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Kitbag storage allocation &amp; key assignment</span>
                                    </div>
                                </button>

                                <button type="button" @click="open = false; $wire.openIntroLetterModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Affiliated Club Intro Letter</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Generate formal reciprocal visit certificate</span>
                                    </div>
                                </button>
                            </div>

                            <!-- Group 2: Membership & Administration -->
                            <div class="pt-2 space-y-0.5">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block px-1.5 mb-1">Membership &amp; Secretarial</span>

                                <button type="button" @click="open = false; $wire.openRenewModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Renew Membership Dues</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Extend term validity with prepaid wallet balance check</span>
                                    </div>
                                </button>

                                <button type="button" @click="open = false; $wire.openCardModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">
                                            {{ $member->activeCard ? 'Replace RFID Smartcard' : 'Issue RFID Smartcard' }}
                                        </span>
                                        <span class="text-[10px] text-slate-500 block truncate">
                                            {{ $member->activeCard ? 'Card #' . $member->activeCard->card_number . ' (retains balance)' : 'Link new contactless card UID' }}
                                        </span>
                                    </div>
                                </button>

                                <button type="button" @click="open = false; $wire.openDependentModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Register Family Dependent</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Spouse &amp; child roster &amp; wallet spending controls</span>
                                    </div>
                                </button>

                                <button type="button" @click="open = false; $wire.openAddNoteModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Add Administrative Note</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Record committee directive or dining preference</span>
                                    </div>
                                </button>

                                <button type="button" @click="open = false; $wire.openStatusModal()"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Change Lifecycle Status</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Active, Suspended, or Resigned standing</span>
                                    </div>
                                </button>

                                <a href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('edit', ['record' => $member]) }}"
                                    class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                    <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 group-hover:bg-slate-200 dark:group-hover:bg-slate-700 group-hover:text-slate-900 dark:group-hover:text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white block">Edit Member Master</span>
                                        <span class="text-[10px] text-slate-500 block truncate">Update contact, address, and statutory KYC proofs</span>
                                    </div>
                                </a>
                            </div>

                            <!-- Group 3: Gate Security -->
                            <div class="pt-2 space-y-0.5">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block px-1.5 mb-1">Turnstile &amp; Gate Security</span>

                                @if ($member->activeCard)
                                    <button type="button" @click="open = false; $wire.blockActiveCard()"
                                        class="w-full p-2 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                        <div class="w-7 h-7 rounded-md bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0 group-hover:bg-rose-100 dark:group-hover:bg-rose-900">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <span class="text-xs font-semibold text-rose-700 dark:text-rose-400 block">Emergency Block Gate Access</span>
                                            <span class="text-[10px] text-rose-500 block truncate">Immediately barricade RFID card from turnstiles</span>
                                        </div>
                                    </button>
                                @endif

                                @php
                                    $blockedCard = $member->cards->where('status', \App\Domain\Shared\Enums\CardStatus::BLOCKED)->first();
                                @endphp
                                @if ($blockedCard && !$member->activeCard)
                                    <button type="button" @click="open = false; $wire.unblockCard({{ $blockedCard->id }})"
                                        class="w-full p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-left transition-colors flex items-center gap-2.5 cursor-pointer group">
                                        <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center shrink-0 group-hover:bg-slate-200 dark:group-hover:bg-slate-700">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <span class="text-xs font-semibold text-slate-900 dark:text-white block">Restore Gate Access</span>
                                            <span class="text-[10px] text-slate-500 block truncate">Unblock card {{ $blockedCard->card_number }}</span>
                                        </div>
                                    </button>
                                @endif
                            </div>

                        </div>
                    </div>

                    <!-- 3. WhatsApp Direct Share Button (If phone exists) -->
                    @if ($member->phone)
                        <a href="{{ $whatsappUrl }}" target="_blank"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 active:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors border border-slate-300 dark:border-slate-700 shadow-2xs inline-flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.045-1.013-.075-.242-.077-.552-.19-1.002-.387-1.854-.808-3.056-2.698-3.149-2.82-.092-.123-.746-.991-.746-1.89 0-.9.472-1.343.64-1.528.169-.185.367-.231.49-.231.122 0 .245.002.352.007.112.005.263-.042.411.314.154.372.525 1.28.572 1.374.046.094.077.204.015.328-.061.123-.092.2-.184.308-.092.108-.193.241-.276.323-.092.093-.188.193-.081.377.108.185.478.788 1.025 1.275.704.628 1.298.822 1.482.915.185.092.293.077.4-.046.108-.123.461-.539.585-.724.123-.185.246-.154.415-.092.169.061 1.077.508 1.261.6.185.093.308.139.354.216.046.077.046.446-.098.851zM12 2C6.477 2 2 6.477 2 12c0 1.891.526 3.66 1.442 5.176L2 22l4.981-1.306C8.423 21.524 10.154 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
                            </svg>
                            <span>WhatsApp Statement</span>
                        </a>
                    @endif

                </div>

                <!-- Right: Print Statement Button -->
                <button type="button" wire:click="printStatement"
                    class="h-8.5 px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 active:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors border border-slate-300 dark:border-slate-700 shadow-2xs inline-flex items-center justify-center gap-1.5 cursor-pointer shrink-0">
                    <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>Print Statement</span>
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 2. MOBILE HORIZONTAL SCROLLING TAB BAR    -->
        <!-- ========================================== -->
        <div class="relative print:hidden no-print">
            <div class="flex items-center gap-1 overflow-x-auto no-scrollbar p-1 bg-slate-100 dark:bg-slate-800/90 rounded-xl border border-slate-200/90 dark:border-slate-700/80 scroll-smooth">
                
                <!-- Tab: Overview -->
                <button type="button" wire:click="setActiveTab('overview')"
                    class="h-8.5 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer select-none {{ $activeTab === 'overview' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-2xs border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-700/50' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span>Overview &amp; KYC</span>
                </button>

                <!-- Tab: Wallet Ledger -->
                <button type="button" wire:click="setActiveTab('wallet')"
                    class="h-8.5 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer select-none {{ $activeTab === 'wallet' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-2xs border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-700/50' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span>Prepaid Wallet Ledger</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-medium bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                        ₹{{ number_format($balanceRupees, 2) }}
                    </span>
                </button>

                <!-- Tab: Bills & Invoices -->
                <button type="button" wire:click="setActiveTab('bills')"
                    class="h-8.5 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer select-none {{ $activeTab === 'bills' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-2xs border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-700/50' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Invoices &amp; Bills</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                        {{ $totalBillsCount }}
                    </span>
                </button>

                <!-- Tab: Sports & Facilities -->
                <button type="button" wire:click="setActiveTab('facilities')"
                    class="h-8.5 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer select-none {{ $activeTab === 'facilities' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-2xs border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-700/50' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span>Sports &amp; Facilities</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                        {{ $facilitiesCount }}
                    </span>
                </button>

                <!-- Tab: Gate & Turnstile Logs -->
                <button type="button" wire:click="setActiveTab('access')"
                    class="h-8.5 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer select-none {{ $activeTab === 'access' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-2xs border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-700/50' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <span>Gate &amp; Turnstile Logs</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                        {{ $accessEvents->count() }}
                    </span>
                </button>

                <!-- Tab: Dependents & Guests -->
                <button type="button" wire:click="setActiveTab('dependents')"
                    class="h-8.5 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shrink-0 transition-colors cursor-pointer select-none {{ $activeTab === 'dependents' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-2xs border border-slate-200 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-700/50' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span>Family &amp; Guests</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                        {{ $dependents->count() + $sponsoredGuests->count() }}
                    </span>
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 3. TAB 1: OVERVIEW & KYC                  -->
        <!-- ========================================== -->
        @if ($activeTab === 'overview')
            <div class="space-y-6">
                <!-- 4 KPI Metrics Grid (Mobile 2x2, Desktop 4x1) -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <!-- Metric 1: Total Recharged -->
                    <div class="p-4 sm:p-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs">
                        <div class="flex items-center justify-between text-slate-400 mb-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider">Lifetime Deposits</span>
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                                </svg>
                            </div>
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">
                            ₹{{ number_format($totalRechargeRupees, 2) }}
                        </div>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400">Total credited to wallet</span>
                    </div>

                    <!-- Metric 2: Total Spent -->
                    <div class="p-4 sm:p-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs">
                        <div class="flex items-center justify-between text-slate-400 mb-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider">Lifetime Consumption</span>
                            <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                                </svg>
                            </div>
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">
                            ₹{{ number_format($totalSpendRupees, 2) }}
                        </div>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400">Total wallet debits</span>
                    </div>

                    <!-- Metric 3: Total Bills Count -->
                    <div class="p-4 sm:p-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs">
                        <div class="flex items-center justify-between text-slate-400 mb-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider">Tax Invoices</span>
                            <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">
                            {{ $totalBillsCount }}
                        </div>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400">₹{{ number_format($totalPaidBillsRupees, 2) }} total paid</span>
                    </div>

                    <!-- Metric 4: Bookings & Subscriptions -->
                    <div class="p-4 sm:p-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs">
                        <div class="flex items-center justify-between text-slate-400 mb-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider">Club Facilities</span>
                            <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">
                            {{ $facilitiesCount }}
                        </div>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400">Badminton, gym, rooms</span>
                    </div>
                </div>

                <!-- Two-Column Cards: KYC & Governance Details -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    
                    <!-- Card 1: Statutory KYC & Identity Details -->
                    <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                </svg>
                                <span>Statutory KYC &amp; Personal Records</span>
                            </h3>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                Verified
                            </span>
                        </div>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">First Name</dt>
                                <dd class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $member->person?->first_name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">Last Name</dt>
                                <dd class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $member->person?->last_name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">Primary Mobile Phone</dt>
                                <dd class="text-sm font-bold text-slate-900 dark:text-white mt-0.5 font-mono">{{ $member->person?->phone ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">Email Address</dt>
                                <dd class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $member->person?->email ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">Statutory KYC ID Proof</dt>
                                <dd class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                                    {{ $member->person?->id_proof_type ?? 'Aadhaar Card' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">ID Proof Number</dt>
                                <dd class="text-sm font-mono font-bold text-slate-900 dark:text-white mt-0.5">
                                    {{ $member->person?->id_proof_number ?? 'Not Recorded' }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-[11px] font-bold uppercase text-slate-400">Residential Address / Notation</dt>
                                <dd class="text-xs text-slate-700 dark:text-slate-300 mt-0.5 bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
                                    {{ $member->person?->notes ?: 'Address on file at Clubhouse Secretariat, Vadakkencherry, Palakkad.' }}
                                </dd>
                            </div>

                            <div class="sm:col-span-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <dt class="text-[11px] font-bold uppercase text-slate-400 mb-1.5 flex items-center justify-between">
                                    <span>Statutory Document Vault (Confidential &bull; Private Access Only)</span>
                                    <span class="text-[10px] text-emerald-600 font-bold uppercase">Encrypted Storage</span>
                                </dt>
                                <dd class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @php
                                        $hasIdProof = !empty($member->person?->id_proof_path);
                                        $hasAppForm = !empty($member->person?->application_form_path);
                                        $editMemberUrl = \App\Filament\Resources\Members\MemberResource::getUrl('edit', ['record' => $member]);
                                    @endphp

                                    <!-- 1. Statutory Identity Proof -->
                                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/90 dark:border-slate-700/80 text-slate-700 dark:text-slate-300">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-xs truncate">{{ $member->person?->id_proof_type ?? 'Statutory ID Proof' }}</div>
                                                <div class="text-[10px] text-slate-500 flex items-center gap-1.5">
                                                    @if ($hasIdProof)
                                                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            Uploaded
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                            Not Uploaded
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        @if ($hasIdProof && auth()->user()?->hasClubPermission('members.kyc_view'))
                                            <a href="/documents/members/{{ $member->id }}/id_proof.jpg" target="_blank"
                                                class="px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:text-slate-900 dark:text-slate-200 dark:hover:text-white bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg shadow-2xs hover:bg-slate-50 transition shrink-0 inline-flex items-center gap-1">
                                                <span>View Proof</span>
                                                <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        @elseif ($hasIdProof)
                                            <span class="px-2.5 py-1 text-[11px] font-medium text-slate-400 dark:text-slate-500">Restricted</span>
                                        @elseif (auth()->user()?->hasClubPermission('members.manage'))
                                            <a href="{{ $editMemberUrl }}"
                                                class="px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg transition shrink-0 inline-flex items-center gap-1">
                                                <span>Upload</span>
                                                <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        @endif
                                    </div>

                                    <!-- 2. Membership Application Form -->
                                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/90 dark:border-slate-700/80 text-slate-700 dark:text-slate-300">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-xs truncate">Application Form</div>
                                                <div class="text-[10px] text-slate-500 flex items-center gap-1.5">
                                                    @if ($hasAppForm)
                                                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            Uploaded
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                            Not Uploaded
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        @if ($hasAppForm && auth()->user()?->hasClubPermission('members.kyc_view'))
                                            <a href="/documents/members/{{ $member->id }}/application_form.pdf" target="_blank"
                                                class="px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:text-slate-900 dark:text-slate-200 dark:hover:text-white bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg shadow-2xs hover:bg-slate-50 transition shrink-0 inline-flex items-center gap-1">
                                                <span>View Form</span>
                                                <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        @elseif ($hasAppForm)
                                            <span class="px-2.5 py-1 text-[11px] font-medium text-slate-400 dark:text-slate-500">Restricted</span>
                                        @elseif (auth()->user()?->hasClubPermission('members.manage'))
                                            <a href="{{ $editMemberUrl }}"
                                                class="px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg transition shrink-0 inline-flex items-center gap-1">
                                                <span>Upload</span>
                                                <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        @endif
                                    </div>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Card 2: Club Governance, Cards & Classification -->
                    <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <span>Membership Classification &amp; Access</span>
                            </h3>
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-lg bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                {{ $member->category?->code ?? 'MEM' }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">Membership Category</dt>
                                <dd class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $member->category?->name ?? 'Standard' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">Category Monthly Dues</dt>
                                <dd class="text-sm font-bold text-slate-900 dark:text-white mt-0.5 font-mono">
                                    ₹{{ number_format(($member->category?->monthly_fee_paise ?? 0) / 100, 2) }} / mo
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">General Body Voting Rights</dt>
                                <dd class="text-sm font-bold mt-0.5">
                                    @if ($member->category?->can_vote ?? true)
                                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span>Eligible Voting Member</span>
                                        </span>
                                    @else
                                        <span class="text-slate-500">Non-Voting Class</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase text-slate-400">Validity / Expiry Date</dt>
                                <dd class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                                    {{ $member->valid_until ? $member->valid_until->format('d M Y') : 'Lifetime (No Expiry)' }}
                                </dd>
                            </div>
                            
                            <!-- RFID Hardware Cards Assigned -->
                            <div class="sm:col-span-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                                <dt class="text-[11px] font-bold uppercase text-slate-400 mb-1.5">Registered RFID Physical Cards</dt>
                                <dd class="space-y-1.5">
                                    @forelse ($member->cards as $card)
                                        <div class="flex items-center justify-between text-xs p-2 rounded-xl {{ $card->status->value === 'active' ? 'bg-blue-50/70 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-900' : 'bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700' }}">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $card->card_number }}</span>
                                                <span class="text-[10px] text-slate-500">Issued: {{ $card->issued_at?->format('d M Y') }}</span>
                                            </div>
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $card->status->value === 'active' ? 'bg-emerald-600 text-white' : 'bg-slate-300 dark:bg-slate-700 text-slate-700 dark:text-slate-300' }}">
                                                {{ $card->status->value }}
                                            </span>
                                        </div>
                                    @empty
                                        <div class="text-xs text-slate-400 italic">No physical RFID cards registered yet. Click "Issue RFID Card" above.</div>
                                    @endforelse
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Card: Annual Subscription & Statutory Dues -->
                <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                                </svg>
                                <span>Statutory Membership Fees &amp; Renewal Tracker</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                One-time admission fee (capital ledger) and annual subscription dues (operational dues) tracker.
                            </p>
                        </div>

                        <button wire:click="openRenewModal" type="button"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition-colors shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>{{ (($admissionFeeInfo['status'] ?? 'paid') === 'pending' || ($annualDuesInfo['fee_status'] ?? 'paid') === 'pending') ? 'Collect Fee Now' : 'Renew Subscription' }}</span>
                        </button>
                    </div>

                    @if (($admissionFeeInfo['status'] ?? 'paid') === 'pending' || ($annualDuesInfo['fee_status'] ?? 'paid') === 'pending')
                        <div class="p-3.5 rounded-xl bg-amber-50/90 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800/80 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">Initial Membership Admission Fee Pending Payment</h4>
                                    <p class="text-[11px] text-slate-600 dark:text-slate-400">
                                        @if (($admissionFeeInfo['status'] ?? 'paid') === 'pending' && ($annualDuesInfo['fee_status'] ?? 'paid') === 'pending')
                                            One-Time Admission Fee (<strong class="text-slate-900 dark:text-white font-mono">₹{{ number_format($admissionFeeInfo['admission_fee_rupees'], 2) }}</strong>) and Annual Subscription Dues (<strong class="text-slate-900 dark:text-white font-mono">₹{{ number_format($annualDuesInfo['annual_fee_rupees'], 2) }}</strong>) are outstanding. Payment has not yet been recorded for this membership.
                                        @elseif (($admissionFeeInfo['status'] ?? 'paid') === 'pending')
                                            One-Time Admission Fee of <strong class="text-slate-900 dark:text-white font-mono">₹{{ number_format($admissionFeeInfo['admission_fee_rupees'], 2) }}</strong> is outstanding. Payment has not yet been recorded for this membership.
                                        @else
                                            Annual Subscription Dues of <strong class="text-slate-900 dark:text-white font-mono">₹{{ number_format($annualDuesInfo['annual_fee_rupees'], 2) }}</strong> are outstanding. Payment has not yet been recorded for this membership.
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <button wire:click="openRenewModal" type="button"
                                class="h-8 px-3 rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 font-semibold text-xs shadow-2xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                <span>Collect Fee Now</span>
                                <span aria-hidden="true">&rarr;</span>
                            </button>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 text-xs">
                        <!-- Tile 1: One-Time Admission Fee -->
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                            <span class="text-[11px] font-bold uppercase text-slate-400 block">One-Time Admission Fee</span>
                            <span class="text-lg font-black text-slate-900 dark:text-white font-mono mt-0.5 block">
                                ₹{{ number_format($admissionFeeInfo['admission_fee_rupees'], 2) }}
                            </span>
                            <div class="mt-1">
                                @if (($admissionFeeInfo['status'] ?? 'pending') === 'paid')
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                        Paid &bull; Lifetime
                                    </span>
                                @elseif (($admissionFeeInfo['status'] ?? 'pending') === 'waived')
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700">
                                        Waived / Exempt
                                    </span>
                                @else
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                        Pending Payment
                                    </span>
                                @endif
                            </div>
                            <span class="text-[10px] text-slate-500 block mt-1 truncate">
                                @if (($admissionFeeInfo['status'] ?? 'pending') === 'paid')
                                    {{ !empty($admissionFeeInfo['receipt_number']) ? 'Rcpt: ' . $admissionFeeInfo['receipt_number'] : $admissionFeeInfo['paid_at_formatted'] }}
                                @else
                                    Capital entrance dues
                                @endif
                            </span>
                        </div>

                        <!-- Tile 2: Annual Subscription Fee -->
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                            <span class="text-[11px] font-bold uppercase text-slate-400 block">Annual Subscription</span>
                            <span class="text-lg font-black text-slate-900 dark:text-white font-mono mt-0.5 block">
                                ₹{{ number_format($annualDuesInfo['annual_fee_rupees'], 2) }}
                            </span>
                            <div class="mt-1">
                                @if ($annualDuesInfo['status'] === 'fee_pending')
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                        Fee Pending
                                    </span>
                                @elseif ($annualDuesInfo['status'] === 'active')
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                        Good Standing &bull; Paid
                                    </span>
                                @elseif ($annualDuesInfo['status'] === 'expiring_soon')
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                        Expiring in {{ $annualDuesInfo['days_remaining'] }}d
                                    </span>
                                @else
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-700">
                                        Expired / Due
                                    </span>
                                @endif
                            </div>
                            <span class="text-[10px] text-slate-500 block mt-1">Per statutory bylaws</span>
                        </div>

                        <!-- Tile 3: Membership Standing -->
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                            <span class="text-[11px] font-bold uppercase text-slate-400 block">Membership Standing</span>
                            <div class="mt-1">
                                @if (($admissionFeeInfo['status'] ?? 'paid') === 'pending' || $annualDuesInfo['status'] === 'fee_pending')
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                        Fee Pending Payment
                                    </span>
                                @elseif ($annualDuesInfo['status'] === 'active')
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                        Good Standing
                                    </span>
                                @elseif ($annualDuesInfo['status'] === 'expiring_soon')
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                        Renew Soon
                                    </span>
                                @else
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-700">
                                        Expired / Due
                                    </span>
                                @endif
                            </div>
                            <span class="text-[10px] text-slate-500 block mt-1">
                                {{ (($admissionFeeInfo['status'] ?? 'paid') === 'pending' || $annualDuesInfo['status'] === 'fee_pending') ? 'Dues settlement required' : 'Voting & facility eligible' }}
                            </span>
                        </div>

                        <!-- Tile 4: Current Term Valid Until -->
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                            <span class="text-[11px] font-bold uppercase text-slate-400 block">Current Term Valid Until</span>
                            @if ($annualDuesInfo['status'] === 'fee_pending')
                                <span class="text-sm font-bold text-amber-700 dark:text-amber-400 font-mono mt-0.5 block">
                                    Pending Settlement
                                </span>
                                <span class="text-[10px] text-slate-500 block">Validity inactive</span>
                            @else
                                <span class="text-sm font-black text-slate-900 dark:text-white font-mono mt-0.5 block truncate">
                                    {{ $annualDuesInfo['valid_until_formatted'] }}
                                </span>
                                <span class="text-[10px] text-slate-500 block">Renewal window open</span>
                            @endif
                        </div>

                        <!-- Tile 5: Last Renewal / Settlement -->
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                            <span class="text-[11px] font-bold uppercase text-slate-400 block">Last Renewal / Settlement</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white font-mono mt-0.5 block truncate">
                                {{ $annualDuesInfo['last_renewed_formatted'] }}
                            </span>
                            @if (!empty($annualDuesInfo['last_receipt_number']))
                                <span class="text-[10px] text-slate-500 font-mono block truncate">Rcpt: {{ $annualDuesInfo['last_receipt_number'] }}</span>
                            @else
                                <span class="text-[10px] text-slate-500 block">Roster record</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Card: Dining & Consumption Analytics -->
                <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>Dining &amp; Department Consumption Insights</span>
                        </h3>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                            Hospitality Intelligence
                        </span>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <!-- Left: Department Breakdown -->
                        <div class="space-y-2">
                            <span class="text-[11px] font-bold uppercase text-slate-400 block mb-1">Spend Distribution by Club Outlet</span>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">Food Contractor &amp; Restaurant</span>
                                    </div>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format($departmentSpend['restaurant'] ?? 0, 2) }}</span>
                                </div>

                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">Bar &amp; Lounge Counter</span>
                                    </div>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format($departmentSpend['bar'] ?? 0, 2) }}</span>
                                </div>

                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">Sports &amp; Badminton Courts</span>
                                    </div>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format($departmentSpend['sports'] ?? 0, 2) }}</span>
                                </div>

                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">Card Room &amp; Recreation</span>
                                    </div>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format($departmentSpend['card_room'] ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Favorite Items -->
                        <div class="space-y-2">
                            <span class="text-[11px] font-bold uppercase text-slate-400 block mb-1">Most Frequently Ordered Items</span>
                            @if ($favoriteItems->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach ($favoriteItems as $fav)
                                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 text-xs">
                                            <div>
                                                <span class="font-bold text-slate-900 dark:text-white block">{{ $fav['name'] }}</span>
                                                <span class="text-[10px] text-slate-500 uppercase font-semibold">{{ $fav['department'] }} &bull; {{ $fav['quantity'] }} orders</span>
                                            </div>
                                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200 text-xs">₹{{ number_format($fav['total_rupees'], 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-6 text-center text-slate-400 text-xs border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                                    No itemized consumption lines recorded yet. F&amp;B preferences will populate automatically after counter billing.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Card 3: Confidential Committee & Staff Notes (Internal Audit) -->
                <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <span>Confidential Committee &amp; Staff Notes</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Internal remarks recorded by Secretarial and Managing Committee staff. Strictly restricted from public member views.
                            </p>
                        </div>

                        <button wire:click="openAddNoteModal" type="button"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition-colors shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span>Add Administrative Note</span>
                        </button>
                    </div>

                    <div class="space-y-2.5">
                        @forelse ($committeeNotes as $cNote)
                            @php
                                $catBadge = match ($cNote->category) {
                                    'committee_directive' => 'bg-purple-100 dark:bg-purple-950 text-purple-800 dark:text-purple-300 border-purple-200',
                                    'disciplinary' => 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-200',
                                    'hospitality' => 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-200',
                                    default => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200',
                                };
                            @endphp
                            <div class="p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md border {{ $catBadge }}">
                                            {{ str_replace('_', ' ', $cNote->category) }}
                                        </span>
                                        <span class="text-slate-500 font-medium">Recorded by <strong class="text-slate-800 dark:text-slate-200">{{ $cNote->author?->name ?? 'Secretariat Staff' }}</strong></span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 font-mono">{{ $cNote->created_at->format('d M Y, H:i') }}</span>
                                </div>
                                <p class="text-slate-800 dark:text-slate-200 font-medium leading-relaxed pl-0.5">
                                    {{ $cNote->note }}
                                </p>
                            </div>
                        @empty
                            <div class="p-6 text-center text-slate-400 italic text-xs bg-slate-50 dark:bg-slate-800/30 rounded-xl border border-dashed border-slate-200 dark:border-slate-800">
                                No confidential committee or administrative notes recorded for this member. Click "Add Administrative Note" above to log a memo.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 4. TAB 2: PREPAID WALLET LEDGER           -->
        <!-- ========================================== -->
        @if ($activeTab === 'wallet')
            <div class="space-y-5">
                <!-- Wallet Header Bar with Actions -->
                <div class="p-3.5 sm:p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/90 dark:border-slate-800 shadow-2xs flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Server-Authoritative Ledger</div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Prepaid Account Transaction History</h2>
                        <p class="text-xs text-slate-500">Backed by immutable double-entry journal vouchers in MySQL.</p>
                    </div>

                    <div class="flex items-center gap-1.5 print:hidden no-print">
                        <button wire:click="openTopUpModal" type="button"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition-colors shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span>Top-Up Balance</span>
                        </button>

                        @if ($member->phone)
                            <a href="{{ $whatsappUrl }}" target="_blank"
                                class="h-8.5 px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 active:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors border border-slate-300 dark:border-slate-700 shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                                <svg class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.634.045-1.013-.075-.242-.077-.552-.19-1.002-.387-1.854-.808-3.056-2.698-3.149-2.82-.092-.123-.746-.991-.746-1.89 0-.9.472-1.343.64-1.528.169-.185.367-.231.49-.231.122 0 .245.002.352.007.112.005.263-.042.411.314.154.372.525 1.28.572 1.374.046.094.077.204.015.328-.061.123-.092.2-.184.308-.092.108-.193.241-.276.323-.092.093-.188.193-.081.377.108.185.478.788 1.025 1.275.704.628 1.298.822 1.482.915.185.092.293.077.4-.046.108-.123.461-.539.585-.724.123-.185.246-.154.415-.092.169.061 1.077.508 1.261.6.185.093.308.139.354.216.046.077.046.446-.098.851zM12 2C6.477 2 2 6.477 2 12c0 1.891.526 3.66 1.442 5.176L2 22l4.981-1.306C8.423 21.524 10.154 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
                                </svg>
                                <span>Share WhatsApp</span>
                            </a>
                        @endif

                        <button type="button" onclick="window.print()"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 active:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors border border-slate-300 dark:border-slate-700 shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <span>Print</span>
                        </button>
                    </div>
                </div>

                <!-- Ledger Table with Horizontal Mobile Scroll -->
                <div class="sm:hidden text-[11px] text-slate-400 px-1 italic flex items-center gap-1">
                    <span>&larr; Swipe horizontally to view full ledger &rarr;</span>
                </div>

                <div class="border border-slate-200/90 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs min-w-[620px]">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-[11px]">
                                <tr>
                                    <th class="p-3.5">Timestamp</th>
                                    <th class="p-3.5">Posting Event</th>
                                    <th class="p-3.5">Reference</th>
                                    <th class="p-3.5 text-right">Debit (₹)</th>
                                    <th class="p-3.5 text-right">Credit (₹)</th>
                                    <th class="p-3.5 text-right">Running Balance (₹)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($walletEntries as $entry)
                                    @php
                                        $isDebit = $entry->amount_paise < 0;
                                        $absRupees = abs($entry->amount_paise) / 100;
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono text-xs whitespace-nowrap">
                                            {{ $entry->created_at->format('d M Y, H:i') }}
                                        </td>
                                        <td class="p-3.5">
                                            <span class="font-bold text-slate-900 dark:text-white capitalize block">
                                                {{ str_replace('_', ' ', $entry->type) }}
                                            </span>
                                            @if ($entry->notes)
                                                <span class="text-[11px] text-slate-500 dark:text-slate-400 block line-clamp-1">{{ $entry->notes }}</span>
                                            @endif
                                        </td>
                                        <td class="p-3.5 font-mono text-[11px] text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                            {{ $entry->reference_type ? class_basename($entry->reference_type) . ' #' . $entry->reference_id : 'Manual / Counter' }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-sm {{ $isDebit ? 'text-rose-600 dark:text-rose-400' : 'text-slate-300 dark:text-slate-700' }}">
                                            {{ $isDebit ? '₹' . number_format($absRupees, 2) : '—' }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-sm {{ !$isDebit ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-300 dark:text-slate-700' }}">
                                            {{ !$isDebit ? '₹' . number_format($absRupees, 2) : '—' }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-extrabold text-slate-900 dark:text-white text-sm">
                                            ₹{{ number_format(($entry->balance_after_paise ?? 0) / 100, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400 text-xs">
                                            No ledger transactions recorded yet. Click "Top-Up Balance" to make the first prepaid deposit.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 5. TAB 3: INVOICES & BILLS                -->
        <!-- ========================================== -->
        @if ($activeTab === 'bills')
            <div class="space-y-4">
                <!-- Bills Filter Bar -->
                <div class="p-3 sm:p-3.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/90 dark:border-slate-800 shadow-2xs flex flex-wrap items-center justify-between gap-2.5">
                    <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[260px]">
                        <input type="text" wire:model.live.debounce.300ms="billSearch" placeholder="Search by Bill #..."
                            class="px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 w-full sm:w-56 h-8.5">

                        <select wire:model.live="billDepartmentFilter"
                            class="px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 h-8.5">
                            <option value="all">All Outlets / Departments</option>
                            <option value="restaurant">Restaurant / F&B</option>
                            <option value="bar">Bar Counter</option>
                            <option value="sports">Sports Complex</option>
                            <option value="reception">Reception / Front Desk</option>
                        </select>
                    </div>

                    <div class="text-xs text-slate-500">
                        Showing <strong>{{ $bills->count() }}</strong> invoices
                    </div>
                </div>

                <!-- Bills Table -->
                <div class="sm:hidden text-[11px] text-slate-400 px-1 italic flex items-center gap-1">
                    <span>&larr; Swipe horizontally to view full table &rarr;</span>
                </div>

                <div class="border border-slate-200/90 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs min-w-[620px]">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-[11px]">
                                <tr>
                                    <th class="p-3.5">Invoice #</th>
                                    <th class="p-3.5">Date & Time</th>
                                    <th class="p-3.5">Department</th>
                                    <th class="p-3.5">Payment Method</th>
                                    <th class="p-3.5 text-right">Net Amount</th>
                                    <th class="p-3.5 text-center">Status</th>
                                    <th class="p-3.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($bills as $bill)
                                    @php
                                        $bStatus = $bill->status instanceof \BackedEnum ? $bill->status->value : (string) $bill->status;
                                        $primaryTender = $bill->receipts->first()?->tender_type;
                                        $tenderStr = $primaryTender instanceof \BackedEnum ? $primaryTender->value : ($primaryTender ? (string) $primaryTender : 'Prepaid');
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="p-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                            {{ $bill->bill_number }}
                                        </td>
                                        <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono text-xs whitespace-nowrap">
                                            {{ $bill->created_at->format('d M Y, H:i') }}
                                        </td>
                                        <td class="p-3.5 capitalize font-medium text-slate-700 dark:text-slate-300">
                                            {{ $bill->department_code ?: 'Restaurant' }}
                                        </td>
                                        <td class="p-3.5">
                                            <span class="inline-flex items-center gap-1 font-bold text-[11px] uppercase text-slate-600 dark:text-slate-400">
                                                {{ ucfirst($tenderStr) }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-extrabold text-slate-900 dark:text-white text-sm">
                                            ₹{{ number_format($bill->net_amount, 2) }}
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $bStatus === 'paid' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300' : 'bg-rose-100 text-rose-800' }}">
                                                {{ $bStatus }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            <button wire:click="openInvoiceModal({{ $bill->id }})" type="button"
                                                class="h-7.5 px-2.5 py-1 rounded-md bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs border border-slate-300 dark:border-slate-700 transition cursor-pointer">
                                                View Bill
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-8 text-center text-slate-400 text-xs">
                                            No bills or tax invoices found for this member matching the filter criteria.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 6. TAB 4: SPORTS & FACILITIES             -->
        <!-- ========================================== -->
        @if ($activeTab === 'facilities')
            <div class="space-y-6">
                <!-- Front-Desk Quick Booking Toolbar -->
                <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h4 class="font-bold text-slate-900 dark:text-white text-sm">Direct Front-Desk Facility Booking</h4>
                        <p class="text-xs text-slate-500">Quickly book badminton slots or guest rooms with this member pre-selected.</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button wire:click="openBookCourtModal" type="button"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition-colors shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>Book Badminton Slot</span>
                        </button>
                        <button wire:click="openBookRoomModal" type="button"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 active:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors border border-slate-300 dark:border-slate-700 shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span>Reserve Guest Room</span>
                        </button>
                    </div>
                </div>

                <!-- Section A: Subscriptions -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>Active &amp; Historical Facility Subscriptions</span>
                        </h3>
                    </div>

                    <div class="border border-slate-200/90 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[580px]">
                                <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-[11px]">
                                    <tr>
                                        <th class="p-3.5">Sub #</th>
                                        <th class="p-3.5">Facility / Plan</th>
                                        <th class="p-3.5">Validity Range</th>
                                        <th class="p-3.5 text-right">Fee (₹)</th>
                                        <th class="p-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($subscriptions as $sub)
                                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                                            <td class="p-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                                {{ $sub->subscription_number }}
                                            </td>
                                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-200">
                                                {{ $sub->tariff?->facility?->name ?? 'Badminton / Gym' }} — {{ $sub->plan_type }}
                                            </td>
                                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono text-xs">
                                                {{ $sub->starts_at?->format('d M Y') }} &rarr; {{ $sub->ends_at?->format('d M Y') }}
                                            </td>
                                            <td class="p-3.5 text-right font-mono font-bold text-sm">
                                                ₹{{ number_format(($sub->total_fee_paise ?? 0) / 100, 2) }}
                                            </td>
                                            <td class="p-3.5 text-center">
                                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $sub->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                                    {{ $sub->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-6 text-center text-slate-400 text-xs">
                                                No active or historical facility subscriptions found for this member.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section B: Indoor Badminton Court Bookings -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>Indoor Badminton Court Bookings</span>
                        </h3>
                    </div>

                    <div class="border border-slate-200/90 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[580px]">
                                <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-[11px]">
                                    <tr>
                                        <th class="p-3.5">Booking #</th>
                                        <th class="p-3.5">Date</th>
                                        <th class="p-3.5">Time Slot</th>
                                        <th class="p-3.5">Plan Covered?</th>
                                        <th class="p-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($courtBookings as $booking)
                                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                                            <td class="p-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                                {{ $booking->booking_number }}
                                            </td>
                                            <td class="p-3.5 text-slate-700 dark:text-slate-300 font-mono">
                                                {{ $booking->booking_date?->format('d M Y') }}
                                            </td>
                                            <td class="p-3.5 font-bold text-slate-900 dark:text-white font-mono">
                                                {{ substr($booking->start_time, 0, 5) }} &rarr; {{ substr($booking->end_time, 0, 5) }}
                                            </td>
                                            <td class="p-3.5">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $booking->is_plan_covered ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800' }}">
                                                    {{ $booking->is_plan_covered ? 'Covered by Plan' : 'Pay-per-Play' }}
                                                </span>
                                            </td>
                                            <td class="p-3.5 text-center">
                                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $booking->status === 'confirmed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                                    {{ $booking->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-6 text-center text-slate-400 text-xs">
                                                No court bookings recorded yet for this member.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section C: Room Reservations -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span>Accommodation Room Reservations</span>
                        </h3>
                    </div>

                    <div class="border border-slate-200/90 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[580px]">
                                <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-[11px]">
                                    <tr>
                                        <th class="p-3.5">Res #</th>
                                        <th class="p-3.5">Room</th>
                                        <th class="p-3.5">Stay Dates</th>
                                        <th class="p-3.5 text-right">Tariff Total</th>
                                        <th class="p-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($roomReservations as $res)
                                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                                            <td class="p-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                                {{ $res->reservation_number }}
                                            </td>
                                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-200">
                                                {{ $res->facility?->name ?? 'Room #' . $res->facility_id }}
                                            </td>
                                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono text-xs">
                                                {{ $res->check_in_date?->format('d M Y') }} &rarr; {{ $res->check_out_date?->format('d M Y') }}
                                            </td>
                                            <td class="p-3.5 text-right font-mono font-bold text-sm">
                                                ₹{{ number_format(($res->total_charge_paise ?? 0) / 100, 2) }}
                                            </td>
                                            <td class="p-3.5 text-center">
                                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $res->status === 'confirmed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                                    {{ $res->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-6 text-center text-slate-400 text-xs">
                                                No accommodation room reservations recorded for this member.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section D: Club Locker & Kitbag Allocation -->
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <span>Club Locker &amp; Kitbag Allocation</span>
                        </h3>

                        <button wire:click="openAssignLockerModal" type="button"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition-colors shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span>Assign Club Locker</span>
                        </button>
                    </div>

                    <div class="border border-slate-200/90 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[580px]">
                                <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-[11px]">
                                    <tr>
                                        <th class="p-3.5">Locker #</th>
                                        <th class="p-3.5">Facility / Pavilion Zone</th>
                                        <th class="p-3.5">Key Number</th>
                                        <th class="p-3.5">Allocated On</th>
                                        <th class="p-3.5 text-right">Security Deposit</th>
                                        <th class="p-3.5 text-center">Status</th>
                                        <th class="p-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($assignedLockers as $lck)
                                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                                            <td class="p-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                                {{ $lck->locker_number }}
                                            </td>
                                            <td class="p-3.5 font-bold text-slate-800 dark:text-slate-200">
                                                {{ $lck->zone }}
                                            </td>
                                            <td class="p-3.5 text-slate-700 dark:text-slate-300 font-mono">
                                                {{ $lck->key_number ?: 'Standard Pin' }}
                                            </td>
                                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono text-xs">
                                                {{ $lck->assigned_at ? $lck->assigned_at->format('d M Y') : '—' }}
                                            </td>
                                            <td class="p-3.5 text-right font-mono font-bold text-sm">
                                                ₹{{ number_format($lck->deposit_rupees, 2) }}
                                            </td>
                                            <td class="p-3.5 text-center">
                                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                                    {{ $lck->status }}
                                                </span>
                                            </td>
                                            <td class="p-3.5 text-right">
                                                <button wire:click="vacateLocker({{ $lck->id }})" type="button"
                                                    class="px-2.5 py-1 text-[11px] font-bold text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition border border-rose-200 dark:border-rose-900 cursor-pointer">
                                                    Surrender &amp; Refund
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="p-6 text-center text-slate-400 text-xs">
                                                No club lockers currently allocated to this member. Click "Assign Club Locker" above to allocate one.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 6. TAB: GATE & TURNSTILE ACTIVITY LOGS     -->
        <!-- ========================================== -->
        @if ($activeTab === 'access')
            <div class="space-y-6">
                <!-- Turnstile Security Header & Emergency Revoke -->
                <div class="p-3.5 sm:p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/90 dark:border-slate-800 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <span>Turnstile &amp; Biometric Access Controller</span>
                            </h3>
                            @if ($member->activeCard)
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    Token Active
                                </span>
                            @else
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                    No Active Token
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500">
                            Real-time event logging from optical turnstiles, swimming pool barriers, and clubhouse gate controllers.
                        </p>
                    </div>

                    <div class="flex items-center gap-1.5">
                        @if ($member->activeCard)
                            <button wire:click="blockActiveCard" type="button"
                                class="h-8.5 px-3 py-1.5 rounded-lg border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-700 dark:text-rose-300 font-semibold text-xs inline-flex items-center gap-1.5 cursor-pointer">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                                <span>Emergency Block Access</span>
                            </button>
                        @endif

                        @php
                            $blockedCard = $member->cards->where('status', \App\Domain\Shared\Enums\CardStatus::BLOCKED)->first();
                        @endphp
                        @if ($blockedCard && !$member->activeCard)
                            <button wire:click="unblockCard({{ $blockedCard->id }})" type="button"
                                class="h-8.5 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white hover:bg-slate-50 text-slate-700 dark:text-slate-200 font-semibold text-xs inline-flex items-center gap-1.5 cursor-pointer">
                                <svg class="w-3.5 h-3.5 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Restore Gate Access</span>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Event Logs Table -->
                <div class="border border-slate-200/90 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                    <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Chronological Gate Events (Last 50)</span>
                        <span class="text-xs font-mono font-bold text-slate-600 dark:text-slate-400">{{ $accessEvents->count() }} Entries</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs min-w-[620px]">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-[11px]">
                                <tr>
                                    <th class="p-3.5">Timestamp</th>
                                    <th class="p-3.5">Gate / Turnstile</th>
                                    <th class="p-3.5">Card / Token UID</th>
                                    <th class="p-3.5 text-center">Outcome</th>
                                    <th class="p-3.5">Reason / Remarks</th>
                                    <th class="p-3.5">Hardware Controller</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($accessEvents as $event)
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="p-3.5 font-mono text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                            {{ $event->recorded_at ? $event->recorded_at->format('d M Y, H:i:s') : '—' }}
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-900 dark:text-white">
                                            {{ $event->facility_code ?: 'Clubhouse Main Turnstile' }}
                                        </td>
                                        <td class="p-3.5 font-mono text-slate-600 dark:text-slate-400">
                                            {{ $event->card_number ?: 'N/A' }}
                                        </td>
                                        <td class="p-3.5 text-center">
                                            @if ($event->event_type === 'granted')
                                                <span class="text-[10px] font-bold uppercase px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                                    Granted
                                                </span>
                                            @else
                                                <span class="text-[10px] font-bold uppercase px-2.5 py-0.5 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-700">
                                                    Denied
                                                </span>
                                            @endif
                                        </td>
                                        <td class="p-3.5 text-slate-600 dark:text-slate-400">
                                            {{ $event->reason ?: 'Card validated successfully' }}
                                        </td>
                                        <td class="p-3.5 font-mono text-slate-500 text-[11px]">
                                            {{ $event->device_id ?: 'GATE-CTRL-01' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400 text-xs">
                                            No gate or turnstile entry events recorded for this member or associated cards yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 7. TAB 5: DEPENDENTS & GUESTS             -->
        <!-- ========================================== -->
        @if ($activeTab === 'dependents')
            <div class="space-y-6">
                
                <!-- Section A: Family Dependents -->
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <span>Registered Family Dependents</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <strong>Strict Club Policy:</strong> Dependents are restricted from debiting the primary member's wallet unless specifically authorized in writing by the primary member.
                            </p>
                        </div>

                        <button wire:click="openDependentModal" type="button"
                            class="h-8.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition-colors shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span>Add Dependent</span>
                        </button>
                    </div>

                    <!-- Dependents Cards Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @forelse ($dependents as $dep)
                            <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h4 class="font-bold text-slate-900 dark:text-white text-sm">
                                            {{ $dep->person?->full_name ?? 'Dependent' }}
                                        </h4>
                                        <span class="text-xs font-semibold text-amber-700 dark:text-amber-400">
                                            {{ $dep->relationship }}
                                        </span>
                                    </div>

                                    @if ($dep->can_spend_wallet)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300">
                                            Wallet Authorized
                                        </span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300">
                                            Restricted
                                        </span>
                                    @endif
                                </div>

                                <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1 pt-1 border-t border-slate-100 dark:border-slate-800">
                                    <div>Phone: <strong class="font-mono text-slate-800 dark:text-slate-200">{{ $dep->person?->phone ?? 'Not recorded' }}</strong></div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                    <button wire:click="toggleDependentWalletDebit({{ $dep->id }})" type="button"
                                        class="h-8 px-3 py-1 rounded-lg text-xs font-semibold transition w-full {{ $dep->can_spend_wallet ? 'bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800' : 'bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700' }} cursor-pointer">
                                        {{ $dep->can_spend_wallet ? 'Revoke Wallet Permission' : 'Authorize Wallet Debits' }}
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="sm:col-span-2 lg:col-span-3 p-8 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 text-xs">
                                No family dependents registered yet for Member #{{ $member->membership_number }}.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Section B: Sponsored Visiting Guests -->
                <div class="space-y-3 pt-3">
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span>Sponsored Visiting Guests</span>
                    </h3>

                    <div class="border border-slate-200/90 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs bg-white dark:bg-slate-900">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[580px]">
                                <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-[11px]">
                                    <tr>
                                        <th class="p-3.5">Guest Name</th>
                                        <th class="p-3.5">Mobile Phone</th>
                                        <th class="p-3.5">Visit Date</th>
                                        <th class="p-3.5">Passes Issued</th>
                                        <th class="p-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($sponsoredGuests as $guest)
                                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                                            <td class="p-3.5 font-bold text-slate-900 dark:text-white">
                                                {{ $guest->name }}
                                            </td>
                                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono text-xs">
                                                {{ $guest->phone ?? '—' }}
                                            </td>
                                            <td class="p-3.5 text-slate-600 dark:text-slate-400 font-mono text-xs">
                                                {{ $guest->visit_date?->format('d M Y') ?? '—' }}
                                            </td>
                                            <td class="p-3.5">
                                                <span class="font-bold text-slate-700 dark:text-slate-300">
                                                    {{ $guest->passes->count() }} pass(es)
                                                </span>
                                            </td>
                                            <td class="p-3.5 text-center">
                                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-blue-100 text-blue-800">
                                                    {{ $guest->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-6 text-center text-slate-400 text-xs">
                                                No visiting guests sponsored yet by this member.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- 8. INTERACTIVE MODALS                      -->
    <!-- ========================================== -->

        <!-- MODAL: Top-Up Prepaid Wallet -->
        @if ($showTopUpModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-xs">
                                ₹
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Top-Up Prepaid Wallet</h3>
                                <p class="text-[11px] text-slate-500">Member #{{ $member->membership_number }} &bull; {{ $member->name }}</p>
                            </div>
                        </div>
                        <button wire:click="closeTopUpModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="processTopUp" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Recharge Amount (₹) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">₹</span>
                                <input type="number" step="1" min="1" wire:model="topUpAmount" placeholder="e.g. 2000" autofocus required
                                    class="w-full pl-7 pr-3 py-2 text-sm font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 font-mono">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Collection Tender Type <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="topUpTender" class="w-full px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                <option value="cash">Cash (Counter Cash Drawer)</option>
                                <option value="digital">Digital / UPI / QR Settlement</option>
                                <option value="card">Card (POS Terminal)</option>
                                <option value="bank_transfer">Direct Bank Transfer / NEFT</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Transaction / Bank Reference #
                            </label>
                            <input type="text" wire:model="topUpRef" placeholder="e.g. UPI-123456789 or Cash Slip #"
                                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 font-mono">
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeTopUpModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Confirm &amp; Credit Wallet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- MODAL: Issue or Replace RFID Card -->
        @if ($showCardModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ $member->activeCard ? 'Replace RFID Access Card' : 'Issue Initial RFID Card' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Member #{{ $member->membership_number }} &bull; {{ $member->name }}</p>
                        </div>
                        <button wire:click="closeCardModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    @if ($member->activeCard)
                        <div class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-xs text-amber-900 dark:text-amber-300">
                            <strong>Notice:</strong> Current Card <code class="font-bold">{{ $member->activeCard->card_number }}</code> will be blocked immediately. Statutory replacement fee: <strong>₹{{ number_format($lostCardFee, 2) }}</strong>. Wallet balance is preserved.
                        </div>
                    @endif

                    <form wire:submit="processCardIssue" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Physical RFID Card UID / Scan <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" wire:model="newCardNumber" placeholder="Tap card or enter UID (e.g. CARD-VENU-02)" autofocus required
                                class="w-full px-3 py-2 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Issuance / Replacement Reason
                            </label>
                            <input type="text" wire:model="cardReason" placeholder="e.g. Lost physical card / Damaged antenna"
                                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeCardModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Issue Physical Card
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- MODAL: Add Family Dependent -->
        @if ($showDependentModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Register Family Dependent</h3>
                            <p class="text-[11px] text-slate-500">Linked to Member #{{ $member->membership_number }}</p>
                        </div>
                        <button wire:click="closeDependentModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="processDependentRegister" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    First Name <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" wire:model="depFirstName" placeholder="e.g. Anjali" autofocus required
                                    class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Last Name
                                </label>
                                <input type="text" wire:model="depLastName" placeholder="e.g. Nair"
                                    class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Relationship to Member <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="depRelationship" class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                <option value="Spouse">Spouse</option>
                                <option value="Child">Child (Son / Daughter)</option>
                                <option value="Parent">Parent (Father / Mother)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Contact Mobile Number
                            </label>
                            <input type="text" wire:model="depPhone" placeholder="e.g. +91 98470 12345"
                                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 font-mono">
                        </div>

                        <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="depCanSpendWallet" class="w-4 h-4 rounded text-slate-900 focus:ring-slate-900">
                                <span class="text-xs font-semibold text-slate-900 dark:text-white">Authorize Counter Wallet Debits</span>
                            </label>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Disabled by default. Only check if member has provided written authorization to debit wallet.
                            </p>
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeDependentModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Save Dependent
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- MODAL: Change Lifecycle Status -->
        @if ($showStatusModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Manage Membership Status</h3>
                            <p class="text-[11px] text-slate-500">Member #{{ $member->membership_number }} &bull; {{ $member->name }}</p>
                        </div>
                        <button wire:click="closeStatusModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="processStatusChange" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                New Membership Status <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="newStatus" class="w-full px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                <option value="active">Active (Full Club &amp; Facility Access)</option>
                                <option value="suspended">Suspended (Temporary Bar / Disciplinary)</option>
                                <option value="expired">Expired (Annual Dues Lapsed)</option>
                                <option value="resigned">Resigned (Voluntary Surrender)</option>
                                <option value="terminated">Terminated (Committee Expulsion)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Audit Reason / Notation
                            </label>
                            <input type="text" wire:model="statusReason" placeholder="e.g. Relocated abroad / Disciplinary hold"
                                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeStatusModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- MODAL: View Invoice Details -->
        @if ($showInvoiceModal && $selectedInvoice)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity print:p-0 print:static print:bg-transparent print:overflow-visible print:block print:w-full print:h-auto">
                <div class="bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-lg p-5 sm:p-6 space-y-4 max-h-[92vh] overflow-y-auto print:max-h-none print:shadow-none print:border-none print:p-0 print:overflow-visible print:bg-white print:text-slate-900 print:max-w-none print:w-full print:block print:h-auto">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase text-amber-700 dark:text-amber-400">Tax Invoice / Club Receipt</div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white font-mono">{{ $selectedInvoice['bill_number'] }}</h3>
                            <p class="text-[11px] text-slate-500">{{ $selectedInvoice['created_at'] }} &bull; {{ ucfirst($selectedInvoice['department_code'] ?? 'Restaurant') }}</p>
                        </div>
                        <button wire:click="closeInvoiceModal" type="button" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer print:hidden no-print">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Line Items Table -->
                    <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="p-2.5">Item</th>
                                    <th class="p-2.5 text-center">Qty</th>
                                    <th class="p-2.5 text-right">Rate</th>
                                    <th class="p-2.5 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                                @foreach ($selectedInvoice['lines'] as $line)
                                    <tr>
                                        <td class="p-2.5 text-slate-900 dark:text-white">
                                            {{ $line['item_name'] }}
                                            @if ($line['tax_percent'] > 0)
                                                <span class="text-[10px] text-slate-400 block">{{ $line['tax_percent'] }}% Tax</span>
                                            @endif
                                        </td>
                                        <td class="p-2.5 text-center font-mono">{{ $line['quantity'] }}</td>
                                        <td class="p-2.5 text-right font-mono">₹{{ number_format($line['unit_price'], 2) }}</td>
                                        <td class="p-2.5 text-right font-mono font-bold">₹{{ number_format($line['line_total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Financial Summary Breakup -->
                    <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl space-y-1.5 text-xs">
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Gross Total</span>
                            <span class="font-mono">₹{{ number_format($selectedInvoice['gross_rupees'], 2) }}</span>
                        </div>
                        @if ($selectedInvoice['discount_rupees'] > 0)
                            <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                                <span>Discount</span>
                                <span class="font-mono">-₹{{ number_format($selectedInvoice['discount_rupees'], 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Applicable Taxes (CGST/SGST/VAT)</span>
                            <span class="font-mono">₹{{ number_format($selectedInvoice['tax_rupees'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-extrabold text-slate-900 dark:text-white border-t border-slate-200 dark:border-slate-700 pt-1.5">
                            <span>Net Bill Total</span>
                            <span class="font-mono text-emerald-600 dark:text-emerald-400">₹{{ number_format($selectedInvoice['net_rupees'], 2) }}</span>
                        </div>
                    </div>

                    <!-- Payment Tender Allocations -->
                    @if (!empty($selectedInvoice['receipts']))
                        <div class="text-xs space-y-1">
                            <span class="text-[11px] font-bold uppercase text-slate-400 block">Settlement Allocations</span>
                            @foreach ($selectedInvoice['receipts'] as $rcpt)
                                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-[11px]">
                                    <span class="font-bold capitalize">{{ $rcpt['tender_type'] }} ({{ $rcpt['receipt_number'] }})</span>
                                    <span class="font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format($rcpt['amount'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="pt-2 flex items-center justify-between border-t border-slate-100 dark:border-slate-800">
                        <span class="text-[11px] text-slate-400">Handled by: <strong>{{ $selectedInvoice['cashier'] }}</strong></span>
                        <div class="flex items-center gap-1.5 print:hidden no-print">
                            <button type="button" onclick="window.print()"
                                class="h-8.5 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white hover:bg-slate-50 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer">
                                Print Receipt
                            </button>
                            <button wire:click="closeInvoiceModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition cursor-pointer">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- MODAL 6: DIRECT BADMINTON COURT BOOKING                      -->
        <!-- ============================================================ -->
        @if ($showBookCourtModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Book Badminton Court Slot</h3>
                                <p class="text-[11px] text-slate-500">Member #{{ $member->membership_number }} &bull; {{ $member->name }}</p>
                            </div>
                        </div>
                        <button wire:click="closeBookCourtModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="processDirectCourtBooking" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Sports Facility Court <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="bookCourtFacilityId" class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                @if ($courtFacility)
                                    <option value="{{ $courtFacility->id }}">{{ $courtFacility->name }} ({{ $courtFacility->code }})</option>
                                @endif
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Booking Date <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" wire:model.live="bookCourtDate" min="{{ now()->toDateString() }}" required
                                class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Start Time (1-Hr) <span class="text-rose-500">*</span>
                                </label>
                                <select wire:model="bookCourtStartTime" class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono">
                                    <option value="06:00">06:00 AM</option>
                                    <option value="07:00">07:00 AM</option>
                                    <option value="08:00">08:00 AM</option>
                                    <option value="09:00">09:00 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="16:00">04:00 PM</option>
                                    <option value="17:00">05:00 PM</option>
                                    <option value="18:00">06:00 PM</option>
                                    <option value="19:00">07:00 PM</option>
                                    <option value="20:00">08:00 PM</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    End Time
                                </label>
                                <select wire:model="bookCourtEndTime" class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono">
                                    <option value="07:00">07:00 AM</option>
                                    <option value="08:00">08:00 AM</option>
                                    <option value="09:00">09:00 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="17:00">05:00 PM</option>
                                    <option value="18:00">06:00 PM</option>
                                    <option value="19:00">07:00 PM</option>
                                    <option value="20:00">08:00 PM</option>
                                    <option value="21:00">09:00 PM</option>
                                </select>
                            </div>
                        </div>

                        <!-- Entitlement status pill -->
                        @if ($courtIsPlanCovered)
                            <div class="p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>Active Sports Subscription:</strong> Slot fee is 100% plan covered (₹0.00).</span>
                            </div>
                        @else
                            <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span><strong>Pay-per-Play:</strong> No active badminton subscription. Hourly court tariff applies.</span>
                            </div>
                        @endif

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeBookCourtModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Confirm Court Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- MODAL 7: DIRECT ACCOMMODATION ROOM RESERVATION                -->
        <!-- ============================================================ -->
        @if ($showBookRoomModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Reserve Guest Room</h3>
                                <p class="text-[11px] text-slate-500">Sponsor: Member #{{ $member->membership_number }} &bull; {{ $member->name }}</p>
                            </div>
                        </div>
                        <button wire:click="closeBookRoomModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="processDirectRoomBooking" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Accommodation Room <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="bookRoomFacilityId" class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                @foreach ($availableRooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Check-In Date <span class="text-rose-500">*</span>
                                </label>
                                <input type="date" wire:model="bookRoomCheckIn" min="{{ now()->toDateString() }}" required
                                    class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Check-Out Date <span class="text-rose-500">*</span>
                                </label>
                                <input type="date" wire:model="bookRoomCheckOut" min="{{ now()->addDay()->toDateString() }}" required
                                    class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Nightly Rate (₹)
                                </label>
                                <input type="number" wire:model="bookRoomRateRupees" step="100" min="500" required
                                    class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Advance Deposit (₹)
                                </label>
                                <input type="number" wire:model="bookRoomDepositRupees" step="100" min="0" required
                                    class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Primary Guest / Occupant Name
                            </label>
                            <input type="text" wire:model="bookRoomGuestName" placeholder="e.g. Kishore Warrier or visiting family"
                                class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeBookRoomModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Confirm Room Reservation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- MODAL 8: AFFILIATED CLUB INTRODUCTION LETTER GENERATOR       -->
        <!-- ============================================================ -->
        @if ($showIntroLetterModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Affiliated Club Introduction Letter</h3>
                                <p class="text-[11px] text-slate-500">Reciprocal Visit &bull; Member #{{ $member->membership_number }}</p>
                            </div>
                        </div>
                        <button wire:click="closeIntroLetterModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="generateIntroLetter" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Destination Affiliated Club <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" wire:model="letterDestinationClub" list="affiliated_clubs_list" required
                                class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                            <datalist id="affiliated_clubs_list">
                                <option value="Trivandrum Club, Vazhuthacaud, Thiruvananthapuram">
                                <option value="Rama Varma Club, Club Road, Kochi">
                                <option value="Lotus Club, Warriam Road, Ernakulam">
                                <option value="Bangalore Club, Residency Road, Bengaluru">
                                <option value="Cosmopolitan Club, Anna Salai, Chennai">
                                <option value="Ooty Club, Nilgiris, Tamil Nadu">
                                <option value="Secunderabad Club, Picket, Secunderabad">
                            </datalist>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Visit From <span class="text-rose-500">*</span>
                                </label>
                                <input type="date" wire:model="letterVisitStart" required
                                    class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Visit To <span class="text-rose-500">*</span>
                                </label>
                                <input type="date" wire:model="letterVisitEnd" required
                                    class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Accompanying Dependents
                            </label>
                            <input type="text" wire:model="letterAccompanying" placeholder="e.g. Spouse & 2 Children"
                                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Purpose of Visit / Remarks
                            </label>
                            <input type="text" wire:model="letterPurpose" placeholder="e.g. Personal recreation & leisure stay"
                                class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeIntroLetterModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Generate Official Certificate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- MODAL 9: FORMAL CERTIFICATE LETTER VIEW & PRINT              -->
        <!-- ============================================================ -->
        @if ($showLetterViewModal && $generatedLetter)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity print:p-0 print:static print:bg-transparent print:overflow-visible print:block print:w-full print:h-auto">
                <div id="printable-intro-letter" class="bg-white rounded-t-2xl sm:rounded-xl border border-slate-200 shadow-2xl w-full max-w-2xl p-5 sm:p-7 space-y-5 max-h-[94vh] overflow-y-auto text-slate-900 print:max-h-none print:shadow-none print:border-none print:p-0 print:overflow-visible print:max-w-none print:w-full print:block print:h-auto">
                    
                    <!-- Official Letterhead Header -->
                    <div class="border-b border-slate-300 pb-3 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-base shadow-2xs">
                                MC
                            </div>
                            <div>
                                <h2 class="text-lg font-bold uppercase tracking-tight text-slate-900">{{ config('club.name', 'Malabar Club') }}</h2>
                                <p class="text-xs font-medium text-slate-600 uppercase tracking-wider">Secretariat &bull; Vadakkencherry, Palakkad &bull; Kerala 678683</p>
                                <p class="text-[11px] text-slate-500">Affiliated to Premier Clubs across India &bull; Est. 1998 &bull; Phone: +91 4922 255222</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Ref Number</span>
                            <span class="text-xs font-mono font-bold text-slate-900 block">{{ $generatedLetter['reference_number'] }}</span>
                            <span class="text-[11px] text-slate-500 block mt-0.5">{{ $generatedLetter['date'] }}</span>
                        </div>
                    </div>

                    <!-- Addressee -->
                    <div class="text-xs leading-relaxed space-y-1">
                        <p class="font-bold">To,</p>
                        <p class="font-bold text-slate-900 text-sm">{{ $generatedLetter['destination_club'] }}</p>
                        <p class="text-slate-600">Attention: The Honorary Secretary / General Manager</p>
                    </div>

                    <!-- Subject -->
                    <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-200 text-xs font-bold text-slate-900">
                        SUBJECT: Letter of Introduction &amp; Reciprocal Visiting Privileges — Member # {{ $generatedLetter['membership_number'] }}
                    </div>

                    <!-- Body Prose -->
                    <div class="text-xs leading-relaxed space-y-2.5 text-slate-800">
                        <p>Dear Sir / Madam,</p>
                        <p>
                            We have great pleasure in introducing our esteemed member, <strong>{{ $generatedLetter['member_name'] }}</strong> (Membership No: <strong class="font-mono">{{ $generatedLetter['membership_number'] }}</strong>, Classification: <strong>{{ $generatedLetter['category_name'] }}</strong>), who is a bonafide member of Malabar Club in good standing with all statutory subscriptions up to date.
                        </p>
                        <p>
                            {{ $generatedLetter['member_name'] }} proposes to visit your esteemed club during the period from <strong>{{ $generatedLetter['visit_start'] }}</strong> to <strong>{{ $generatedLetter['visit_end'] }}</strong> accompanied by {{ $generatedLetter['accompanying'] ?: 'family members' }}.
                        </p>
                        <p>
                            Under our mutual reciprocal affiliation agreement, we would be grateful if you could kindly extend temporary visiting privileges, dining, accommodation, and sports facilities to our member during their stay. Any expenses incurred will be settled directly by the member as per your club's counter billing regulations.
                        </p>
                        <p>
                            We thank you in advance for your kind courtesy and hospitality, and assure you of our warmest reciprocity whenever your members visit Palakkad.
                        </p>
                    </div>

                    <!-- Sign-off Block -->
                    <div class="pt-3 flex items-end justify-between border-t border-slate-200 text-xs">
                        <div>
                            <div class="w-14 h-14 rounded-lg border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-[10px] text-slate-400 font-mono text-center p-1">
                                [ Seal ]
                            </div>
                        </div>
                        <div class="text-right space-y-0.5">
                            <p class="text-slate-600">Yours faithfully,</p>
                            <p class="font-bold text-slate-900 pt-3">For {{ config('club.name', 'Malabar Club') }}</p>
                            <p class="font-bold text-sm text-slate-900">{{ $generatedLetter['secretary_name'] }}</p>
                            <p class="text-[11px] text-slate-500 uppercase font-medium">Honorary Secretary / General Manager</p>
                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100 print:hidden no-print">
                        <button type="button" onclick="window.print()"
                            class="h-8.5 px-3.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 text-white font-semibold text-xs transition-colors shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <span>Print Official Letter</span>
                        </button>
                        <button wire:click="closeLetterViewModal" type="button"
                            class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 font-semibold text-xs text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- MODAL 10: ADD CONFIDENTIAL COMMITTEE / STAFF NOTE            -->
        <!-- ============================================================ -->
        @if ($showAddNoteModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Add Administrative Note</h3>
                                <p class="text-[11px] text-slate-500">Internal Audit &bull; Member #{{ $member->membership_number }}</p>
                            </div>
                        </div>
                        <button wire:click="closeAddNoteModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="processAddNote" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Note Classification / Category <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="noteCategory" class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                <option value="general">General Administrative Note</option>
                                <option value="committee_directive">Managing Committee Directive</option>
                                <option value="disciplinary">Disciplinary / Policy Observation</option>
                                <option value="hospitality">Dining &amp; Table Hospitality Preference</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Confidential Note Remarks <span class="text-rose-500">*</span>
                            </label>
                            <textarea wire:model="noteContent" rows="3" placeholder="Enter administrative observations, committee instructions, or member preferences..." required
                                class="w-full px-3 py-2 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"></textarea>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                Note will be permanently recorded with your name ({{ auth()->user()?->name }}) and current timestamp for administrative auditing.
                            </p>
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeAddNoteModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Save Administrative Note
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- MODAL 11: ASSIGN CLUB LOCKER                                  -->
        <!-- ============================================================ -->
        @if ($showAssignLockerModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Assign Club Locker</h3>
                                <p class="text-[11px] text-slate-500">Member #{{ $member->membership_number }} &bull; {{ $member->name }}</p>
                            </div>
                        </div>
                        <button wire:click="closeAssignLockerModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="processAssignLocker" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Available Locker <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="selectedLockerId" required class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                @forelse ($availableLockers as $lck)
                                    <option value="{{ $lck->id }}">{{ $lck->locker_number }} — {{ $lck->zone }} (Deposit: ₹{{ number_format($lck->deposit_rupees, 2) }})</option>
                                @empty
                                    <option value="">No lockers currently available in inventory</option>
                                @endforelse
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Deposit Amount (₹) <span class="text-rose-500">*</span>
                                </label>
                                <input type="number" wire:model="lockerDepositRupees" min="0" step="100" required
                                    class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                    Physical Key Number
                                </label>
                                <input type="text" wire:model="lockerKeyNumber" placeholder="e.g. KEY-101"
                                    class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Allocation Remarks
                            </label>
                            <input type="text" wire:model="lockerNotes" placeholder="e.g. Assigned for badminton kit storage"
                                class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeAssignLockerModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                Confirm Locker Assignment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- MODAL 12: RENEW ANNUAL MEMBERSHIP DUES                        -->
        <!-- ============================================================ -->
        @if ($showRenewModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl w-full max-w-md p-4 sm:p-5 space-y-3.5 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ (($admissionFeeInfo['status'] ?? 'paid') === 'pending' || ($annualDuesInfo['fee_status'] ?? 'paid') === 'pending') ? 'Collect Statutory Membership Fees' : 'Renew Annual Subscription' }}
                                </h3>
                                <p class="text-[11px] text-slate-500">
                                    Member #{{ $member->membership_number }} &bull; {{ (($admissionFeeInfo['status'] ?? 'paid') === 'pending' || ($annualDuesInfo['fee_status'] ?? 'paid') === 'pending') ? 'Statutory Fees Settlement' : 'Current Valid Until: ' . $annualDuesInfo['valid_until_formatted'] }}
                                </p>
                            </div>
                        </div>
                        <button wire:click="closeRenewModal" type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="processRenewMembership" class="space-y-3">
                        @if (($admissionFeeInfo['status'] ?? 'paid') === 'paid')
                            <div class="p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span><strong>One-Time Admission Fee:</strong> ₹{{ number_format($admissionFeeInfo['admission_fee_rupees'], 2) }} already paid ({{ $admissionFeeInfo['paid_at_formatted'] }}{{ !empty($admissionFeeInfo['receipt_number']) ? ' &bull; Rcpt: ' . $admissionFeeInfo['receipt_number'] : '' }}). This renewal settles recurring annual dues only.</span>
                            </div>
                        @else
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200 cursor-pointer">
                                        <input type="checkbox" wire:model.live="collectAdmissionFee" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                        <span>Collect One-Time Admission Fee</span>
                                    </label>
                                    <span class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase">Capital Fee</span>
                                </div>
                                @if ($collectAdmissionFee)
                                    <div>
                                        <label class="block text-[11px] font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">
                                            Admission Fee Amount (₹) <span class="text-rose-500">*</span>
                                        </label>
                                        <input type="number" wire:model.live="renewalAdmissionFeeRupees" min="0" step="500" required
                                            class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Recurring Annual Subscription Dues</span>
                                <span class="text-[10px] font-bold text-slate-500 uppercase">Operational Dues</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">
                                        Period <span class="text-rose-500">*</span>
                                    </label>
                                    <select wire:model="renewalYears" class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                        <option value="1">1 Year</option>
                                        <option value="2">2 Years</option>
                                        <option value="3">3 Years</option>
                                        <option value="5">5 Years</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">
                                        Annual Dues (₹) <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="number" wire:model.live="renewalFeeRupees" min="0" step="500" required
                                        class="w-full px-3 py-1.5 text-xs font-mono font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
                                </div>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Total Settlement Payable:</span>
                            <span class="text-sm font-black text-slate-900 dark:text-white font-mono">
                                ₹{{ number_format(($collectAdmissionFee ? (float)$renewalAdmissionFeeRupees : 0) + (float)$renewalFeeRupees, 2) }}
                            </span>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Payment Tender Mode <span class="text-rose-500">*</span>
                            </label>
                            <select wire:model="renewalTender" class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400">
                                <option value="prepaid">Prepaid Wallet Debit (Current Balance: ₹{{ number_format($balanceRupees, 2) }})</option>
                                <option value="cash">Counter Cash Receipt</option>
                                <option value="digital">Digital UPI / POS Card</option>
                                <option value="bank_transfer">Direct Bank Remittance (NEFT / RTGS / Cheque)</option>
                            </select>
                        </div>

                        <!-- Zero-Credit Policy Notice -->
                        <div class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300">
                            <strong>Prepaid Wallet Policy:</strong> If paid via Prepaid Wallet, sufficient balance is strictly verified on the server ledger. Overdrafts, negative balances, and credit tabs are strictly blocked.
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                Statutory Audit Remarks
                            </label>
                            <input type="text" wire:model="renewalNotes"
                                class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button wire:click="closeRenewModal" type="button"
                                class="h-8.5 px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="h-8.5 px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-900 text-white font-semibold text-xs transition shadow-2xs cursor-pointer">
                                {{ (($admissionFeeInfo['status'] ?? 'paid') === 'pending' || ($annualDuesInfo['fee_status'] ?? 'paid') === 'pending') ? 'Confirm Payment & Activate Validity' : 'Confirm Subscription Renewal' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
</x-filament-panels::page>
