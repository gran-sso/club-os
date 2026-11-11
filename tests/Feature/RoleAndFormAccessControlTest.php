<?php

namespace Tests\Feature;

use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Access\Models\Guest;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Models\ContractorSettlementBatch;
use App\Domain\Contractor\Services\ContractorSettlementService;
use App\Domain\Finance\Models\Account;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Domain\Shared\Security\ClubPermissionRegistry;
use App\Domain\Shared\Services\ClubGlobalSearchProvider;
use App\Domain\Shared\Services\UserLandingService;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Pages\RoomRackPage;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Widgets\ExecutiveStatsOverviewWidget;
use App\Filament\Widgets\FrontDeskPulseWidget;
use App\Livewire\CounterPos;
use App\Models\User;
use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoleAndFormAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $reception;
    protected User $cashier;
    protected User $accountant;
    protected User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductionBootstrapSeeder::class);

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $this->manager = User::create([
            'name' => 'Test Manager',
            'email' => 'manager-rbac@malabarclub.local',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $this->manager->assignRole('manager');

        $this->reception = User::create([
            'name' => 'Test Reception',
            'email' => 'reception-rbac@malabarclub.local',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $this->reception->assignRole('reception');

        $this->cashier = User::create([
            'name' => 'Test Cashier',
            'email' => 'cashier-rbac@malabarclub.local',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $this->cashier->assignRole('cashier');

        $this->accountant = User::create([
            'name' => 'Test Accountant',
            'email' => 'accountant-rbac@malabarclub.local',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $this->accountant->assignRole('accountant');

        $this->auditor = User::create([
            'name' => 'Test Auditor',
            'email' => 'auditor-rbac@malabarclub.local',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $this->auditor->assignRole('auditor');
    }

    private function asStaff(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user, 'web');
    }

    public function test_only_admin_or_users_manage_can_access_roles_and_form_access_resource(): void
    {
        $this->asStaff($this->admin)->get('/admin/roles')->assertSuccessful();
        $this->asStaff($this->admin)->get('/admin/roles/create')->assertSuccessful();

        foreach ([$this->manager, $this->reception, $this->cashier, $this->accountant, $this->auditor] as $staff) {
            $this->asStaff($staff)->get('/admin/roles')->assertForbidden();
            $this->asStaff($staff)->get('/admin/roles/create')->assertForbidden();
        }

        // Granting users.manage explicitly enables access
        $this->manager->givePermissionTo('users.manage');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->asStaff($this->manager)->get('/admin/roles')->assertSuccessful();
    }

    public function test_admin_can_create_custom_role_and_enforce_module_and_readonly_boundaries(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'bar_storekeeper',
                'permission_groups' => [
                    'pos' => [],
                    'frontdesk' => [],
                    'membership' => [],
                    'inventory' => ['stock.view', 'stock.manage'],
                    'procurement' => ['purchases.view', 'purchases.manage', 'suppliers.view'],
                    'finance' => [],
                    'system' => [],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'bar_storekeeper')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('stock.manage'));
        $this->assertTrue($role->hasPermissionTo('purchases.manage'));
        $this->assertTrue($role->hasPermissionTo('suppliers.view'));
        $this->assertFalse($role->hasPermissionTo('suppliers.manage'));
        $this->assertTrue(RoleResource::canDelete($role));

        $storekeeper = User::create([
            'name' => 'Gopal Storekeeper',
            'email' => 'storekeeper@malabarclub.local',
            'password' => Hash::make('Secret@123'),
            'is_active' => true,
        ]);
        $storekeeper->assignRole($role);

        $supplier = Supplier::create([
            'name' => 'Kerala Beverage Corp',
            'code' => 'SUP-BEVCO',
            'is_active' => true,
        ]);

        // Once assigned to a staff user, role cannot be deleted
        $this->assertFalse(RoleResource::canDelete($role->fresh()));

        // Storekeeper lands cleanly in /admin and cannot access POS or unrelated forms
        $this->assertSame(url('/admin'), app(UserLandingService::class)->resolveHomeUrlForUser($storekeeper));
        $this->actingAs($storekeeper)->get('/pos')->assertForbidden();
        $this->actingAs($storekeeper)->get('/admin/inventory/inventory-items')->assertSuccessful();
        $this->actingAs($storekeeper)->get('/admin/purchases/purchase-receipts')->assertSuccessful();
        $this->actingAs($storekeeper)->get('/admin/purchases/purchase-receipts/create')->assertSuccessful();

        // Read-only suppliers.view allows index but blocks create/edit
        $this->actingAs($storekeeper)->get('/admin/suppliers')->assertSuccessful();
        $this->actingAs($storekeeper)->get('/admin/suppliers/create')->assertForbidden();
        $this->actingAs($storekeeper)->get("/admin/suppliers/{$supplier->id}/edit")->assertForbidden();

        // Blocked from unrelated club modules & widgets
        $this->actingAs($storekeeper)->get('/admin/members')->assertForbidden();
        $this->actingAs($storekeeper)->get('/admin/room-rack')->assertForbidden();
        $this->actingAs($storekeeper)->get('/admin/financial-reports')->assertForbidden();
        $this->actingAs($storekeeper)->get('/admin/expenses')->assertForbidden();

        $this->actingAs($storekeeper);
        $this->assertFalse(ExecutiveStatsOverviewWidget::canView());
        $this->assertFalse(FrontDeskPulseWidget::canView());
    }

    public function test_admin_revoking_reception_permissions_immediately_blocks_screens_kyc_search_and_updates_landing(): void
    {
        Storage::fake('local');

        $category = MembershipCategory::create([
            'name' => 'Ordinary RBAC',
            'code' => 'ORD-RBAC-1',
            'annual_fee_paise' => 500000,
            'admission_fee_paise' => 1000000,
        ]);

        $person = Person::create([
            'first_name' => 'Meera',
            'last_name' => 'Menon',
            'phone' => '9847001122',
            'id_proof_path' => 'kyc/test-meera-id.jpg',
        ]);
        Storage::disk('local')->put('kyc/test-meera-id.jpg', 'fake-image-bytes');

        $member = Member::create([
            'person_id' => $person->id,
            'membership_category_id' => $category->id,
            'membership_number' => 'MC-9001',
            'status' => MemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        // Before customization: reception accesses Room Rack, Reservations, and Member KYC
        $this->asStaff($this->reception)->get('/admin/room-rack')->assertSuccessful();
        $this->asStaff($this->reception)->get('/admin/room-reservations')->assertSuccessful();
        $this->asStaff($this->reception)->get("/documents/members/{$member->id}/id_proof.jpg")->assertSuccessful();

        $receptionRole = Role::where('name', 'reception')->firstOrFail();

        // Admin edits Reception role to uncheck rooms + KYC while keeping courts + members.manage
        $this->asStaff($this->admin);
        Livewire::test(EditRole::class, ['record' => $receptionRole->getRouteKey()])
            ->fillForm([
                'permission_groups' => [
                    'pos' => ['counter.operate', 'sales.checkout', 'wallet.recharge'],
                    'frontdesk' => ['courts.view_schedule', 'courts.manage_bookings'],
                    'membership' => ['members.view', 'members.manage', 'categories.view', 'guests.view', 'guests.register', 'subscriptions.view', 'subscriptions.manage', 'notices.manage'],
                    'inventory' => [],
                    'procurement' => [],
                    'finance' => [],
                    'system' => [],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $freshReception = $this->reception->fresh(['roles', 'permissions']);

        // Room Rack, Room Reservations, and Member KYC now return 403 Forbidden
        $this->asStaff($freshReception)->get('/admin/room-rack')->assertForbidden();
        $this->asStaff($freshReception)->get('/admin/room-reservations')->assertForbidden();
        $this->asStaff($freshReception)->get("/documents/members/{$member->id}/id_proof.jpg")->assertForbidden();

        // Court schedule still works, and login redirects safely to /admin/court-schedule instead of 403 room-rack
        $this->asStaff($freshReception)->get('/admin/court-schedule')->assertSuccessful();
        $this->assertSame(url('/admin/court-schedule'), app(UserLandingService::class)->resolveHomeUrlForUser($freshReception));

        // Global search hides Accommodation Deck from Reception
        $this->asStaff($freshReception);
        $results = app(ClubGlobalSearchProvider::class)->getResults('accommodation');
        $screenCategory = $results->getCategories()['Navigation & Club Screens'] ?? collect();
        $this->assertFalse($screenCategory->contains(fn ($r) => str_contains($r->title, 'Accommodation Deck')));

        // Admin clicks Restore Role Defaults -> Reception immediately regains Room Rack & KYC
        $this->asStaff($this->admin);
        Livewire::test(EditRole::class, ['record' => $receptionRole->getRouteKey()])
            ->callAction('reset_defaults');

        $restoredReception = $this->reception->fresh(['roles', 'permissions']);
        $this->asStaff($restoredReception)->get('/admin/room-rack')->assertSuccessful();
        $this->asStaff($restoredReception)->get("/documents/members/{$member->id}/id_proof.jpg")->assertSuccessful();
    }

    public function test_pos_action_permissions_and_cashier_panel_lockout_when_admin_forms_unchecked(): void
    {
        $counter = Counter::create([
            'code' => 'CNT-TEST-RBAC',
            'name' => 'RBAC Counter',
            'department_code' => 'bar',
            'is_active' => true,
        ]);

        $category = MembershipCategory::create([
            'name' => 'Life RBAC',
            'code' => 'LIFE-RBAC-1',
            'annual_fee_paise' => 0,
            'admission_fee_paise' => 5000000,
        ]);

        $person = Person::create(['first_name' => 'Arjun', 'last_name' => 'Nair', 'phone' => '9895001122']);
        $member = Member::create([
            'person_id' => $person->id,
            'membership_category_id' => $category->id,
            'membership_number' => 'MC-9002',
            'status' => MemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        WalletAccount::create([
            'walletable_type' => Member::class,
            'walletable_id' => $member->id,
            'balance_paise' => 0,
            'reserved_paise' => 0,
            'currency' => 'INR',
            'status' => 'active',
        ]);

        $cashierRole = Role::where('name', 'cashier')->firstOrFail();

        // Revoke all Admin panel forms + wallet.recharge from cashier, keeping ONLY counter.operate & sales.checkout
        $cashierRole->syncPermissions(['counter.operate', 'sales.checkout']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $freshCashier = $this->cashier->fresh(['roles', 'permissions']);

        // Cashier can open /pos cleanly, top bar hides Accommodation/Court/Club Admin links, and /admin returns 403
        $posResponse = $this->asStaff($freshCashier)->get('/pos');
        $posResponse->assertSuccessful();
        $posResponse->assertDontSee('Accommodation Deck');
        $posResponse->assertDontSee('Court Schedule');
        $posResponse->assertDontSee('Club Admin');

        $this->asStaff($freshCashier)->get('/admin')->assertForbidden();
        $this->asStaff($freshCashier)->get('/admin/room-rack')->assertForbidden();

        // Attempting wallet recharge without wallet.recharge permission fails safely with zero balance mutation
        $shift = Shift::create([
            'counter_id' => $counter->id,
            'user_id' => $freshCashier->id,
            'shift_number' => 'SHF-RBAC-1',
            'business_date' => today(),
            'status' => 'open',
            'opened_at' => now(),
            'opening_float_paise' => 200000,
        ]);

        Livewire::actingAs($freshCashier)
            ->test(CounterPos::class)
            ->set('selectedMember', $member)
            ->set('rechargeAmount', 1000)
            ->call('rechargeWallet')
            ->assertSet('errorMessage', 'Access denied. Prepaid wallet recharges require wallet.recharge permission.');

        $this->assertSame(0, $member->wallet->fresh()->balance_paise);

        // Revoking counter.operate completely blocks /pos
        $cashierRole->syncPermissions([]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->asStaff($freshCashier->fresh(['roles', 'permissions']))->get('/pos')->assertForbidden();
    }

    public function test_auditor_readonly_boundaries_and_action_level_room_rack_guard(): void
    {
        // Auditor has *.view across finance & bills/shifts, so index routes work cleanly
        $this->actingAs($this->auditor)->get('/admin/bills')->assertSuccessful();
        $this->actingAs($this->auditor)->get('/admin/shifts')->assertSuccessful();
        $this->actingAs($this->auditor)->get('/admin/expenses')->assertSuccessful();
        $this->actingAs($this->auditor)->get('/admin/accounts')->assertSuccessful();
        $this->actingAs($this->auditor)->get('/admin/journal-headers')->assertSuccessful();
        $this->actingAs($this->auditor)->get('/admin/financial-periods')->assertSuccessful();
        $this->actingAs($this->auditor)->get('/admin/tariffs')->assertSuccessful();

        // Auditor is blocked (403) from create/manage forms
        $this->actingAs($this->auditor)->get('/admin/bills/create')->assertForbidden();
        $this->actingAs($this->auditor)->get('/admin/expenses/create')->assertForbidden();
        $this->actingAs($this->auditor)->get('/admin/accounts/create')->assertForbidden();
        $this->actingAs($this->auditor)->get('/admin/journal-headers/create')->assertForbidden();
        $this->actingAs($this->auditor)->get('/admin/financial-periods/create')->assertForbidden();
        $this->actingAs($this->auditor)->get('/admin/tariffs/create')->assertForbidden();

        // A role with rooms.view_rack ONLY (without rooms.manage_reservations) can view the deck, but mutating actions abort 403
        $observerRole = Role::create(['name' => 'deck_observer', 'guard_name' => 'web']);
        $observerRole->givePermissionTo('rooms.view_rack');

        $observer = User::create([
            'name' => 'Deck Observer',
            'email' => 'observer@malabarclub.local',
            'password' => Hash::make('Secret@123'),
            'is_active' => true,
        ]);
        $observer->assignRole($observerRole);

        $this->asStaff($observer)->get('/admin/room-rack')->assertSuccessful();

        Livewire::actingAs($observer)
            ->test(RoomRackPage::class)
            ->call('openNewBookingModal')
            ->assertForbidden();
    }

    public function test_service_layer_enforces_refund_and_contractor_settle_permissions_without_role_bypass(): void
    {
        $category = MembershipCategory::create([
            'name' => 'Member Cat RBAC',
            'code' => 'MEM-RBAC-1',
            'annual_fee_paise' => 100000,
            'admission_fee_paise' => 100000,
        ]);
        $person = Person::create(['first_name' => 'Ravi', 'last_name' => 'Varma', 'phone' => '9447001122']);
        $member = Member::create([
            'person_id' => $person->id,
            'membership_category_id' => $category->id,
            'membership_number' => 'MC-9003',
            'status' => MemberStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $counter = Counter::create([
            'code' => 'CNT-SVC',
            'name' => 'Service Counter',
            'department_code' => 'food',
            'is_active' => true,
        ]);
        $shift = Shift::create([
            'counter_id' => $counter->id,
            'user_id' => $this->cashier->id,
            'shift_number' => 'SHF-SVC-1',
            'business_date' => today(),
            'status' => 'open',
            'opened_at' => now(),
            'opening_float_paise' => 100000,
        ]);

        $bill = Bill::create([
            'bill_number' => 'BIL-RBAC-1',
            'business_date' => today(),
            'customer_type' => Member::class,
            'customer_id' => $member->id,
            'department_code' => 'food',
            'shift_id' => $shift->id,
            'status' => BillStatus::PAID,
            'gross_amount_paise' => 10000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 0,
            'net_amount_paise' => 10000,
            'paid_amount_paise' => 10000,
            'idempotency_key' => 'bil-rbac-1',
            'created_by_user_id' => $this->cashier->id,
        ]);
        $receipt = PaymentReceipt::create([
            'receipt_number' => 'RCT-RBAC-1',
            'bill_id' => $bill->id,
            'customer_type' => Member::class,
            'customer_id' => $member->id,
            'amount_paise' => 10000,
            'tender_type' => TenderType::CASH,
            'shift_id' => $shift->id,
            'idempotency_key' => 'rct-rbac-1',
            'confirmed_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]);

        // Manager loses refunds.approve -> BillingService::refund MUST throw UnauthorizedActionException even though role is 'manager'
        $managerRole = Role::where('name', 'manager')->firstOrFail();
        $managerRole->revokePermissionTo('refunds.approve');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $billingService = app(BillingService::class);
        $this->expectException(UnauthorizedActionException::class);
        $billingService->refund(
            bill: $bill,
            amountPaise: 5000,
            reason: 'Unauthorized refund test',
            approver: $this->manager->fresh(['roles', 'permissions']),
            idempotencyKey: 'ref-unauth-1'
        );
    }

    public function test_accountant_losing_contractor_settle_blocks_batch_approval(): void
    {
        $contractor = Contractor::create([
            'name' => 'Malabar Kitchen RBAC',
            'code' => 'CON-RBAC-1',
            'status' => 'active',
        ]);

        $batch = ContractorSettlementBatch::create([
            'batch_number' => 'CSB-RBAC-1',
            'contractor_id' => $contractor->id,
            'business_date' => today(),
            'cutoff_at' => now(),
            'gross_food_sales_paise' => 50000,
            'club_commission_paise' => 5000,
            'gst_on_commission_paise' => 0,
            'net_payable_paise' => 45000,
            'paid_amount_paise' => 0,
            'residual_payable_paise' => 45000,
            'status' => 'draft',
            'created_by_user_id' => $this->admin->id,
        ]);

        $accountantRole = Role::where('name', 'accountant')->firstOrFail();
        $accountantRole->revokePermissionTo('contractor.settle');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->expectException(UnauthorizedActionException::class);
        app(ContractorSettlementService::class)->approveSettlementBatch(
            $batch,
            $this->accountant->fresh(['roles', 'permissions'])
        );
    }

    public function test_system_role_and_admin_lockout_protections_and_non_overwriting_seeder(): void
    {
        // System roles cannot be deleted even if all users removed
        foreach (ClubPermissionRegistry::SYSTEM_ROLES as $systemRoleName) {
            $role = Role::where('name', $systemRoleName)->firstOrFail();
            $this->actingAs($this->admin);
            $this->assertFalse(RoleResource::canDelete($role));
        }

        // Sole admin cannot remove admin role or deactivate self in EditUser
        $this->actingAs($this->admin);
        Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasFormErrors(['is_active']);

        $cashierRole = Role::where('name', 'cashier')->firstOrFail();
        Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
            ->fillForm([
                'roles' => [$cashierRole->id],
            ])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        // Customize cashier permissions and verify re-running ProductionBootstrapSeeder does NOT overwrite them
        $cashierRole->syncPermissions(['counter.operate']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seed(ProductionBootstrapSeeder::class);

        $cashierRoleAfter = Role::where('name', 'cashier')->firstOrFail();
        $this->assertEquals(['counter.operate'], $cashierRoleAfter->permissions()->pluck('name')->all());

        // Admin role always stays synced to every defined permission
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->assertCount(count(ClubPermissionRegistry::permissionNames()), $adminRole->permissions);
    }
}

