<?php

namespace Tests\Unit;

use App\Domain\Shared\Services\BusinessDateService;
use Carbon\Carbon;
use Tests\TestCase;

class BusinessDateServiceTest extends TestCase
{
    protected BusinessDateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusinessDateService();
    }

    public function test_cutoff_time_normalization(): void
    {
        $this->assertEquals('00:00:00', $this->service->getCutoffTime('00:00'));
        $this->assertEquals('03:00:00', $this->service->getCutoffTime('03:00'));
        $this->assertEquals('03:00:00', $this->service->getCutoffTime('3:00'));
        $this->assertEquals('03:30:00', $this->service->getCutoffTime('03:30'));
        $this->assertEquals('04:15:30', $this->service->getCutoffTime('04:15:30'));
    }

    public function test_midnight_cutoff_calculation(): void
    {
        config(['club.business_day_cutoff_time' => '00:00']);

        // Evening 23:45 on Oct 18 belongs to Oct 18
        $this->assertEquals('2026-10-18', $this->service->getBusinessDate('2026-10-18 23:45:00'));

        // 01:30 on Oct 19 belongs to the new business date (Oct 19)
        $this->assertEquals('2026-10-19', $this->service->getBusinessDate('2026-10-19 01:30:00'));

        // Business date windows
        [$start18, $end18] = $this->service->getBusinessDateWindow('2026-10-18');
        $this->assertEquals('2026-10-18 00:00:00', $start18->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-18 23:59:59', $end18->format('Y-m-d H:i:s'));

        [$start19, $end19] = $this->service->getBusinessDateWindow('2026-10-19');
        $this->assertEquals('2026-10-19 00:00:00', $start19->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-19 23:59:59', $end19->format('Y-m-d H:i:s'));

        // 01:30 is in Oct 19 window, not Oct 18 window
        $ts0130 = Carbon::parse('2026-10-19 01:30:00', $this->service->getTimezone());
        $this->assertFalse($ts0130->between($start18, $end18));
        $this->assertTrue($ts0130->between($start19, $end19));
    }

    public function test_configured_0300_cutoff_calculation(): void
    {
        config(['club.business_day_cutoff_time' => '03:00']);

        // Evening 23:45 on Oct 18 belongs to Oct 18
        $this->assertEquals('2026-10-18', $this->service->getBusinessDate('2026-10-18 23:45:00'));

        // 01:30 on Oct 19 is before 03:00, so belongs to previous business date (Oct 18)
        $this->assertEquals('2026-10-18', $this->service->getBusinessDate('2026-10-19 01:30:00'));

        // 02:59 on Oct 19 belongs to Oct 18
        $this->assertEquals('2026-10-18', $this->service->getBusinessDate('2026-10-19 02:59:59'));

        // 03:00 on Oct 19 is on cutoff, belongs to new business date (Oct 19)
        $this->assertEquals('2026-10-19', $this->service->getBusinessDate('2026-10-19 03:00:00'));

        // 03:15 on Oct 19 belongs to Oct 19
        $this->assertEquals('2026-10-19', $this->service->getBusinessDate('2026-10-19 03:15:00'));

        // Business date windows
        [$start18, $end18] = $this->service->getBusinessDateWindow('2026-10-18');
        $this->assertEquals('2026-10-18 03:00:00', $start18->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-19 02:59:59', $end18->format('Y-m-d H:i:s'));

        [$start19, $end19] = $this->service->getBusinessDateWindow('2026-10-19');
        $this->assertEquals('2026-10-19 03:00:00', $start19->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-20 02:59:59', $end19->format('Y-m-d H:i:s'));

        // 01:30 is in Oct 18 window under 03:00 cutoff, not Oct 19 window
        $ts0130 = Carbon::parse('2026-10-19 01:30:00', $this->service->getTimezone());
        $this->assertTrue($ts0130->between($start18, $end18));
        $this->assertFalse($ts0130->between($start19, $end19));
    }

    public function test_is_timestamp_in_business_date(): void
    {
        // Midnight cutoff
        config(['club.business_day_cutoff_time' => '00:00']);
        $this->assertFalse($this->service->isTimestampInBusinessDate('2026-10-19 01:30:00', '2026-10-18'));
        $this->assertTrue($this->service->isTimestampInBusinessDate('2026-10-19 01:30:00', '2026-10-19'));

        // 03:00 cutoff
        config(['club.business_day_cutoff_time' => '03:00']);
        $this->assertTrue($this->service->isTimestampInBusinessDate('2026-10-19 01:30:00', '2026-10-18'));
        $this->assertFalse($this->service->isTimestampInBusinessDate('2026-10-19 01:30:00', '2026-10-19'));
    }
}
