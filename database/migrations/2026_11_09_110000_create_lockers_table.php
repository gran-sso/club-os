<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lockers', function (Blueprint $table) {
            $table->id();
            $table->string('locker_number', 32)->unique();
            $table->string('zone', 64)->default('Badminton Hall');
            $table->string('status', 32)->default('available'); // available, occupied, maintenance
            $table->unsignedBigInteger('deposit_amount_paise')->default(0);
            $table->foreignId('assigned_member_id')->nullable()->constrained('members', 'id', 'fk_lockers_member')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->date('allocated_until')->nullable();
            $table->string('key_number', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'zone'], 'idx_lockers_status_zone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lockers');
    }
};

