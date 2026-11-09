<x-filament-widgets::widget>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-lg">
                    🏨
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">Front-Desk &amp; Facilities Pulse</h3>
                    <p class="text-[11px] text-slate-500">Live accommodation &amp; court bookings</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/admin/room-rack') }}" class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1">
                    <span>Accommodation Deck</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- Section 1: Accommodation Rooms 101-105 -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between text-xs">
                <span class="font-bold uppercase tracking-wider text-slate-500 text-[10px]">Guest Rooms ({{ str_replace('ROOM-', '', $roomStatuses->first()['code'] ?? '101') }}–{{ str_replace('ROOM-', '', $roomStatuses->last()['code'] ?? '105') }})</span>
                <span class="text-[11px] font-mono text-slate-500">{{ $roomStatuses->count() }} Deluxe {{ \Illuminate\Support\Str::plural('Room', $roomStatuses->count()) }}</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                @foreach ($roomStatuses as $room)
                    @php
                        $badgeStyle = match ($room['status']) {
                            'occupied' => 'bg-blue-500/10 border-blue-500/30 text-blue-700 dark:text-blue-400',
                            'reserved_today' => 'bg-purple-500/10 border-purple-500/30 text-purple-700 dark:text-purple-400',
                            'cleaning' => 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-400',
                            'maintenance' => 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-400',
                            default => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400',
                        };

                        $statusLabel = match ($room['status']) {
                            'occupied' => 'Occupied',
                            'reserved_today' => 'Reserved',
                            'cleaning' => 'Cleaning',
                            'maintenance' => 'Maint.',
                            default => 'Vacant',
                        };
                    @endphp

                    <div class="p-3 rounded-xl border {{ $badgeStyle }} flex flex-col justify-between text-left min-h-[72px]">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-sm font-extrabold tracking-tight">{{ $room['code'] }}</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-white/50 dark:bg-slate-900/50 shadow-xs">
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <div class="mt-2 truncate text-xs text-slate-700 dark:text-slate-300 font-medium" title="{{ $room['occupant'] ?? 'Ready for guest check-in' }}">
                            {{ $room['occupant'] ?? 'Ready for check-in' }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Section 2: Badminton Wooden Court -->
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 space-y-3">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5">
                    <span>🏸</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">Indoor Badminton Court &bull; {{ $court?->name ?? 'Wooden Court' }}</span>
                </div>
                <a href="{{ url('/admin/court-schedule') }}" class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                    Schedule &rarr;
                </a>
            </div>

            <!-- Capacity Bar & Next Booking in 2 columns on wide screens -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                <div class="space-y-1.5">
                    <div class="flex justify-between text-[11px] text-slate-500 font-mono">
                        <span>Today's Bookings: <strong class="text-slate-900 dark:text-white">{{ $bookedSlots }} / {{ $totalDailySlots }}</strong> slots</span>
                        <span class="font-bold text-amber-600 dark:text-amber-400">{{ $utilizationPct }}% Capacity</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-gradient-to-r from-amber-500 to-emerald-500 h-2.5 rounded-full transition-all duration-500"
                            style="width: {{ min(100, max(5, $utilizationPct)) }}%"></div>
                    </div>
                </div>

                <div class="p-3 bg-slate-50 dark:bg-slate-950/60 rounded-xl border border-slate-100 dark:border-slate-800/70 text-xs flex items-center justify-between">
                    @if ($nextBooking)
                        <div class="truncate">
                            <span class="text-slate-500 text-[11px]">Next Slot:</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-white ml-1">{{ date('g:i A', strtotime($nextBooking->start_time)) }}</span>
                            <span class="text-slate-500 dark:text-slate-400 text-[11px] ml-1">({{ $nextBooking->member?->name ?? 'Member' }})</span>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 shrink-0">
                            Booked
                        </span>
                    @else
                        <div class="text-slate-500 text-[11px]">
                            No remaining reservations scheduled for today.
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 shrink-0">
                            Court Open
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
