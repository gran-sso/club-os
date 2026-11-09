<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('type', 32); // asset, liability, equity, revenue, expense
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('journal_headers', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number', 50)->unique();
            $table->date('business_date');
            $table->string('source_type', 50); // recharge, bill, refund, settlement
            $table->unsignedBigInteger('source_id');
            $table->string('description', 255);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Crucial: unique source event prevents double journal posting!
            $table->unique(['source_type', 'source_id']);
            $table->index('business_date');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_header_id')->constrained('journal_headers')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            $table->unsignedBigInteger('debit_paise')->default(0);
            $table->unsignedBigInteger('credit_paise')->default(0);
            $table->string('narration', 255)->nullable();
            $table->string('department_code', 32)->nullable();
            $table->timestamps();

            $table->index(['journal_header_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_headers');
        Schema::dropIfExists('accounts');
    }
};
