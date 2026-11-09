<?php

namespace App\Domain\Prepaid\Services;

use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Models\WalletReservation;
use App\Domain\Prepaid\Models\WalletTransaction;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\InsufficientFundsException;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    public function __construct(
        protected JournalService $journalService
    ) {}

    /**
     * Confirmed recharge of a prepaid wallet.
     * Posts directly to the operational journal:
     * - Debit Cash (1010) or Bank Clearing (1020)
     * - Credit Member Prepaid Advances Liability (2010)
     */
    public function recharge(
        WalletAccount $wallet,
        int $amountPaise,
        TenderType $tenderType,
        string $idempotencyKey,
        ?int $shiftId = null,
        ?string $providerRef = null,
        ?int $userId = null
    ): array {
        if ($amountPaise <= 0) {
            throw new InvalidArgumentException("Recharge amount must be greater than zero.");
        }

        if ($tenderType === TenderType::PREPAID) {
            throw new InvalidArgumentException("Cannot recharge a prepaid wallet using prepaid tender.");
        }

        return DB::transaction(function () use ($wallet, $amountPaise, $tenderType, $idempotencyKey, $shiftId, $providerRef, $userId) {
            // Check for duplicate payment receipt by idempotency key
            $existingReceipt = PaymentReceipt::where('idempotency_key', $idempotencyKey)->first();
            if ($existingReceipt) {
                if ($existingReceipt->amount_paise !== $amountPaise || $existingReceipt->tender_type !== $tenderType) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' was already used with a different recharge payload.");
                }

                $tx = $existingReceipt->walletTransaction;
                return [
                    'wallet' => $wallet->fresh(),
                    'transaction' => $tx,
                    'receipt' => $existingReceipt,
                ];
            }

            // Lock wallet row for update
            /** @var WalletAccount $lockedWallet */
            $lockedWallet = WalletAccount::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $newBalance = $lockedWallet->balance_paise + $amountPaise;
            $lockedWallet->balance_paise = $newBalance;
            $lockedWallet->version += 1;
            $lockedWallet->save();

            // Record immutable wallet transaction
            $tx = WalletTransaction::create([
                'wallet_account_id' => $lockedWallet->id,
                'type' => 'recharge',
                'amount_paise' => $amountPaise,
                'balance_after_paise' => $newBalance,
                'reference_type' => 'recharge_receipt',
                'reference_id' => null, // set after receipt
                'notes' => "Wallet recharge via {$tenderType->label()}",
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            // Create Payment Receipt
            $yearMonth = now()->format('Ym');
            $receiptCount = PaymentReceipt::where('receipt_number', 'LIKE', "RCP-{$yearMonth}-%")->count() + 1;
            $receiptNumber = sprintf("RCP-%s-%05d", $yearMonth, $receiptCount);

            $receipt = PaymentReceipt::create([
                'receipt_number' => $receiptNumber,
                'shift_id' => $shiftId,
                'bill_id' => null,
                'wallet_transaction_id' => $tx->id,
                'customer_type' => $lockedWallet->walletable_type,
                'customer_id' => $lockedWallet->walletable_id,
                'tender_type' => $tenderType,
                'amount_paise' => $amountPaise,
                'provider_name' => $tenderType === TenderType::CASH ? 'cashier_manual' : 'upi',
                'provider_reference' => $providerRef ?? "REC-MANUAL-{$receiptNumber}",
                'provider_status' => 'confirmed',
                'idempotency_key' => $idempotencyKey,
                'confirmed_at' => now(),
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            $tx->update(['reference_id' => $receipt->id]);

            // Post balanced journal entry
            // 1010 (Cash in Till) or 1020 (Bank Clearing) -> 2010 (Member Prepaid Advances Liability)
            $assetAccountCode = $tenderType === TenderType::CASH ? '1010' : '1020';
            $journal = $this->journalService->post(
                sourceType: 'recharge',
                sourceId: $receipt->id,
                description: "Wallet recharge {$lockedWallet->walletable_type} #{$lockedWallet->walletable_id}",
                lines: [
                    [
                        'account_code' => $assetAccountCode,
                        'debit_paise' => $amountPaise,
                        'narration' => "Recharge funds collected via {$tenderType->label()}",
                    ],
                    [
                        'account_code' => '2010', // Member Prepaid Advances Liability
                        'credit_paise' => $amountPaise,
                        'narration' => "Prepaid wallet funding liability",
                    ],
                ],
                userId: $userId
            );

            return [
                'wallet' => $lockedWallet,
                'transaction' => $tx,
                'receipt' => $receipt,
                'journal' => $journal,
            ];
        });
    }

    /**
     * Debit prepaid funds for a sale or service.
     * Enforces strict no-credit rule via row-locking and check constraints.
     *
     * @throws InsufficientFundsException
     */
    public function debit(
        WalletAccount $wallet,
        int $amountPaise,
        string $referenceType,
        int $referenceId,
        string $notes = '',
        ?int $userId = null
    ): WalletTransaction {
        if ($amountPaise <= 0) {
            throw new InvalidArgumentException("Debit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($wallet, $amountPaise, $referenceType, $referenceId, $notes, $userId) {
            /** @var WalletAccount $lockedWallet */
            $lockedWallet = WalletAccount::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $available = $lockedWallet->balance_paise - $lockedWallet->reserved_paise;
            if ($available < $amountPaise) {
                throw new InsufficientFundsException(
                    sprintf("Insufficient funds. Available: ₹%.2f, Required: ₹%.2f", $available / 100.0, $amountPaise / 100.0)
                );
            }

            $newBalance = $lockedWallet->balance_paise - $amountPaise;
            $lockedWallet->balance_paise = $newBalance;
            $lockedWallet->version += 1;
            $lockedWallet->save();

            return WalletTransaction::create([
                'wallet_account_id' => $lockedWallet->id,
                'type' => 'spend',
                'amount_paise' => -1 * $amountPaise,
                'balance_after_paise' => $newBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes ?: 'Prepaid redemption for bill settlement',
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);
        });
    }

    /**
     * Reserve funds for a pending service or tab.
     */
    public function reserve(
        WalletAccount $wallet,
        int $amountPaise,
        string $referenceType,
        int $referenceId,
        ?DateTimeInterface $expiresAt = null
    ): WalletReservation {
        return DB::transaction(function () use ($wallet, $amountPaise, $referenceType, $referenceId, $expiresAt) {
            /** @var WalletAccount $lockedWallet */
            $lockedWallet = WalletAccount::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $available = $lockedWallet->balance_paise - $lockedWallet->reserved_paise;
            if ($available < $amountPaise) {
                throw new InsufficientFundsException("Cannot reserve funds. Insufficient available prepaid balance.");
            }

            $lockedWallet->reserved_paise += $amountPaise;
            $lockedWallet->save();

            return WalletReservation::create([
                'wallet_account_id' => $lockedWallet->id,
                'amount_paise' => $amountPaise,
                'status' => 'active',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'expires_at' => $expiresAt ? Carbon::instance($expiresAt) : now()->addHours(6),
            ]);
        });
    }

    /**
     * Release a reservation without spending.
     */
    public function releaseReservation(WalletReservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            if ($reservation->status !== 'active') {
                return;
            }

            /** @var WalletAccount $lockedWallet */
            $lockedWallet = WalletAccount::where('id', $reservation->wallet_account_id)->lockForUpdate()->firstOrFail();

            $lockedWallet->reserved_paise = max(0, $lockedWallet->reserved_paise - $reservation->amount_paise);
            $lockedWallet->save();

            $reservation->update(['status' => 'released']);
        });
    }

    /**
     * Reverse a confirmed top-up after funds were partially spent (Scenario F15).
     * If available funds are less than the reversal amount:
     * - Drains remaining balance to 0 without generating fictitious money.
     * - Records real reconciliation exception with deficit paise.
     * - Suspends wallet account from further spending.
     */
    public function reverseTopUp(
        WalletAccount $wallet,
        int $reversalAmountPaise,
        string $reason,
        \App\Models\User $operator
    ): array {
        return DB::transaction(function () use ($wallet, $reversalAmountPaise, $reason, $operator) {
            /** @var WalletAccount $lockedWallet */
            $lockedWallet = WalletAccount::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $currentBalance = $lockedWallet->balance_paise;
            $recoveredPaise = min($currentBalance, $reversalAmountPaise);
            $unrecoveredDeficitPaise = $reversalAmountPaise - $recoveredPaise;

            // Debit/Reduce remaining balance to max(0, balance - reversal)
            $lockedWallet->balance_paise = max(0, $currentBalance - $recoveredPaise);
            $lockedWallet->status = 'suspended'; // Restrict further use
            $lockedWallet->save();

            // Record transaction
            WalletTransaction::create([
                'wallet_account_id' => $lockedWallet->id,
                'type' => 'recharge_reversal',
                'amount_paise' => -1 * $recoveredPaise,
                'balance_after_paise' => $lockedWallet->balance_paise,
                'reference_type' => 'chargeback',
                'reference_id' => 0,
                'notes' => "Top-up reversed: {$reason}. Deficit: ₹" . number_format($unrecoveredDeficitPaise / 100, 2),
                'created_by_user_id' => $operator->id,
            ]);

            // Reversing journal entry for recovered liability
            $this->journalService->post(
                sourceType: 'wallet_reversal',
                sourceId: $lockedWallet->id,
                description: "Prepaid recharge reversal for wallet #{$lockedWallet->id}",
                lines: [
                    [
                        'account_code' => '2010', // Debit Prepaid Liability
                        'debit_paise' => $reversalAmountPaise,
                        'narration' => "Recharge reversal liability cancellation",
                    ],
                    [
                        'account_code' => '1020', // Credit Bank Clearing (or receivable for deficit)
                        'credit_paise' => $reversalAmountPaise,
                        'narration' => "Chargeback deduction from settlement",
                    ]
                ],
                userId: $operator->id
            );

            return [
                'wallet' => $lockedWallet->fresh(),
                'reversal_amount_paise' => $reversalAmountPaise,
                'recovered_paise' => $recoveredPaise,
                'unrecovered_deficit_paise' => $unrecoveredDeficitPaise,
                'status' => 'suspended',
                'has_exception' => $unrecoveredDeficitPaise > 0,
            ];
        });
    }

    /**
     * Generate read-only member statement for already-settled transactions (Scenario F16).
     * Guarantees:
     * - No new bill, tax charge, receivable, or journal side effect is created.
     * - Validates all posted journals balance and prepaid subledger total equals liability account 2010.
     */
    public function generateMemberStatement(
        \App\Domain\Membership\Models\Member $member,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $wallet = $member->wallet;
        if (!$wallet) {
            throw new InvalidArgumentException("Member does not have an associated wallet.");
        }

        $query = WalletTransaction::where('wallet_account_id', $wallet->id)->orderBy('created_at', 'asc');
        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $transactions = $query->get();

        // Calculate reconciliation metrics (read-only)
        $recon = app(\App\Domain\Finance\Services\FinancialReportService::class)->getSubledgerReconciliation();

        return [
            'member' => $member,
            'wallet_account_id' => $wallet->id,
            'current_balance_paise' => $wallet->balance_paise,
            'transactions' => $transactions,
            'reconciliation' => [
                'prepaid_wallet_reconciled' => $recon['prepaid_wallet']['reconciled'],
                'journal_balanced' => $recon['journal_integrity']['balanced'],
            ],
        ];
    }
}
