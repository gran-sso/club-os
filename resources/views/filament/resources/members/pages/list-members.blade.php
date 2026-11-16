<x-filament-panels::page>
    <div class="space-y-4 text-slate-800 dark:text-slate-100">
        
        <!-- ========================================== -->
        <!-- TOP VIEW MODE SWITCHER & QUICK METRICS     -->
        <!-- ========================================== -->
        <div class="p-3 sm:px-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            
            <!-- Left: Segmented View Mode Toggle -->
            <div class="flex items-center gap-2.5">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">View Layout:</span>
                <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 shadow-inner">
                    <!-- Table View Button -->
                    <button type="button" wire:click="setViewMode('table')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer select-none {{ $viewMode === 'table' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs border border-slate-200/80 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-slate-700/40' }}">
                        <svg class="w-4 h-4 shrink-0 {{ $viewMode === 'table' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                        <span>Table View</span>
                    </button>

                    <!-- Directory Cards Button -->
                    <button type="button" wire:click="setViewMode('cards')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer select-none {{ $viewMode === 'cards' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs border border-slate-200/80 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-slate-700/40' }}">
                        <svg class="w-4 h-4 shrink-0 {{ $viewMode === 'cards' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                        </svg>
                        <span>Directory Cards</span>
                        @if ($viewMode === 'cards')
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        @endif
                    </button>
                </div>
            </div>

            <!-- Right: Roster Statistics -->
            <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                <div class="inline-flex items-center gap-1.5">
                    <span>Total Members:</span>
                    <strong class="text-slate-900 dark:text-white font-mono font-bold">{{ $totalMembersCount }}</strong>
                </div>
                <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                <div class="inline-flex items-center gap-1.5">
                    <span>Active:</span>
                    <strong class="text-emerald-600 dark:text-emerald-400 font-mono font-bold">{{ $activeMembersCount }}</strong>
                </div>
                @if ($committeeMembersCount > 0)
                    <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                    <div class="inline-flex items-center gap-1.5">
                        <span class="text-amber-600 dark:text-amber-400 font-semibold">★ Committee:</span>
                        <strong class="text-slate-900 dark:text-white font-mono font-bold">{{ $committeeMembersCount }}</strong>
                    </div>
                @endif
            </div>
        </div>

        <!-- ========================================== -->
        <!-- VIEW MODE 1: FILAMENT STANDARD TABLE       -->
        <!-- ========================================== -->
        <div class="{{ $viewMode === 'table' ? '' : 'hidden' }}">
            {{ $this->table }}
        </div>

        <!-- ========================================== -->
        <!-- VIEW MODE 2: DIRECTORY PHOTO CARDS GRID    -->
        <!-- ========================================== -->
        <div class="{{ $viewMode === 'cards' ? '' : 'hidden' }} space-y-4">
            
            <!-- Filter & Search Toolbar for Cards -->
            <div class="p-3 sm:p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2.5">
                    
                    <!-- Search Input (takes all remaining horizontal width) -->
                    <div class="flex-1 min-w-[220px] relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input type="text"
                            wire:model.live.debounce.300ms="cardSearch"
                            placeholder="Search name, member #, profession, phone, vehicle..."
                            class="w-full pl-9 pr-8 h-10 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 transition">
                        @if (!empty($cardSearch))
                            <button type="button" wire:click="$set('cardSearch', '')" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>

                    <!-- Category Selector -->
                    <div class="w-full sm:w-auto shrink-0 min-w-[190px]">
                        <select wire:model.live="cardCategory"
                            class="w-full h-10 py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 transition">
                            <option value="">All Membership Categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Standing Selector -->
                    <div class="w-full sm:w-auto shrink-0 min-w-[155px]">
                        <select wire:model.live="cardStatus"
                            class="w-full h-10 py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 transition">
                            <option value="">All Standing</option>
                            <option value="active">Active (Good Standing)</option>
                            <option value="suspended">Suspended</option>
                            <option value="expired">Expired</option>
                            <option value="resigned">Resigned / Terminated</option>
                        </select>
                    </div>

                    <!-- Committee Toggle Button -->
                    <div class="w-full sm:w-auto shrink-0">
                        <button type="button" wire:click="$toggle('cardCommitteeOnly')"
                            class="w-full sm:w-auto h-10 px-3.5 text-xs font-semibold rounded-xl border transition flex items-center justify-center gap-1.5 cursor-pointer select-none whitespace-nowrap {{ $cardCommitteeOnly ? 'bg-amber-500 text-white border-amber-600 shadow-xs' : 'bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                            <span class="{{ $cardCommitteeOnly ? 'text-amber-100' : 'text-amber-500' }}">★</span>
                            <span>Committee Only</span>
                        </button>
                    </div>
                </div>

                <!-- Active Filters Summary & Reset -->
                @if (!empty($cardSearch) || !empty($cardCategory) || !empty($cardStatus) || $cardCommitteeOnly)
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <div class="flex flex-wrap items-center gap-1.5 text-slate-500">
                            <span>Filtering by:</span>
                            @if (!empty($cardSearch))
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-mono font-bold border border-slate-200 dark:border-slate-700">"{{ $cardSearch }}"</span>
                            @endif
                            @if (!empty($cardCategory))
                                @php $selectedCat = $categories->firstWhere('id', (int) $cardCategory); @endphp
                                @if ($selectedCat)
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-medium border border-slate-200 dark:border-slate-700">{{ $selectedCat->name }}</span>
                                @endif
                            @endif
                            @if (!empty($cardStatus))
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-medium border border-slate-200 dark:border-slate-700">{{ ucfirst($cardStatus) }}</span>
                            @endif
                            @if ($cardCommitteeOnly)
                                <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800 font-bold">Managing Committee</span>
                            @endif
                        </div>

                        <button type="button" wire:click="resetCardFilters"
                            class="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 hover:underline cursor-pointer">
                            Clear All Filters &times;
                        </button>
                    </div>
                @endif
            </div>

            <!-- Members Cards Grid -->
            @if ($cardMembers->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($cardMembers as $member)
                        @php
                            $person = $member->person;
                            $name = $member->name;
                            $title = $person?->title;
                            $fullNameWithTitle = $person?->full_name_with_title ?? $name;
                            $photoUrl = $member->photo_url;

                            $nameParts = explode(' ', trim($name));
                            $initials = count($nameParts) >= 2 
                                ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
                                : strtoupper(substr($nameParts[0] ?? 'M', 0, 2));

                            $statusVal = $member->status instanceof \BackedEnum ? $member->status->value : (string) $member->status;
                            $statusClasses = match ($statusVal) {
                                'active' => 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                                'suspended' => 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
                                'resigned' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
                                default => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
                            };

                            $viewUrl = \App\Filament\Resources\Members\MemberResource::getUrl('view', ['record' => $member]);
                            $editUrl = \App\Filament\Resources\Members\MemberResource::getUrl('edit', ['record' => $member]);
                            $walletBalanceRupees = ($member->wallet?->balance_paise ?? 0) / 100;
                        @endphp

                        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs hover:shadow-md hover:border-slate-300 dark:hover:border-slate-700 transition flex flex-col justify-between p-4 sm:p-5 relative overflow-hidden group">
                            
                            <div class="space-y-3.5">
                                
                                <!-- Top Row: Avatar + Identifiers & Badges -->
                                <div class="flex items-start gap-3">
                                    
                                    <!-- Photo Avatar -->
                                    <a href="{{ $viewUrl }}" class="shrink-0 group/photo relative">
                                        @if ($photoUrl)
                                            <img src="{{ $photoUrl }}" alt="{{ $name }}"
                                                class="w-14 h-14 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shadow-2xs group-hover/photo:ring-2 group-hover/photo:ring-amber-400 transition"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div style="display: none;" class="w-14 h-14 rounded-xl bg-slate-900 dark:bg-slate-100 items-center justify-center font-bold text-white dark:text-slate-900 text-base shrink-0 select-none shadow-2xs">
                                                {{ $initials }}
                                            </div>
                                        @else
                                            <div class="w-14 h-14 rounded-xl bg-slate-900 dark:bg-slate-100 flex items-center justify-center font-bold text-white dark:text-slate-900 text-base shrink-0 select-none shadow-2xs">
                                                {{ $initials }}
                                            </div>
                                        @endif
                                    </a>

                                    <!-- Member Name & Meta Badges -->
                                    <div class="min-w-0 flex-1 space-y-1">
                                        
                                        <!-- Top Badge Row -->
                                        <div class="flex flex-wrap items-center gap-1">
                                            <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                # {{ $member->membership_number }}
                                            </span>

                                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 truncate max-w-[120px]">
                                                {{ $member->category?->name ?? 'Standard' }}
                                            </span>

                                            <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border {{ $statusClasses }}">
                                                {{ ucfirst($statusVal) }}
                                            </span>

                                            @if ($member->committee_role)
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700/80 inline-flex items-center gap-0.5 shadow-2xs">
                                                    <span class="text-amber-500 text-[10px]">★</span>
                                                    <span class="truncate max-w-[130px]">{{ $member->committee_role }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Full Name with Honorific Prefix -->
                                        <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight leading-snug">
                                            <a href="{{ $viewUrl }}" class="hover:text-amber-600 dark:hover:text-amber-400 transition-colors">
                                                @if ($title)
                                                    <span class="text-amber-600 dark:text-amber-400 font-semibold">{{ $title }}</span>
                                                @endif
                                                <span>{{ $person?->first_name ?? $member->name }} {{ $person?->last_name ?? '' }}</span>
                                            </a>
                                        </h3>
                                    </div>
                                </div>

                                <!-- Professional Standing / Headline -->
                                @if ($person?->professional_headline)
                                    <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/60 p-2 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
                                        <svg class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="truncate">{{ $person->professional_headline }}</span>
                                    </div>
                                @endif

                                <!-- Contact Information -->
                                <div class="space-y-1 text-xs text-slate-500 dark:text-slate-400">
                                    @if ($person?->phone)
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                            </svg>
                                            <a href="tel:{{ $person->phone }}" class="hover:text-slate-900 dark:hover:text-white transition font-mono">
                                                {{ $person->phone }}
                                            </a>
                                        </div>
                                    @endif

                                    @if ($person?->email)
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                            <a href="mailto:{{ $person->email }}" class="hover:text-slate-900 dark:hover:text-white transition truncate max-w-[240px]">
                                                {{ $person->email }}
                                            </a>
                                        </div>
                                    @endif

                                    @if ($person?->city)
                                        <div class="flex items-center gap-1.5 text-slate-500">
                                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <span class="truncate">{{ $person->city }}{{ $person->state ? ', ' . $person->state : '' }}</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Demographic & Asset Chips -->
                                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                    @if ($person?->blood_group)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 text-[10px] font-bold text-rose-700 dark:text-rose-400">
                                            <span class="text-rose-500">🩸</span>
                                            <span>{{ $person->blood_group }}</span>
                                        </span>
                                    @endif

                                    @if ($person?->age)
                                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            Age: {{ $person->age }}y
                                        </span>
                                    @endif

                                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        {{ $member->activeCard ? '💳 ' . $member->activeCard->card_number : 'No Card' }}
                                    </span>

                                    @if (!empty($member->vehicle_numbers))
                                        @php $vehList = explode(',', $member->vehicle_numbers); @endphp
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 truncate max-w-[130px]">
                                            🚗 {{ trim($vehList[0]) }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Bottom Section: Wallet Balance & Action Buttons -->
                            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                                
                                <!-- Live Wallet Balance Widget -->
                                <div>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Prepaid Balance</span>
                                    <div class="text-base sm:text-lg font-black text-slate-900 dark:text-white font-mono tracking-tight leading-tight">
                                        ₹{{ number_format($walletBalanceRupees, 2) }}
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <a href="{{ $editUrl }}" title="Edit Member Profile"
                                        class="h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center transition cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </a>

                                    <a href="{{ $viewUrl }}"
                                        class="h-8 px-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 dark:bg-white dark:hover:bg-slate-100 dark:text-slate-950 text-white font-semibold text-xs transition inline-flex items-center gap-1 shadow-2xs cursor-pointer">
                                        <span>360° Profile</span>
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination Strip -->
                <div class="p-3 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <div class="text-slate-500 dark:text-slate-400">
                        Showing <strong class="text-slate-900 dark:text-white font-mono">{{ $cardMembers->firstItem() ?? 0 }}</strong> to <strong class="text-slate-900 dark:text-white font-mono">{{ $cardMembers->lastItem() ?? 0 }}</strong> of <strong class="text-slate-900 dark:text-white font-mono">{{ $cardMembers->total() }}</strong> members
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button type="button" wire:click="previousCardPage"
                            @disabled($cardMembers->onFirstPage())
                            class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer select-none disabled:opacity-40 disabled:cursor-not-allowed {{ $cardMembers->onFirstPage() ? 'border-slate-200 text-slate-400' : 'border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50' }}">
                            &larr; Previous
                        </button>

                        <span class="px-2 font-mono text-slate-500 font-medium">
                            Page {{ $cardMembers->currentPage() }} of {{ $cardMembers->lastPage() }}
                        </span>

                        <button type="button" wire:click="nextCardPage"
                            @disabled(!$cardMembers->hasMorePages())
                            class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer select-none disabled:opacity-40 disabled:cursor-not-allowed {{ !$cardMembers->hasMorePages() ? 'border-slate-200 text-slate-400' : 'border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50' }}">
                            Next &rarr;
                        </button>
                    </div>
                </div>
            @else
                <!-- Empty State -->
                <div class="p-8 sm:p-12 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 text-center space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">No members found</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        No member records match your active search or filters. Try adjusting your search query or clearing the filters.
                    </p>
                    <button type="button" wire:click="resetCardFilters"
                        class="px-3 py-1.5 rounded-lg bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-xs font-semibold transition cursor-pointer">
                        Reset All Filters
                    </button>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>

