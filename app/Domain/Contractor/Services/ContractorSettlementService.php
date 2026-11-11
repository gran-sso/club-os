<?php

namespace App\Domain\Contractor\Services;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Models\ContractorSettlementAdjustment;
use App\Domain\Contractor\Models\ContractorSettlementAllocation;
use App\Domain\Contractor\Models\ContractorSettlementBatch;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ContractorSettlementService
{
    public function __construct(
        protected JournalService $journalService,
        protected ?\App\Domain\Shared\Services\BusinessDateService $businessDateService = null
    ) {
        $this->businessDateService = $this->businessDateService ?? app(\App\Domain\Shared\Services\BusinessDateService::class);
    }

    /**
     * Preview eligible food sales for a given business date.
     * Only paid food bill lines are included; unused wallet top-ups are excluded.
     * Follows the configured cutoff policy (midnight vs configured cutoff like 03:00).
     */
    public function previewDailySettlement(Contractor $contractor, string $businessDate): array
    {
        $date = Carbon::parse($businessDate)->toDateString();
        [$windowStart, $windowEnd] = $this->businessDateService->getBusinessDateWindow($date);

        // Find all paid bill lines for food department on this business date
        // that are not yet allocated to any settlement batch
        $eligibleLines = BillLine::where('department_code', 'food')
            ->whereHas('bill', function ($q) use ($date, $windowStart, $windowEnd) {
                $q->where('status', BillStatus::PAID)
                    ->where(function ($subQ) use ($date, $windowStart, $windowEnd) {
                        $subQ->whereBetween('created_at', [$windowStart, $windowEnd])
                            ->orWhere(function ($fallback) use ($date) {
                                $fallback->whereNull('created_at')
                                    ->where('business_date', $date);
                            });
                    });
            })
            ->whereDoesntHave('contractorSettlementAllocation')
            ->with(['bill.receipts'])
            ->get();

        $totalGrossPaise = 0;
        $totalTaxPaise = 0;
        $totalNetPaise = 0;
        $tenderBreakdown = [
            'cash' => 0,
            'digital' => 0,
            'prepaid' => 0,
        ];

        foreach ($eligibleLines as $line) {
            $totalGrossPaise += $line->line_total_paise - $line->tax_amount_paise;
            $totalTaxPaise += $line->tax_amount_paise;
            $totalNetPaise += $line->line_total_paise;

            $primaryReceipt = $line->bill->receipts->first();
            $tenderType = $primaryReceipt ? $primaryReceipt->tender_type->value : 'cash';
            $tenderBreakdown[$tenderType] = ($tenderBreakdown[$tenderType] ?? 0) + $line->line_total_paise;
        }

        // Unapplied adjustments (e.g. post-settlement refunds from earlier batches)
        $pendingAdjustments = ContractorSettlementAdjustment::where('contractor_id', $contractor->id)
            ->where('is_applied', false)
            ->get();

        $totalAdjustmentsPaise = $pendingAdjustments->sum('amount_paise'); // negative deductions
        $netPayablePaise = max(0, $totalNetPaise + $totalAdjustmentsPaise);

        return [
            'contractor' => $contractor,
            'business_date' => $date,
            'total_lines' => $eligibleLines->count(),
            'total_gross_paise' => $totalGrossPaise,
            'total_tax_paise' => $totalTaxPaise,
            'total_net_paise' => $totalNetPaise,
            'tender_breakdown' => $tenderBreakdown,
            'pending_adjustments_count' => $pendingAdjustments->count(),
            'total_adjustments_paise' => $totalAdjustmentsPaise,
            'net_payable_paise' => $netPayablePaise,
            'lines' => $eligibleLines,
        ];
    }

    /**
     * Create daily settlement batch.
     * Idempotent: returns existing batch if already created for this date (Scenario C03).
     */
    public function createSettlementBatch(Contractor $contractor, string $businessDate, ?int $userId = null): ContractorSettlementBatch
    {
        $date = Carbon::parse($businessDate)->toDateString();

        return DB::transaction(function () use ($contractor, $date, $userId) {
            $existing = ContractorSettlementBatch::where('contractor_id', $contractor->id)
                ->where('business_date', $date)
                ->first();

            if ($existing) {
                return $existing;
            }

            $preview = $this->previewDailySettlement($contractor, $date);

            $batchCount = ContractorSettlementBatch::where('business_date', $date)->count() + 1;
            $batchNumber = sprintf("SET-%s-%03d", str_replace('-', '', $date), $batchCount);

            $batch = ContractorSettlementBatch::create([
                'batch_number' => $batchNumber,
                'contractor_id' => $contractor->id,
                'business_date' => $date,
                'cutoff_at' => now(),
                'total_food_sales_gross_paise' => $preview['total_gross_paise'],
                'total_food_sales_tax_paise' => $preview['total_tax_paise'],
                'total_food_sales_net_paise' => $preview['total_net_paise'],
                'total_adjustments_paise' => $preview['total_adjustments_paise'],
                'net_payable_paise' => $preview['net_payable_paise'],
                'paid_amount_paise' => 0,
                'residual_payable_paise' => $preview['net_payable_paise'],
                'status' => 'draft',
                'created_by_user_id' => $userId ?? auth()->id() ?? 1,
            ]);

            // Create allocations for all eligible lines
            foreach ($preview['lines'] as $line) {
                $primaryReceipt = $line->bill->receipts->first();
                $tender = $primaryReceipt ? $primaryReceipt->tender_type->value : 'cash';

                ContractorSettlementAllocation::create([
                    'settlement_batch_id' => $batch->id,
                    'bill_line_id' => $line->id,
                    'line_net_paise' => $line->line_total_paise,
                    'tender_type' => $tender,
                    'status' => 'included',
                ]);
            }

            // Link pending adjustments to this batch
            ContractorSettlementAdjustment::where('contractor_id', $contractor->id)
                ->where('is_applied', false)
                ->update([
                    'applied_batch_id' => $batch->id,
                    'is_applied' => true,
                ]);

            return $batch->fresh(['allocations', 'contractor']);
        });
    }

    /**
     * Approve settlement batch.
     * Posts journal: Debit Contractor Food Expense (5020), Credit Food Contractor Payable (2020).
     */
    public function approveSettlementBatch(ContractorSettlementBatch $batch, User $approver): ContractorSettlementBatch
    {
        if ($batch->status !== 'draft') {
            throw new InvalidArgumentException("Only draft settlement batches can be approved.");
        }

        if (!$approver->hasClubPermission('contractor.settle')) {
            throw new UnauthorizedActionException("Only an Accountant, Manager, or Administrator can approve contractor settlements.");
        }

        return DB::transaction(function () use ($batch, $approver) {
            $batch->update([
                'status' => 'approved',
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
            ]);

            // Post financial journal:
            // Debit 5020 (Contractor Food Expense)
            // Credit 2020 (Food Contractor Payable)
            if ($batch->net_payable_paise > 0) {
                $this->journalService->post(
                    sourceType: 'contractor_settlement',
                    sourceId: $batch->id,
                    description: "Approved contractor settlement {$batch->batch_number} for {$batch->contractor->name}",
                    lines: [
                        [
                            'account_code' => '5020', // Contractor Food Expense
                            'debit_paise' => $batch->net_payable_paise,
                            'narration' => "Food sales obligation {$batch->batch_number}",
                            'department_code' => 'food',
                        ],
                        [
                            'account_code' => '2020', // Food Contractor Payable
                            'credit_paise' => $batch->net_payable_paise,
                            'narration' => "Payable to {$batch->contractor->name}",
                            'department_code' => 'food',
                        ],
                    ],
                    businessDate: Carbon::parse($batch->business_date),
                    userId: $approver->id
                );
            }

            return $batch->fresh();
        });
    }

    /**
     * Confirm payout to contractor (full or partial).
     * Handles Scenario C04: partial payments reduce payable, residual remains visible.
     */
    public function confirmSettlementPayment(
        ContractorSettlementBatch $batch,
        int $paidAmountPaise,
        string $paymentReference,
        ?int $userId = null
    ): ContractorSettlementBatch {
        if (!in_array($batch->status, ['approved', 'partially_paid'])) {
            throw new InvalidArgumentException("Payment can only be confirmed for approved batches.");
        }

        if ($paidAmountPaise <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        if ($paidAmountPaise > $batch->residual_payable_paise) {
            throw new InvalidArgumentException(
                sprintf("Payment amount (₹%.2f) exceeds residual payable (₹%.2f).", $paidAmountPaise / 100.0, $batch->residual_payable_paise / 100.0)
            );
        }

        return DB::transaction(function () use ($batch, $paidAmountPaise, $paymentReference, $userId) {
            $newPaid = $batch->paid_amount_paise + $paidAmountPaise;
            $residual = $batch->net_payable_paise - $newPaid;
            $newStatus = $residual <= 0 ? 'paid' : 'partially_paid';

            $batch->update([
                'paid_amount_paise' => $newPaid,
                'residual_payable_paise' => $residual,
                'status' => $newStatus,
                'payment_reference' => $paymentReference,
                'paid_at' => now(),
            ]);

            // Post payout journal:
            // Debit 2020 (Food Contractor Payable)
            // Credit 1020 (Bank Clearing)
            $this->journalService->post(
                sourceType: 'contractor_payout',
                sourceId: $batch->id,
                description: "Contractor disbursement {$paymentReference} for {$batch->batch_number}",
                lines: [
                    [
                        'account_code' => '2020', // Food Contractor Payable
                        'debit_paise' => $paidAmountPaise,
                        'narration' => "Disbursement for batch {$batch->batch_number}",
                        'department_code' => 'food',
                    ],
                    [
                        'account_code' => '1020', // Bank Clearing
                        'credit_paise' => $paidAmountPaise,
                        'narration' => "Bank transfer {$paymentReference}",
                        'department_code' => 'food',
                    ],
                ],
                userId: $userId
            );

            return $batch->fresh();
        });
    }

    /**
     * Handle post-settlement refund (Scenario C05).
     * If a food line belongs to an already settled/locked batch,
     * record a linked adjustment for the next settlement without altering the locked batch.
     */
    public function handlePostSettlementRefund(
        BillLine $foodLine,
        int $refundAmountPaise,
        string $reason,
        ?int $userId = null
    ): ?ContractorSettlementAdjustment {
        $allocation = ContractorSettlementAllocation::where('bill_line_id', $foodLine->id)->first();
        if (!$allocation) {
            return null;
        }

        $batch = $allocation->batch;
        if (!$batch || !$batch->isLocked()) {
            // Batch not locked yet; can be adjusted in draft or allocation marked refunded
            $allocation->update(['status' => 'refunded']);
            return null;
        }

        // Batch IS locked/paid! Create adjustment for NEXT batch (Scenario C05)
        return ContractorSettlementAdjustment::create([
            'contractor_id' => $batch->contractor_id,
            'original_batch_id' => $batch->id,
            'applied_batch_id' => null,
            'bill_line_id' => $foodLine->id,
            'type' => 'post_settlement_refund',
            'amount_paise' => -$refundAmountPaise, // negative deduction
            'reason' => "Post-settlement refund on {$foodLine->item_name}: {$reason}",
            'is_applied' => false,
            'created_by_user_id' => $userId ?? auth()->id() ?? 1,
        ]);
    }
}
