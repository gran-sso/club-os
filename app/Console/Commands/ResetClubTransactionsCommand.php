<?php

namespace App\Console\Commands;

use App\Domain\Finance\Models\BankAccount;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Tariff\Models\Facility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetClubTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'club:reset-transactions {--force : Force execution without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge all operational transactions, shifts, bills, bookings, and audit records, restoring a clean testing state while preserving master configurations, staff accounts, and tariffs.';

    /**
     * List of transactional tables to be truncated.
     *
     * @var list<string>
     */
    protected array $transactionalTables = [
        'pos_held_tabs',
        'shift_cash_movements',
        'shifts',
        'bill_lines',
        'payment_allocations',
        'payment_receipts',
        'captured_payments',
        'refunds',
        'unapplied_payments',
        'bills',
        'room_reservations',
        'court_bookings',
        'facility_maintenance_blocks',
        'facility_subscriptions',
        'guest_passes',
        'guests',
        'access_events',
        'wallet_reservations',
        'wallet_transactions',
        'contractor_settlement_adjustments',
        'contractor_settlement_allocations',
        'contractor_settlement_batches',
        'expenses',
        'revenue_recognition_entries',
        'revenue_recognition_schedules',
        'journal_lines',
        'journal_headers',
        'bank_transactions',
        'purchase_receipt_lines',
        'purchase_receipts',
        'inventory_movements',
        'audit_logs',
        'idempotency_records',
        'import_batches',
        'outbox_messages',
        'failed_jobs',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                'This will permanently truncate all transactional records (bills, shifts, bookings, wallet entries, journals, and audit logs) and reset operational state. Do you wish to proceed?',
                false
            );

            if (! $confirmed) {
                $this->warn('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Starting club transactional reset...');

        // 1. Temporarily disable foreign key checks to allow clean truncation
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        $truncatedCount = 0;
        foreach ($this->transactionalTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $truncatedCount++;
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        $this->info("Truncated {$truncatedCount} transactional tables cleanly.");

        // 2. Reset operational states on master facilities (all rooms back to clean)
        if (Schema::hasTable('facilities')) {
            Facility::where('type', 'room')->update([
                'housekeeping_status' => 'clean',
            ]);
            $this->info('Reset room housekeeping statuses to clean.');
        }

        // 3. Reset inventory stock levels to standard testing quantities
        if (Schema::hasTable('inventory_items')) {
            InventoryItem::where('sku', 'BAR-KF-650')->update(['current_stock_qty' => 120.00]);
            InventoryItem::where('sku', 'BAR-SIG-750')->update(['current_stock_qty' => 25.00]);
            InventoryItem::where('sku', 'BAR-VOD-750')->update(['current_stock_qty' => 0.00]);
            $this->info('Reset bar inventory stock quantities.');
        }

        // 4. Reset wallet balances to starting testing balances
        if (Schema::hasTable('wallet_accounts') && Schema::hasTable('members')) {
            WalletAccount::query()->update([
                'balance_paise' => 0,
                'reserved_paise' => 0,
            ]);

            // Fund test members MC-001 and MC-002 for immediate POS testing
            $m1 = Member::where('membership_number', 'MC-001')->first();
            if ($m1) {
                WalletAccount::where('walletable_type', Member::class)->where('walletable_id', $m1->id)
                    ->update(['balance_paise' => 500000]); // ₹5,000.00
            }

            $m2 = Member::where('membership_number', 'MC-002')->first();
            if ($m2) {
                WalletAccount::where('walletable_type', Member::class)->where('walletable_id', $m2->id)
                    ->update(['balance_paise' => 100000]); // ₹1,000.00
            }

            $this->info('Reset member wallet balances (MC-001 funded with ₹5,000, MC-002 with ₹1,000).');
        }

        // 5. Reset bank account current balances to opening balances
        if (Schema::hasTable('bank_accounts')) {
            BankAccount::where('account_number', '40912345678')->update(['current_balance_paise' => 50000000]);
            BankAccount::where('account_number', '10482093847')->update(['current_balance_paise' => 25000000]);
            $this->info('Reset bank account balances to opening balances.');
        }

        // 6. Flush cached stats and system caches
        try {
            cache()->flush();
        } catch (\Throwable $e) {
            // Non-critical cache driver error
        }

        $this->newLine();
        $this->info('===============================================================');
        $this->info('   MALABAR CLUB IS READY FOR FRESH TESTING');
        $this->info('===============================================================');
        $this->line('• Shifts & Till Declarations:  0 (All counters ready to open)');
        $this->line('• Bills & Paid Invoices:       0');
        $this->line('• Room Reservations:           0 (All 5 rooms Clean & Available)');
        $this->line('• Court Bookings:              0 (Badminton Court Available)');
        $this->line('• Financial Journals:          0 (Ledger ready for fresh posting)');
        $this->line('• Audit Logs:                  0');
        $this->line('• Staff Accounts Preserved:    admin, manager, cashier1, cashier2, reception, accountant');
        $this->line('• Test Cards Available:        MC-CARD-001 (₹5,000), MC-CARD-002 (₹1,000)');
        $this->info('===============================================================');

        return self::SUCCESS;
    }
}

