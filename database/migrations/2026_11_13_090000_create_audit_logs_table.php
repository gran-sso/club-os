<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name', 150)->nullable();
                $table->string('user_role', 50)->nullable();
                $table->string('category', 50)->default('system'); // security, governance, tariffs, inventory, members, shifts, facilities, data_privacy, finance
                $table->string('action', 80); // e.g. auth.login, auth.failed, tariff.updated, etc.
                $table->nullableMorphs('auditable'); // auditable_type, auditable_id
                $table->string('description', 255);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['created_at']);
                $table->index(['category', 'created_at']);
                $table->index(['action', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        \App\Domain\Shared\Security\ClubPermissionRegistry::syncMigrationDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

