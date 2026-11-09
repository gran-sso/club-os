<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 100);
            $table->unsignedBigInteger('aggregate_id');
            $table->json('payload');
            $table->string('status', 32)->default('pending'); // pending, delivered, acknowledged, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'status']);
            $table->index('created_at');
        });

        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 100)->unique();
            $table->string('request_hash', 64);
            $table->string('route', 255)->nullable();
            $table->unsignedSmallInteger('response_code');
            $table->json('response_body');
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique();
            $table->string('type', 50); // members, opening_balances
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('preview_data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 32)->default('preview'); // preview, completed, rejected
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('outbox_messages');
    }
};
