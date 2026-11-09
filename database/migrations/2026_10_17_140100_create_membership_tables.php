<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->unsignedBigInteger('annual_fee_paise')->default(0);
            $table->boolean('has_voting_rights')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 25)->nullable()->index();
            $table->string('email', 150)->nullable()->index();
            $table->string('id_proof_type', 50)->nullable();
            $table->string('id_proof_number', 100)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('membership_number', 50)->unique();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('membership_category_id')->constrained('membership_categories');
            $table->string('status', 32)->default('active'); // active, suspended, expired, terminated
            $table->date('joined_at');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_until']);
        });

        Schema::create('dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('relationship', 50); // Spouse, Child, Parent
            $table->boolean('can_spend_wallet')->default(false); // Non-negotiable rule: default false
            $table->timestamps();

            $table->unique(['member_id', 'person_id']);
        });

        Schema::create('affiliated_clubs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('city', 100);
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 25)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('affiliated_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliated_club_id')->constrained('affiliated_clubs')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('home_membership_number', 50);
            $table->date('valid_until')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamps();

            $table->index(['affiliated_club_id', 'home_membership_number'], 'aff_members_club_member_idx');
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_number', 64)->unique(); // Internal token / RFID UID
            $table->string('cardable_type', 100); // App\Domain\Membership\Models\Member or Guest
            $table->unsignedBigInteger('cardable_id');
            $table->string('status', 32)->default('active'); // active, blocked, replaced
            $table->dateTime('issued_at');
            $table->dateTime('blocked_at')->nullable();
            $table->foreignId('replaced_by_card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cardable_type', 'cardable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
        Schema::dropIfExists('affiliated_members');
        Schema::dropIfExists('affiliated_clubs');
        Schema::dropIfExists('dependents');
        Schema::dropIfExists('members');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('membership_categories');
    }
};
