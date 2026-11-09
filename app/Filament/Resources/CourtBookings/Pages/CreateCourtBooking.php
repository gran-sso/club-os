<?php

namespace App\Filament\Resources\CourtBookings\Pages;

use App\Domain\Facility\Services\CourtBookingService;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use App\Filament\Resources\CourtBookings\CourtBookingResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCourtBooking extends CreateRecord
{
    protected static string $resource = CourtBookingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $court = Facility::findOrFail((int) $data['facility_id']);
        $member = Member::findOrFail((int) $data['member_id']);
        $startTime = $data['start_time'];
        $endTime = Carbon::parse($startTime)->addHour()->toTimeString();

        $slotStart = Carbon::parse("{$data['booking_date']} {$startTime}");
        if ($slotStart->isPast()) {
            throw new \InvalidArgumentException("Cannot book a badminton court slot for a past time ({$slotStart->format('d M Y, g:i A')}).");
        }

        return app(CourtBookingService::class)->bookCourt(
            court: $court,
            member: $member,
            bookingDate: $data['booking_date'],
            startTime: $startTime,
            endTime: $endTime,
            user: auth()->user()
        );
    }
}

