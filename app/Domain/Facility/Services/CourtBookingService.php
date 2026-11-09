<?php

namespace App\Domain\Facility\Services;

use App\Domain\Billing\Models\BillLine;
use App\Domain\Facility\Exceptions\SlotUnavailableException;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CourtBookingService
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Book a badminton court slot with atomic capacity protection.
     * Plan-covered members pay 0 court fee, but still respect strict slot capacity.
     * Booking for a past time slot is strictly rejected unless explicitly overridden.
     */
    public function bookCourt(
        Facility $court,
        Member $member,
        string $bookingDate,
        string $startTime,
        string $endTime,
        ?BillLine $billLine = null,
        ?User $user = null,
        bool $allowPast = false
    ): CourtBooking {
        if ($court->type !== 'court') {
            throw new InvalidArgumentException("Facility {$court->code} is not a sports court.");
        }

        $slotStart = Carbon::parse("{$bookingDate} {$startTime}");
        if (!$allowPast && $slotStart->isPast()) {
            throw new InvalidArgumentException("Cannot book a badminton court slot for a past time ({$slotStart->format('d M Y, g:i A')}).");
        }

        $date = Carbon::parse($bookingDate)->startOfDay();

        return DB::transaction(function () use ($court, $member, $date, $startTime, $endTime, $billLine, $user) {
            // Lock facility row for concurrency protection
            Facility::where('id', $court->id)->lockForUpdate()->firstOrFail();

            // Check if slot is already booked
            $existing = CourtBooking::where('facility_id', $court->id)
                ->where('booking_date', $date->toDateString())
                ->where('start_time', $startTime)
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw new SlotUnavailableException("Court {$court->name} is already booked on {$date->toDateString()} at {$startTime}.");
            }

            // Check if member has active plan covering badminton
            $entitlement = $this->subscriptionService->checkEntitlement($member, 'badminton', $date);
            $isPlanCovered = $entitlement['is_plan_covered'] ?? false;

            try {
                return CourtBooking::create([
                    'booking_number' => 'CB-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                    'facility_id' => $court->id,
                    'booking_date' => $date->toDateString(),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'member_id' => $member->id,
                    'is_plan_covered' => $isPlanCovered,
                    'bill_line_id' => $billLine?->id,
                    'status' => 'confirmed',
                    'created_by_user_id' => $user?->id,
                ]);
            } catch (QueryException $e) {
                // Catch unique constraint collision (uq_cb_slot)
                if (str_contains($e->getMessage(), 'uq_cb_slot') || str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw new SlotUnavailableException("Court slot was just claimed by another booking.");
                }
                throw $e;
            }
        });
    }

    /**
     * Check if a specific court slot is available.
     */
    public function isSlotAvailable(Facility $court, string $bookingDate, string $startTime): bool
    {
        $date = Carbon::parse($bookingDate)->toDateString();

        return !CourtBooking::where('facility_id', $court->id)
            ->where('booking_date', $date)
            ->where('start_time', $startTime)
            ->where('status', 'confirmed')
            ->exists();
    }

    /**
     * Check if a specific court slot start time has already passed.
     */
    public function isPastSlot(string $bookingDate, string $startTime): bool
    {
        return Carbon::parse("{$bookingDate} {$startTime}")->isPast();
    }

    /**
     * Cancel an existing court booking.
     */
    public function cancelBooking(CourtBooking $booking, ?User $user = null): CourtBooking
    {
        $booking->update(['status' => 'cancelled']);

        return $booking;
    }
}
