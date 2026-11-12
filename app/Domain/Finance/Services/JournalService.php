<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Models\JournalLine;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Shared\Exceptions\UnbalancedJournalException;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService
{
    /**
     * Post a balanced double-entry journal entry.
     *
     * @param string $sourceType e.g. 'recharge', 'bill', 'refund'
     * @param int $sourceId
     * @param string $description
     * @param array<array{account_code: string, debit_paise?: int, credit_paise?: int, narration?: string, department_code?: string}> $lines
     * @param DateTimeInterface|null $businessDate
     * @param int|null $userId
     * @return JournalHeader
     *
     * @throws UnbalancedJournalException
     */
    public function post(
        string $sourceType,
        int $sourceId,
        string $description,
        array $lines,
        ?DateTimeInterface $businessDate = null,
        ?int $userId = null,
        bool $queueOutbox = true
    ): JournalHeader {
        return DB::transaction(function () use ($sourceType, $sourceId, $description, $lines, $businessDate, $userId, $queueOutbox) {
            // Check if already posted for this source transaction
            $existing = JournalHeader::where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $date = $businessDate
                ? Carbon::instance($businessDate)
                : Carbon::parse(app(\App\Domain\Shared\Services\BusinessDateService::class)->getBusinessDate());
            $totalDebits = 0;
            $totalCredits = 0;
            $resolvedLines = [];

            foreach ($lines as $lineData) {
                $accountCode = $lineData['account_code'];
                $account = Account::where('code', $accountCode)->first();

                if (!$account) {
                    throw new InvalidArgumentException("Account code not found: {$accountCode}");
                }

                $debit = (int) ($lineData['debit_paise'] ?? 0);
                $credit = (int) ($lineData['credit_paise'] ?? 0);

                if ($debit < 0 || $credit < 0) {
                    throw new InvalidArgumentException("Debit and credit amounts in paise cannot be negative.");
                }

                if ($debit > 0 && $credit > 0) {
                    throw new InvalidArgumentException("A single journal line cannot contain both debit and credit amounts.");
                }

                $totalDebits += $debit;
                $totalCredits += $credit;

                $resolvedLines[] = [
                    'account_id' => $account->id,
                    'debit_paise' => $debit,
                    'credit_paise' => $credit,
                    'narration' => $lineData['narration'] ?? $description,
                    'department_code' => $lineData['department_code'] ?? null,
                ];
            }

            if ($totalDebits !== $totalCredits) {
                throw new UnbalancedJournalException($totalDebits, $totalCredits);
            }

            // Generate sequential journal number
            $yearMonth = $date->format('Ym');
            $count = JournalHeader::where('journal_number', 'LIKE', "JNL-{$yearMonth}-%")->count() + 1;
            $journalNumber = sprintf("JNL-%s-%05d", $yearMonth, $count);

            $header = JournalHeader::create([
                'journal_number' => $journalNumber,
                'business_date' => $date->toDateString(),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'created_by_user_id' => $userId ?? auth()->id(),
            ]);

            foreach ($resolvedLines as $resolved) {
                $header->lines()->create($resolved);
            }

            // Queue outbox message for Tally sync when requested
            if ($queueOutbox) {
                OutboxMessage::create([
                    'event_type' => 'finance.journal.posted',
                    'aggregate_type' => 'JournalHeader',
                    'aggregate_id' => $header->id,
                    'payload' => [
                        'journal_number' => $header->journal_number,
                        'business_date' => $header->business_date->toDateString(),
                        'total_paise' => $totalDebits,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                    ],
                    'status' => 'pending',
                    'idempotency_key' => "outbox_jnl_{$header->id}",
                ]);
            }

            return $header;
        });
    }
}
