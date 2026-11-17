<?php

use App\Domain\Shared\Enums\AccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create waiters / floor staff bearers table
        if (! Schema::hasTable('waiters')) {
            Schema::create('waiters', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->string('phone', 30)->nullable();
                $table->string('department_code', 32)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'department_code']);
            });

            // Seed default bearers
            DB::table('waiters')->insert([
                ['code' => 'B01', 'name' => 'Rajesh Kumar', 'phone' => '+91 98470 10001', 'department_code' => 'bar', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['code' => 'B02', 'name' => 'Suresh M.', 'phone' => '+91 98470 10002', 'department_code' => 'bar', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['code' => 'B03', 'name' => 'Babu P.', 'phone' => '+91 98470 10003', 'department_code' => 'card_room', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['code' => 'B04', 'name' => 'Manikandan K.', 'phone' => '+91 98470 10004', 'department_code' => 'food', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['code' => 'B05', 'name' => 'Shaji V.', 'phone' => '+91 98470 10005', 'department_code' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // 2. Add waiter, table, tip, and unsettled walkout fields to bills
        Schema::table('bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bills', 'waiter_id')) {
                $table->foreignId('waiter_id')->nullable()->after('customer_id')->constrained('waiters')->nullOnDelete();
            }
            if (! Schema::hasColumn('bills', 'table_number')) {
                $table->string('table_number', 50)->nullable()->after('waiter_id');
            }
            if (! Schema::hasColumn('bills', 'tip_amount_paise')) {
                $table->unsignedBigInteger('tip_amount_paise')->default(0)->after('net_amount_paise');
            }
            if (! Schema::hasColumn('bills', 'unsettled_at')) {
                $table->dateTime('unsettled_at')->nullable()->after('business_date');
            }
            if (! Schema::hasColumn('bills', 'unsettled_reason')) {
                $table->text('unsettled_reason')->nullable()->after('notes');
            }
        });

        // 3. Add waiter and tip fields to pos_held_tabs
        if (Schema::hasTable('pos_held_tabs')) {
            Schema::table('pos_held_tabs', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_held_tabs', 'waiter_id')) {
                    $table->foreignId('waiter_id')->nullable()->after('table_identifier')->constrained('waiters')->nullOnDelete();
                }
                if (! Schema::hasColumn('pos_held_tabs', 'tip_amount_paise')) {
                    $table->unsignedBigInteger('tip_amount_paise')->default(0)->after('net_amount_paise');
                }
            });
        }

        // 4. Ensure Liability account 2040 (Staff Tips & Gratuity Payable) exists
        if (Schema::hasTable('accounts')) {
            $accountExists = DB::table('accounts')->where('code', '2040')->exists();
            if (! $accountExists) {
                DB::table('accounts')->insert([
                    'code' => '2040',
                    'name' => 'Staff Tips & Gratuity Payable',
                    'type' => 'liability',
                    'is_system' => true,
                    'category' => 'Current Liabilities',
                    'description' => 'Tips and gratuities collected for staff bearers and waiters pending disbursement',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pos_held_tabs')) {
            Schema::table('pos_held_tabs', function (Blueprint $table) {
                if (Schema::hasColumn('pos_held_tabs', 'waiter_id')) {
                    $table->dropForeign(['waiter_id']);
                    $table->dropColumn(['waiter_id', 'tip_amount_paise']);
                }
            });
        }

        Schema::table('bills', function (Blueprint $table) {
            if (Schema::hasColumn('bills', 'waiter_id')) {
                $table->dropForeign(['waiter_id']);
                $table->dropColumn([
                    'waiter_id',
                    'table_number',
                    'tip_amount_paise',
                    'unsettled_at',
                    'unsettled_reason',
                ]);
            }
        });

        Schema::dropIfExists('waiters');
    }
};
