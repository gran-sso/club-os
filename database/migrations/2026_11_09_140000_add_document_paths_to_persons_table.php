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
            $table->string('id_proof_path')->nullable()->after('id_proof_number');
            $table->string('application_form_path')->nullable()->after('id_proof_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['id_proof_path', 'application_form_path']);
        });
    }
};

