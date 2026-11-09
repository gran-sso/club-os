<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name', 150); // e.g. "SBI Main Operational Current A/c"
            $table->string('bank_name', 100);    // e.g. "State Bank of India"
            $table->string('branch_name', 100);  // e.g. "Vadakkencherry"
            $table->string('account_number', 50)->unique();
            $table->string('account_type', 32)->default('current'); // current, savings, fixed_deposit, escrow
            $table->string('ifsc_code', 20);
            $table->string('upi_id', 100)->nullable();
            $table->foreignId('gl_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedBigInteger('opening_balance_paise')->default(0);
            $table->bigInteger('current_balance_paise')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_primary']);
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->string('type', 32); // cash_deposit, inter_bank_transfer, expense_payout, contractor_payout, withdrawal, adjustment
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('transfer_to_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->date('business_date');
            $table->string('reference_number', 100)->nullable(); // Pay-in slip #, challan #, UTR #
            $table->string('deposited_by', 100)->nullable(); // Staff or depositor name
            $table->string('description', 255);
            $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_account_id', 'business_date']);
            $table->index('type');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};

