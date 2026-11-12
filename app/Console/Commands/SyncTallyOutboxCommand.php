<?php

namespace App\Console\Commands;

use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Integrations\TallySyncService;
use App\Domain\Shared\Models\SystemSetting;
use Illuminate\Console\Command;

class SyncTallyOutboxCommand extends Command
{
    protected $signature = 'tally:sync
        {--limit=200 : Maximum number of pending vouchers to process in this run}
        {--force : Process outbox even if tally_driver is not set to live in production}';

    protected $description = 'Process pending Malabar Club General Ledger vouchers queued for TallyPrime synchronization.';

    public function handle(AccountingConnectorInterface $connector, TallySyncService $syncService): int
    {
        $driver = (string) SystemSetting::get('tally_driver', app()->environment('production') ? 'live' : 'simulator');

        if (app()->environment('production') && $driver !== 'live' && ! $this->option('force')) {
            $this->warn('Skipping Tally outbox sync: production environment requires tally_driver=live (or pass --force).');

            return self::SUCCESS;
        }

        $limit = max(1, min(1000, (int) $this->option('limit')));
        $report = $syncService->processPendingOutbox($connector, $limit);

        $this->info(sprintf(
            'Tally outbox sync complete: %d pending evaluated, %d acknowledged, %d failed/retryable.',
            $report['total_pending'],
            $report['processed'],
            $report['failed']
        ));

        foreach ($report['errors'] as $error) {
            $this->warn("  - {$error}");
        }

        return self::SUCCESS;
    }
}

