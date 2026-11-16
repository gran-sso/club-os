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
        Schema::table('persons', function (Blueprint $table) {
            $table->string('title', 20)->nullable()->after('id');
            $table->date('date_of_birth')->nullable()->after('last_name');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('blood_group', 10)->nullable()->after('gender');
            $table->string('profession', 100)->nullable()->after('email');
            $table->string('designation', 100)->nullable()->after('profession');
            $table->string('organization', 150)->nullable()->after('designation');
            $table->string('address_line1', 255)->nullable()->after('organization');
            $table->string('address_line2', 255)->nullable()->after('address_line1');
            $table->string('city', 100)->nullable()->after('address_line2');
            $table->string('state', 100)->nullable()->default('Kerala')->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('emergency_contact_name', 100)->nullable()->after('postal_code');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('proposer_member_id')
                ->nullable()
                ->after('membership_category_id')
                ->constrained('members')
                ->nullOnDelete();

            $table->foreignId('seconder_member_id')
                ->nullable()
                ->after('proposer_member_id')
                ->constrained('members')
                ->nullOnDelete();

            $table->date('wedding_anniversary')->nullable()->after('valid_until');
            $table->string('vehicle_numbers', 255)->nullable()->after('wedding_anniversary');
            $table->string('committee_role', 100)->nullable()->after('vehicle_numbers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['proposer_member_id']);
            $table->dropForeign(['seconder_member_id']);
            $table->dropColumn([
                'proposer_member_id',
                'seconder_member_id',
                'wedding_anniversary',
                'vehicle_numbers',
                'committee_role',
            ]);
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'date_of_birth',
                'gender',
                'blood_group',
                'profession',
                'designation',
                'organization',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};

