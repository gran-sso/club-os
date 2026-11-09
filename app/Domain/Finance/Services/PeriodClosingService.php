<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Exceptions\PeriodClosedException;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PeriodClosingService
{
    /**
     * Close a financial period. Ordinary users cannot backdate edits or transactions into it (R04).
     */
    public function closePeriod(
        string $periodCode,
        string $startDate,
        string $endDate,
        ?string $notes = null,
        ?User $user = null
    ): FinancialPeriod {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        return FinancialPeriod::updateOrCreate(
            ['period_code' => $periodCode],
            [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_user_id' => $user?->id,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Check if a specific date falls into any closed financial period.
     */
    public function isDateClosed(CarbonInterface|string $date): bool
    {
        $d = Carbon::parse($date)->toDateString();

        return FinancialPeriod::where('status', 'closed')
            ->where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->exists();
    }

    /**
     * Assert that a date is open. Throws PeriodClosedException if closed.
     */
    public function assertDateNotClosed(CarbonInterface|string $date): void
    {
        if ($this->isDateClosed($date)) {
            $d = Carbon::parse($date)->toDateString();
            throw new PeriodClosedException("Date {$d} falls within a closed financial period. Backdating or modifications are forbidden.");
        }
    }
}
