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
        if (Schema::hasTable('bills')) {
            Schema::table('bills', function (Blueprint $table) {
                if (! Schema::hasColumn('bills', 'accompanying_guest_name')) {
                    $table->string('accompanying_guest_name', 150)->nullable()->after('table_number');
                }
            });
        }

        if (Schema::hasTable('pos_held_tabs')) {
            Schema::table('pos_held_tabs', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_held_tabs', 'accompanying_guest_name')) {
                    $table->string('accompanying_guest_name', 150)->nullable()->after('table_identifier');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bills')) {
            Schema::table('bills', function (Blueprint $table) {
                if (Schema::hasColumn('bills', 'accompanying_guest_name')) {
                    $table->dropColumn('accompanying_guest_name');
                }
            });
        }

        if (Schema::hasTable('pos_held_tabs')) {
            Schema::table('pos_held_tabs', function (Blueprint $table) {
                if (Schema::hasColumn('pos_held_tabs', 'accompanying_guest_name')) {
                    $table->dropColumn('accompanying_guest_name');
                }
            });
        }
    }
};

