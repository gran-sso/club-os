<?php

namespace App\Filament\Resources\JournalHeaders\Pages;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Services\JournalService;
use App\Filament\Resources\JournalHeaders\JournalHeaderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateJournalHeader extends CreateRecord
{
    protected static string $resource = JournalHeaderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = $data['lines'] ?? [];
        $totalDebits = 0;
        $totalCredits = 0;
        $formattedLines = [];

        foreach ($lines as $line) {
            $account = Account::findOrFail((int) $line['account_id']);
            $debit = (int) ($line['debit_paise'] ?? 0);
            $credit = (int) ($line['credit_paise'] ?? 0);

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $totalDebits += $debit;
            $totalCredits += $credit;

            $formattedLines[] = [
                'account_code' => $account->code,
                'debit_paise' => $debit,
                'credit_paise' => $credit,
                'narration' => $line['narration'] ?? $data['description'],
                'department_code' => $line['department_code'] ?? null,
            ];
        }

        if ($totalDebits !== $totalCredits) {
            throw ValidationException::withMessages([
                'data.lines' => "Unbalanced journal voucher! Total Debits (₹" . number_format($totalDebits / 100, 2) . ") must equal Total Credits (₹" . number_format($totalCredits / 100, 2) . ").",
            ]);
        }

        if ($totalDebits === 0) {
            throw ValidationException::withMessages([
                'data.lines' => "Journal voucher must contain at least one non-zero debit and credit entry.",
            ]);
        }

        return app(JournalService::class)->post(
            sourceType: $data['source_type'] ?? 'manual_journal',
            sourceId: rand(100000, 999999),
            description: $data['description'],
            lines: $formattedLines,
            businessDate: !empty($data['business_date']) ? \Carbon\Carbon::parse($data['business_date']) : null,
            userId: auth()->id()
        );
    }
}
