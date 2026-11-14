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

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['rooms.view_rack', 'courts.view_schedule']));
    }

    public function getViewData(): array
    {
        // 1. Accommodation Rooms (Rooms 101 - 105)
        $rooms = Facility::where('type', 'room')->orderBy('code')->get();

        $todayStr = today()->toDateString();

        $roomStatuses = $rooms->map(function (Facility $room) use ($todayStr) {
            $inMaintenance = !$room->is_active || \App\Domain\Facility\Models\FacilityMaintenanceBlock::where('facility_id', $room->id)
                ->where('status', 'active')
                ->whereDate('start_date', '<=', $todayStr)
                ->whereDate('end_date', '>', $todayStr)
                ->exists();

            $occupiedRes = RoomReservation::with(['guest.person', 'sponsorMember.person', 'payer'])
                ->where('facility_id', $room->id)
                ->where('status', 'checked_in')
                ->whereDate('check_in_date', '<=', $todayStr)
                ->orderByDesc('check_in_date')
                ->orderByDesc('id')
                ->first();

            $reservedToday = !$occupiedRes
                ? RoomReservation::with(['guest.person', 'sponsorMember.person', 'payer'])
                    ->where('facility_id', $room->id)
                    ->where('status', 'reserved')
                    ->whereDate('check_in_date', '<=', $todayStr)
                    ->whereDate('check_out_date', '>', $todayStr)
                    ->orderBy('check_in_date')
                    ->first()
                : null;

            $latestCheckedOut = (!$occupiedRes && !$reservedToday)
                ? RoomReservation::where('facility_id', $room->id)
                    ->where('status', 'checked_out')
                    ->orderByDesc('checked_out_at')
                    ->orderByDesc('id')
                    ->first()
                : null;

            $status = 'available';
            $occupantName = null;

            if ($inMaintenance) {
                $status = 'maintenance';
            } elseif ($occupiedRes) {
                $status = 'occupied';
                $occupantName = $occupiedRes->occupant_name;
            } elseif ($reservedToday) {
                $status = 'reserved_today';
                $occupantName = 'Arriving: ' . $reservedToday->occupant_name;
            } elseif (($room->housekeeping_status ?? 'clean') === 'dirty' || $latestCheckedOut?->housekeeping_status === 'dirty') {
                $status = 'cleaning';
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
