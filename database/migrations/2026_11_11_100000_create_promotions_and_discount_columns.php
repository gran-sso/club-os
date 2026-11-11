<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Commercial Promotions & Bar/Counter Offers (e.g. Buy 2 Pegs, Get 1 Free)
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('type', 32)->default('buy_x_get_y'); // buy_x_get_y
            $table->unsignedInteger('buy_qty')->default(2);
            $table->unsignedInteger('get_qty')->default(1);
            $table->decimal('discount_percent', 5, 2)->default(100.00);
            $table->string('customer_scope', 32)->default('members_only'); // members_only, all
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('max_free_units')->nullable();
            $table->unsignedInteger('redeemed_free_units')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'id', 'fk_promo_creator')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'effective_from', 'effective_to'], 'idx_promo_active_dates');
        });

        // 2. Link eligible Tariffs to each Promotion (keeps 30ml vs 60ml servings isolated unless explicitly grouped)
        Schema::create('promotion_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions', 'id', 'fk_pt_promo')->cascadeOnDelete();
            $table->foreignId('tariff_id')->constrained('tariffs', 'id', 'fk_pt_tariff')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'tariff_id'], 'uq_promo_tariff');
            $table->index('tariff_id', 'idx_pt_tariff');
        });

        // 3. Supplier Cash / Scheme Discounts on Purchase Receipts
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('total_discount_paise')->default(0)->after('total_gross_paise');
        });

        Schema::table('purchase_receipt_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('gross_unit_cost_paise')->default(0)->after('unit_cost_paise');
            $table->unsignedBigInteger('discount_paise')->default(0)->after('gross_unit_cost_paise');
            $table->string('scheme_notes', 255)->nullable()->after('line_total_paise');
        });

        // 4. Promotional & Discount Snapshots on Immutable Bill Lines & Held Tabs
        Schema::table('bill_lines', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('item_name')->constrained('promotions', 'id', 'fk_bl_promo')->nullOnDelete();
            $table->string('promotion_name', 150)->nullable()->after('promotion_id');
            $table->decimal('free_quantity', 10, 2)->default(0.00)->after('quantity');
            $table->unsignedBigInteger('gross_amount_paise')->default(0)->after('unit_price_paise');
            $table->unsignedBigInteger('discount_amount_paise')->default(0)->after('gross_amount_paise');
        });

        Schema::table('pos_held_tabs', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_amount_paise')->default(0)->after('gross_amount_paise');
        });

        // 5. Refresh Bill Lines Immutability Trigger to protect promotion/discount columns on posted bills
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_bill_lines_prevent_update;');
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
                           OR NEW.free_quantity != OLD.free_quantity
                           OR NEW.unit_price_paise != OLD.unit_price_paise
                           OR NEW.gross_amount_paise != OLD.gross_amount_paise
                           OR NEW.discount_amount_paise != OLD.discount_amount_paise
                           OR IFNULL(NEW.promotion_id, 0) != IFNULL(OLD.promotion_id, 0)
                           OR IFNULL(NEW.promotion_name, '') != IFNULL(OLD.promotion_name, '')
                           OR NEW.tax_percent != OLD.tax_percent
                           OR NEW.tax_amount_paise != OLD.tax_amount_paise
                           OR NEW.line_total_paise != OLD.line_total_paise THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Bill lines of posted bills are immutable: quantities, rates, discounts, and totals cannot be updated.';
                        END IF;
                    END IF;
                END;
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_bill_lines_prevent_update;');
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
        }

        Schema::table('pos_held_tabs', function (Blueprint $table) {
            $table->dropColumn('discount_amount_paise');
        });

        Schema::table('bill_lines', function (Blueprint $table) {
            $table->dropForeign('fk_bl_promo');
            $table->dropColumn([
                'promotion_id',
                'promotion_name',
                'free_quantity',
                'gross_amount_paise',
                'discount_amount_paise',
            ]);
        });

        Schema::table('purchase_receipt_lines', function (Blueprint $table) {
            $table->dropColumn(['gross_unit_cost_paise', 'discount_paise', 'scheme_notes']);
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropColumn('total_discount_paise');
        });

        Schema::dropIfExists('promotion_tariffs');
        Schema::dropIfExists('promotions');
    }
};

