<?php

namespace App\Filament\Widgets;

use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Tariff\Models\Facility;
use Filament\Widgets\Widget;

class FrontDeskPulseWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.front-desk-pulse';

    protected ?string $pollingInterval = '30s';

    public function getViewData(): array
    {
        // 1. Accommodation Rooms (Rooms 101 - 105)
        $rooms = Facility::where('type', 'room')->orderBy('code')->get();

        $roomStatuses = $rooms->map(function (Facility $room) {
            $activeRes = RoomReservation::with('sponsorMember.person')
                ->where('facility_id', $room->id)
                ->whereIn('status', ['checked_in', 'confirmed'])
                ->whereDate('check_in_date', '<=', today())
                ->whereDate('check_out_date', '>=', today())
                ->first();

            $status = $room->is_active ? 'available' : 'maintenance';
            $occupantName = null;

            if ($activeRes && $activeRes->status === 'checked_in') {
                $status = 'occupied';
                $occupantName = $activeRes->guest_name ?? $activeRes->sponsorMember?->name ?? 'Occupied Guest';
            } elseif ($activeRes && $activeRes->status === 'confirmed') {
                $status = 'reserved_today';
                $occupantName = 'Arriving: ' . ($activeRes->guest_name ?? $activeRes->sponsorMember?->name ?? 'Guest');
            }

            return [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'status' => $status,
                'occupant' => $occupantName,
            ];
        });

        // 2. Indoor Badminton Wooden Court
        $court = Facility::where('type', 'court')->first();
        $totalDailySlots = 16; // 6:00 AM - 10:00 PM
        $bookedSlots = CourtBooking::where('booking_date', today())
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();
        $utilizationPct = (int) round(($bookedSlots / max(1, $totalDailySlots)) * 100);

        $nextBooking = CourtBooking::with('member.person')
            ->where('booking_date', today())
            ->where('status', 'confirmed')
            ->where('start_time', '>=', now()->format('H:i:s'))
            ->orderBy('start_time')
            ->first();

        return [
            'roomStatuses' => $roomStatuses,
            'court' => $court,
            'totalDailySlots' => $totalDailySlots,
            'bookedSlots' => $bookedSlots,
            'utilizationPct' => $utilizationPct,
            'nextBooking' => $nextBooking,
        ];
    }
}
