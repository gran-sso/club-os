<?php

namespace Tests\Feature;

use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();

        RateLimiter::clear('login:staff:cashier1|127.0.0.1');
        RateLimiter::clear('login:member:mc-001|127.0.0.1');
        RateLimiter::clear('activate:member:127.0.0.1');
    }

    public function test_security_headers_middleware_is_attached_to_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
    }

    public function test_staff_login_throttling_locks_out_after_consecutive_failed_attempts(): void
    {
        $ip = '127.0.0.1';
        $throttleKey = 'login:staff:cashier1|' . $ip;
        RateLimiter::clear($throttleKey);

        // 5 failed login attempts
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->from('/login')->post('/login', [
                'login' => 'cashier1',
                'password' => 'wrong-password-' . $i,
            ]);
            $response->assertSessionHasErrors('login');
        }

        // 6th attempt should be blocked by rate limiter
        $blockedResponse = $this->from('/login')->post('/login', [
            'login' => 'cashier1',
            'password' => 'wrong-password-6',
        ]);

        $blockedResponse->assertSessionHasErrors(['login']);
        $error = session('errors')->first('login');
        $this->assertStringContainsString('Too many failed login attempts', $error);

        // Verify security audit logs
        $failedAudit = AuditLog::where('action', 'auth.failed')
            ->where('category', 'security')
            ->latest()
            ->first();
        $this->assertNotNull($failedAudit);

        $throttledAudit = AuditLog::where('action', 'auth.throttled')
            ->where('category', 'security')
            ->latest()
            ->first();
        $this->assertNotNull($throttledAudit);
        $this->assertStringContainsString('Rate limit triggered', $throttledAudit->description);
    }

    public function test_member_login_throttling_locks_out_after_consecutive_failed_attempts(): void
    {
        $ip = '127.0.0.1';
        $throttleKey = 'login:member:mc-001|' . $ip;
        RateLimiter::clear($throttleKey);

        // 5 failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->from('/member/login')->post('/member/login', [
                'login' => 'MC-001',
                'password' => '999' . $i,
            ]);
            $response->assertSessionHasErrors('password');
        }

        // 6th attempt should be blocked by rate limiter
        $blockedResponse = $this->from('/member/login')->post('/member/login', [
            'login' => 'MC-001',
            'password' => '9996',
        ]);

        $blockedResponse->assertSessionHasErrors(['login']);
        $error = session('errors')->first('login');
        $this->assertStringContainsString('Too many failed login attempts', $error);

        // Verify audit log
        $throttledAudit = AuditLog::where('action', 'auth.throttled')
            ->where('category', 'security')
            ->where('description', 'like', '%member portal%')
            ->latest()
            ->first();
        $this->assertNotNull($throttledAudit);
    }

    public function test_setup_wizard_is_locked_when_installed_lock_exists(): void
    {
        $lockFile = storage_path('installed.lock');
        File::put($lockFile, json_encode(['installed_at' => now()->toIso8601String()]));

        try {
            $response = $this->get('/setup-club');
            $response->assertStatus(403);

            $executeResponse = $this->post('/setup-club', [
                'token' => 'malabar-club-setup-dev',
                'unlock' => 1,
            ]);
            $executeResponse->assertStatus(403);
        } finally {
            if (File::exists($lockFile)) {
                File::delete($lockFile);
            }
        }
    }
}

