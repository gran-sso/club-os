<?php

use App\Domain\Shared\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tally:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(function (): bool {
        try {
            return SystemSetting::get('tally_driver', 'simulator') === 'live'
                && SystemSetting::getBool('tally_auto_sync', false);
        } catch (\Throwable) {
            return false;
        }
    });

Schedule::command('audit:prune')
    ->daily()
    ->withoutOverlapping();

