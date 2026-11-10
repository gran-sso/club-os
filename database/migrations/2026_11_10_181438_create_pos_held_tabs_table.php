<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pos_held_tabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('counter_id')->constrained('counters')->cascadeOnDelete();
            $table->string('tab_number', 32);
            $table->string('customer_type', 32)->default('member'); // 'member' or 'room_guest'
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('room_reservation_id')->nullable()->constrained('room_reservations')->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_identifier'); // e.g. 'MC-001' or 'ROOM-101'
            $table->string('table_identifier')->nullable(); // e.g. 'Table 4', 'Bar Stool 2'
            $table->text('notes')->nullable();
            $table->json('cart_items');
            $table->unsignedBigInteger('gross_amount_paise')->default(0);
            $table->unsignedBigInteger('tax_amount_paise')->default(0);
            $table->unsignedBigInteger('net_amount_paise')->default(0);
            $table->string('status', 20)->default('open'); // 'open', 'settled', 'voided'
            $table->dateTime('settled_at')->nullable();
            $table->foreignId('settled_bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shift_id', 'status']);
            $table->index(['counter_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_held_tabs');
    }
};
