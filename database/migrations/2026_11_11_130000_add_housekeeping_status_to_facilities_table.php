<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('facilities', 'housekeeping_status')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->string('housekeeping_status', 32)->default('clean')->after('is_active');
            });
        }

        // Backfill any room whose most recent check-out remains dirty and has no active check-in
        $rooms = DB::table('facilities')->where('type', 'room')->pluck('id');
        foreach ($rooms as $roomId) {
            $hasCheckedIn = DB::table('room_reservations')
                ->where('facility_id', $roomId)
                ->where('status', 'checked_in')
                ->exists();

            if ($hasCheckedIn) {
                continue;
            }

            $latestCheckedOut = DB::table('room_reservations')
                ->where('facility_id', $roomId)
                ->where('status', 'checked_out')
                ->orderByDesc('checked_out_at')
                ->orderByDesc('id')
                ->first();

            if ($latestCheckedOut && $latestCheckedOut->housekeeping_status === 'dirty') {
                DB::table('facilities')
                    ->where('id', $roomId)
                    ->update(['housekeeping_status' => 'dirty']);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('facilities', 'housekeeping_status')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->dropColumn('housekeeping_status');
            });
        }
    }
};

