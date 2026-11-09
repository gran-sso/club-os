<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Models\ShiftCashMovement;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Exceptions\ShiftNotOpenException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShiftService
{
    /**
     * Open a counter shift with an opening float.
     */
    public function openShift(
        int $counterId,
        int $userId,
        int $openingFloatPaise = 0,
        ?string $notes = null,
        ?string $businessDate = null
    ): Shift {
        return DB::transaction(function () use ($counterId, $userId, $openingFloatPaise, $notes, $businessDate) {
            $counter = Counter::findOrFail($counterId);

            // Check if counter already has an open shift
            $existingCounterShift = Shift::where('counter_id', $counterId)
                ->where('status', ShiftStatus::OPEN)
                ->first();

            if ($existingCounterShift) {
                throw new InvalidArgumentException("Counter {$counter->name} already has an open shift (#{$existingCounterShift->shift_number}).");
            }

            // Check if user already has an open shift elsewhere
            $existingUserShift = Shift::where('user_id', $userId)
                ->where('status', ShiftStatus::OPEN)
                ->first();

            if ($existingUserShift) {
                throw new InvalidArgumentException("Staff user already has an active open shift (#{$existingUserShift->shift_number}).");
            }

            $yearMonth = now()->format('Ym');
            $count = Shift::where('shift_number', 'LIKE', "SFT-{$yearMonth}-%")->count() + 1;
            $shiftNumber = sprintf("SFT-%s-%05d", $yearMonth, $count);

            return Shift::create([
                'shift_number' => $shiftNumber,
                'counter_id' => $counterId,
                'user_id' => $userId,
                'business_date' => $businessDate ?? app(\App\Domain\Shared\Services\BusinessDateService::class)->getBusinessDate(),
                'opened_at' => now(),
                'opening_float_paise' => $openingFloatPaise,
                'status' => ShiftStatus::OPEN,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Record cash movement in or out of the shift till.
     */
    public function recordCashMovement(
        Shift $shift,
        string $type,
        int $amountPaise,
        string $notes,
        ?int $userId = null
    ): ShiftCashMovement {
        if (!$shift->isOpen()) {
            throw new ShiftNotOpenException("Cannot record cash movement on a closed shift.");
        }

        if ($amountPaise <= 0) {
            throw new InvalidArgumentException("Cash movement amount must be greater than zero.");
        }

        return ShiftCashMovement::create([
            'shift_id' => $shift->id,
            'type' => $type,
            'amount_paise' => $amountPaise,
            'notes' => $notes,
            'created_by_user_id' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Close cashier shift with counted cash reconciliation.
     */
    public function closeShift(
        Shift $shift,
        int $closingCashCountedPaise,
        ?string $notes = null
    ): Shift {
        if (!$shift->isOpen()) {
            throw new ShiftNotOpenException("Shift is already closed.");
        }

        return DB::transaction(function () use ($shift, $closingCashCountedPaise, $notes) {
            $expectedCash = $shift->calculateExpectedCashPaise();
            $discrepancy = $closingCashCountedPaise - $expectedCash;

            $shift->update([
                'closed_at' => now(),
                'closing_cash_counted_paise' => $closingCashCountedPaise,
                'expected_cash_paise' => $expectedCash,
                'discrepancy_paise' => $discrepancy,
                'status' => ShiftStatus::CLOSED,
                'notes' => $notes ? ($shift->notes . " | " . $notes) : $shift->notes,
            ]);

            return $shift->fresh();
        });
    }
}
