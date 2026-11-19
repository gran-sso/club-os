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

    /**
     * Compile comprehensive shift transaction details, tender breakdowns, and drawer reconciliation.
     *
     * @return array<string, mixed>
     */
    public function getShiftTransactionDetails(Shift $shift): array
    {
        $shift->loadMissing(['counter', 'user', 'cashMovements.createdByUser']);

        $openedAt = $shift->opened_at?->timezone(config('club.timezone', 'Asia/Kolkata'));
        $closedAt = $shift->closed_at?->timezone(config('club.timezone', 'Asia/Kolkata'));
        $isClosed = $shift->status === ShiftStatus::CLOSED || ($shift->status?->value ?? $shift->status) === 'closed';

        $duration = $openedAt && $closedAt
            ? $openedAt->diffForHumans($closedAt, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2])
            : ($openedAt ? $openedAt->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]) . ' (Active)' : '—');

        $timings = $openedAt
            ? ($openedAt->format('h:i A') . ($closedAt ? ' - ' . $closedAt->format('h:i A') : ' - Now') . " ({$duration})")
            : '—';

        // 1. All Bills on Shift
        $bills = $shift->bills()->with(['customer', 'waiter', 'receipts', 'lines'])->latest()->get();
        $paidBills = $bills->where('status', BillStatus::PAID);
        $unsettledBills = $bills->where('status', BillStatus::UNSETTLED);
        $reversedBills = $bills->where('status', BillStatus::REVERSED);

        $totalBillsCount = $bills->count();
        $paidBillsCount = $paidBills->count();
        $totalGrossPaise = (int) $paidBills->sum('gross_amount_paise');
        $totalTaxPaise = (int) $paidBills->sum('tax_amount_paise');
        $totalNetPaise = (int) $paidBills->sum('net_amount_paise');
        $totalTipPaise = (int) $paidBills->sum('tip_amount_paise');

        // Tender breakdown on paid bills from confirmed receipts
        $billReceipts = PaymentReceipt::query()
            ->where('shift_id', $shift->id)
            ->whereNotNull('bill_id')
            ->where('provider_status', 'confirmed')
            ->get();

        $cashBilledPaise = 0;
        $digitalBilledPaise = 0;
        $prepaidBilledPaise = 0;

        foreach ($billReceipts as $rcpt) {
            $tVal = $rcpt->tender_type instanceof BackedEnum ? (string) $rcpt->tender_type->value : (string) $rcpt->tender_type;
            if ($tVal === 'cash') {
                $cashBilledPaise += (int) $rcpt->amount_paise;
            } elseif (in_array($tVal, ['digital', 'upi', 'card'], true)) {
                $digitalBilledPaise += (int) $rcpt->amount_paise;
            } elseif (in_array($tVal, ['prepaid', 'wallet'], true)) {
                $prepaidBilledPaise += (int) $rcpt->amount_paise;
            }
        }

        // If bill receipts are missing or unlinked, fall back to bill level calculations
        if ($billReceipts->isEmpty() && $paidBills->isNotEmpty()) {
            $cashBilledPaise = (int) $paidBills->where('tender_type', 'cash')->sum('net_amount_paise');
            $digitalBilledPaise = (int) $paidBills->whereIn('tender_type', ['digital', 'upi', 'card'])->sum('net_amount_paise');
            $prepaidBilledPaise = (int) $paidBills->whereIn('tender_type', ['prepaid', 'wallet'])->sum('net_amount_paise');
        }

        // 2. Member Prepaid Wallet Top-Ups (Recharges) on Shift
        $topupReceipts = PaymentReceipt::query()
            ->where('shift_id', $shift->id)
            ->whereNotNull('wallet_transaction_id')
            ->where('provider_status', 'confirmed')
            ->with(['customer'])
            ->latest()
            ->get();

        $cashTopupsPaise = 0;
        $digitalTopupsPaise = 0;
        foreach ($topupReceipts as $rcpt) {
            $tVal = $rcpt->tender_type instanceof BackedEnum ? (string) $rcpt->tender_type->value : (string) $rcpt->tender_type;
            if ($tVal === 'cash') {
                $cashTopupsPaise += (int) $rcpt->amount_paise;
            } else {
                $digitalTopupsPaise += (int) $rcpt->amount_paise;
            }
        }
        $totalTopupsPaise = $cashTopupsPaise + $digitalTopupsPaise;

        // 3. Cash Drawer Movements (Drops, Float in, Transfers)
        $movements = $shift->cashMovements()->with('createdByUser')->latest()->get();
        $floatInPaise = (int) $movements->where('type', 'float_in')->sum('amount_paise');
        $cashDropsPaise = (int) $movements->whereIn('type', ['cash_drop', 'transfer_out'])->sum('amount_paise');

        // 4. Physical Cash Drawer Balancing
        $openingFloatPaise = (int) $shift->opening_float_paise;
        $totalCashInflowPaise = $cashBilledPaise + $cashTopupsPaise;
        $expectedCashPaise = $openingFloatPaise + $totalCashInflowPaise + $floatInPaise - $cashDropsPaise;
        $countedCashPaise = $shift->closing_cash_counted_paise;

        if ($countedCashPaise !== null) {
            $discrepancyPaise = $countedCashPaise - $expectedCashPaise;
            $discrepancyStatus = $discrepancyPaise === 0 ? 'Balanced' : ($discrepancyPaise < 0 ? 'Shortage' : 'Surplus');
        } else {
            $discrepancyPaise = null;
            $discrepancyStatus = 'Pending Closing Count';
        }

        // 5. Staff / Bearer Tips Breakdown (Account 2040)
        $waiterTips = [];
        foreach ($paidBills->where('tip_amount_paise', '>', 0) as $b) {
            $wId = $b->waiter_id ?? 0;
            $wName = $b->waiter?->name ?? 'Floor / Counter Service Staff';
            $wCode = $b->waiter?->code ?? 'STAFF';
            if (!isset($waiterTips[$wId])) {
                $waiterTips[$wId] = [
                    'waiter_id' => $wId,
                    'waiter_name' => $wName,
                    'waiter_code' => $wCode,
                    'bills_count' => 0,
                    'total_tip_paise' => 0,
                ];
            }
            $waiterTips[$wId]['bills_count']++;
            $waiterTips[$wId]['total_tip_paise'] += (int) $b->tip_amount_paise;
        }
        $waiterTips = array_values($waiterTips);

        // 6. Food Contractor vs Club Bar Revenue Split
        $foodContractorPaise = 0;
        $clubBarPaise = 0;
        foreach ($paidBills as $b) {
            foreach ($b->lines as $line) {
                $lineAmt = (int) ($line->line_total_paise ?? $line->net_amount_paise ?? 0);
                if (in_array($line->department_code, ['catering', 'restaurant', 'food'], true)) {
                    $foodContractorPaise += $lineAmt;
                } else {
                    $clubBarPaise += $lineAmt;
                }
            }
        }

        return [
            'shift' => $shift,
            'shift_number' => $shift->shift_number,
            'counter_name' => $shift->counter?->name ?? 'POS Station',
            'cashier_name' => $shift->user?->name ?? 'Staff Cashier',
            'business_date' => $shift->business_date ? $shift->business_date->format('d M Y') : now()->format('d M Y'),
            'opened_at' => $openedAt ? $openedAt->format('d M Y, h:i A') : '—',
            'closed_at' => $closedAt ? $closedAt->format('d M Y, h:i A') : 'Active / Still Open',
            'duration' => $duration,
            'timings' => $timings,
            'is_closed' => $isClosed,
            'status_label' => $isClosed ? 'Closed & Reconciled' : 'Active / In Progress',

            'billed_turnover' => [
                'total_bills' => $paidBillsCount,
                'all_bills_count' => $totalBillsCount,
                'gross_paise' => $totalGrossPaise,
                'tax_paise' => $totalTaxPaise,
                'net_paise' => $totalNetPaise,
                'gross_rupees' => number_format($totalGrossPaise / 100, 2),
                'tax_rupees' => number_format($totalTaxPaise / 100, 2),
                'net_rupees' => number_format($totalNetPaise / 100, 2),
                'food_contractor_paise' => $foodContractorPaise,
                'club_bar_paise' => $clubBarPaise,
                'food_contractor_rupees' => number_format($foodContractorPaise / 100, 2),
                'club_bar_rupees' => number_format($clubBarPaise / 100, 2),
            ],

            'tender_breakdown' => [
                'cash_sales_paise' => $cashBilledPaise,
                'digital_sales_paise' => $digitalBilledPaise,
                'prepaid_sales_paise' => $prepaidBilledPaise,
                'cash_sales_rupees' => number_format($cashBilledPaise / 100, 2),
                'digital_sales_rupees' => number_format($digitalBilledPaise / 100, 2),
                'prepaid_sales_rupees' => number_format($prepaidBilledPaise / 100, 2),
            ],

            'wallet_topups' => [
                'total_count' => $topupReceipts->count(),
                'count' => $topupReceipts->count(),
                'total_paise' => $totalTopupsPaise,
                'total_amount_paise' => $totalTopupsPaise,
                'total_rupees' => number_format($totalTopupsPaise / 100, 2),
                'cash_topups_paise' => $cashTopupsPaise,
                'cash_topups_rupees' => number_format($cashTopupsPaise / 100, 2),
                'digital_topups_paise' => $digitalTopupsPaise,
                'digital_topups_rupees' => number_format($digitalTopupsPaise / 100, 2),
            ],

            'staff_tips' => [
                'total_tips_paise' => $totalTipPaise,
                'total_tips_rupees' => number_format($totalTipPaise / 100, 2),
                'waiters_count' => count($waiterTips),
                'breakdown' => $waiterTips,
            ],

            'drawer_reconciliation' => [
                'opening_float_paise' => $openingFloatPaise,
                'opening_float_rupees' => number_format($openingFloatPaise / 100, 2),
                'cash_sales_paise' => $cashBilledPaise,
                'cash_sales_rupees' => number_format($cashBilledPaise / 100, 2),
                'cash_topups_paise' => $cashTopupsPaise,
                'cash_topups_rupees' => number_format($cashTopupsPaise / 100, 2),
                'total_cash_inflow_paise' => $totalCashInflowPaise,
                'total_cash_inflow_rupees' => number_format($totalCashInflowPaise / 100, 2),
                'float_in_paise' => $floatInPaise,
                'float_in_rupees' => number_format($floatInPaise / 100, 2),
                'cash_drops_paise' => $cashDropsPaise,
                'cash_drops_rupees' => number_format($cashDropsPaise / 100, 2),
                'expected_cash_paise' => $expectedCashPaise,
                'expected_cash_rupees' => number_format($expectedCashPaise / 100, 2),
                'counted_cash_paise' => $countedCashPaise,
                'counted_cash_rupees' => $countedCashPaise !== null ? number_format($countedCashPaise / 100, 2) : '—',
                'discrepancy_paise' => $discrepancyPaise,
                'discrepancy_rupees' => $discrepancyPaise !== null ? number_format($discrepancyPaise / 100, 2) : '—',
                'discrepancy_status' => $discrepancyStatus,
                'notes' => $shift->notes,
            ],

            'exceptions' => [
                'unsettled_bills_count' => $unsettledBills->count(),
                'unsettled_bills_paise' => (int) $unsettledBills->sum('net_amount_paise'),
                'reversed_bills_count' => $reversedBills->count(),
                'reversed_bills_paise' => (int) $reversedBills->sum('net_amount_paise'),
                'voided_bills_count' => $reversedBills->count(),
                'voided_bills_paise' => (int) $reversedBills->sum('net_amount_paise'),
            ],

            // Itemized Transaction Records
            'bills' => $bills,
            'topup_receipts' => $topupReceipts,
            'movements' => $movements,

            // Convenience Aliases for POS Components & Templates
            'totals' => [
                'billed_turnover_paise' => $totalNetPaise,
                'gross_sales_paise' => $totalGrossPaise,
                'tax_paise' => $totalTaxPaise,
            ],
            'tenders' => [
                'cash' => [
                    'amount_paise' => $cashBilledPaise,
                    'count' => $billReceipts->where('tender_type', 'cash')->count(),
                ],
                'digital' => [
                    'amount_paise' => $digitalBilledPaise,
                    'count' => $billReceipts->where('tender_type', 'digital')->count(),
                ],
                'prepaid' => [
                    'amount_paise' => $prepaidBilledPaise,
                    'count' => $billReceipts->where('tender_type', 'prepaid')->count(),
                ],
            ],
            'drawer' => [
                'opening_float_paise' => $openingFloatPaise,
                'cash_sales_paise' => $cashBilledPaise,
                'cash_topups_paise' => $cashTopupsPaise,
                'cash_drops_paise' => $cashDropsPaise,
                'expected_cash_paise' => $expectedCashPaise,
                'closing_cash_counted_paise' => $countedCashPaise,
                'discrepancy_paise' => $discrepancyPaise,
                'discrepancy_status' => $discrepancyStatus,
            ],
            'tips' => [
                'total_tip_paise' => $totalTipPaise,
                'count' => count($waiterTips),
            ],
            'contractor_turnover' => [
                'food_contractor_paise' => $foodContractorPaise,
                'bar_paise' => $clubBarPaise,
            ],
            'lists' => [
                'bills' => $bills,
                'topups' => $topupReceipts,
                'cash_movements' => $movements,
            ],
        ];
    }
}
