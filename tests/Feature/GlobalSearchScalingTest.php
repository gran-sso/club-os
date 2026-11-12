<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Communication\Models\MemberNotice;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Models\ContractorSettlementBatch;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\PurchaseReceipt;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Services\ClubGlobalSearchProvider;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\Contractors\ContractorResource;
use App\Filament\Resources\ContractorSettlements\ContractorSettlementBatchResource;
use App\Filament\Resources\CourtBookings\CourtBookingResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\FinancialPeriods\FinancialPeriodResource;
use App\Filament\Resources\Guests\GuestResource;
use App\Filament\Resources\Inventory\InventoryItemResource;
use App\Filament\Resources\JournalHeaders\JournalHeaderResource;
use App\Filament\Resources\MemberNotices\MemberNoticeResource;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\MembershipCategories\MembershipCategoryResource;
use App\Filament\Resources\Purchases\PurchaseReceiptResource;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Filament\Resources\Subscriptions\FacilitySubscriptionResource;
use App\Filament\Resources\Tariffs\TariffResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Livewire\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GlobalSearchScalingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->member = Member::where('status', 'active')->firstOrFail();
    }

    public function test_all_18_resources_have_global_search_configured(): void
    {
        $this->actingAs($this->admin);

        $resources = [
            MemberResource::class,
            GuestResource::class,
            FacilitySubscriptionResource::class,
            MembershipCategoryResource::class,
            RoomReservationResource::class,
            CourtBookingResource::class,
            ShiftResource::class,
            BillResource::class,
            InventoryItemResource::class,
            TariffResource::class,
            PurchaseReceiptResource::class,
            ContractorResource::class,
            ContractorSettlementBatchResource::class,
            ExpenseResource::class,
            BankAccountResource::class,
            JournalHeaderResource::class,
            FinancialPeriodResource::class,
            FacilityResource::class,
            \App\Filament\Resources\Suppliers\SupplierResource::class,
            \App\Filament\Resources\Users\UserResource::class,
            \App\Filament\Resources\Accounts\AccountResource::class,
            MemberNoticeResource::class,
        ];

        foreach ($resources as $resource) {
            $this->assertTrue(
                $resource::canGloballySearch(),
                "Resource {$resource} must be globally searchable."
            );
            $this->assertNotEmpty(
                $resource::getGloballySearchableAttributes(),
                "Resource {$resource} must define globally searchable attributes."
            );
        }
    }

    public function test_club_global_search_provider_returns_navigation_screens(): void
    {
        // 1. When acting as Cashier, POS is returned
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->actingAs($cashier);
        $provider = new ClubGlobalSearchProvider();

        $posResults = $provider->getResults('pos');
        $this->assertNotNull($posResults);
        $categories = $posResults->getCategories();
        $this->assertTrue($categories->has('Operational Screens & Quick Links'));
        $posItems = collect($categories->get('Operational Screens & Quick Links'));
        $this->assertTrue($posItems->contains(fn ($res) => str_contains($res->title, 'Counter POS')));

        // 2. When acting as Admin, Counter POS is hidden from navigation screens
        $this->actingAs($this->admin);
        $adminPosResults = $provider->getResults('pos');
        if ($adminPosResults && $adminPosResults->getCategories()->has('Operational Screens & Quick Links')) {
            $adminPosItems = collect($adminPosResults->getCategories()->get('Operational Screens & Quick Links'));
            $this->assertFalse($adminPosItems->contains(fn ($res) => str_contains($res->title, 'Counter POS')));
        }

        // 3. Search for Reports as Admin
        $reportResults = $provider->getResults('reports');
        $this->assertNotNull($reportResults);
        $reportCategories = $reportResults->getCategories();
        $this->assertTrue($reportCategories->has('Operational Screens & Quick Links'));
        $reportItems = collect($reportCategories->get('Operational Screens & Quick Links'));
        $this->assertTrue($reportItems->contains(fn ($res) => str_contains($res->title, 'Executive Reporting Hub')));

        // 4. Search for Guest Rooms
        $roomResults = $provider->getResults('guest rooms');
        $this->assertNotNull($roomResults);
        $roomCategories = $roomResults->getCategories();
        $this->assertTrue($roomCategories->has('Operational Screens & Quick Links'));
        $roomItems = collect($roomCategories->get('Operational Screens & Quick Links'));
        $this->assertTrue($roomItems->contains(fn ($res) => str_contains($res->title, 'Guest Rooms (101–105)')));
    }

    public function test_member_resource_search_by_name_phone_and_details(): void
    {
        $this->actingAs($this->admin);

        // Search by Member First Name
        $results = MemberResource::getGlobalSearchResults($this->member->person->first_name);
        $this->assertNotEmpty($results);
        $first = $results->first();
        $this->assertStringContainsString($this->member->membership_number, $first->title);
        $this->assertArrayHasKey('Phone', $first->details);
        $this->assertArrayHasKey('Wallet', $first->details);
        $this->assertArrayHasKey('Category', $first->details);

        // Search by Membership Number
        $numResults = MemberResource::getGlobalSearchResults($this->member->membership_number);
        $this->assertNotEmpty($numResults);

        // Search by Phone if present
        if ($this->member->phone) {
            $phoneResults = MemberResource::getGlobalSearchResults(substr($this->member->phone, -6));
            $this->assertNotEmpty($phoneResults);
        }
    }

    public function test_guest_resource_search(): void
    {
        $this->actingAs($this->admin);

        $guest = Guest::with('person')->first() ?? Guest::create([
            'person_id' => Person::create([
                'first_name' => 'Siddharth',
                'last_name' => 'Menon',
                'phone' => '+91 99950 12345',
            ])->id,
            'sponsor_type' => Member::class,
            'sponsor_id' => $this->member->id,
            'status' => 'registered',
            'visit_date' => now()->toDateString(),
        ]);

        $searchTerm = $guest->person?->first_name ?? 'Guest';
        $results = GuestResource::getGlobalSearchResults($searchTerm);
        $this->assertNotEmpty($results);
        $this->assertStringContainsString($guest->name, $results->first()->title);
        $this->assertArrayHasKey('Sponsor', $results->first()->details);
    }

    public function test_bill_resource_search(): void
    {
        $this->actingAs($this->admin);

        $counter = Counter::first() ?? Counter::create([
            'name' => 'Main POS',
            'code' => 'POS-1',
            'department_code' => 'bar',
            'is_active' => true,
        ]);

        $bill = Bill::first() ?? Bill::create([
            'bill_number' => 'BILL-SEARCH-999',
            'counter_id' => $counter->id,
            'department_code' => 'bar',
            'business_date' => today(),
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'gross_amount_paise' => 50000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 2500,
            'net_amount_paise' => 52500,
            'paid_amount_paise' => 52500,
            'idempotency_key' => 'IDEM-BILL-SEARCH-999',
            'status' => 'paid',
            'created_by_user_id' => $this->admin->id,
        ]);

        $results = BillResource::getGlobalSearchResults($bill->bill_number);
        $this->assertNotEmpty($results);
        $this->assertStringContainsString($bill->bill_number, $results->first()->title);
        $this->assertArrayHasKey('Amount', $results->first()->details);
    }

    public function test_room_reservation_search(): void
    {
        $this->actingAs($this->admin);

        $room = Facility::where('type', 'room')->firstOrFail();
        $reservation = RoomReservation::first() ?? RoomReservation::create([
            'reservation_number' => 'RES-SRCH-101',
            'facility_id' => $room->id,
            'sponsor_member_id' => $this->member->id,
            'payer_type' => Member::class,
            'payer_id' => $this->member->id,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
            'rate_per_night_paise' => 250000,
            'total_charge_paise' => 500000,
            'paid_amount_paise' => 500000,
            'status' => 'confirmed',
            'created_by_user_id' => $this->admin->id,
        ]);

        $results = RoomReservationResource::getGlobalSearchResults($reservation->reservation_number);
        $this->assertNotEmpty($results);
        $this->assertStringContainsString($reservation->reservation_number, $results->first()->title);
        $this->assertArrayHasKey('Guest / Member', $results->first()->details);
        $this->assertArrayHasKey('Dates', $results->first()->details);
    }

    public function test_inventory_and_tariff_search(): void
    {
        $this->actingAs($this->admin);

        $item = InventoryItem::first() ?? InventoryItem::create([
            'sku' => 'BEV-TEST-ITEM',
            'name' => 'Premium Sparkling Water',
            'barcode' => '890987654321',
            'department_code' => 'bar',
            'category' => 'beverage',
            'base_unit' => 'bottle',
            'current_stock_qty' => 50,
            'current_avg_cost_paise' => 4000,
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        $results = InventoryItemResource::getGlobalSearchResults($item->name);
        $this->assertNotEmpty($results);
        $this->assertStringContainsString($item->name, $results->first()->title);
        $this->assertArrayHasKey('Stock on Hand', $results->first()->details);

        // Tariff Search
        $tariff = Tariff::first();
        if ($tariff) {
            $tResults = TariffResource::getGlobalSearchResults($tariff->name);
            $this->assertNotEmpty($tResults);
            $this->assertStringContainsString($tariff->name, $tResults->first()->title);
            $this->assertArrayHasKey('Rate', $tResults->first()->details);
        }
    }

    public function test_finance_resources_search(): void
    {
        $this->actingAs($this->admin);

        // Bank Account
        $bank = BankAccount::first();
        if ($bank) {
            $bankResults = BankAccountResource::getGlobalSearchResults($bank->account_name);
            $this->assertNotEmpty($bankResults);
            $this->assertStringContainsString($bank->account_name, $bankResults->first()->title);
            $this->assertArrayHasKey('A/C #', $bankResults->first()->details);
        }

        // Expense
        $account = \App\Domain\Finance\Models\Account::firstOrFail();
        $expense = Expense::first() ?? Expense::create([
            'expense_number' => 'EXP-SRCH-001',
            'expense_date' => today(),
            'payee' => 'Palakkad Electrical Supplies',
            'document_reference' => 'INV-8899',
            'account_id' => $account->id,
            'amount_paise' => 450000,
            'status' => 'posted',
            'created_by_user_id' => $this->admin->id,
        ]);

        $expResults = ExpenseResource::getGlobalSearchResults($expense->payee);
        $this->assertNotEmpty($expResults);
        $this->assertStringContainsString($expense->expense_number, $expResults->first()->title);
        $this->assertArrayHasKey('Amount', $expResults->first()->details);
    }

    public function test_dashboard_does_not_contain_redundant_search_widget(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin');
        $response->assertSuccessful();
        $response->assertDontSee('Club-Wide Global Search &amp; Command Hub', false);
    }

    public function test_shift_resource_and_livewire_global_search_with_enum_statuses(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        $counter = Counter::first() ?? Counter::create([
            'name' => 'Main Bar POS',
            'code' => 'BAR-1',
            'department_code' => 'bar',
            'is_active' => true,
        ]);

        Shift::create([
            'shift_number' => 'SHF-SRCH-OPEN',
            'counter_id' => $counter->id,
            'user_id' => $this->admin->id,
            'business_date' => today(),
            'opened_at' => now(),
            'opening_float_paise' => 100000,
            'status' => ShiftStatus::OPEN,
        ]);

        Shift::create([
            'shift_number' => 'SHF-SRCH-CLOSED',
            'counter_id' => $counter->id,
            'user_id' => $this->admin->id,
            'business_date' => today()->subDay(),
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay()->addHours(4),
            'opening_float_paise' => 100000,
            'closing_cash_counted_paise' => 100000,
            'expected_cash_paise' => 100000,
            'discrepancy_paise' => 0,
            'status' => ShiftStatus::CLOSED,
        ]);

        $results = ShiftResource::getGlobalSearchResults('SHF-SRCH');
        $this->assertCount(2, $results);
        $statuses = $results->map(fn ($r) => $r->details['Status'] ?? null)->sort()->values()->all();
        $this->assertSame(['Closed', 'Open'], $statuses);

        Livewire::actingAs($this->admin)
            ->test(GlobalSearch::class)
            ->set('search', 'SHF-SRCH')
            ->assertSee('Shift #SHF-SRCH-OPEN')
            ->assertSee('Shift #SHF-SRCH-CLOSED');

        Livewire::actingAs($this->admin)
            ->test(GlobalSearch::class)
            ->set('search', 'a')
            ->assertSuccessful();
    }

    public function test_global_search_handles_missing_permissions_and_non_admin_roles(): void
    {
        Filament::setCurrentPanel('admin');

        MemberNotice::create([
            'title' => 'Club Maintenance Bulletin',
            'content' => 'Pool closed Friday morning.',
            'department' => 'admin',
            'priority' => 'info',
            'target_type' => 'all',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        // Simulate a deployment where a newly introduced permission was not yet seeded in DB
        Permission::where('name', 'notices.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();

        Livewire::actingAs($cashier)
            ->test(GlobalSearch::class)
            ->set('search', 'Club Maintenance')
            ->assertSuccessful();

        Livewire::actingAs($this->admin)
            ->test(GlobalSearch::class)
            ->set('search', 'Club Maintenance')
            ->assertSee('Club Maintenance Bulletin');
    }

    public function test_settings_quick_link_points_to_valid_settings_route(): void
    {
        $this->actingAs($this->admin);
        $provider = new ClubGlobalSearchProvider();

        $results = $provider->getResults('settings');
        $this->assertNotNull($results);

        $quickLinks = collect($results->getCategories()->get('Operational Screens & Quick Links'));
        $settingsResult = $quickLinks->first(fn ($res) => str_contains($res->title, 'Club System Settings'));

        $this->assertNotNull($settingsResult);
        $this->assertSame(url('/admin/settings'), $settingsResult->url);
        $this->get('/admin/settings')->assertSuccessful();
    }
}
