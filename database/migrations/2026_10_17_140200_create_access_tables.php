<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('sponsor_type', 100); // App\Domain\Membership\Models\Member or AffiliatedMember
            $table->unsignedBigInteger('sponsor_id');
            $table->date('visit_date');
            $table->string('status', 32)->default('registered'); // registered, checked_in, departed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sponsor_type', 'sponsor_id']);
            $table->index(['visit_date', 'status']);
        });

        Schema::create('guest_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->string('pass_code', 64)->unique();
            $table->string('facility_code', 32); // bar_card_room, gym, badminton
            $table->unsignedBigInteger('bill_line_id')->nullable(); // linked when paid
            $table->string('status', 32)->default('active'); // active, used, expired, revoked
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->unsignedInteger('entry_count')->default(0);
            $table->unsignedInteger('max_entries')->default(1);
            $table->timestamps();

            $table->index(['guest_id', 'facility_code', 'status']);
            $table->index(['valid_from', 'valid_until']);
        });

        Schema::create('access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('guest_pass_id')->nullable()->constrained('guest_passes')->nullOnDelete();
            $table->string('card_number', 64)->nullable()->index();
            $table->string('facility_code', 32);
            $table->string('event_type', 32); // granted, denied
            $table->string('reason', 255)->nullable();
            $table->string('device_id', 64)->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();
        });

        Schema::create('device_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('device_identifier', 64)->unique();
            $table->string('device_type', 32); // card_reader, biometric, turnstile
            $table->string('facility_code', 32);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_mappings');
        Schema::dropIfExists('access_events');
        Schema::dropIfExists('guest_passes');
        Schema::dropIfExists('guests');
    }
};
