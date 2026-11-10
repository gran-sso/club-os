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
        Schema::create('member_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('department')->default('admin'); // 'admin', 'accounts', 'sports', 'food', 'reception'
            $table->string('priority')->default('info'); // 'info', 'reminder', 'urgent', 'event'
            $table->string('target_type')->default('all'); // 'all', 'category', 'dues_pending', 'individual'
            $table->foreignId('target_category_id')->nullable()->constrained('membership_categories')->nullOnDelete();
            $table->foreignId('target_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('is_banner')->default(false); // Prominent sticky banner at top of member dashboard
            $table->dateTime('published_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('send_whatsapp')->default(false);
            $table->boolean('send_sms')->default(false);
            $table->dateTime('dispatched_whatsapp_at')->nullable();
            $table->dateTime('dispatched_sms_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'published_at', 'expires_at']);
            $table->index('target_type');
        });

        Schema::create('member_notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained('member_notices')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->dateTime('read_at')->useCurrent();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            $table->unique(['notice_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_notice_reads');
        Schema::dropIfExists('member_notices');
    }
};
