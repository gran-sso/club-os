<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MemberService;
use App\Domain\Shared\Models\AuditLog;
use App\Domain\Shared\Models\SystemSetting;
use App\Domain\Shared\Services\AuditLogService;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Pages\SystemSettingsPage;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Tariffs\Pages\CreateTariff;
use App\Filament\Resources\Tariffs\Pages\EditTariff;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditTrailAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected User $accountant;
    protected AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->accountant = User::where('email', 'accountant@malabarclub.local')->firstOrFail();
        $this->auditLogService = app(AuditLogService::class);
    }

    public function test_audit_log_service_creates_structured_entry_and_masks_sensitive_fields(): void
    {
        $log = $this->auditLogService->log(
            category: 'security',
            action: 'auth.test',
            description: 'Testing audit log creation with sensitive values',
            oldValues: ['password' => 'super-secret', 'email' => 'admin@malabarclub.local'],
            newValues: ['password' => 'new-secret', 'email' => 'admin@malabarclub.local', 'remember_token' => 'xyz'],
            user: $this->admin
        );

        $this->assertNotNull($log);
        $this->assertEquals('security', $log->category);
        $this->assertEquals('auth.test', $log->action);
        $this->assertEquals($this->admin->id, $log->user_id);
        $this->assertEquals($this->admin->name, $log->user_name);

        // Sensitive values masked
        $this->assertEquals('***', $log->old_values['password']);
        $this->assertEquals('***', $log->new_values['password']);
        $this->assertEquals('***', $log->new_values['remember_token']);
        $this->assertEquals('admin@malabarclub.local', $log->old_values['email']);
    }

    public function test_audit_log_pruning_removes_records_older_than_configured_retention(): void
    {
        // Set retention to 15 days
        SystemSetting::set('audit_log_retention_days', 15, 'security');

        // Create log from 20 days ago (expired)
        $expiredLog = AuditLog::create([
            'user_name' => 'Old Staff',
            'category' => 'security',
            'action' => 'auth.old',
            'description' => 'Old log from 20 days ago',
            'created_at' => now()->subDays(20),
        ]);

        // Create log from 5 days ago (retained)
        $recentLog = AuditLog::create([
            'user_name' => 'Recent Staff',
            'category' => 'tariffs',
            'action' => 'tariff.recent',
            'description' => 'Recent log from 5 days ago',
            'created_at' => now()->subDays(5),
        ]);

        $deletedCount = $this->auditLogService->pruneExpired();

        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseMissing('audit_logs', ['id' => $expiredLog->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recentLog->id]);

        // Test artisan command: audit:prune --days=3
        $this->artisan('audit:prune', ['--days' => 3])
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $recentLog->id]);
    }

    public function test_system_settings_page_configures_retention_period_and_triggers_manual_pruning(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SystemSettingsPage::class)
            ->set('activeTab', 'audit')
            ->set('auditLogRetentionDays', 45)
            ->call('saveSettings');

        $this->assertEquals(45, (int) SystemSetting::get('audit_log_retention_days'));

        // Validation bounds: min 7, max 365
        Livewire::actingAs($this->admin)
            ->test(SystemSettingsPage::class)
            ->set('activeTab', 'audit')
            ->set('auditLogRetentionDays', 4)
            ->call('saveSettings')
            ->assertHasErrors(['auditLogRetentionDays']);

        // Manual prune trigger
        AuditLog::create([
            'user_name' => 'Stale Record',
            'category' => 'system',
            'action' => 'system.stale',
            'description' => 'Old record for pruning test',
            'created_at' => now()->subDays(50),
        ]);

        Livewire::actingAs($this->admin)
            ->test(SystemSettingsPage::class)
            ->call('pruneAuditLogsNow');

        $this->assertDatabaseMissing('audit_logs', ['description' => 'Old record for pruning test']);
    }

    public function test_auth_events_automatically_create_audit_logs(): void
    {
        // 1. Login event
        event(new Login('web', $this->cashier, false));

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'security',
            'action' => 'auth.login',
            'user_id' => $this->cashier->id,
        ]);

        // 2. Failed login event
        event(new Failed('web', null, ['email' => 'hacker@malabarclub.local', 'password' => 'wrong']));

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'security',
            'action' => 'auth.failed',
        ]);

        // 3. Logout event
        event(new Logout('web', $this->cashier));

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'security',
            'action' => 'auth.logout',
            'user_id' => $this->cashier->id,
        ]);
    }

    public function test_tariff_and_pricing_modifications_generate_audit_log_with_diff(): void
    {
        // 1. Create tariff
        Livewire::actingAs($this->admin)
            ->test(CreateTariff::class)
            ->fillForm([
                'code' => 'AUDIT-TRF-01',
                'name' => 'Audit Premium Mocktail',
                'department_code' => 'bar',
                'rate_paise' => 180.00,
                'tax_percent' => '18.00',
                'effective_from' => '2026-11-13',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tariff = Tariff::where('code', 'AUDIT-TRF-01')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'tariffs',
            'action' => 'tariff.created',
            'auditable_type' => Tariff::class,
            'auditable_id' => $tariff->id,
        ]);

        // 2. Edit tariff rate
        Livewire::actingAs($this->admin)
            ->test(EditTariff::class, ['record' => $tariff->getKey()])
            ->fillForm([
                'rate_paise' => 220.00,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $updateLog = AuditLog::where('category', 'tariffs')
            ->where('action', 'tariff.updated')
            ->where('auditable_id', $tariff->id)
            ->latest()
            ->firstOrFail();

        $this->assertEquals(18000, $updateLog->old_values['rate_paise']);
        $this->assertEquals(22000, $updateLog->new_values['rate_paise']);
    }

    public function test_shift_closed_with_variance_generates_variance_audit_log(): void
    {
        $counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $shiftService = app(ShiftService::class);

        $shift = $shiftService->openShift(
            counterId: $counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 100000 // ₹1,000 float
        );

        // Counted ₹800 -> ₹200 shortage
        $closedShift = $shiftService->closeShift(
            shift: $shift,
            closingCashCountedPaise: 80000,
            notes: 'Cash shortage due to till discrepancy'
        );

        $varianceLog = AuditLog::where('category', 'shifts')
            ->where('action', 'shift.closed_with_variance')
            ->where('auditable_id', $shift->id)
            ->first();

        $this->assertNotNull($varianceLog);
        $this->assertStringContainsString('Shortage', $varianceLog->description);
        $this->assertEquals(-20000, $varianceLog->new_values['variance_paise']);
        $this->assertEquals('Cash shortage due to till discrepancy', $varianceLog->new_values['reconciliation_notes']);
    }

    public function test_role_changes_and_defaults_restoration_generate_governance_logs(): void
    {
        $receptionRole = Role::where('name', 'reception')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(EditRole::class, ['record' => $receptionRole->getRouteKey()])
            ->callAction('reset_defaults');

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'governance',
            'action' => 'role.restored_defaults',
            'auditable_id' => $receptionRole->id,
        ]);
    }

    public function test_member_export_and_profile_updates_generate_audit_logs(): void
    {
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();

        // 1. Export CSV
        app(MemberService::class)->streamMembersCsvExport(
            members: [$member],
            includeKyc: false,
            filename: 'test_export.csv'
        );

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'data_privacy',
            'action' => 'data.exported',
        ]);
    }

    public function test_audit_logs_filament_screen_permissions_and_immutability(): void
    {
        // 1. Admin can view audit logs table
        $this->actingAs($this->admin);
        $this->assertTrue(AuditLogResource::canViewAny());
        $this->assertFalse(AuditLogResource::canCreate());

        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'user_name' => $this->admin->name,
            'user_role' => 'admin',
            'category' => 'security',
            'action' => 'auth.login',
            'description' => 'Test log for table visibility',
            'created_at' => now(),
        ]);

        $this->assertFalse(AuditLogResource::canEdit($log));
        $this->assertFalse(AuditLogResource::canDelete($log));

        Livewire::actingAs($this->admin)
            ->test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$log]);

        // 2. Cashier without audit_logs.view cannot view
        $this->actingAs($this->cashier);
        $this->assertFalse(AuditLogResource::canViewAny());
    }
}

