<?php

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
        // 1. Add admission_fee_paise to membership_categories
        Schema::table('membership_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('admission_fee_paise')->default(0)->after('name');
        });

        // 2. Add admission fee tracking to members
        Schema::table('members', function (Blueprint $table) {
            $table->string('admission_fee_status', 32)->default('pending')->after('fee_status'); // paid, pending, waived
            $table->unsignedBigInteger('admission_fee_paise')->default(0)->after('admission_fee_status');
            $table->string('admission_fee_receipt_number', 50)->nullable()->after('admission_fee_paise');
            $table->timestamp('admission_fee_paid_at')->nullable()->after('admission_fee_receipt_number');
        });

        // 3. Populate existing categories with standard admission fees
        DB::table('membership_categories')->where('code', 'ORD')->update(['admission_fee_paise' => 5000000]); // ₹50,000
        DB::table('membership_categories')->where('code', 'LIFE')->update(['admission_fee_paise' => 20000000, 'annual_fee_paise' => 0]); // ₹2,00,000 entry, ₹0 annual
        DB::table('membership_categories')->where('code', 'CORP')->update(['admission_fee_paise' => 50000000, 'annual_fee_paise' => 2500000]); // ₹5,00,000 entry, ₹25,000 annual

        // 4. Mark pre-existing active members as having paid their admission fee
        DB::table('members')
            ->where('status', 'active')
            ->whereNotNull('valid_until')
            ->update([
                'admission_fee_status' => 'paid',
                'admission_fee_paid_at' => DB::raw('COALESCE(joined_at, NOW())'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'admission_fee_status',
                'admission_fee_paise',
                'admission_fee_receipt_number',
                'admission_fee_paid_at',
            ]);
        });

        Schema::table('membership_categories', function (Blueprint $table) {
            $table->dropColumn(['admission_fee_paise']);
        });
    }
};

