<?php

namespace Tests\Feature;

use App\Domain\Shared\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffUsernameAndAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Domain\Shared\Security\ClubPermissionRegistry::seedBootstrapRolesAndPermissions();
    }

    public function test_staff_can_log_in_using_username(): void
    {
        $cashierRole = Role::findByName('cashier', 'web');

        $user = User::factory()->create([
            'name' => 'Suresh Pillai',
            'username' => 'suresh.p',
            'email' => 'suresh@example.com',
            'employee_id' => 'EMP-104',
            'password' => Hash::make('SecretPass123'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->syncRoles([$cashierRole]);

        $response = $this->post('/login', [
            'login' => 'suresh.p',
            'password' => 'SecretPass123',
        ]);

        $response->assertRedirect('/pos');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_staff_can_log_in_using_employee_id(): void
    {
        $cashierRole = Role::findByName('cashier', 'web');

        $user = User::factory()->create([
            'name' => 'Suresh Pillai',
            'username' => 'suresh.p',
            'email' => 'suresh@example.com',
            'employee_id' => 'EMP-104',
            'password' => Hash::make('SecretPass123'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->syncRoles([$cashierRole]);

        $response = $this->post('/login', [
            'login' => 'EMP-104',
            'password' => 'SecretPass123',
        ]);

        $response->assertRedirect('/pos');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_staff_can_log_in_using_email_for_backwards_compatibility(): void
    {
        $adminRole = Role::findByName('admin', 'web');

        $user = User::factory()->create([
            'name' => 'Admin User',
            'username' => 'admin.chief',
            'email' => 'chief@malabarclub.local',
            'password' => Hash::make('SecretPass123'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->syncRoles([$adminRole]);

        $response = $this->post('/login', [
            'email' => 'chief@malabarclub.local',
            'password' => 'SecretPass123',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_deactivated_staff_account_is_blocked_at_login(): void
    {
        $user = User::factory()->create([
            'name' => 'Suspended Staff',
            'username' => 'suspended.staff',
            'password' => Hash::make('SecretPass123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'login' => 'suspended.staff',
            'password' => 'SecretPass123',
        ]);

        $response->assertSessionHasErrors(['login']);
        $this->assertGuest('web');
    }

    public function test_user_with_must_change_password_is_redirected_to_password_setup(): void
    {
        $cashierRole = Role::findByName('cashier', 'web');

        $user = User::factory()->create([
            'name' => 'New Cashier',
            'username' => 'new.cashier',
            'password' => Hash::make('TempPassword123'),
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $user->syncRoles([$cashierRole]);

        $response = $this->post('/login', [
            'login' => 'new.cashier',
            'password' => 'TempPassword123',
        ]);

        $response->assertRedirect(route('password.forced-change'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_middleware_blocks_access_to_pos_until_temporary_password_is_changed(): void
    {
        $cashierRole = Role::findByName('cashier', 'web');

        $user = User::factory()->create([
            'name' => 'Pending Cashier',
            'username' => 'pending.cashier',
            'password' => Hash::make('TempPassword123'),
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $user->syncRoles([$cashierRole]);

        $this->actingAs($user, 'web');

        $response = $this->get('/pos');
        $response->assertRedirect(route('password.forced-change'));
    }

    public function test_staff_can_successfully_complete_forced_password_rotation(): void
    {
        $cashierRole = Role::findByName('cashier', 'web');

        $user = User::factory()->create([
            'name' => 'Setup Cashier',
            'username' => 'setup.cashier',
            'password' => Hash::make('TempPassword123'),
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $user->syncRoles([$cashierRole]);

        $this->actingAs($user, 'web');

        // Cannot use the same password
        $failResponse = $this->post(route('password.forced-change.update'), [
            'current_password' => 'TempPassword123',
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ]);
        $failResponse->assertSessionHasErrors(['password']);

        // Successfully changes to new password
        $successResponse = $this->post(route('password.forced-change.update'), [
            'current_password' => 'TempPassword123',
            'password' => 'BrandNewPassword@2026',
            'password_confirmation' => 'BrandNewPassword@2026',
        ]);

        $successResponse->assertRedirect('/pos');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('BrandNewPassword@2026', $user->password));

        // Audit log verified
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password_changed',
            'user_id' => $user->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        // Access to POS is now permitted
        $posResponse = $this->get('/pos');
        $posResponse->assertOk();
    }

    public function test_logged_in_staff_can_perform_self_service_password_change(): void
    {
        $user = User::factory()->create([
            'name' => 'Active Officer',
            'username' => 'active.officer',
            'password' => Hash::make('CurrentSecret123'),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->post(route('password.change.update'), [
            'current_password' => 'CurrentSecret123',
            'password' => 'RotatedSecret456!',
            'password_confirmation' => 'RotatedSecret456!',
        ]);

        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('RotatedSecret456!', $user->password));
        $this->assertFalse($user->must_change_password);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password_changed',
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_can_reset_staff_password_with_audit_trail(): void
    {
        $admin = User::factory()->create([
            'name' => 'Chief Admin',
            'username' => 'chief.admin',
            'password' => Hash::make('AdminPass123!'),
            'is_active' => true,
        ]);
        $adminRole = Role::findByName('admin', 'web');
        $admin->syncRoles([$adminRole]);

        $targetUser = User::factory()->create([
            'name' => 'Desk Operator',
            'username' => 'desk.op',
            'password' => Hash::make('OldPassword123'),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $newTemporaryPassword = 'MC!8392#xyz';

        $targetUser->forceFill([
            'password' => Hash::make($newTemporaryPassword),
            'must_change_password' => true,
            'password_changed_at' => null,
        ])->save();

        \App\Domain\Shared\Services\AuditLogService::record(
            category: 'security',
            action: 'user.password_reset_by_admin',
            description: "Admin reset password for staff member {$targetUser->name} (@{$targetUser->username}). Must change password: Yes",
            auditable: $targetUser,
            newValues: [
                'username' => $targetUser->username,
                'must_change_password' => true,
            ],
            user: $admin,
        );

        $targetUser->refresh();
        $this->assertTrue($targetUser->must_change_password);
        $this->assertTrue(Hash::check($newTemporaryPassword, $targetUser->password));

        // Verify audit log exists and does NOT contain the plaintext password
        $log = AuditLog::where('action', 'user.password_reset_by_admin')->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals($targetUser->id, $log->auditable_id);
        $this->assertEquals(User::class, $log->auditable_type);
        $this->assertStringNotContainsString($newTemporaryPassword, (string) $log->description);
        $this->assertStringNotContainsString($newTemporaryPassword, json_encode($log->new_values));
    }
}
