<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\PosHeldTab;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Models\ShiftCashMovement;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Exceptions\ShiftNotOpenException;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Collection;
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
        if ($openingFloatPaise < 0) {
            throw new InvalidArgumentException("Opening cash float cannot be negative.");
        }

        return DB::transaction(function () use ($counterId, $userId, $openingFloatPaise, $notes, $businessDate) {
            // Lock parent rows in deterministic order (Counter -> User) to prevent concurrent duplicate open shifts.
            $counter = Counter::lockForUpdate()->findOrFail($counterId);
            User::lockForUpdate()->findOrFail($userId);

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
            do {
                $shiftNumber = sprintf("SFT-%s-%05d", $yearMonth, $count);
                $count++;
            } while (Shift::where('shift_number', $shiftNumber)->exists());

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
        if ($closingCashCountedPaise < 0) {
            throw new InvalidArgumentException("Counted closing cash cannot be negative.");
        }

        return DB::transaction(function () use ($shift, $closingCashCountedPaise, $notes) {
            $lockedShift = Shift::lockForUpdate()->findOrFail($shift->id);

            if (!$lockedShift->isOpen()) {
                throw new ShiftNotOpenException("Shift is already closed.");
            }

            $openTabsCount = PosHeldTab::forShift($lockedShift->id)->open()->count();
            if ($openTabsCount > 0) {
                throw new InvalidArgumentException("Cannot close shift #{$lockedShift->shift_number}: There are {$openTabsCount} unsettled running bar tab(s). Settle or void all open tabs before shift reconciliation.");
            }

            $expectedCash = $lockedShift->calculateExpectedCashPaise();
            $discrepancy = $closingCashCountedPaise - $expectedCash;

            $lockedShift->update([
                'closed_at' => now(),
                'closing_cash_counted_paise' => $closingCashCountedPaise,
                'expected_cash_paise' => $expectedCash,
                'discrepancy_paise' => $discrepancy,
                'status' => ShiftStatus::CLOSED,
                'notes' => $notes ? ($lockedShift->notes ? ($lockedShift->notes . " | " . $notes) : $notes) : $lockedShift->notes,
            ]);

            $freshShift = $lockedShift->fresh(['counter', 'user']);

            if ($discrepancy !== 0) {
                app(\App\Domain\Shared\Services\AuditLogService::class)->logShiftVariance($freshShift, $discrepancy, $notes);
            } else {
                app(\App\Domain\Shared\Services\AuditLogService::class)->log(
                    category: 'shifts',
                    action: 'shift.closed',
                    description: "Shift #{$freshShift->shift_number} closed with balanced drawer (₹" . number_format($closingCashCountedPaise / 100, 2) . ")",
                    auditable: $freshShift
                );
            }

            return $freshShift;
        });
    }

    /**
     * Return batched operational snapshots for every active counter station (plus any counter with an open shift).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getCounterStationSnapshots(): Collection
    {
        $counters = Counter::query()
            ->with('department')
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereHas('shifts', fn ($sq) => $sq->where('status', ShiftStatus::OPEN));
            })
            ->orderBy('code')
            ->get();

        if ($counters->isEmpty()) {
            return collect();
        }

        $counterIds = $counters->pluck('id')->all();

        $openShiftsByCounter = Shift::query()
            ->with('cashier')
            ->whereIn('counter_id', $counterIds)
            ->where('status', ShiftStatus::OPEN)
            ->orderByDesc('opened_at')
            ->get()
            ->unique('counter_id')
            ->keyBy('counter_id');

        $idleCounterIds = $counters
            ->reject(fn (Counter $counter): bool => $openShiftsByCounter->has($counter->id))
            ->pluck('id')
            ->all();

        $lastClosedByCounter = empty($idleCounterIds)
            ? collect()
            : Shift::query()
                ->with('cashier')
                ->whereIn('counter_id', $idleCounterIds)
                ->where('status', ShiftStatus::CLOSED)
                ->orderByDesc('closed_at')
                ->orderByDesc('id')
                ->get()
                ->unique('counter_id')
                ->keyBy('counter_id');

        $openShiftIds = $openShiftsByCounter->pluck('id')->all();

        $receiptsByShiftAndTender = empty($openShiftIds)
            ? collect()
            : PaymentReceipt::query()
                ->whereIn('shift_id', $openShiftIds)
                ->where('provider_status', 'confirmed')
                ->selectRaw('shift_id, tender_type, SUM(amount_paise) as total_paise')
                ->groupBy('shift_id', 'tender_type')
                ->get()
                ->groupBy('shift_id');

        $movementsByShiftAndType = empty($openShiftIds)
            ? collect()
            : ShiftCashMovement::query()
                ->whereIn('shift_id', $openShiftIds)
                ->selectRaw('shift_id, type, SUM(amount_paise) as total_paise')
                ->groupBy('shift_id', 'type')
                ->get()
                ->groupBy('shift_id');

        $billStatsByShift = empty($openShiftIds)
            ? collect()
            : Bill::query()
                ->whereIn('shift_id', $openShiftIds)
                ->where('status', BillStatus::PAID)
                ->selectRaw('shift_id, COUNT(*) as bill_count, SUM(net_amount_paise) as billed_paise')
                ->groupBy('shift_id')
                ->get()
                ->keyBy('shift_id');

        $openTabCountsByShift = empty($openShiftIds)
            ? collect()
            : PosHeldTab::query()
                ->whereIn('shift_id', $openShiftIds)
                ->where('status', 'open')
                ->selectRaw('shift_id, COUNT(*) as tab_count')
                ->groupBy('shift_id')
                ->pluck('tab_count', 'shift_id');

        return $counters->map(function (Counter $counter) use (
            $openShiftsByCounter,
            $lastClosedByCounter,
            $receiptsByShiftAndTender,
            $movementsByShiftAndType,
            $billStatsByShift,
            $openTabCountsByShift
        ): array {
            /** @var Shift|null $openShift */
            $openShift = $openShiftsByCounter->get($counter->id);
            /** @var Shift|null $lastClosedShift */
            $lastClosedShift = $openShift ? null : $lastClosedByCounter->get($counter->id);

            if (! $openShift) {
                return [
                    'counter' => $counter,
                    'is_open' => false,
                    'is_stale' => false,
                    'open_shift' => null,
                    'last_closed_shift' => $lastClosedShift,
                    'expected_cash_paise' => 0,
                    'cash_sales_paise' => 0,
                    'digital_sales_paise' => 0,
                    'prepaid_sales_paise' => 0,
                    'paid_bills_count' => 0,
                    'billed_total_paise' => 0,
                    'open_tabs_count' => 0,
                ];
            }

            $tenderTotals = [];
            foreach ($receiptsByShiftAndTender->get($openShift->id, collect()) as $row) {
                $tenderKey = $row->tender_type instanceof BackedEnum
                    ? (string) $row->tender_type->value
                    : (string) $row->tender_type;
                $tenderTotals[$tenderKey] = ($tenderTotals[$tenderKey] ?? 0) + (int) $row->total_paise;
            }

            $movementTotals = [];
            foreach ($movementsByShiftAndType->get($openShift->id, collect()) as $row) {
                $movementTotals[(string) $row->type] = (int) $row->total_paise;
            }

            $cashReceiptsPaise = $tenderTotals['cash'] ?? 0;
            $digitalReceiptsPaise = ($tenderTotals['digital'] ?? 0) + ($tenderTotals['upi'] ?? 0) + ($tenderTotals['card'] ?? 0);
            $prepaidReceiptsPaise = ($tenderTotals['prepaid'] ?? 0) + ($tenderTotals['wallet'] ?? 0);

            $expectedCashPaise = $openShift->opening_float_paise
                + $cashReceiptsPaise
                + ($movementTotals['float_in'] ?? 0)
                - ($movementTotals['transfer_out'] ?? 0)
                - ($movementTotals['cash_drop'] ?? 0);

            $billStats = $billStatsByShift->get($openShift->id);

            return [
                'counter' => $counter,
                'is_open' => true,
                'is_stale' => (bool) ($openShift->business_date && $openShift->business_date->lt(today())),
                'open_shift' => $openShift,
                'last_closed_shift' => null,
                'expected_cash_paise' => $expectedCashPaise,
                'cash_sales_paise' => $cashReceiptsPaise,
                'digital_sales_paise' => $digitalReceiptsPaise,
                'prepaid_sales_paise' => $prepaidReceiptsPaise,
                'paid_bills_count' => (int) ($billStats->bill_count ?? 0),
                'billed_total_paise' => (int) ($billStats->billed_paise ?? 0),
                'open_tabs_count' => (int) ($openTabCountsByShift->get($openShift->id) ?? 0),
            ];
        })->values();
    }
}
