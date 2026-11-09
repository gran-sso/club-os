<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bills: Prevent financial modification or deletion of posted bills
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

        // 2. Bill Lines: Prevent modification or deletion of lines belonging to posted bills
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

        // 3. Journal Headers: Prevent modification of header references or deletion
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

        // 4. Journal Lines: Prevent any modification or deletion
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

        // 5. Wallet Transactions: Ledger entries are strictly append-only
        DB::unprepared("
            CREATE TRIGGER trg_wallet_transactions_prevent_update
            BEFORE UPDATE ON wallet_transactions
            FOR EACH ROW
            BEGIN
                -- Allow reference_id update once immediately after receipt generation
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

    public function down(): void
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
};
