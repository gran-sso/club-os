<?php

namespace App\Domain\Shared\Services;

use Carbon\Carbon;
use DateTimeInterface;

class BusinessDateService
{
    /**
     * Get configured cutoff time in 'H:i:s' format.
     */
    public function getCutoffTime(?string $overrideCutoff = null): string
    {
        $cutoff = $overrideCutoff ?? config('club.business_day_cutoff_time', '00:00');
        $cutoff = trim($cutoff);

        $parts = explode(':', $cutoff);
        $hours = str_pad($parts[0] ?? '0', 2, '0', STR_PAD_LEFT);
        $minutes = str_pad($parts[1] ?? '0', 2, '0', STR_PAD_LEFT);
        $seconds = str_pad($parts[2] ?? '0', 2, '0', STR_PAD_LEFT);

        return "{$hours}:{$minutes}:{$seconds}";
    }

    /**
     * Get operating timezone.
     */
    public function getTimezone(): string
    {
        return config('club.timezone', config('app.timezone', 'Asia/Kolkata'));
    }

    /**
     * Calculate business date for a given timestamp based on cutoff time.
     *
     * With '00:00' (midnight cutoff):
     * A 01:30 transaction on 2026-10-19 belongs to '2026-10-19' (new business date),
     * even if yesterday's cashier shift remains open.
     *
     * With '03:00' cutoff:
     * A 01:30 transaction on 2026-10-19 belongs to '2026-10-18' (previous business date).
     * A 03:15 transaction on 2026-10-19 belongs to '2026-10-19'.
     */
    public function getBusinessDate(
        DateTimeInterface|string|null $timestamp = null,
        ?string $overrideCutoff = null
    ): string {
        $tz = $this->getTimezone();
        $cutoff = $this->getCutoffTime($overrideCutoff);

        if ($timestamp === null) {
            $dt = now($tz);
        } elseif (is_string($timestamp)) {
            $dt = Carbon::parse($timestamp, $tz);
        } else {
            $dt = Carbon::instance($timestamp)->setTimezone($tz);
        }

        $timeString = $dt->format('H:i:s');

        // If cutoff is midnight (00:00:00), business date is always the calendar date
        if ($cutoff === '00:00:00') {
            return $dt->toDateString();
        }

        // If the timestamp time is earlier than the cutoff, it belongs to the previous business day
        if ($timeString < $cutoff) {
            return $dt->copy()->subDay()->toDateString();
        }

        return $dt->toDateString();
    }

    /**
     * Get the datetime start and end window for a given business date.
     *
     * For cutoff 00:00 on date 2026-10-18:
     * Start: 2026-10-18 00:00:00
     * End:   2026-10-18 23:59:59.999999
     *
     * For cutoff 03:00 on date 2026-10-18:
     * Start: 2026-10-18 03:00:00
     * End:   2026-10-19 02:59:59.999999
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getBusinessDateWindow(
        DateTimeInterface|string $businessDate,
        ?string $overrideCutoff = null
    ): array {
        $tz = $this->getTimezone();
        $cutoff = $this->getCutoffTime($overrideCutoff);
        $dateStr = $businessDate instanceof DateTimeInterface
            ? Carbon::instance($businessDate)->setTimezone($tz)->toDateString()
            : Carbon::parse($businessDate, $tz)->toDateString();

        if ($cutoff === '00:00:00') {
            $start = Carbon::parse("{$dateStr} 00:00:00", $tz);
            $end = Carbon::parse("{$dateStr} 23:59:59.999999", $tz);
        } else {
            $start = Carbon::parse("{$dateStr} {$cutoff}", $tz);
            $end = Carbon::parse("{$dateStr} {$cutoff}", $tz)->addDay()->subMicrosecond();
        }

        return [$start, $end];
    }

    /**
     * Check if a timestamp falls within a business date.
     */
    public function isTimestampInBusinessDate(
        DateTimeInterface|string $timestamp,
        DateTimeInterface|string $businessDate,
        ?string $overrideCutoff = null
    ): bool {
        $tz = $this->getTimezone();
        $dateStr = $businessDate instanceof DateTimeInterface
            ? Carbon::instance($businessDate)->setTimezone($tz)->toDateString()
            : Carbon::parse($businessDate, $tz)->toDateString();

        return $this->getBusinessDate($timestamp, $overrideCutoff) === $dateStr;
    }
}
