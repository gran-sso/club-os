<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupRestorationVerificationTest extends TestCase
{
    /**
     * Scenario X08:
     * Restore a backup to a clean isolated environment. Verify representative bills and reconcile
     * prepaid, journals, contractor payable and stock against the backup checkpoint.
     */
    public function test_x08_isolated_backup_restoration_and_subledger_reconciliation(): void
    {
        $exitCode = Artisan::call('club:verify-backup-restoration', [
            '--source-db' => config('database.connections.mysql.database'),
            '--target-db' => 'malabar_club_x08_isolated_test',
        ]);

        $output = Artisan::output();

        if ($exitCode !== 0) {
            $this->fail("Command failed with output: " . $output);
        }

        $this->assertEquals(0, $exitCode, "Backup restoration verification command must succeed with exit code 0.");
        $this->assertStringContainsString("SUCCESS: Isolated backup restoration and reconciliation passed 100%", $output);
        $this->assertStringContainsString("Measured Restore Time:", $output);
    }
}
