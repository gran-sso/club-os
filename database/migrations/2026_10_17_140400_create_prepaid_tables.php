<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('walletable_type', 100);
            $table->unsignedBigInteger('walletable_id');
            $table->bigInteger('balance_paise')->default(0); // Signed integer paise
            $table->unsignedBigInteger('reserved_paise')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 32)->default('active'); // active, frozen, closed
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['walletable_type', 'walletable_id']);
        });

        // Add check constraint for non-negative balance on MySQL
        DB::statement('ALTER TABLE wallet_accounts ADD CONSTRAINT chk_wallet_balance_non_negative CHECK (balance_paise >= 0)');

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
            $table->string('type', 32); // recharge, spend, refund, adjustment
            $table->bigInteger('amount_paise'); // positive for credit/recharge, negative for spend
            $table->bigInteger('balance_after_paise');
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['wallet_account_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('wallet_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->string('status', 32)->default('active'); // active, captured, released
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_reservations');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_accounts');
    }
};
