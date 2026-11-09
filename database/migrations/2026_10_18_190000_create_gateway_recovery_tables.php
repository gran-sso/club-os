<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captured_payments', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name', 50);
            $table->string('provider_reference', 100)->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 10)->default('INR');
            $table->string('status', 32)->default('captured'); // captured, matched, refunded, failed
            $table->nullableMorphs('customer');
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('payment_receipt_id')->nullable()->constrained('payment_receipts')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->string('signature', 255)->nullable();
            $table->dateTime('captured_at');
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['provider_name', 'status']);
            $table->index('captured_at');
        });

        Schema::create('unapplied_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('bill_id')->constrained('bills');
            $table->nullableMorphs('customer');
            $table->string('tender_type', 32);
            $table->unsignedBigInteger('amount_paise');
            $table->string('status', 32)->default('held'); // held, refunded, applied
            $table->string('idempotency_key', 100)->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['shift_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unapplied_payments');
        Schema::dropIfExists('captured_payments');
    }
};
