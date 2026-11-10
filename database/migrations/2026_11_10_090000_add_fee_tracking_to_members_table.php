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
        Schema::table('members', function (Blueprint $table) {
            $table->string('fee_status', 32)->default('pending')->after('status'); // paid, pending, waived
            $table->timestamp('last_fee_paid_at')->nullable()->after('fee_status');
            $table->string('last_fee_receipt_number', 50)->nullable()->after('last_fee_paid_at');
        });

        // Initialize pre-existing members with active validity as paid
        DB::table('members')
            ->whereNotNull('valid_until')
            ->where('status', 'active')
            ->update([
                'fee_status' => 'paid',
                'last_fee_paid_at' => DB::raw('COALESCE(joined_at, NOW())'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['fee_status', 'last_fee_paid_at', 'last_fee_receipt_number']);
        });
    }
};

