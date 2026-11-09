<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Room Reservations
        Schema::create('room_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_number')->unique();
            $table->foreignId('facility_id')->constrained('facilities', 'id', 'fk_rr_facility');
            $table->foreignId('guest_id')->nullable()->constrained('guests', 'id', 'fk_rr_guest');
            $table->foreignId('sponsor_member_id')->nullable()->constrained('members', 'id', 'fk_rr_sponsor');
            $table->string('payer_type')->nullable(); // Member or Guest
            $table->unsignedBigInteger('payer_id')->nullable();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedBigInteger('rate_per_night_paise');
            $table->unsignedBigInteger('total_charge_paise');
            $table->unsignedBigInteger('paid_amount_paise')->default(0);
            $table->string('status')->default('reserved'); // reserved, checked_in, checked_out, cancelled
            $table->string('housekeeping_status')->default('clean'); // clean, dirty, maintenance
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'id', 'fk_rr_user');
            $table->timestamps();

            $table->index(['facility_id', 'check_in_date', 'check_out_date'], 'idx_rr_facility_dates');
        });

        // 2. Facility Maintenance Blocks
        Schema::create('facility_maintenance_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities', 'id', 'fk_fmb_facility');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason');
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'id', 'fk_fmb_user');
            $table->timestamps();

            $table->index(['facility_id', 'start_date', 'end_date'], 'idx_fmb_facility_dates');
        });

        // 3. Facility Subscriptions (Gym & Badminton monthly/annual memberships)
        Schema::create('facility_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_number')->unique();
            $table->foreignId('member_id')->constrained('members', 'id', 'fk_sub_member');
            $table->string('facility_code'); // gym, badminton
            $table->foreignId('tariff_id')->constrained('tariffs', 'id', 'fk_sub_tariff');
            $table->string('plan_type'); // monthly, annual
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedBigInteger('total_fee_paise');
            $table->foreignId('payment_receipt_id')->nullable()->constrained('payment_receipts', 'id', 'fk_sub_receipt');
            $table->string('status')->default('active'); // active, expired, cancelled
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'id', 'fk_sub_user');
            $table->timestamps();

            $table->index(['member_id', 'facility_code', 'status'], 'idx_sub_member_facility');
        });

        // 4. Court Bookings (Badminton slots)
        Schema::create('court_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number')->unique();
            $table->foreignId('facility_id')->constrained('facilities', 'id', 'fk_cb_facility');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('member_id')->constrained('members', 'id', 'fk_cb_member');
            $table->boolean('is_plan_covered')->default(false);
            $table->foreignId('bill_line_id')->nullable()->constrained('bill_lines', 'id', 'fk_cb_line');
            $table->string('status')->default('confirmed'); // confirmed, completed, cancelled, no_show
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'id', 'fk_cb_user');
            $table->timestamps();

            $table->unique(['facility_id', 'booking_date', 'start_time'], 'uq_cb_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_bookings');
        Schema::dropIfExists('facility_subscriptions');
        Schema::dropIfExists('facility_maintenance_blocks');
        Schema::dropIfExists('room_reservations');
    }
};
