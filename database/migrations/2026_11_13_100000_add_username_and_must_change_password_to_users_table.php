<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 50)->nullable()->unique()->after('name');
            }
            if (Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->change();
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            }
        });

        // Backfill clean usernames for existing users based on email or name
        $existingUsers = DB::table('users')->select('id', 'name', 'email', 'username')->get();
        $usedUsernames = [];

        foreach ($existingUsers as $u) {
            if (! empty($u->username)) {
                $usedUsernames[$u->username] = true;
                continue;
            }

            // Derive candidate slug from email local part or name
            if (! empty($u->email) && str_contains($u->email, '@')) {
                $base = strtolower(explode('@', $u->email)[0]);
            } else {
                $base = strtolower(Str::slug($u->name, '.'));
            }

            // Sanitize: keep only lowercase alphanumeric, dots, hyphens, and underscores
            $candidate = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'user';
            $candidate = substr($candidate, 0, 40);

            // Ensure uniqueness
            $finalUsername = $candidate;
            $counter = 1;
            while (isset($usedUsernames[$finalUsername]) || DB::table('users')->where('username', $finalUsername)->where('id', '!=', $u->id)->exists()) {
                $finalUsername = $candidate . $counter;
                $counter++;
            }

            $usedUsernames[$finalUsername] = true;
            DB::table('users')->where('id', $u->id)->update(['username' => $finalUsername]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'must_change_password', 'password_changed_at']);
        });
    }
};

