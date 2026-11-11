<?php

use App\Domain\Shared\Security\ClubPermissionRegistry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Register granular form/screen permissions idempotently and attach newly
     * introduced permissions to existing built-in roles without overwriting
     * customized permissions.
     */
    public function up(): void
    {
        ClubPermissionRegistry::syncMigrationDefaults();
    }

    public function down(): void
    {
        // Intentionally preserve permissions on rollback to avoid breaking live role bindings.
    }
};

