<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Expense;
use App\Domain\Tariff\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExpenseService
{
    public function __construct(
        protected JournalService $journalService,
        protected PeriodClosingService $periodClosingService
    ) {}

    /**
     * Record and approve an operating expense with balanced journal posting.
     */
    public function recordExpense(array $data, ?User $user = null): Expense
    {
        $amountPaise = (int) $data['amount_paise'];
        if ($amountPaise <= 0) {
            throw new InvalidArgumentException("Expense amount must be greater than zero.");
        }

        $expenseDate = Carbon::parse($data['expense_date'] ?? now());

        // Enforce period lock (R04)
        $this->periodClosingService->assertDateNotClosed($expenseDate);

        return DB::transaction(function () use ($data, $amountPaise, $expenseDate, $user) {
            $department = null;
            if (!empty($data['department_id'])) {
                $department = Department::find($data['department_id']);
            } elseif (!empty($data['department_code'])) {
                $department = Department::where('code', $data['department_code'])->first();
            }

            $expenseAccountId = $data['account_id'] ?? null;
            if (!$expenseAccountId) {
                $expenseAccount = Account::where('code', '5030')->firstOrFail();
            } else {
                $expenseAccount = Account::findOrFail($expenseAccountId);
            }

            $paymentMethod = $data['payment_method'] ?? 'bank_transfer';
            $bankAccountId = $data['bank_account_id'] ?? null;
            $bankAccount = $bankAccountId ? \App\Domain\Finance\Models\BankAccount::find($bankAccountId) : null;

            if ($bankAccount && in_array($paymentMethod, ['bank_transfer', 'cheque'])) {
                $paymentAccountCode = $bankAccount->glAccount?->code ?? '1020';
            } else {
                $paymentAccountCode = ($paymentMethod === 'cash') ? '1010' : '1020';
            }
            $paymentAccount = Account::where('code', $paymentAccountCode)->first()
                ?? Account::where('code', '1020')->firstOrFail();

            $expenseNumber = 'EXP-' . $expenseDate->format('Ymd') . '-' . strtoupper(Str::random(6));

            $expense = Expense::create([
                'expense_number' => $expenseNumber,
                'department_id' => $department?->id,
                'account_id' => $expenseAccount->id,
                'bank_account_id' => $bankAccount?->id,
                'payee' => $data['payee'],
                'document_reference' => $data['document_reference'] ?? null,
                'amount_paise' => $amountPaise,
                'payment_method' => $paymentMethod,
                'status' => 'paid',
                'expense_date' => $expenseDate->toDateString(),
                'approved_by_user_id' => $user?->id,
                'journal_header_id' => null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $user?->id,
            ]);

            // Post operational journal entry
            $lines = [
                [
                    'account_code' => $expenseAccount->code,
                    'debit_paise' => $amountPaise,
                    'credit_paise' => 0,
                    'department_code' => $department?->code,
                    'narration' => "Expense: {$data['payee']} - " . ($data['notes'] ?? 'Operational disbursement'),
                ],
                [
                    'account_code' => $paymentAccount->code,
                    'debit_paise' => 0,
                    'credit_paise' => $amountPaise,
                    'department_code' => $department?->code,
                    'narration' => "Disbursement via {$paymentMethod} to {$data['payee']}",
                ],
            ];

            $journal = $this->journalService->post(
                'Expense',
                $expense->id,
                "Operating Expense: {$data['payee']} [{$expenseNumber}]",
                $lines,
                $expenseDate,
                $user?->id
            );

            $expense->update(['journal_header_id' => $journal->id]);

            if ($bankAccount && in_array($paymentMethod, ['bank_transfer', 'cheque'])) {
                app(\App\Domain\Finance\Services\BankingService::class)->recordDisbursement(
                    bankAccount: $bankAccount,
                    amountPaise: $amountPaise,
                    type: 'expense_payout',
                    description: "Operating Expense: {$data['payee']} [{$expenseNumber}]",
                    businessDate: $expenseDate->toDateString(),
                    referenceNumber: $data['document_reference'] ?? null,
                    journalHeaderId: $journal->id,
                    userId: $user?->id
                );
            }

            return $expense;
        });
    }
}
