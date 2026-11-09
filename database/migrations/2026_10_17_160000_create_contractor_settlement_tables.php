<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Contractors (Food / Kitchen catering partner)
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('department_code')->default('food');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->decimal('commission_rate_percent', 5, 2)->default(0); // 0% commission default per D15
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Contractor Settlement Batches (daily cutoff and payout)
        Schema::create('contractor_settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->foreignId('contractor_id')->constrained('contractors', 'id', 'fk_csb_contractor');
            $table->date('business_date'); // date of food sales being settled
            $table->timestamp('cutoff_at');
            $table->unsignedBigInteger('total_food_sales_gross_paise')->default(0);
            $table->unsignedBigInteger('total_food_sales_tax_paise')->default(0);
            $table->unsignedBigInteger('total_food_sales_net_paise')->default(0);
            $table->bigInteger('total_adjustments_paise')->default(0); // recoveries from post-settlement refunds (signed)
            $table->unsignedBigInteger('net_payable_paise')->default(0);
            $table->unsignedBigInteger('paid_amount_paise')->default(0);
            $table->unsignedBigInteger('residual_payable_paise')->default(0);
            $table->string('status')->default('draft'); // draft, approved, paid, partially_paid, locked
            $table->string('payment_reference')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', 'id', 'fk_csb_approver');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['contractor_id', 'business_date'], 'uq_csb_contractor_date');
        });

        // 3. Settlement Allocations (1-to-1 link to paid Bill Lines, protecting against duplicate batching)
        Schema::create('contractor_settlement_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_batch_id')->constrained('contractor_settlement_batches', 'id', 'fk_csa_batch')->cascadeOnDelete();
            $table->foreignId('bill_line_id')->unique('uq_csa_line')->constrained('bill_lines', 'id', 'fk_csa_line');
            $table->unsignedBigInteger('line_net_paise');
            $table->string('tender_type'); // cash, digital, prepaid
            $table->string('status')->default('included'); // included, refunded
            $table->timestamps();
        });

        // 4. Contractor Settlement Adjustments (handles refunds occurring after batch was locked/settled)
        Schema::create('contractor_settlement_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors', 'id', 'fk_cadj_contractor');
            $table->foreignId('original_batch_id')->nullable()->constrained('contractor_settlement_batches', 'id', 'fk_cadj_orig_batch');
            $table->foreignId('applied_batch_id')->nullable()->constrained('contractor_settlement_batches', 'id', 'fk_cadj_app_batch');
            $table->foreignId('bill_line_id')->nullable()->constrained('bill_lines', 'id', 'fk_cadj_line');
            $table->string('type')->default('post_settlement_refund');
            $table->integer('amount_paise'); // negative for deductions
            $table->string('reason');
            $table->boolean('is_applied')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users', 'id', 'fk_cadj_user');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_settlement_adjustments');
        Schema::dropIfExists('contractor_settlement_allocations');
        Schema::dropIfExists('contractor_settlement_batches');
        Schema::dropIfExists('contractors');
    }
};
