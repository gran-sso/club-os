<?php

namespace App\Console\Commands;

use App\Domain\Shared\Models\SystemSetting;
use App\Domain\Shared\Services\AuditLogService;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune {--days= : Custom retention days to override system settings}';

    protected $description = 'Prune audit log entries older than the configured club retention period';

    public function handle(AuditLogService $auditLogService): int
    {
        $overrideDays = $this->option('days') !== null ? (int) $this->option('days') : null;
        $days = $overrideDays !== null
            ? max(1, min(365, $overrideDays))
            : max(7, min(365, (int) SystemSetting::get('audit_log_retention_days', 30)));

        $this->info("Pruning audit log entries older than {$days} days...");

        $deleted = $auditLogService->pruneExpired($days);

        $this->info("Successfully pruned {$deleted} expired audit log entries.");

        return self::SUCCESS;
    }
}
