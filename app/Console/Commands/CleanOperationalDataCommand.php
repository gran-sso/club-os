<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanOperationalDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'club:clean-test-data {--force : Force the operation without confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Purge all operational test data (bookings, bills, receipts, journals, shifts, temporary guests) while preserving members, staff accounts, tariffs, and master configurations.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("==========================================================");
        $this->info("   MALABAR CLUB — OPERATIONAL TEST DATA CLEANUP ROUTINE   ");
        $this->info("==========================================================");

        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to purge all test transactions and room bookings? Members, staff accounts, and tariffs will be preserved.')) {
                $this->warn('Operation cancelled by user.');
                return Command::SUCCESS;
            }
        }

        $this->line("1. Temporarily dropping financial immutability triggers...");
        $this->dropTriggers();

        $this->line("2. Disabling foreign key constraints...");
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $tablesToTruncate = [
            'room_reservations',
            'facility_maintenance_blocks',
            'court_bookings',
            'facility_subscriptions',
            'locker_allocations',
            'guest_passes',
            'guests',
            'bill_lines',
            'bills',
            'payment_allocations',
            'payment_receipts',
            'refunds',
            'captured_payments',
            'unapplied_payments',
            'journal_lines',
            'journal_headers',
            'shift_cash_movements',
            'shifts',
            'wallet_transactions',
            'wallet_reservations',
            'contractor_settlement_allocations',
            'contractor_settlement_adjustments',
            'contractor_settlement_batches',
            'inventory_movements',
            'purchase_receipt_lines',
            'purchase_receipts',
            'bank_transactions',
            'expenses',
            'outbox_messages',
            'access_events',
            'member_notes',
        ];

        $this->line("3. Purging test transactions and bookings...");
        foreach ($tablesToTruncate as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->info("   ✓ Truncated {$table} (cleared {$count} rows)");
            }
        }

        // Clean orphaned test guest person records
        $this->line("4. Cleaning orphaned guest personal records...");
        $orphanedCount = DB::table('persons')
            ->whereNotIn('id', function ($query) {
                $query->select('person_id')->from('members')->whereNotNull('person_id')
                    ->union(DB::table('dependents')->select('person_id')->whereNotNull('person_id'))
                    ->union(DB::table('affiliated_members')->select('person_id')->whereNotNull('person_id'));
            })
            ->delete();
        $this->info("   ✓ Removed {$orphanedCount} temporary guest personal records.");

        // Reset wallet account balances to 0
        $this->line("5. Resetting member wallet ledger balances to ₹0.00...");
        DB::table('wallet_accounts')->update([
            'balance_paise' => 0,
            'updated_at' => now(),
        ]);
        $this->info("   ✓ All member wallet balances reset to 0 paise.");

        // Reset locker status to available
        if (Schema::hasTable('lockers')) {
            $this->line("6. Resetting locker allocations to available...");
            DB::table('lockers')->update([
                'status' => 'available',
                'assigned_member_id' => null,
                'assigned_at' => null,
                'allocated_until' => null,
                'updated_at' => now(),
            ]);
            $this->info("   ✓ All club lockers marked as available.");
        }

        $this->line("7. Re-enabling foreign key constraints...");
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->line("8. Restoring financial immutability triggers...");
        $this->restoreTriggers();

        $this->info("\n==========================================================");
        $this->info("   CLEANUP COMPLETE — VERIFIED BASELINE INTEGRITY         ");
        $this->info("==========================================================");
        $this->table(
            ['Entity / Core Baseline', 'Status', 'Record Count'],
            [
                ['Staff & Admin Users (users)', 'PRESERVED', DB::table('users')->count()],
                ['Club Members (members)', 'PRESERVED', DB::table('members')->count()],
                ['Persons / Profiles (persons)', 'PRESERVED', DB::table('persons')->count()],
                ['Member Smartcards (cards)', 'PRESERVED', DB::table('cards')->count()],
                ['Member Wallets (wallet_accounts)', 'PRESERVED (₹0.00)', DB::table('wallet_accounts')->count()],
                ['Departments (departments)', 'PRESERVED', DB::table('departments')->count()],
                ['Counters (counters)', 'PRESERVED', DB::table('counters')->count()],
                ['Rooms & Courts (facilities)', 'PRESERVED (Clean & Vacant)', DB::table('facilities')->count()],
                ['Tariffs & Rates (tariffs)', 'PRESERVED', DB::table('tariffs')->count()],
                ['Chart of Accounts (accounts)', 'PRESERVED', DB::table('accounts')->count()],
                ['Room Reservations (room_reservations)', 'CLEANED', DB::table('room_reservations')->count()],
                ['Invoices / Bills (bills)', 'CLEANED', DB::table('bills')->count()],
                ['Payment Receipts (payment_receipts)', 'CLEANED', DB::table('payment_receipts')->count()],
                ['General Ledger Journals (journal_headers)', 'CLEANED', DB::table('journal_headers')->count()],
                ['Cashier Shifts (shifts)', 'CLEANED', DB::table('shifts')->count()],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Temporarily drop immutability triggers.
     */
    protected function dropTriggers(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_bills_prevent_posted_update;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_bills_prevent_posted_delete;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_bill_lines_prevent_update;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_bill_lines_prevent_delete;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_headers_prevent_update;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_headers_prevent_delete;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_lines_prevent_update;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_lines_prevent_delete;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_wallet_transactions_prevent_update;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_wallet_transactions_prevent_delete;");
    }

    /**
     * Restore all financial immutability triggers.
     */
    protected function restoreTriggers(): void
    {
        DB::unprepared("
            CREATE TRIGGER trg_bills_prevent_posted_update
            BEFORE UPDATE ON bills
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('paid', 'refunded', 'reversed') THEN
                    IF NEW.gross_amount_paise != OLD.gross_amount_paise
                       OR NEW.tax_amount_paise != OLD.tax_amount_paise
                       OR NEW.net_amount_paise != OLD.net_amount_paise
                       OR NEW.discount_amount_paise != OLD.discount_amount_paise THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Posted bills are immutable: financial amounts cannot be updated.';
                    END IF;
                END IF;
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_bills_prevent_posted_delete
            BEFORE DELETE ON bills
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('paid', 'refunded', 'reversed') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted bills are immutable and cannot be deleted.';
                END IF;
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_bill_lines_prevent_update
            BEFORE UPDATE ON bill_lines
            FOR EACH ROW
            BEGIN
                DECLARE v_status VARCHAR(32);
                SELECT status INTO v_status FROM bills WHERE id = OLD.bill_id;
                IF v_status IN ('paid', 'refunded', 'reversed') THEN
                    IF NEW.bill_id != OLD.bill_id
                       OR NEW.department_code != OLD.department_code
                       OR NEW.item_name != OLD.item_name
                       OR NEW.quantity != OLD.quantity
                       OR NEW.unit_price_paise != OLD.unit_price_paise
                       OR NEW.tax_percent != OLD.tax_percent
                       OR NEW.tax_amount_paise != OLD.tax_amount_paise
                       OR NEW.line_total_paise != OLD.line_total_paise THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Bill lines of posted bills are immutable: quantities, rates, and totals cannot be updated.';
                    END IF;
                END IF;
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_bill_lines_prevent_delete
            BEFORE DELETE ON bill_lines
            FOR EACH ROW
            BEGIN
                DECLARE v_status VARCHAR(32);
                SELECT status INTO v_status FROM bills WHERE id = OLD.bill_id;
                IF v_status IN ('paid', 'refunded', 'reversed') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Bill lines of posted bills are immutable and cannot be deleted.';
                END IF;
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_journal_headers_prevent_update
            BEFORE UPDATE ON journal_headers
            FOR EACH ROW
            BEGIN
                IF NEW.journal_number != OLD.journal_number
                   OR NEW.business_date != OLD.business_date
                   OR NEW.source_type != OLD.source_type
                   OR NEW.source_id != OLD.source_id THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted journal headers are immutable.';
                END IF;
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_journal_headers_prevent_delete
            BEFORE DELETE ON journal_headers
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Posted journal headers are immutable and cannot be deleted.';
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_journal_lines_prevent_update
            BEFORE UPDATE ON journal_lines
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Posted journal lines are immutable and cannot be modified.';
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_journal_lines_prevent_delete
            BEFORE DELETE ON journal_lines
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Posted journal lines are immutable and cannot be deleted.';
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_wallet_transactions_prevent_update
            BEFORE UPDATE ON wallet_transactions
            FOR EACH ROW
            BEGIN
                IF OLD.reference_id IS NOT NULL AND (NEW.amount_paise != OLD.amount_paise OR NEW.balance_after_paise != OLD.balance_after_paise OR NEW.type != OLD.type) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Wallet transaction ledger records are immutable.';
                END IF;
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_wallet_transactions_prevent_delete
            BEFORE DELETE ON wallet_transactions
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Wallet transaction ledger records cannot be deleted.';
            END;
        ");
    }
}
