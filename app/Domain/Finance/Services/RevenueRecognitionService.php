<?php

namespace App\Domain\Finance\Services;

use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\RevenueRecognitionEntry;
use App\Domain\Finance\Models\RevenueRecognitionSchedule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RevenueRecognitionService
{
    public function __construct(
        protected JournalService $journalService
    ) {}

    /**
     * Create straight-line revenue recognition schedule for an upfront subscription (R02, D14).
     */
    public function createSchedule(FacilitySubscription $subscription): RevenueRecognitionSchedule
    {
        $totalDays = max(1, $subscription->starts_at->diffInDays($subscription->ends_at));
        $dailyRatePaise = intdiv($subscription->total_fee_paise, $totalDays);

        return RevenueRecognitionSchedule::firstOrCreate(
            ['subscription_id' => $subscription->id],
            [
                'total_amount_paise' => $subscription->total_fee_paise,
                'recognised_amount_paise' => 0,
                'service_start_date' => $subscription->starts_at->toDateString(),
                'service_end_date' => $subscription->ends_at->toDateString(),
                'total_days' => $totalDays,
                'daily_rate_paise' => $dailyRatePaise,
                'status' => 'active',
            ]
        );
    }

    /**
     * Recognize revenue for all active schedules for a given calendar date.
     * Protected against duplicate runs (idempotent, Scenario R02).
     */
    public function recogniseForDate(CarbonInterface|string $date, ?User $user = null): array
    {
        $d = Carbon::parse($date)->startOfDay();
        $dateStr = $d->toDateString();

        $schedules = RevenueRecognitionSchedule::where('status', 'active')
            ->where('service_start_date', '<=', $dateStr)
            ->where('service_end_date', '>', $dateStr)
            ->get();

        $results = [];

        foreach ($schedules as $schedule) {
            $entry = DB::transaction(function () use ($schedule, $d, $dateStr, $user) {
                // Check if already posted for this day
                $existing = RevenueRecognitionEntry::where('schedule_id', $schedule->id)
                    ->where('recognition_date', $dateStr)
                    ->first();

                if ($existing) {
                    return $existing; // Idempotent return without duplicating
                }

                $locked = RevenueRecognitionSchedule::where('id', $schedule->id)->lockForUpdate()->firstOrFail();

                // If last day of the service period, recognise remaining unrecognised paise (deterministic rounding, D14)
                $isLastDay = ($dateStr === $locked->service_end_date->copy()->subDay()->toDateString());
                if ($isLastDay) {
                    $amountToRecognise = max(0, $locked->total_amount_paise - $locked->recognised_amount_paise);
                } else {
                    $amountToRecognise = min($locked->daily_rate_paise, $locked->total_amount_paise - $locked->recognised_amount_paise);
                }

                if ($amountToRecognise <= 0) {
                    return null;
                }

                $entry = RevenueRecognitionEntry::create([
                    'schedule_id' => $locked->id,
                    'recognition_date' => $dateStr,
                    'amount_paise' => $amountToRecognise,
                    'journal_header_id' => null,
                    'status' => 'posted',
                ]);

                // Post balanced journal: Debit 2010 (Liability) / Credit 4040 (Revenue)
                $lines = [
                    [
                        'account_code' => '2010',
                        'debit_paise' => $amountToRecognise,
                        'credit_paise' => 0,
                        'department_code' => 'sports',
                        'narration' => "Recognised subscription revenue for {$dateStr} (Plan #{$locked->subscription->subscription_number})",
                    ],
                    [
                        'account_code' => '4040',
                        'debit_paise' => 0,
                        'credit_paise' => $amountToRecognise,
                        'department_code' => 'sports',
                        'narration' => "Recognised subscription revenue for {$dateStr}",
                    ],
                ];

                $journal = $this->journalService->post(
                    'RevenueRecognitionEntry',
                    $entry->id,
                    "Subscription Revenue Recognition: {$dateStr} [{$locked->subscription->subscription_number}]",
                    $lines,
                    $d,
                    $user?->id
                );

                $entry->update(['journal_header_id' => $journal->id]);

                $locked->recognised_amount_paise += $amountToRecognise;
                if ($locked->recognised_amount_paise >= $locked->total_amount_paise) {
                    $locked->status = 'completed';
                }
                $locked->save();

                return $entry;
            });

            if ($entry) {
                $results[] = $entry;
            }
        }

        return $results;
    }
}
