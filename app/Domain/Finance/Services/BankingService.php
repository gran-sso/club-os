<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\BankTransaction;
use App\Domain\Shared\Services\BusinessDateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankingService
{
    public function __construct(
        protected JournalService $journalService,
        protected PeriodClosingService $periodClosingService,
        protected BusinessDateService $businessDateService
    ) {}

    /**
     * Create a new club bank account.
     */
    public function createBankAccount(array $data): BankAccount
    {
        return DB::transaction(function () use ($data) {
            $isPrimary = (bool) ($data['is_primary'] ?? false);
            if ($isPrimary) {
                BankAccount::where('is_primary', true)->update(['is_primary' => false]);
            }

            $openingBalancePaise = (int) ($data['opening_balance_paise'] ?? 0);

            // If GL account not supplied, link to 1020 Bank Clearing or match bank name
            $glAccountId = $data['gl_account_id'] ?? null;
            if (!$glAccountId) {
                $glAccount = Account::where('code', '1020')->first();
                $glAccountId = $glAccount?->id;
            }

            return BankAccount::create([
                'account_name' => trim($data['account_name']),
                'bank_name' => trim($data['bank_name']),
                'branch_name' => trim($data['branch_name'] ?? 'Main Branch'),
                'account_number' => trim($data['account_number']),
                'account_type' => $data['account_type'] ?? 'current',
                'ifsc_code' => strtoupper(trim($data['ifsc_code'])),
                'upi_id' => !empty($data['upi_id']) ? trim($data['upi_id']) : null,
                'gl_account_id' => $glAccountId,
                'opening_balance_paise' => $openingBalancePaise,
                'current_balance_paise' => $openingBalancePaise,
                'is_primary' => $isPrimary,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Deposit cash received at counter tills into a club bank account (Contra Voucher).
     *
     * Accounting entry:
     *   Debit: Bank Account (Asset 102x or 1020)
     *   Credit: Cash in Hand (Asset 1010)
     */
    public function depositCash(
        BankAccount $bankAccount,
        int $amountPaise,
        ?string $businessDate = null,
        ?string $referenceNumber = null,
        ?string $depositedBy = null,
        ?string $description = null,
        ?int $userId = null
    ): BankTransaction {
        if ($amountPaise <= 0) {
            throw new InvalidArgumentException("Deposit amount must be greater than zero.");
        }

        $dateStr = $businessDate ?? $this->businessDateService->getBusinessDate();
        $this->periodClosingService->assertDateNotClosed($dateStr);

        return DB::transaction(function () use (
            $bankAccount,
            $amountPaise,
            $dateStr,
            $referenceNumber,
            $depositedBy,
            $description,
            $userId
        ) {
            // Lock bank account row
            $account = BankAccount::lockForUpdate()->findOrFail($bankAccount->id);

            $date = Carbon::parse($dateStr);
            $yearMonth = $date->format('Ym');
            $count = BankTransaction::where('transaction_number', 'LIKE', "DEP-{$yearMonth}-%")->count() + 1;
            $txNumber = sprintf("DEP-%s-%05d", $yearMonth, $count);

            $desc = $description ?: "Counter Cash Deposit to {$account->bank_name} ({$account->account_name})" . ($referenceNumber ? " [Challan/Slip #{$referenceNumber}]" : '');

            $transaction = BankTransaction::create([
                'transaction_number' => $txNumber,
                'type' => 'cash_deposit',
                'bank_account_id' => $account->id,
                'amount_paise' => $amountPaise,
                'business_date' => $date->toDateString(),
                'reference_number' => $referenceNumber,
                'deposited_by' => $depositedBy,
                'description' => $desc,
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            // Increment bank balance
            $account->increment('current_balance_paise', $amountPaise);

            // Post balanced double-entry contra journal
            $bankGlCode = $account->glAccount?->code ?? '1020';
            $journal = $this->journalService->post(
                sourceType: 'bank_deposit',
                sourceId: $transaction->id,
                description: $desc,
                lines: [
                    [
                        'account_code' => $bankGlCode,
                        'debit_paise' => $amountPaise,
                        'credit_paise' => 0,
                        'narration' => "Deposit into {$account->account_name}" . ($referenceNumber ? " (Ref: {$referenceNumber})" : ''),
                    ],
                    [
                        'account_code' => '1010', // Cash in Hand (Counter Tills)
                        'debit_paise' => 0,
                        'credit_paise' => $amountPaise,
                        'narration' => "Counter cash remittance to {$account->bank_name}" . ($depositedBy ? " by {$depositedBy}" : ''),
                    ],
                ],
                businessDate: $date,
                userId: $userId ?? auth()->id()
            );

            $transaction->update(['journal_header_id' => $journal->id]);

            return $transaction;
        });
    }

    /**
     * Transfer funds between two club bank accounts (Inter-Bank Transfer Contra Voucher).
     *
     * Accounting entry:
     *   Debit: Destination Bank Account
     *   Credit: Source Bank Account
     */
    public function transferFunds(
        BankAccount $fromAccount,
        BankAccount $toAccount,
        int $amountPaise,
        ?string $businessDate = null,
        ?string $referenceNumber = null,
        ?string $description = null,
        ?int $userId = null
    ): BankTransaction {
        if ($fromAccount->id === $toAccount->id) {
            throw new InvalidArgumentException("Cannot transfer funds to the same bank account.");
        }

        if ($amountPaise <= 0) {
            throw new InvalidArgumentException("Transfer amount must be greater than zero.");
        }

        $dateStr = $businessDate ?? $this->businessDateService->getBusinessDate();
        $this->periodClosingService->assertDateNotClosed($dateStr);

        return DB::transaction(function () use (
            $fromAccount,
            $toAccount,
            $amountPaise,
            $dateStr,
            $referenceNumber,
            $description,
            $userId
        ) {
            // Lock accounts in order to prevent deadlocks
            $firstId = min($fromAccount->id, $toAccount->id);
            $secondId = max($fromAccount->id, $toAccount->id);

            BankAccount::lockForUpdate()->find($firstId);
            BankAccount::lockForUpdate()->find($secondId);

            $from = BankAccount::findOrFail($fromAccount->id);
            $to = BankAccount::findOrFail($toAccount->id);

            $date = Carbon::parse($dateStr);
            $yearMonth = $date->format('Ym');
            $count = BankTransaction::where('transaction_number', 'LIKE', "TRF-{$yearMonth}-%")->count() + 1;
            $txNumber = sprintf("TRF-%s-%05d", $yearMonth, $count);

            $desc = $description ?: "Inter-Bank Transfer from {$from->account_name} to {$to->account_name}" . ($referenceNumber ? " [Ref #{$referenceNumber}]" : '');

            $transaction = BankTransaction::create([
                'transaction_number' => $txNumber,
                'type' => 'inter_bank_transfer',
                'bank_account_id' => $from->id,
                'transfer_to_bank_account_id' => $to->id,
                'amount_paise' => $amountPaise,
                'business_date' => $date->toDateString(),
                'reference_number' => $referenceNumber,
                'description' => $desc,
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            $from->decrement('current_balance_paise', $amountPaise);
            $to->increment('current_balance_paise', $amountPaise);

            $fromGl = $from->glAccount?->code ?? '1020';
            $toGl = $to->glAccount?->code ?? '1020';

            // Post journal if distinct accounts, or balanced memo
            $journal = $this->journalService->post(
                sourceType: 'bank_transfer',
                sourceId: $transaction->id,
                description: $desc,
                lines: [
                    [
                        'account_code' => $toGl,
                        'debit_paise' => $amountPaise,
                        'credit_paise' => 0,
                        'narration' => "Received transfer from {$from->account_name}",
                    ],
                    [
                        'account_code' => $fromGl,
                        'debit_paise' => 0,
                        'credit_paise' => $amountPaise,
                        'narration' => "Transferred out to {$to->account_name}",
                    ],
                ],
                businessDate: $date,
                userId: $userId ?? auth()->id()
            );

            $transaction->update(['journal_header_id' => $journal->id]);

            return $transaction;
        });
    }

    /**
     * Record an operating disbursement / payout drawn from a bank account.
     */
    public function recordDisbursement(
        BankAccount $bankAccount,
        int $amountPaise,
        string $type,
        string $description,
        ?string $businessDate = null,
        ?string $referenceNumber = null,
        ?int $journalHeaderId = null,
        ?int $userId = null
    ): BankTransaction {
        if ($amountPaise <= 0) {
            throw new InvalidArgumentException("Disbursement amount must be greater than zero.");
        }

        $dateStr = $businessDate ?? $this->businessDateService->getBusinessDate();
        $date = Carbon::parse($dateStr);
        $yearMonth = $date->format('Ym');
        $count = BankTransaction::where('transaction_number', 'LIKE', "PAY-{$yearMonth}-%")->count() + 1;
        $txNumber = sprintf("PAY-%s-%05d", $yearMonth, $count);

        return DB::transaction(function () use (
            $bankAccount,
            $amountPaise,
            $type,
            $description,
            $date,
            $referenceNumber,
            $journalHeaderId,
            $userId,
            $txNumber
        ) {
            $account = BankAccount::lockForUpdate()->findOrFail($bankAccount->id);
            $account->decrement('current_balance_paise', $amountPaise);

            return BankTransaction::create([
                'transaction_number' => $txNumber,
                'type' => $type,
                'bank_account_id' => $account->id,
                'amount_paise' => $amountPaise,
                'business_date' => $date->toDateString(),
                'reference_number' => $referenceNumber,
                'description' => $description,
                'journal_header_id' => $journalHeaderId,
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);
        });
    }
}

