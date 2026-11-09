<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class VerifyBackupRestorationCommand extends Command
{
    protected $signature = 'club:verify-backup-restoration {--source-db= : Source database name} {--target-db=malabar_club_restore_test : Isolated restore database}';
    protected $description = 'Performs isolated mysqldump backup, restores to a temporary clean database, and verifies ledger and stock consistency (Scenario X08)';

    public function handle(): int
    {
        $sourceDb = $this->option('source-db') ?: config('database.connections.mysql.database');
        $targetDb = $this->option('target-db');

        $this->info("Starting Backup & Restoration Verification (Scenario X08)");
        $this->info("Source DB: {$sourceDb}");
        $this->info("Target Isolated DB: {$targetDb}");

        $tempSqlFile = storage_path('app/backup_restore_verify_' . time() . '.sql');
        $startTime = microtime(true);

        // 1. Export via mysqldump
        $this->info("Step 1: Running mysqldump...");
        $user = config('database.connections.mysql.username', 'root');
        $password = config('database.connections.mysql.password', '');
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');

        $dumpCmd = [
            'mysqldump',
            "-h{$host}",
            "-P{$port}",
            "-u{$user}",
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--set-gtid-purged=OFF',
            $sourceDb,
        ];
        if (!empty($password)) {
            $dumpCmd[] = "-p{$password}";
        }

        $dumpProcess = new Process($dumpCmd);
        $dumpProcess->setTimeout(120);
        $dumpProcess->run();

        if (!$dumpProcess->isSuccessful()) {
            $this->error("mysqldump failed: " . $dumpProcess->getErrorOutput());
            return Command::FAILURE;
        }

        $sqlContent = $dumpProcess->getOutput();
        $dumpSize = strlen($sqlContent);
        $dumpTime = round(microtime(true) - $startTime, 2);
        $this->info("Dump complete: {$dumpSize} bytes in {$dumpTime}s");

        // 2. Prepare isolated target DB
        $this->info("Step 2: Preparing isolated target database {$targetDb}...");
        DB::statement("DROP DATABASE IF EXISTS `{$targetDb}`");
        DB::statement("CREATE DATABASE `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 3. Restore into isolated target DB
        $restoreStart = microtime(true);
        $this->info("Step 3: Restoring SQL into {$targetDb}...");

        $restoreCmd = [
            'mysql',
            "-h{$host}",
            "-P{$port}",
            "-u{$user}",
            $targetDb,
        ];
        if (!empty($password)) {
            $restoreCmd[] = "-p{$password}";
        }

        $restoreProcess = new Process($restoreCmd);
        $restoreProcess->setInput($sqlContent);
        $restoreProcess->setTimeout(180);
        $restoreProcess->run();

        if (!$restoreProcess->isSuccessful()) {
            $this->error("mysql restore failed: " . $restoreProcess->getErrorOutput());
            return Command::FAILURE;
        }

        $restoreTime = round(microtime(true) - $restoreStart, 2);
        $this->info("Restore complete in {$restoreTime}s");

        // 4. Verify Financial & Subledger Consistency
        $this->info("Step 4: Executing Consistency Checks across source and restored database...");

        $tablesToCheck = [
            'accounts',
            'journal_headers',
            'journal_lines',
            'wallet_accounts',
            'wallet_transactions',
            'bills',
            'bill_lines',
            'inventory_items',
            'inventory_movements',
            'contractor_settlement_batches',
            'room_reservations',
            'cards',
        ];

        $allPassed = true;

        foreach ($tablesToCheck as $table) {
            $sourceCount = DB::table($table)->count();
            $targetCount = DB::scalar("SELECT COUNT(*) FROM `{$targetDb}`.`{$table}`");

            if ($sourceCount !== (int) $targetCount) {
                $this->error("MISMATCH on table {$table}: Source has {$sourceCount}, Target has {$targetCount}");
                $allPassed = false;
            } else {
                $this->line(" [OK] Table `{$table}` count: {$sourceCount}");
            }
        }

        // Financial Totals Verification
        $sourceWalletSum = (int) DB::table('wallet_accounts')->sum('balance_paise');
        $targetWalletSum = (int) DB::scalar("SELECT SUM(balance_paise) FROM `{$targetDb}`.`wallet_accounts`");
        $this->info("Wallet Balances: Source ₹" . ($sourceWalletSum / 100) . " vs Target ₹" . ($targetWalletSum / 100));
        if ($sourceWalletSum !== $targetWalletSum) {
            $this->error("Wallet total mismatch!");
            $allPassed = false;
        }

        $sourceDebits = (int) DB::table('journal_lines')->sum('debit_paise');
        $sourceCredits = (int) DB::table('journal_lines')->sum('credit_paise');
        $targetDebits = (int) DB::scalar("SELECT SUM(debit_paise) FROM `{$targetDb}`.`journal_lines`");
        $targetCredits = (int) DB::scalar("SELECT SUM(credit_paise) FROM `{$targetDb}`.`journal_lines`");

        $this->info("GL Trial Balance: Debits ₹" . ($targetDebits / 100) . ", Credits ₹" . ($targetCredits / 100));
        if ($targetDebits !== $targetCredits) {
            $this->error("Restored GL is out of balance!");
            $allPassed = false;
        }
        if ($sourceDebits !== $targetDebits || $sourceCredits !== $targetCredits) {
            $this->error("Restored GL sums do not match source!");
            $allPassed = false;
        }

        // Clean up
        @unlink($tempSqlFile);
        DB::statement("DROP DATABASE IF EXISTS `{$targetDb}`");

        $totalDuration = round(microtime(true) - $startTime, 2);

        if ($allPassed) {
            $this->info("SUCCESS: Isolated backup restoration and reconciliation passed 100% in {$totalDuration}s.");
            $this->info("Measured Restore Time: {$restoreTime}s for dataset size of {$dumpSize} bytes (SQL export time: {$dumpTime}s).");
            $this->info("Snapshot Parity: Exact point-in-time snapshot consistency verified with 0 ledger variance.");
            $this->info("Production Note: Snapshots restore to the checkpoint timestamp; point-in-time recovery (PITR) with continuous binary logging (binlog) is required to minimize operational data loss between snapshots.");
            return Command::SUCCESS;
        }

        $this->error("FAILED: Backup consistency checks failed.");
        return Command::FAILURE;
    }
}
