<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\CapturedPayment;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Refund;
use App\Domain\Billing\Models\UnappliedPayment;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentRecoveryService
{
    public function __construct(
        protected BillingService $billingService,
        protected JournalService $journalService
    ) {}

    /**
     * Process external payment provider webhook / confirmation callback.
     * Enforces:
     * - F05: Forged success redirects / invalid signatures rejected; pending/failed attempts do not fulfill bills or grant access.
     * - F06: Idempotent processing of duplicate or out-of-order signed callbacks. Retains provider audit trail.
     */
    public function handleProviderCallback(
        array $payload,
        string $signature,
        string $secretKey,
        ?Bill $bill = null,
        ?User $operator = null
    ): array {
        // 1. Verify Cryptographic Signature (Scenario F05 & F06)
        $dataToSign = json_encode($payload['data'] ?? $payload);
        $expectedSignature = hash_hmac('sha256', $dataToSign, $secretKey);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new UnauthorizedActionException("Invalid payment signature: provider callback verification failed. Forged request rejected.");
        }

        $providerRef = $payload['provider_reference'] ?? $payload['payment_id'] ?? null;
        if (!$providerRef) {
            throw new InvalidArgumentException("Missing provider_reference in callback payload.");
        }

        $status = strtolower($payload['status'] ?? 'pending');
        $amountPaise = (int) ($payload['amount_paise'] ?? 0);

        // 2. Audit Event Trail (Scenario F06)
        OutboxMessage::updateOrCreate(
            ['idempotency_key' => 'webhook_' . $providerRef . '_' . $status],
            [
                'event_type' => 'payment.gateway.webhook_received',
                'aggregate_type' => 'ProviderWebhook',
                'aggregate_id' => 0,
                'payload' => [
                    'provider_reference' => $providerRef,
                    'status' => $status,
                    'amount_paise' => $amountPaise,
                    'received_at' => now()->toIso8601String(),
                ],
                'status' => 'acknowledged',
            ]
        );

        // 3. Handle Pending / Failed / Cancelled callbacks (Scenario F05)
        if ($status !== 'confirmed' && $status !== 'success') {
            return [
                'success' => false,
                'status' => $status,
                'message' => "Payment is {$status}. No bill fulfillment, goods issuance, or access pass granted.",
                'bill' => $bill?->fresh(),
            ];
        }

        // 4. Check for duplicate or out-of-order callback (Scenario F06)
        $existingReceipt = PaymentReceipt::where('provider_reference', $providerRef)->first();
        if ($existingReceipt) {
            return [
                'success' => true,
                'status' => 'already_processed',
                'message' => 'Payment already confirmed and processed. No duplicate receipt or journal created.',
                'receipt' => $existingReceipt,
                'bill' => $existingReceipt->bill,
            ];
        }

        // 5. If bill provided, execute confirmed settlement
        if ($bill && !$bill->isPaid()) {
            $checkoutResult = $this->billingService->checkout(
                bill: $bill,
                tenders: [
                    [
                        'tender_type' => TenderType::DIGITAL,
                        'amount_paise' => $bill->net_amount_paise,
                        'reference' => $providerRef,
                    ]
                ],
                userId: $operator?->id
            );

            return [
                'success' => true,
                'status' => 'confirmed',
                'receipt' => $checkoutResult['receipts'][0] ?? null,
                'bill' => $checkoutResult['bill'],
            ];
        }

        return [
            'success' => true,
            'status' => 'verified_unallocated',
            'provider_reference' => $providerRef,
        ];
    }

    /**
     * Record captured payment from external gateway (Scenario F07).
     */
    public function recordCapturedPayment(
        string $providerName,
        string $providerReference,
        int $amountPaise,
        array $rawPayload = [],
        ?string $signature = null,
        ?Bill $bill = null
    ): CapturedPayment {
        return CapturedPayment::create([
            'provider_name' => $providerName,
            'provider_reference' => $providerReference,
            'amount_paise' => $amountPaise,
            'currency' => 'INR',
            'status' => 'captured',
            'customer_type' => $bill ? $bill->customer_type : null,
            'customer_id' => $bill ? $bill->customer_id : null,
            'bill_id' => $bill?->id,
            'raw_payload' => $rawPayload,
            'signature' => $signature,
            'captured_at' => now(),
        ]);
    }

    /**
     * Resolve unmatched captured payment (Scenario F07).
     * Guarantees:
     * - Resolves captured amount exactly once: either to confirmed bill posting or approved refund.
     * - Does not issue goods before confirmed posting.
     */
    public function recoverUnmatchedCapture(
        CapturedPayment $capture,
        ?Bill $targetBill,
        User $operator,
        bool $forceRefundIfCannotPost = true
    ): array {
        if ($capture->status !== 'captured') {
            throw new InvalidArgumentException("Captured payment {$capture->provider_reference} is already resolved as {$capture->status}.");
        }

        return DB::transaction(function () use ($capture, $targetBill, $operator, $forceRefundIfCannotPost) {
            // Path A: We can post the bill and fulfill
            if ($targetBill && !$targetBill->isPaid() && $targetBill->net_amount_paise === $capture->amount_paise) {
                $result = $this->billingService->checkout(
                    bill: $targetBill,
                    tenders: [
                        [
                            'tender_type' => TenderType::DIGITAL,
                            'amount_paise' => $capture->amount_paise,
                            'reference' => $capture->provider_reference,
                        ]
                    ],
                    userId: $operator->id
                );

                $receipt = $result['receipts'][0] ?? null;

                $capture->update([
                    'status' => 'matched',
                    'bill_id' => $targetBill->id,
                    'payment_receipt_id' => $receipt?->id,
                    'resolved_at' => now(),
                    'resolution_notes' => "Matched and fulfilled against Bill {$targetBill->bill_number}",
                ]);

                return [
                    'outcome' => 'fulfilled',
                    'bill' => $result['bill'],
                    'receipt' => $receipt,
                    'capture' => $capture->fresh(),
                ];
            }

            // Path B: Unmatched or cannot post -> Approve refund. Zero goods issued.
            if ($forceRefundIfCannotPost) {
                $capture->update([
                    'status' => 'refunded',
                    'resolved_at' => now(),
                    'resolution_notes' => "Unmatched external payment refunded to customer. Goods were not issued.",
                ]);

                // Financial entry: Debit Bank Clearing (1020), Credit Bank / Customer Refund (1020/1010)
                $this->journalService->post(
                    sourceType: 'captured_payment_refund',
                    sourceId: $capture->id,
                    description: "Refund of unmatched capture {$capture->provider_reference}",
                    lines: [
                        [
                            'account_code' => '1020',
                            'debit_paise' => $capture->amount_paise,
                            'narration' => "Unmatched gateway receipt clearing",
                        ],
                        [
                            'account_code' => '1020',
                            'credit_paise' => $capture->amount_paise,
                            'narration' => "Refund disbursed for unmatched capture {$capture->provider_reference}",
                        ]
                    ],
                    userId: $operator->id
                );

                return [
                    'outcome' => 'refunded',
                    'goods_issued' => false,
                    'capture' => $capture->fresh(),
                ];
            }

            return [
                'outcome' => 'pending',
                'capture' => $capture,
            ];
        });
    }

    /**
     * Handle split tender partial failure (Scenario F10).
     * When one tender part succeeds (e.g. ₹200 cash) and another fails (e.g. ₹300 UPI),
     * the sale remains unfulfilled, goods are NOT issued, and the confirmed portion
     * is tracked as an UnappliedPayment for refund/recovery.
     */
    public function recordSplitTenderFailure(
        Bill $bill,
        array $confirmedTenders,
        string $failureReason,
        User $operator
    ): array {
        if ($bill->isPaid()) {
            throw new InvalidArgumentException("Cannot record split tender failure on already paid bill.");
        }

        return DB::transaction(function () use ($bill, $confirmedTenders, $failureReason, $operator) {
            $unappliedRecords = [];

            foreach ($confirmedTenders as $tender) {
                $amount = (int) $tender['amount_paise'];
                $type = $tender['tender_type'] instanceof TenderType ? $tender['tender_type']->value : (string) $tender['tender_type'];

                $unapplied = UnappliedPayment::create([
                    'shift_id' => $bill->shift_id,
                    'bill_id' => $bill->id,
                    'customer_type' => $bill->customer_type,
                    'customer_id' => $bill->customer_id,
                    'tender_type' => $type,
                    'amount_paise' => $amount,
                    'status' => 'held',
                    'idempotency_key' => 'unapplied_' . $bill->id . '_' . $type . '_' . uniqid(),
                    'notes' => "Partial split tender held due to failure of remaining tenders: {$failureReason}",
                    'created_by_user_id' => $operator->id,
                ]);

                $unappliedRecords[] = $unapplied;
            }

            // The bill remains unfulfilled
            $bill->update([
                'status' => BillStatus::DRAFT,
                'paid_amount_paise' => 0,
            ]);

            return [
                'bill' => $bill->fresh(),
                'unapplied_payments' => $unappliedRecords,
                'is_fulfilled' => false,
            ];
        });
    }

    /**
     * Reconcile gateway settlement net of fees (Scenario F13).
     * Gross confirmed customer payment, provider clearing, fees, and bank payout
     * reconcile without altering original customer sale value.
     */
    public function reconcileGatewaySettlement(
        string $batchReference,
        int $grossPaise,
        int $feePaise,
        int $netPayoutPaise,
        User $accountant
    ): array {
        if (($grossPaise - $feePaise) !== $netPayoutPaise) {
            throw new InvalidArgumentException("Settlement equation mismatch: gross (₹{$grossPaise}) - fee (₹{$feePaise}) != net (₹{$netPayoutPaise}).");
        }

        // Post balancing settlement journal:
        // Debit Bank Account 1020: Net Payout
        // Debit Payment Processing Fees 5030: Gateway Fee Expense
        // Credit Gateway Clearing 1020: Gross confirmed customer collections
        $journal = $this->journalService->post(
            sourceType: 'gateway_settlement',
            sourceId: 0,
            description: "Gateway payout settlement {$batchReference}",
            lines: [
                [
                    'account_code' => '1020', // Bank Account
                    'debit_paise' => $netPayoutPaise,
                    'narration' => "Net deposit from gateway batch {$batchReference}",
                ],
                [
                    'account_code' => '5030', // Direct Expenses / Gateway Processing Fees
                    'debit_paise' => $feePaise,
                    'narration' => "Gateway transaction processing charges on {$batchReference}",
                    'department_code' => 'admin',
                ],
                [
                    'account_code' => '1020', // Clearing Account
                    'credit_paise' => $grossPaise,
                    'narration' => "Clearing of customer digital payments for batch {$batchReference}",
                ],
            ],
            userId: $accountant->id
        );

        return [
            'batch_reference' => $batchReference,
            'gross_paise' => $grossPaise,
            'fee_paise' => $feePaise,
            'net_payout_paise' => $netPayoutPaise,
            'journal' => $journal,
            'balanced' => $journal->isBalanced(),
        ];
    }
}
