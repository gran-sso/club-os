<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->reception = User::where('email', 'reception@malabarclub.local')->firstOrFail();
    }

    public function test_admin_can_access_user_management_pages(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/users')->assertSuccessful();
        $this->get('/admin/users/create')->assertSuccessful();
        $this->get(UserResource::getUrl('edit', ['record' => $this->cashier]))->assertSuccessful();
    }

    public function test_non_admin_cannot_access_user_management_pages(): void
    {
        $this->actingAs($this->manager);
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();

        $this->actingAs($this->cashier);
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();

        $this->actingAs($this->reception);
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
    }

    public function test_admin_can_create_staff_user_with_role_and_direct_permissions(): void
    {
        $this->actingAs($this->admin);

        $cashierRole = Role::findByName('cashier');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Gopalan Nair',
                'employee_id' => 'EMP-205',
                'designation' => 'Counter Cashier (Sports Counter)',
                'email' => 'gopalan@malabarclub.local',
                'phone' => '+91 98471 23456',
                'is_active' => true,
                'password' => 'ClubSecret2026!',
                'password_confirmation' => 'ClubSecret2026!',
                'roles' => [$cashierRole->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $newUser = User::where('email', 'gopalan@malabarclub.local')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('Gopalan Nair', $newUser->name);
        $this->assertEquals('EMP-205', $newUser->employee_id);
        $this->assertTrue($newUser->is_active);
        $this->assertTrue(Hash::check('ClubSecret2026!', $newUser->password));
        $this->assertTrue($newUser->hasRole('cashier'));
    }

    public function test_deactivated_staff_cannot_access_panel(): void
    {
        $this->cashier->update(['is_active' => false]);
        $panel = \Filament\Facades\Filament::getPanel('admin');

        $this->assertFalse($this->cashier->canAccessPanel($panel));

        $this->actingAs($this->cashier);
        $response = $this->get('/admin');
        // Panel access check will return 403 or redirect
        $this->assertTrue($response->isForbidden() || $response->isRedirect());
    }

    public function test_admin_cannot_deactivate_self_via_edit_form_or_action(): void
    {
        $this->actingAs($this->admin);

        // 1. Table action attempt to deactivate self
        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callTableAction('toggle_status', $this->admin);

        $this->admin->refresh();
        $this->assertTrue($this->admin->is_active);

        // 2. Edit form attempt
        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $this->admin->id])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save');

        $this->admin->refresh();
        $this->assertTrue($this->admin->is_active);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->actingAs($this->admin);

        $this->assertFalse(UserResource::canDelete($this->admin));
    }

    public function test_user_with_historical_records_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin);

        $counter = Counter::first();
        if (! $counter) {
            $counter = Counter::create([
                'name' => 'Main Bar Counter',
                'code' => 'BAR-01',
                'department_code' => 'bar',
                'is_active' => true,
            ]);
        }

        // Link cashier to a historical shift
        Shift::create([
            'shift_number' => 'SFT-TEST-9999',
            'user_id' => $this->cashier->id,
            'counter_id' => $counter->id,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay()->addHours(8),
            'opening_cash_paise' => 500000,
            'closing_cash_paise' => 500000,
            'expected_cash_paise' => 500000,
            'status' => 'closed',
            'business_date' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($this->cashier->hasHistoricalRecords());

        // Attempt deletion via Livewire table DeleteAction
        Livewire::test(ListUsers::class)
            ->callTableAction(DeleteAction::class, $this->cashier);

        // Record must remain in database
        $this->assertDatabaseHas('users', ['id' => $this->cashier->id]);
    }

    public function test_user_resource_global_search(): void
    {
        $this->actingAs($this->admin);

        // Search by name
        $results = UserResource::getGlobalSearchResults('Jayaprakash');
        $this->assertNotEmpty($results);
        $this->assertStringContainsString('Jayaprakash', $results->first()->title);
        $this->assertArrayHasKey('Role', $results->first()->details);
        $this->assertArrayHasKey('Status', $results->first()->details);

        // Search by email
        $emailResults = UserResource::getGlobalSearchResults('admin@malabarclub');
        $this->assertNotEmpty($emailResults);
        $this->assertStringContainsString('admin@malabarclub', $emailResults->first()->title);
    }
}
