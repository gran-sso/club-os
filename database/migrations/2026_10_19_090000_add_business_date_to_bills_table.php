<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('status')->index();
        });

        // Backfill existing bills with date from created_at
        DB::statement("UPDATE bills SET business_date = DATE(created_at) WHERE business_date IS NULL");
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex(['business_date']);
            $table->dropColumn('business_date');
        });
    }
};
