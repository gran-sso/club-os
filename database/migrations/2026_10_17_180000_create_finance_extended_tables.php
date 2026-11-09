<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expenses & Operating Disbursements
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments', 'id', 'fk_exp_dept');
            $table->foreignId('account_id')->constrained('accounts', 'id', 'fk_exp_acc');
            $table->string('payee');
            $table->string('document_reference')->nullable();
            $table->unsignedBigInteger('amount_paise');
            $table->string('payment_method')->default('bank_transfer'); // cash, bank_transfer, cheque
            $table->string('status')->default('paid'); // draft, approved, paid, void
            $table->date('expense_date');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', 'id', 'fk_exp_approver');
            $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers', 'id', 'fk_exp_journal');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'id', 'fk_exp_creator');
            $table->timestamps();

            $table->index(['expense_date', 'department_id'], 'idx_exp_date_dept');
        });

        // 2. Financial Periods (Period Closing - R04)
        Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_code')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open'); // open, locked, closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users', 'id', 'fk_fp_user');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date', 'status'], 'idx_fp_dates_status');
        });

        // 3. Deferred Revenue & Recognition Schedules (R02 & D14)
        Schema::create('revenue_recognition_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->unique()->constrained('facility_subscriptions', 'id', 'fk_rrs_sub');
            $table->unsignedBigInteger('total_amount_paise');
            $table->unsignedBigInteger('recognised_amount_paise')->default(0);
            $table->date('service_start_date');
            $table->date('service_end_date');
            $table->unsignedInteger('total_days');
            $table->unsignedBigInteger('daily_rate_paise');
            $table->string('status')->default('active'); // active, completed
            $table->timestamps();
        });

        // 4. Daily Revenue Recognition Entries
        Schema::create('revenue_recognition_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('revenue_recognition_schedules', 'id', 'fk_rre_sched');
            $table->date('recognition_date');
            $table->unsignedBigInteger('amount_paise');
            $table->foreignId('journal_header_id')->nullable()->constrained('journal_headers', 'id', 'fk_rre_journal');
            $table->string('status')->default('posted');
            $table->timestamps();

            $table->unique(['schedule_id', 'recognition_date'], 'uq_rre_sched_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_recognition_entries');
        Schema::dropIfExists('revenue_recognition_schedules');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('expenses');
    }
};
