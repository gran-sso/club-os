<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Executive Date & Control Toolbar -->
        <div class="p-5 bg-white border border-slate-200/90 rounded-2xl shadow-xs flex flex-wrap items-center justify-between gap-4">
            <!-- Date Navigator -->
            <div class="flex items-center gap-2">
                <button wire:click="previousDay" type="button"
                    class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-300 transition-colors shadow-2xs cursor-pointer inline-flex items-center justify-center"
                    title="Previous Day">
                    <svg width="16" height="16" class="w-4 h-4 shrink-0 text-slate-700" style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <div class="inline-flex items-center">
                    <input type="date" wire:model.live="selectedDate"
                        class="px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none transition-colors shadow-2xs cursor-pointer">
                </div>

                <button wire:click="nextDay" type="button"
                    class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-300 transition-colors shadow-2xs cursor-pointer inline-flex items-center justify-center"
                    title="Next Day">
                    <svg width="16" height="16" class="w-4 h-4 shrink-0 text-slate-700" style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <button wire:click="goToToday" type="button"
                    class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors border border-slate-300 shadow-2xs cursor-pointer">
                    Today
                </button>
            </div>

            <!-- Booking Capacity Stats -->
            @php
                $totalSlots = count($slotsData);
                $bookedCount = count(array_filter($slotsData, fn ($s) => $s['is_booked']));
                $availableCount = $totalSlots - $bookedCount;
            @endphp
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                    <span class="inline-flex items-center gap-1.5 font-bold text-slate-700">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block" style="width: 8px; height: 8px;"></span>
                        <span>{{ $availableCount }} Available</span>
                    </span>
                    <span class="text-slate-300">|</span>
                    <span class="inline-flex items-center gap-1.5 font-bold text-slate-700">
                        <span class="w-2 h-2 rounded-full bg-blue-500 inline-block" style="width: 8px; height: 8px;"></span>
                        <span>{{ $bookedCount }} Booked</span>
                    </span>
                </div>

                <!-- Court Identity -->
                <div class="flex items-center gap-3 border-l border-slate-200 pl-3">
                    <div class="text-right hidden sm:block">
                        <h4 class="font-bold text-xs text-slate-900 tracking-tight">{{ $courtName }}</h4>
                        <span class="text-[10px] text-slate-500 font-medium">Wooden Surface • Max 4 Players</span>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center font-bold text-base shadow-2xs shrink-0" style="width: 36px; height: 36px;">
                        🏸
                    </div>
                </div>
            </div>
        </div>

        <!-- 15 Hourly Slots Grid (06:00 AM to 09:00 PM) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @foreach ($slotsData as $slot)
                @php
                    $isBooked = $slot['is_booked'];
                    $isPast = $slot['is_past'] ?? false;
                    $booking = $slot['booking'];
                    
                    if ($isBooked) {
                        $cardBorder = $isPast
                            ? 'border-slate-300/80 bg-slate-100/60 shadow-2xs opacity-90'
                            : 'border-blue-300/80 bg-blue-50/15 ring-1 ring-blue-400/20 shadow-xs';
                    } elseif ($isPast) {
                        $cardBorder = 'border-slate-200 bg-slate-50/70 shadow-none opacity-75';
                    } else {
                        $cardBorder = 'border-slate-200/90 hover:border-emerald-500/60 bg-white shadow-xs hover:shadow-md';
                    }
                @endphp

                <div class="flex flex-col justify-between min-h-[220px] rounded-2xl border transition-all {{ $cardBorder }} overflow-hidden">
                    <!-- Slot Header -->
                    <div class="px-3.5 py-2.5 {{ $isPast && !$isBooked ? 'bg-slate-100/80' : 'bg-slate-50/80' }} border-b border-slate-100 flex items-center justify-between">
                        <span class="font-mono font-bold text-xs {{ $isPast && !$isBooked ? 'text-slate-500' : 'text-slate-900' }}">{{ $slot['display_time'] }}</span>
                        @if ($isBooked)
                            @if ($isPast)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700 border border-slate-300 uppercase tracking-wider">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-500 inline-block" style="width: 6px; height: 6px;"></span> Concluded
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-900 border border-blue-200 uppercase tracking-wider">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 inline-block" style="width: 6px; height: 6px;"></span> Booked
                                </span>
                            @endif
                        @elseif ($isPast)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-600 border border-slate-300 uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400 inline-block" style="width: 6px; height: 6px;"></span> Time Expired
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-200 uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 inline-block" style="width: 6px; height: 6px;"></span> Open
                            </span>
                        @endif
                    </div>

                    <!-- Slot Content Body (flex-1 for perfect equal vertical distribution) -->
                    <div class="p-3.5 flex-1 flex flex-col justify-center">
                        @if ($isBooked)
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-md {{ $isPast ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-800' }} font-bold text-[10px] flex items-center justify-center shrink-0" style="width: 24px; height: 24px;">
                                        {{ substr($booking['member_name'], 0, 1) }}
                                    </div>
                                    <div class="font-bold text-xs {{ $isPast ? 'text-slate-700' : 'text-slate-900' }} truncate" title="{{ $booking['member_name'] }}">
                                        {{ $booking['member_name'] }}
                                    </div>
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono truncate pl-8">
                                    {{ $booking['membership_number'] }}
                                </div>
                                <div class="pt-1 flex items-center justify-between text-[10px]">
                                    <span class="text-slate-400 font-mono">{{ $booking['booking_number'] }}</span>
                                    @if ($booking['is_plan_covered'])
                                        <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 font-bold border border-emerald-200">
                                            Plan (₹0)
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-800 font-bold border border-amber-200">
                                            Pay-Use (₹100)
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @elseif ($isPast)
                            <div class="text-center py-2 space-y-1 text-slate-400 select-none">
                                <div class="text-xl opacity-40">⏳</div>
                                <div class="text-xs font-semibold text-slate-500">Slot Elapsed</div>
                                <div class="text-[10px] text-slate-400">Past Time (Booking Closed)</div>
                            </div>
                        @else
                            <div class="text-center py-2 space-y-1 text-slate-400">
                                <div class="text-xl">🏸</div>
                                <div class="text-xs font-semibold text-slate-600">Court Slot Open</div>
                                <div class="text-[10px] text-slate-400">Standard 60-Min Play</div>
                            </div>
                        @endif
                    </div>

                    <!-- Slot Footer Button -->
                    <div class="p-2.5 bg-slate-50 border-t border-slate-100">
                        @if ($isBooked)
                            @if ($isPast)
                                <button type="button" disabled
                                    class="w-full py-1.5 bg-slate-100 text-slate-400 border border-slate-200 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 cursor-not-allowed">
                                    <svg width="14" height="14" class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span>Play Concluded</span>
                                </button>
                            @else
                                <button wire:click="openCancelModal({{ $booking['id'] }}, '{{ $booking['booking_number'] }}')" type="button"
                                    class="w-full py-1.5 bg-white hover:bg-rose-50 text-rose-700 hover:text-rose-800 border border-slate-200 hover:border-rose-300 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                    <svg width="14" height="14" class="w-3.5 h-3.5 text-rose-600 shrink-0" style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    <span>Cancel Booking</span>
                                </button>
                            @endif
                        @elseif ($isPast)
                            <button type="button" disabled
                                class="w-full py-1.5 bg-slate-100 text-slate-400 border border-slate-200 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 cursor-not-allowed select-none">
                                <svg width="14" height="14" class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Booking Closed</span>
                            </button>
                        @else
                            <button wire:click="openBookModal('{{ $slot['start_time'] }}', '{{ $slot['end_time'] }}')" type="button"
                                class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                <svg width="14" height="14" class="w-3.5 h-3.5 text-white shrink-0" style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Book This Slot</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- ========================================== -->
        <!-- MODAL: Book Badminton Slot                 -->
        <!-- ========================================== -->
        @if ($showBookModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
                role="dialog" aria-modal="true" aria-labelledby="book-court-modal-title">
                <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-200 p-6 sm:p-7 space-y-5">
                    <!-- Modal Header -->
                    <div class="flex items-start justify-between border-b border-slate-100 pb-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-xl font-bold shadow-2xs shrink-0" style="width: 40px; height: 40px;">
                                🏸
                            </div>
                            <div>
                                <h3 id="book-court-modal-title" class="text-base font-extrabold text-slate-900 tracking-tight">Reserve Badminton Court Slot</h3>
                                <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500 font-medium">
                                    <span class="font-mono">{{ Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</span>
                                    <span>&bull;</span>
                                    <span class="font-mono font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                                        {{ Carbon\Carbon::createFromFormat('H:i', $bookStartTime)->format('g:i A') }} – {{ Carbon\Carbon::createFromFormat('H:i', $bookEndTime)->format('g:i A') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button wire:click="$set('showBookModal', false)" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold p-1 cursor-pointer">&times;</button>
                    </div>

                    <!-- Modal Body Form -->
                    <div class="space-y-4">
                        <!-- Member Selector -->
                        <div>
                            <label for="book-member-id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Select Booking Member (Player 1 / Payer)
                            </label>
                            @php
                                $courtMemberOptions = App\Domain\Membership\Models\Member::with('person')
                                    ->where('status', 'active')
                                    ->orderBy('membership_number')
                                    ->get()
                                    ->map(fn ($m) => [
                                        'id' => $m->id,
                                        'label' => "{$m->membership_number} — {$m->name}",
                                        'subtext' => $m->phone ? "Mobile: {$m->phone}" : null,
                                    ])
                                    ->toArray();
                            @endphp
                            <x-searchable-combobox
                                id="book-member-id"
                                wireModel="bookMemberId"
                                :wireModelLive="true"
                                placeholder="-- Choose Member --"
                                searchPlaceholder="Type member name, # or mobile to search..."
                                :options="$courtMemberOptions"
                            />
                        </div>

                        <!-- Entitlement & Pricing Preview -->
                        @if (!empty($entitlementPreview))
                            <div class="p-4 rounded-2xl border text-xs space-y-2 {{ $entitlementPreview['is_plan_covered'] ? 'bg-emerald-50 border-emerald-300 text-emerald-950' : 'bg-amber-50 border-amber-300 text-amber-950' }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Sports Plan Entitlement</span>
                                    @if ($entitlementPreview['is_plan_covered'])
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-200 text-emerald-950 font-bold text-xs">
                                            ✓ Active Subscription (Free Entry)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-200 text-amber-950 font-bold text-xs">
                                            Pay-Per-Use Court Tariff
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-700">
                                    Verified Plan: <strong>{{ $entitlementPreview['plan_name'] }}</strong>
                                </div>
                                <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between">
                                    <span class="font-bold text-slate-800">Applicable Slot Fee:</span>
                                    <span class="text-lg font-mono font-extrabold {{ $entitlementPreview['is_plan_covered'] ? 'text-emerald-700' : 'text-amber-900' }}">
                                        ₹{{ number_format($entitlementPreview['fee_rupees'], 2) }}
                                    </span>
                                </div>
                            </div>
                        @endif

                        <!-- Court Operating Etiquette -->
                        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 text-xs text-slate-600 space-y-1">
                            <span class="font-bold text-slate-800 uppercase text-[10px] tracking-wider block">Malabar Club Badminton Court Rules</span>
                            <p>&bull; Maximum 4 players permitted on wooden surface per booked hour.</p>
                            <p>&bull; Non-marking gum-sole badminton shoes are mandatory.</p>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button wire:click="$set('showBookModal', false)" type="button"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-xs transition cursor-pointer">
                            Cancel
                        </button>
                        <button wire:click="confirmBooking" type="button"
                            @if(!$bookMemberId) disabled @endif
                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer flex items-center gap-1.5">
                            <svg width="16" height="16" class="w-4 h-4 text-white shrink-0" style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Confirm &amp; Reserve Slot</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- MODAL: Cancel Badminton Booking            -->
        <!-- ========================================== -->
        @if ($showCancelModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
                role="dialog" aria-modal="true" aria-labelledby="cancel-court-modal-title">
                <div class="bg-white rounded-3xl max-w-sm w-full shadow-2xl border border-slate-200 p-6 space-y-4 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center text-xl mx-auto shadow-2xs shrink-0" style="width: 48px; height: 48px;">
                        ⚠️
                    </div>
                    <div>
                        <h3 id="cancel-court-modal-title" class="text-base font-extrabold text-slate-900">Cancel Court Slot?</h3>
                        <p class="text-xs text-slate-500 font-mono mt-1">Booking: {{ $cancelBookingRef }}</p>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        Are you sure you want to release this badminton court reservation? The slot will immediately become available for other members.
                    </p>

                    <div class="flex items-center justify-center gap-3 pt-2">
                        <button wire:click="$set('showCancelModal', false)" type="button"
                            class="flex-1 py-2.5 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-xs rounded-xl transition cursor-pointer">
                            Keep Booking
                        </button>
                        <button wire:click="confirmCancel" type="button"
                            class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer">
                            Release Slot
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
