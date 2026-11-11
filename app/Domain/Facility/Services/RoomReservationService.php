<?php

namespace App\Domain\Facility\Services;

use App\Domain\Facility\Exceptions\FacilityNotReadyException;
use App\Domain\Facility\Exceptions\PaymentRequiredException;
use App\Domain\Facility\Exceptions\RoomUnavailableException;
use App\Domain\Facility\Models\FacilityMaintenanceBlock;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Tariff\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RoomReservationService
{
    /**
     * Reserve a room with half-open interval [check_in_date, check_out_date) protection.
     */
    public function createReservation(array $data, ?User $user = null): RoomReservation
    {
        return DB::transaction(function () use ($data, $user) {
            $facilityId = $data['facility_id'];
            $facility = Facility::where('id', $facilityId)->lockForUpdate()->firstOrFail();

            if ($facility->type !== 'room') {
                throw new InvalidArgumentException("Facility {$facility->code} is not an accommodation room.");
            }

            $checkIn = Carbon::parse($data['check_in_date'])->startOfDay();
            $checkOut = Carbon::parse($data['check_out_date'])->startOfDay();

            if ($checkOut->lte($checkIn)) {
                throw new InvalidArgumentException("Check-out date must be strictly after check-in date.");
            }

            $checkInStr = $checkIn->toDateString();
            $checkOutStr = $checkOut->toDateString();

            // 1. Check reservation overlap using half-open interval rule [A_start, A_end) and [B_start, B_end)
            $overlappingReservation = RoomReservation::where('facility_id', $facilityId)
                ->whereIn('status', ['reserved', 'checked_in'])
                ->where('check_in_date', '<', $checkOutStr)
                ->where('check_out_date', '>', $checkInStr)
                ->exists();

            if ($overlappingReservation) {
                throw new RoomUnavailableException("Room {$facility->name} is already reserved for the requested dates.");
            }

            // 2. Check maintenance blocks
            $overlappingMaintenance = FacilityMaintenanceBlock::where('facility_id', $facilityId)
                ->where('status', 'active')
                ->where('start_date', '<', $checkOutStr)
                ->where('end_date', '>', $checkInStr)
                ->exists();

            if ($overlappingMaintenance) {
                throw new RoomUnavailableException("Room {$facility->name} is under maintenance during the requested dates.");
            }

            $nights = $checkIn->diffInDays($checkOut);
            $ratePerNightPaise = (int) ($data['rate_per_night_paise'] ?? 150000);
            $totalChargePaise = $ratePerNightPaise * $nights;
            $paidAmountPaise = (int) ($data['paid_amount_paise'] ?? 0);

            return RoomReservation::create([
                'reservation_number' => 'RES-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                'facility_id' => $facilityId,
                'guest_id' => $data['guest_id'] ?? null,
                'sponsor_member_id' => $data['sponsor_member_id'] ?? null,
                'payer_type' => $data['payer_type'] ?? null,
                'payer_id' => $data['payer_id'] ?? null,
                'check_in_date' => $checkInStr,
                'check_out_date' => $checkOutStr,
                'rate_per_night_paise' => $ratePerNightPaise,
                'total_charge_paise' => $totalChargePaise,
                'paid_amount_paise' => $paidAmountPaise,
                'status' => 'reserved',
                'housekeeping_status' => $data['housekeeping_status'] ?? 'clean',
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $user?->id,
            ]);
        });
    }

    /**
     * Record payment/deposit towards a reservation without touching the sponsor.
     */
    public function recordPayment(RoomReservation $reservation, int $amountPaise, ?User $user = null): RoomReservation
    {
        return DB::transaction(function () use ($reservation, $amountPaise) {
            $locked = RoomReservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();
            $locked->paid_amount_paise += $amountPaise;
            $locked->save();

            return $locked;
        });
    }

    /**
     * Check in a guest/member to the room.
     * Enforces:
     * 1. Full accommodation payment required (no-credit invariant).
     * 2. Room is clean and ready.
     * 3. No active maintenance blocks today.
     */
    public function checkIn(RoomReservation $reservation, ?User $user = null): RoomReservation
    {
        return DB::transaction(function () use ($reservation) {
            $locked = RoomReservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();
            $facility = Facility::where('id', $locked->facility_id)->lockForUpdate()->firstOrFail();

            if (!$facility->is_active) {
                throw new FacilityNotReadyException("Cannot check in: room {$facility->name} is currently inactive.");
            }

            if (!$locked->isFullyPaid()) {
                $unpaid = $locked->total_charge_paise - $locked->paid_amount_paise;
                throw new PaymentRequiredException("Cannot check in: reservation requires full payment of accommodation charge (unpaid: {$unpaid} paise).");
            }

            $occupiedByOther = RoomReservation::where('facility_id', $locked->facility_id)
                ->where('id', '!=', $locked->id)
                ->where('status', 'checked_in')
                ->lockForUpdate()
                ->exists();

            if ($occupiedByOther) {
                throw new FacilityNotReadyException("Cannot check in: room is still occupied until current guest checks out.");
            }

            if ($locked->housekeeping_status !== 'clean' || ($facility->housekeeping_status ?? 'clean') !== 'clean') {
                $state = $locked->housekeeping_status !== 'clean'
                    ? $locked->housekeeping_status
                    : $facility->housekeeping_status;

                throw new FacilityNotReadyException("Cannot check in: room is not clean (current status: {$state}).");
            }

            $latestCheckedOut = RoomReservation::where('facility_id', $locked->facility_id)
                ->where('id', '!=', $locked->id)
                ->where('status', 'checked_out')
                ->orderByDesc('checked_out_at')
                ->orderByDesc('id')
                ->first();

            if ($latestCheckedOut && $latestCheckedOut->housekeeping_status === 'dirty') {
                throw new FacilityNotReadyException("Cannot check in: room requires housekeeping cleanup after previous check-out.");
            }

            $inMaintenance = FacilityMaintenanceBlock::where('facility_id', $locked->facility_id)
                ->where('status', 'active')
                ->where('start_date', '<=', now()->toDateString())
                ->where('end_date', '>', now()->toDateString())
                ->exists();

            if ($inMaintenance) {
                throw new FacilityNotReadyException("Cannot check in: room is currently blocked for maintenance.");
            }

            $locked->update([
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * Check out from the room and mark it dirty for housekeeping.
     */
    public function checkOut(RoomReservation $reservation, ?User $user = null): RoomReservation
    {
        return DB::transaction(function () use ($reservation) {
            $locked = RoomReservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();
            $facility = Facility::where('id', $locked->facility_id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => 'checked_out',
                'checked_out_at' => now(),
                'housekeeping_status' => 'dirty',
            ]);

            $facility->update(['housekeeping_status' => 'dirty']);

            return $locked;
        });
    }

    /**
     * Set housekeeping status (e.g. clean, dirty, maintenance).
     */
    public function setHousekeepingStatus(RoomReservation $reservation, string $status): RoomReservation
    {
        if (!in_array($status, ['clean', 'dirty', 'maintenance'])) {
            throw new InvalidArgumentException("Invalid housekeeping status: {$status}");
        }

        $reservation->update(['housekeeping_status' => $status]);

        return $reservation;
    }

    /**
     * Update physical room housekeeping / maintenance status cleanly across Facility,
     * RoomReservation, and FacilityMaintenanceBlock records inside a single transaction.
     */
    public function updateRoomOperationalStatus(
        int $facilityId,
        string $targetStatus,
        string $dateStr,
        ?string $maintenanceReason = null,
        ?string $maintenanceEndDate = null,
        ?User $user = null
    ): void {
        if (! in_array($targetStatus, ['clean', 'dirty', 'maintenance'], true)) {
            throw new InvalidArgumentException("Invalid room status: {$targetStatus}");
        }

        DB::transaction(function () use ($facilityId, $targetStatus, $dateStr, $maintenanceReason, $maintenanceEndDate, $user) {
            $facility = Facility::where('id', $facilityId)->lockForUpdate()->firstOrFail();
            if ($facility->type !== 'room') {
                throw new InvalidArgumentException("Facility {$facility->code} is not an accommodation room.");
            }

            $date = Carbon::parse($dateStr)->startOfDay();
            $dayStr = $date->toDateString();

            $activeReservations = RoomReservation::where('facility_id', $facility->id)
                ->whereIn('status', ['reserved', 'checked_in'])
                ->lockForUpdate()
                ->get();

            if ($targetStatus === 'maintenance') {
                if ($activeReservations->where('status', 'checked_in')->isNotEmpty()) {
                    throw new FacilityNotReadyException("Cannot place {$facility->name} under maintenance while a guest is currently checked in. Please check out the occupant first.");
                }

                $end = $maintenanceEndDate
                    ? Carbon::parse($maintenanceEndDate)->startOfDay()
                    : (clone $date)->addDay();

                if ($end->lte($date)) {
                    throw new InvalidArgumentException('Maintenance resume date must be strictly after the selected date.');
                }

                $endStr = $end->toDateString();
                $overlappingReservation = RoomReservation::where('facility_id', $facility->id)
                    ->where('status', 'reserved')
                    ->where('check_in_date', '<', $endStr)
                    ->where('check_out_date', '>', $dayStr)
                    ->lockForUpdate()
                    ->first();

                if ($overlappingReservation) {
                    throw new RoomUnavailableException(
                        "Cannot place {$facility->name} under maintenance from {$dayStr} to {$endStr}: overlapping reservation {$overlappingReservation->reservation_number} exists. Cancel or move the reservation first."
                    );
                }

                $reason = trim((string) ($maintenanceReason ?? ''));
                if ($reason === '') {
                    $reason = 'Scheduled maintenance / repair';
                }

                $existingBlock = FacilityMaintenanceBlock::where('facility_id', $facility->id)
                    ->where('status', 'active')
                    ->where('start_date', '<=', $dayStr)
                    ->where('end_date', '>', $dayStr)
                    ->lockForUpdate()
                    ->first();

                if ($existingBlock) {
                    $existingBlock->update([
                        'end_date' => $endStr,
                        'reason' => $reason,
                    ]);
                } else {
                    $this->createMaintenanceBlock($facility->id, $dayStr, $endStr, $reason, $user);
                }

                return;
            }

            // Leaving maintenance -> complete any active block covering the selected date
            FacilityMaintenanceBlock::where('facility_id', $facility->id)
                ->where('status', 'active')
                ->where('start_date', '<=', $dayStr)
                ->where('end_date', '>', $dayStr)
                ->update(['status' => 'completed']);

            if ($targetStatus === 'clean') {
                $facility->update(['housekeeping_status' => 'clean']);

                foreach ($activeReservations as $res) {
                    $this->setHousekeepingStatus($res, 'clean');
                }

                $dirtyCheckedOut = RoomReservation::where('facility_id', $facility->id)
                    ->where('status', 'checked_out')
                    ->whereIn('housekeeping_status', ['dirty', 'maintenance'])
                    ->get();

                foreach ($dirtyCheckedOut as $checkedOutRes) {
                    $this->setHousekeepingStatus($checkedOutRes, 'clean');
                }

                return;
            }

            // Target: 'dirty'
            $facility->update(['housekeeping_status' => 'dirty']);

            foreach ($activeReservations as $res) {
                if ($res->status === 'checked_in' || $res->check_in_date->toDateString() <= $dayStr) {
                    $this->setHousekeepingStatus($res, 'dirty');
                }
            }

            if ($activeReservations->where('status', 'checked_in')->isEmpty()) {
                $latestCheckedOut = RoomReservation::where('facility_id', $facility->id)
                    ->where('status', 'checked_out')
                    ->orderByDesc('checked_out_at')
                    ->orderByDesc('id')
                    ->first();

                if ($latestCheckedOut) {
                    $this->setHousekeepingStatus($latestCheckedOut, 'dirty');
                }
            }
        });
    }

    /**
     * Block a room for scheduled maintenance.
     */
    public function createMaintenanceBlock(int $facilityId, string $startDate, string $endDate, string $reason, ?User $user = null): FacilityMaintenanceBlock
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lte($start)) {
            throw new InvalidArgumentException("End date must be strictly after start date.");
        }

        return FacilityMaintenanceBlock::create([
            'facility_id' => $facilityId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => $reason,
            'status' => 'active',
            'created_by_user_id' => $user?->id,
        ]);
    }
}
