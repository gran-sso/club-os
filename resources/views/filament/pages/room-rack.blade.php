<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Top Toolbar: Date Navigation, Legend, New Reservation -->
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

            <!-- Operational Status Legend -->
            <div class="hidden xl:flex items-center gap-3 text-xs font-medium text-slate-600 bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block" style="width: 10px; height: 10px;"></span> Vacant Ready</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-teal-600 inline-block" style="width: 10px; height: 10px;"></span> Occupied</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block" style="width: 10px; height: 10px;"></span> Reserved</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block" style="width: 10px; height: 10px;"></span> Needs Cleaning</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block" style="width: 10px; height: 10px;"></span> Maintenance</span>
            </div>

            <!-- Primary Action -->
            <div>
                <button wire:click="openNewBookingModal" type="button"
                    class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-xs shadow-amber-500/20 active:scale-98 transition-all cursor-pointer flex items-center gap-2">
                    <svg width="16" height="16" class="w-4 h-4 text-slate-950 shrink-0" style="width: 16px; height: 16px; min-width: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>New Room Reservation</span>
                </button>
            </div>
        </div>

        <!-- 5 Rooms Grid (Rooms 101 to 105) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-5 items-stretch">
            @foreach ($roomsData as $room)
                @php
                    $isOccupied = $room['status'] === 'occupied';
                    $isReserved = $room['status'] === 'reserved';
                    $isVacantReady = $room['status'] === 'vacant_ready';
                    $isVacantDirty = $room['status'] === 'vacant_dirty';
                    $isMaintenance = $room['status'] === 'maintenance';

                    $cardBorder = match(true) {
                        $isOccupied => 'border-teal-400/80 bg-teal-50/15 ring-1 ring-teal-500/20',
                        $isReserved => 'border-blue-400/80 bg-blue-50/15 ring-1 ring-blue-500/20',
                        $isVacantDirty => 'border-amber-400/80 bg-amber-50/15 ring-1 ring-amber-400/20',
                        $isMaintenance => 'border-rose-300/80 bg-rose-50/15 ring-1 ring-rose-400/20',
                        default => 'border-slate-200/90 bg-white hover:border-emerald-500/60',
                    };

                    $badgeClass = match(true) {
                        $isOccupied => 'bg-teal-100 text-teal-900 border-teal-300',
                        $isReserved => 'bg-blue-100 text-blue-900 border-blue-300',
                        $isVacantDirty => 'bg-amber-100 text-amber-900 border-amber-300',
                        $isMaintenance => 'bg-rose-100 text-rose-900 border-rose-300',
                        default => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                    };

                    $statusLabel = match(true) {
                        $isOccupied => 'OCCUPIED',
                        $isReserved => 'RESERVED',
                        $isVacantDirty => 'DIRTY',
                        $isMaintenance => 'MAINTENANCE',
                        default => 'READY',
                    };
                @endphp

                <div class="flex flex-col justify-between rounded-3xl border transition-all shadow-xs hover:shadow-md bg-white {{ $cardBorder }} overflow-hidden">
                    <!-- Room Header -->
                    <div class="p-4 bg-slate-50/70 border-b border-slate-100">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-[10px] font-mono font-bold text-slate-500 uppercase tracking-wider block">{{ $room['code'] }}</span>
                                <h4 class="font-extrabold text-sm text-slate-900 tracking-tight mt-0.5">{{ $room['name'] }}</h4>
                                <span class="text-[10px] text-slate-500 font-medium">Max {{ $room['capacity'] }} Guests</span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold border tracking-wider uppercase {{ $badgeClass }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                    </div>

                    <!-- Room Main Content Body (flex-1 ensures uniform height) -->
                    <div class="p-4 flex-1 flex flex-col justify-between space-y-3 text-xs">
                        <!-- State Details -->
                        <div>
                            @if ($isOccupied || $isReserved)
                                @php $res = $room['active_reservation']; @endphp
                                <div class="bg-white p-3 rounded-2xl border border-slate-200 space-y-1.5 shadow-2xs">
                                    <div class="font-bold text-slate-900 truncate text-xs" title="{{ $res['guest_name'] }}">
                                        {{ $res['guest_name'] }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono">
                                        {{ $res['guest_phone'] }}
                                    </div>
                                    <div class="pt-1.5 border-t border-slate-100 flex justify-between items-center text-[10px]">
                                        <span class="text-slate-500">Dates:</span>
                                        <span class="font-semibold text-slate-800 font-mono">{{ $res['check_in_date'] }} &rarr; {{ $res['check_out_date'] }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px]">
                                        <span class="text-slate-500">Total:</span>
                                        <span class="font-mono font-bold text-slate-900">₹{{ number_format($res['total_charge_rupees'], 2) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px]">
                                        <span class="text-slate-500">Paid:</span>
                                        <span class="font-mono font-bold {{ $res['is_fully_paid'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                            ₹{{ number_format($res['paid_amount_rupees'], 2) }}
                                        </span>
                                    </div>
                                    @if(!$res['is_fully_paid'])
                                        <div class="pt-1 border-t border-rose-100 flex justify-between items-center text-[10px] text-rose-700 font-bold">
                                            <span>Balance Due:</span>
                                            <span class="font-mono">₹{{ number_format($res['balance_rupees'], 2) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($isMaintenance)
                                <div class="bg-rose-50/60 p-3.5 rounded-2xl border border-rose-200 text-rose-900 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5 text-xs text-rose-800">
                                        <span>🔧</span> Under Maintenance
                                    </div>
                                    <p class="text-[10px] leading-tight text-rose-700">Room blocked for scheduled repairs. Unavailable for reservations.</p>
                                </div>
                            @elseif ($isVacantDirty)
                                <div class="bg-amber-50/60 p-3.5 rounded-2xl border border-amber-200 text-amber-950 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5 text-xs text-amber-800">
                                        <span>🧹</span> Needs Cleaning
                                    </div>
                                    <p class="text-[10px] leading-tight text-amber-700">Previous occupant checked out. Requires linen refresh and sanitization before check-in.</p>
                                </div>
                            @else
                                <div class="bg-emerald-50/60 p-3.5 rounded-2xl border border-emerald-200 text-emerald-950 space-y-1">
                                    <div class="font-bold flex items-center gap-1.5 text-xs text-emerald-800">
                                        <span>✅</span> Clean &amp; Ready
                                    </div>
                                    <p class="text-[10px] text-emerald-700 leading-tight">Room is sanitized and ready for immediate guest check-in.</p>
                                    @if ($room['upcoming_reservation'])
                                        <div class="text-[10px] text-emerald-800 pt-1 border-t border-emerald-200/60 font-medium">
                                            Next: <strong>{{ $room['upcoming_reservation']['guest_name'] }}</strong> ({{ $room['upcoming_reservation']['check_in_date'] }})
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- 7-Day Mini-Forecast Strip -->
                        <div class="pt-2">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block mb-1">7-Day Occupancy Forecast</span>
                            <div class="grid grid-cols-7 gap-1">
                                @foreach ($room['forecast'] as $fc)
                                    @php
                                        $fcColor = match($fc['status']) {
                                            'occupied' => 'bg-teal-500',
                                            'reserved' => 'bg-blue-400',
                                            'maintenance' => 'bg-rose-400',
                                            default => 'bg-slate-200',
                                        };
                                    @endphp
                                    <div class="h-3 rounded-xs {{ $fcColor }}" title="{{ $fc['date'] }}: {{ ucfirst($fc['status']) }}"></div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Room Action Buttons Footer -->
                    <div class="p-3 bg-slate-50 border-t border-slate-100">
                        @if ($isOccupied)
                            <button wire:click="openCheckOut({{ $room['active_reservation']['id'] }})" type="button"
                                class="w-full py-2 bg-white hover:bg-rose-50 text-rose-700 hover:text-rose-800 border border-slate-200 hover:border-rose-300 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                <svg width="14" height="14" class="w-3.5 h-3.5 text-rose-600 shrink-0" style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <span>Check-Out Guest</span>
                            </button>
                        @elseif ($isReserved)
                            <div class="grid grid-cols-2 gap-2">
                                <button wire:click="openCheckIn({{ $room['active_reservation']['id'] }})" type="button"
                                    class="py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">
                                    Check-In
                                </button>
                                @if(!$room['active_reservation']['is_fully_paid'])
                                    <button wire:click="openPaymentModal({{ $room['active_reservation']['id'] }})" type="button"
                                        class="py-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 rounded-xl text-xs font-bold transition cursor-pointer">
                                        Collect ₹
                                    </button>
                                @else
                                    <button wire:click="openHousekeeping({{ $room['id'] }}, '{{ $room['housekeeping_status'] }}')" type="button"
                                        class="py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-xl text-xs font-semibold transition cursor-pointer">
                                        Status
                                    </button>
                                @endif
                            </div>
                        @elseif ($isVacantDirty)
                            <button wire:click="openHousekeeping({{ $room['id'] }}, 'clean')" type="button"
                                class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                <svg width="14" height="14" class="w-3.5 h-3.5 text-white shrink-0" style="width: 14px; height: 14px; min-width: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>Mark as Cleaned</span>
                            </button>
                        @else
                            <div class="grid grid-cols-2 gap-2">
                                <button wire:click="openNewBookingModal({{ $room['id'] }})" type="button"
                                    class="py-2 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-bold rounded-xl text-xs transition shadow-xs cursor-pointer">
                                    Book Room
                                </button>
                                <button wire:click="openHousekeeping({{ $room['id'] }}, '{{ $room['housekeeping_status'] }}')" type="button"
                                    class="py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-xl text-xs font-semibold transition cursor-pointer">
                                    Status
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- ========================================== -->
        <!-- MODAL: New Room Reservation                -->
        <!-- ========================================== -->
        @if ($showNewBookingModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
                role="dialog" aria-modal="true" aria-labelledby="new-room-booking-title">
                <div class="bg-white rounded-3xl max-w-xl w-full shadow-2xl border border-slate-200 p-6 sm:p-7 space-y-5 max-h-[90vh] overflow-y-auto">
                    <!-- Modal Header -->
                    <div class="flex items-start justify-between border-b border-slate-100 pb-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-xl font-bold shadow-2xs">
                                🏨
                            </div>
                            <div>
                                <h3 id="new-room-booking-title" class="text-base font-extrabold text-slate-900 tracking-tight">Front Desk Room Reservation</h3>
                                <p class="text-xs text-slate-500">{{ config('club.name', 'Malabar Club') }} Guest Accommodation Rooms</p>
                            </div>
                        </div>
                        <button wire:click="$set('showNewBookingModal', false)" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold p-1 cursor-pointer">&times;</button>
                    </div>

                    <!-- Form Content Divided in Clear Sections -->
                    @php
                        $rackRoomOptions = App\Domain\Tariff\Models\Facility::where('type', 'room')->where('is_active', true)->get()->map(fn ($f) => [
                            'id' => $f->id,
                            'label' => "{$f->code} — {$f->name}",
                            'subtext' => "Capacity: {$f->capacity} Guests",
                        ])->toArray();

                        $rackGuestOptions = App\Domain\Access\Models\Guest::with('person')->latest('id')->take(100)->get()->map(fn ($g) => [
                            'id' => $g->id,
                            'label' => $g->name,
                            'subtext' => $g->phone ? "Mobile: {$g->phone}" : null,
                        ])->toArray();

                        $rackMemberOptions = App\Domain\Membership\Models\Member::with('person')->where('status', 'active')->orderBy('membership_number')->get()->map(fn ($m) => [
                            'id' => $m->id,
                            'label' => "{$m->membership_number} — {$m->name}",
                            'subtext' => $m->phone ? "Mobile: {$m->phone}" : null,
                        ])->toArray();
                    @endphp
                    <div class="space-y-4">
                        <!-- Section 1: Room & Occupant -->
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600 block">1. Room &amp; Occupant Identification</span>

                            <div>
                                <label for="new-booking-room" class="block text-xs font-bold text-slate-700 mb-1">Accommodation Room</label>
                                <x-searchable-combobox
                                    id="new-booking-room"
                                    wireModel="newBooking.facility_id"
                                    placeholder="-- Choose Room --"
                                    searchPlaceholder="Search room code or name..."
                                    :options="$rackRoomOptions"
                                />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="new-booking-occupant-type" class="block text-xs font-bold text-slate-700 mb-1">Occupant Type</label>
                                    <select id="new-booking-occupant-type" wire:model.live="newBooking.guest_type"
                                        class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                        <option value="guest">Registered Guest</option>
                                        <option value="member">Club Member</option>
                                    </select>
                                </div>

                                <div>
                                    @if ($newBooking['guest_type'] === 'guest')
                                        <label for="new-booking-guest-id" class="block text-xs font-bold text-slate-700 mb-1">Select Guest</label>
                                        <x-searchable-combobox
                                            id="new-booking-guest-id"
                                            wireModel="newBooking.guest_id"
                                            placeholder="-- Choose Guest --"
                                            searchPlaceholder="Type guest name or phone to search..."
                                            :options="$rackGuestOptions"
                                        />
                                    @else
                                        <label for="new-booking-member-id" class="block text-xs font-bold text-slate-700 mb-1">Select Member</label>
                                        <x-searchable-combobox
                                            id="new-booking-member-id"
                                            wireModel="newBooking.member_id"
                                            placeholder="-- Choose Member --"
                                            searchPlaceholder="Type member name, # or phone..."
                                            :options="$rackMemberOptions"
                                        />
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label for="new-booking-sponsor" class="block text-xs font-bold text-slate-700 mb-1">Sponsoring Club Member (Optional)</label>
                                <x-searchable-combobox
                                    id="new-booking-sponsor"
                                    wireModel="newBooking.sponsor_member_id"
                                    placeholder="Self-Sponsored / Direct"
                                    searchPlaceholder="Type sponsor name, # or phone..."
                                    :options="$rackMemberOptions"
                                />
                            </div>
                        </div>

                        <!-- Section 2: Dates & Financial Tariff -->
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600 block">2. Stay Dates &amp; Tariff Charges</span>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="new-booking-checkin" class="block text-xs font-bold text-slate-700 mb-1">Check-In Date</label>
                                    <input type="date" id="new-booking-checkin" wire:model="newBooking.check_in_date"
                                        class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                                <div>
                                    <label for="new-booking-checkout" class="block text-xs font-bold text-slate-700 mb-1">Check-Out Date</label>
                                    <input type="date" id="new-booking-checkout" wire:model="newBooking.check_out_date"
                                        class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="new-booking-rate" class="block text-xs font-bold text-slate-700 mb-1">Rate Per Night (₹)</label>
                                    <div class="relative">
                                        <span class="absolute left-3.5 top-2 font-mono text-slate-400 font-bold">₹</span>
                                        <input type="number" id="new-booking-rate" wire:model="newBooking.rate_per_night_rupees" min="0"
                                            class="w-full pl-8 pr-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                    </div>
                                </div>
                                <div>
                                    <label for="new-booking-paid" class="block text-xs font-bold text-slate-700 mb-1">Advance Collected (₹)</label>
                                    <div class="relative">
                                        <span class="absolute left-3.5 top-2 font-mono text-slate-400 font-bold">₹</span>
                                        <input type="number" id="new-booking-paid" wire:model="newBooking.paid_amount_rupees" min="0"
                                            class="w-full pl-8 pr-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="new-booking-payment-mode" class="block text-xs font-bold text-slate-700 mb-1">Payment Tender Mode</label>
                                <select id="new-booking-payment-mode" wire:model="newBooking.payment_mode"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                    <option value="cash">💵 Cash (Front Desk Till Drawer)</option>
                                    <option value="upi">📱 Digital UPI (Counter QR / POS Terminal)</option>
                                    <option value="wallet">💳 Member Prepaid Wallet (Server Ledger Debit)</option>
                                </select>
                                <span class="text-[10px] text-slate-500 block mt-1">Collecting advance immediately generates an official tax invoice &amp; receipt credited to Room Accommodation Revenue (4030).</span>
                            </div>
                        </div>

                        <!-- Section 3: Arrival Notes -->
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                            <label for="new-booking-notes" class="block text-xs font-bold text-slate-700 mb-1">Stay Remarks &amp; Arrival Notes</label>
                            <input type="text" id="new-booking-notes" wire:model="newBooking.notes" placeholder="e.g. Extra bed request, late evening check-in"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button wire:click="$set('showNewBookingModal', false)" type="button"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-xs transition cursor-pointer">
                            Cancel
                        </button>
                        <button wire:click="confirmNewBooking" type="button"
                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer flex items-center gap-1.5">
                            <svg width="16" height="16" class="w-4 h-4 text-white shrink-0" style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Confirm Reservation</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- MODAL: Front Desk Check-In Confirmation   -->
        <!-- ========================================== -->
        @if ($showCheckInModal && $activeReservationData)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
                role="dialog" aria-modal="true" aria-labelledby="checkin-modal-title">
                <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-200 p-6 space-y-4">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-xl font-bold shadow-2xs">
                                🔑
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Front Desk Check-In</span>
                                <h3 id="checkin-modal-title" class="text-base font-extrabold text-slate-900">{{ $activeReservationData['room_name'] }}</h3>
                                <p class="text-[11px] text-slate-500 font-mono">{{ $activeReservationData['reservation_number'] }}</p>
                            </div>
                        </div>
                        <button wire:click="$set('showCheckInModal', false)" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold p-1 cursor-pointer">&times;</button>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs space-y-2.5">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Guest Name:</span>
                            <span class="font-bold text-slate-900">{{ $activeReservationData['guest_name'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Stay Period:</span>
                            <span class="font-semibold text-slate-800 font-mono">{{ $activeReservationData['check_in_date'] }} &rarr; {{ $activeReservationData['check_out_date'] }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200/70 pt-2">
                            <span class="text-slate-500">Total Room Charge:</span>
                            <span class="font-mono font-bold text-slate-900">₹{{ number_format($activeReservationData['total_rupees'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Advance Paid:</span>
                            <span class="font-mono font-bold text-emerald-700">₹{{ number_format($activeReservationData['paid_rupees'], 2) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200/70 pt-2 font-bold text-xs">
                            <span class="{{ $activeReservationData['is_fully_paid'] ? 'text-emerald-700' : 'text-amber-800' }}">Balance Status:</span>
                            @if ($activeReservationData['is_fully_paid'])
                                <span class="font-mono font-extrabold text-emerald-700">PAID IN FULL (₹0.00)</span>
                            @else
                                <span class="font-mono font-extrabold text-rose-700">DUE: ₹{{ number_format($activeReservationData['unpaid_rupees'], 2) }}</span>
                            @endif
                        </div>

                        @if ($activeReservationData['housekeeping_status'] !== 'clean')
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-[11px] font-semibold flex items-center gap-2">
                                <span>🧹</span>
                                <span>Room is currently marked as <strong>{{ ucfirst($activeReservationData['housekeeping_status']) }}</strong>. Housekeeping must sanitize before key handover.</span>
                            </div>
                        @endif
                    </div>

                    @if (!$activeReservationData['is_fully_paid'])
                        <!-- Integrated Balance Collection Section -->
                        <div class="p-4 bg-amber-50/70 border border-amber-200 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-amber-950 flex items-center gap-1.5">
                                    <span>💵</span> Collect Balance Before Key Handover
                                </span>
                                <span class="text-[10px] font-semibold px-2 py-0.5 bg-rose-100 text-rose-800 rounded-md">No-Credit Policy</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Amount to Collect (₹)</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 font-mono text-slate-400 font-bold">₹</span>
                                        <input type="number" wire:model="paymentAmountRupees" min="1" max="{{ $activeReservationData['unpaid_rupees'] }}"
                                            class="w-full pl-7 pr-3 py-1.5 bg-white border border-slate-300 rounded-xl font-mono font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none text-xs">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Payment Tender Mode</label>
                                    <select wire:model="paymentMode"
                                        class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none text-xs">
                                        <option value="cash">💵 Cash (Till Drawer)</option>
                                        <option value="upi">📱 Digital UPI (Counter QR)</option>
                                        <option value="wallet">💳 Prepaid Wallet (Member Ledger)</option>
                                    </select>
                                </div>
                            </div>
                            <span class="text-[10px] text-amber-900 block leading-tight">
                                Immediate official Tax Invoice &amp; Payment Receipt will be recorded in General Ledger (4030).
                            </span>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                        <button wire:click="$set('showCheckInModal', false)" type="button"
                            class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-xs rounded-xl transition cursor-pointer">
                            Cancel
                        </button>

                        @if (!$activeReservationData['is_fully_paid'])
                            @if ($activeReservationData['housekeeping_status'] === 'clean')
                                <button wire:click="confirmAdvancePayment" type="button"
                                    class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 font-bold text-xs rounded-xl transition cursor-pointer">
                                    Collect Payment Only
                                </button>
                                <button wire:click="confirmCheckInWithPayment" type="button"
                                    class="px-5 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer flex items-center gap-1.5">
                                    <svg width="15" height="15" class="w-3.5 h-3.5 text-white shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                    </svg>
                                    <span>Collect ₹{{ number_format($paymentAmountRupees, 0) }} &amp; Issue Key</span>
                                </button>
                            @else
                                <button wire:click="confirmAdvancePayment" type="button"
                                    class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs rounded-xl transition shadow-xs cursor-pointer flex items-center gap-1.5">
                                    <span>Collect Payment Only (₹{{ number_format($paymentAmountRupees, 0) }})</span>
                                </button>
                            @endif
                        @else
                            @if ($activeReservationData['housekeeping_status'] === 'clean')
                                <button wire:click="confirmCheckIn" type="button"
                                    class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer flex items-center gap-1.5">
                                    <svg width="15" height="15" class="w-3.5 h-3.5 text-white shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                    </svg>
                                    <span>Confirm Check-In &amp; Issue Key</span>
                                </button>
                            @else
                                <div class="text-xs text-amber-700 font-bold py-1">Room must be Cleaned by Housekeeping before key handover</div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- MODAL: Check-Out Guest                     -->
        <!-- ========================================== -->
        @if ($showCheckOutModal && $activeReservationData)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
                role="dialog" aria-modal="true" aria-labelledby="checkout-modal-title">
                <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-200 p-6 space-y-4">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Front Desk Check-Out</span>
                            <h3 id="checkout-modal-title" class="text-base font-extrabold text-slate-900">{{ $activeReservationData['room_name'] }}</h3>
                            <p class="text-[11px] text-slate-500 font-mono">{{ $activeReservationData['reservation_number'] }}</p>
                        </div>
                        <button wire:click="$set('showCheckOutModal', false)" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold p-1 cursor-pointer">&times;</button>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Guest Name:</span>
                            <span class="font-bold text-slate-900">{{ $activeReservationData['guest_name'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Total Settled:</span>
                            <span class="font-mono font-bold text-emerald-700">₹{{ number_format($activeReservationData['paid_rupees'], 2) }}</span>
                        </div>
                        <p class="text-[11px] text-slate-500 pt-2 border-t border-slate-200 leading-relaxed">
                            Completing check-out will release the room key and automatically mark the room as <strong>Dirty</strong> for the housekeeping team.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button wire:click="$set('showCheckOutModal', false)" type="button"
                            class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-xs rounded-xl transition cursor-pointer">
                            Cancel
                        </button>
                        <button wire:click="confirmCheckOut" type="button"
                            class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer">
                            Confirm Check-Out
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- MODAL: Housekeeping Cleanliness Status     -->
        <!-- ========================================== -->
        @if ($showHousekeepingModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
                role="dialog" aria-modal="true" aria-labelledby="housekeeping-modal-title">
                <div class="bg-white rounded-3xl max-w-sm w-full shadow-2xl border border-slate-200 p-6 space-y-4">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Housekeeping Protocol</span>
                            <h3 id="housekeeping-modal-title" class="text-base font-extrabold text-slate-900">{{ $selectedRoomName }}</h3>
                        </div>
                        <button wire:click="$set('showHousekeepingModal', false)" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold p-1 cursor-pointer">&times;</button>
                    </div>

                    <div class="space-y-2.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Select Room Status</label>
                        <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer hover:bg-slate-50 transition {{ $housekeepingStatus === 'clean' ? 'border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500' : 'border-slate-200' }}">
                            <input type="radio" wire:model="housekeepingStatus" value="clean" class="text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <div class="text-xs font-bold text-slate-900">Clean &amp; Ready</div>
                                <div class="text-[10px] text-slate-500">Linen refreshed, room sanitized, ready for check-in</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer hover:bg-slate-50 transition {{ $housekeepingStatus === 'dirty' ? 'border-amber-500 bg-amber-50/50 ring-1 ring-amber-500' : 'border-slate-200' }}">
                            <input type="radio" wire:model="housekeepingStatus" value="dirty" class="text-amber-600 focus:ring-amber-500">
                            <div>
                                <div class="text-xs font-bold text-slate-900">Needs Cleaning (Dirty)</div>
                                <div class="text-[10px] text-slate-500">Occupant checked out; housekeeping pending</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer hover:bg-slate-50 transition {{ $housekeepingStatus === 'maintenance' ? 'border-rose-500 bg-rose-50/50 ring-1 ring-rose-500' : 'border-slate-200' }}">
                            <input type="radio" wire:model="housekeepingStatus" value="maintenance" class="text-rose-600 focus:ring-rose-500">
                            <div>
                                <div class="text-xs font-bold text-slate-900">Under Maintenance</div>
                                <div class="text-[10px] text-slate-500">AC, plumbing, or electrical repair block</div>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button wire:click="$set('showHousekeepingModal', false)" type="button"
                            class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-xs rounded-xl transition cursor-pointer">
                            Cancel
                        </button>
                        <button wire:click="confirmHousekeeping" type="button"
                            class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer">
                            Update Status
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================== -->
        <!-- MODAL: Collect Payment Advance             -->
        <!-- ========================================== -->
        @if ($showPaymentModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-fade-in"
                role="dialog" aria-modal="true" aria-labelledby="payment-advance-modal-title">
                <div class="bg-white rounded-3xl max-w-sm w-full shadow-2xl border border-slate-200 p-6 space-y-4">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Front Desk Cashier</span>
                            <h3 id="payment-advance-modal-title" class="text-base font-extrabold text-slate-900">Record Accommodation Payment</h3>
                        </div>
                        <button wire:click="$set('showPaymentModal', false)" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold p-1 cursor-pointer">&times;</button>
                    </div>

                    <div>
                        <label for="payment-advance-amount" class="block text-xs font-bold text-slate-700 mb-1.5">Amount to Collect (₹)</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 font-mono text-slate-400 font-bold text-base">₹</span>
                            <input type="number" id="payment-advance-amount" wire:model="paymentAmountRupees" min="1"
                                class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-base font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label for="payment-advance-mode" class="block text-xs font-bold text-slate-700 mb-1.5">Tender Mode</label>
                        <select id="payment-advance-mode" wire:model="paymentMode"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <option value="cash">💵 Cash (Front Desk Till Drawer)</option>
                            <option value="upi">📱 Digital UPI (Counter QR / POS Terminal)</option>
                            <option value="wallet">💳 Member Prepaid Wallet (Server Ledger Debit)</option>
                        </select>
                        <span class="text-[10px] text-slate-500 block mt-1">Generates an official Bill &amp; Payment Receipt posted directly to Account 4030.</span>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button wire:click="$set('showPaymentModal', false)" type="button"
                            class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-xs rounded-xl transition cursor-pointer">
                            Cancel
                        </button>
                        <button wire:click="confirmAdvancePayment" type="button"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl transition shadow-xs cursor-pointer">
                            Confirm Receipt
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
