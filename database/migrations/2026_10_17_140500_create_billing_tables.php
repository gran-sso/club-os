<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_number', 50)->unique();
            $table->foreignId('counter_id')->constrained('counters');
            $table->foreignId('user_id')->constrained('users');
            $table->date('business_date');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('opening_float_paise')->default(0);
            $table->unsignedBigInteger('closing_cash_counted_paise')->nullable();
            $table->unsignedBigInteger('expected_cash_paise')->nullable();
            $table->bigInteger('discrepancy_paise')->nullable();
            $table->string('status', 32)->default('open'); // open, closed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['counter_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('business_date');
        });

        Schema::create('shift_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->string('type', 32); // float_in, transfer_out, cash_drop
            $table->unsignedBigInteger('amount_paise');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 50)->unique();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('customer_type', 100); // Member or Guest
            $table->unsignedBigInteger('customer_id');
            $table->string('department_code', 32);
            $table->string('status', 32)->default('draft'); // draft, paid, reversed, refunded
            $table->unsignedBigInteger('gross_amount_paise')->default(0);
            $table->unsignedBigInteger('discount_amount_paise')->default(0);
            $table->unsignedBigInteger('tax_amount_paise')->default(0);
            $table->unsignedBigInteger('net_amount_paise')->default(0);
            $table->unsignedBigInteger('paid_amount_paise')->default(0);
            $table->string('idempotency_key', 100)->unique();
            $table->string('idempotency_hash', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['customer_type', 'customer_id']);
            $table->index(['department_code', 'status']);
            $table->index('created_at');
        });

        Schema::create('bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->string('department_code', 32);
            $table->string('item_type', 50); // tariff, inventory_serving, room_charge, etc.
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_name', 150);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->unsignedBigInteger('unit_price_paise');
            $table->decimal('tax_percent', 5, 2)->default(0.00);
            $table->unsignedBigInteger('tax_amount_paise')->default(0);
            $table->unsignedBigInteger('line_total_paise');
            $table->timestamps();

            $table->index('bill_id');
        });

        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('customer_type', 100);
            $table->unsignedBigInteger('customer_id');
            $table->string('tender_type', 32); // cash, digital, prepaid
            $table->unsignedBigInteger('amount_paise');
            $table->string('provider_name', 50)->default('cashier_manual'); // cashier_manual, upi, card_terminal
            $table->string('provider_reference', 100)->nullable();
            $table->string('provider_status', 32)->default('confirmed'); // confirmed, pending, failed
            $table->string('idempotency_key', 100)->unique();
            $table->dateTime('confirmed_at');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['customer_type', 'customer_id']);
            $table->index(['tender_type', 'provider_status']);
            $table->index('confirmed_at');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_receipt_id')->constrained('payment_receipts')->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->timestamps();

            $table->index(['payment_receipt_id', 'bill_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 50)->unique();
            $table->foreignId('bill_id')->constrained('bills');
            $table->foreignId('payment_receipt_id')->nullable()->constrained('payment_receipts');
            $table->unsignedBigInteger('amount_paise');
            $table->string('tender_type', 32);
            $table->string('reason', 255);
            $table->foreignId('approved_by_user_id')->constrained('users');
            $table->string('idempotency_key', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('bill_lines');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('shift_cash_movements');
        Schema::dropIfExists('shifts');
    }
};
