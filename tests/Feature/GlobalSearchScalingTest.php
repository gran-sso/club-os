<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
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
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\MembershipCategories\MembershipCategoryResource;
use App\Filament\Resources\Purchases\PurchaseReceiptResource;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Filament\Resources\Subscriptions\FacilitySubscriptionResource;
use App\Filament\Resources\Tariffs\TariffResource;
use App\Filament\Widgets\GlobalQuickSearchWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        // 4. Search for Accommodation Deck
        $roomResults = $provider->getResults('accommodation deck');
        $this->assertNotNull($roomResults);
        $roomCategories = $roomResults->getCategories();
        $this->assertTrue($roomCategories->has('Operational Screens & Quick Links'));
        $roomItems = collect($roomCategories->get('Operational Screens & Quick Links'));
        $this->assertTrue($roomItems->contains(fn ($res) => str_contains($res->title, 'Front-Desk Accommodation Deck')));
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

    public function test_dashboard_hero_quick_search_widget_renders_successfully(): void
    {
        // 1. Admin sees search hub but Counter POS is hidden
        $this->actingAs($this->admin);

        Livewire::test(GlobalQuickSearchWidget::class)
            ->assertSuccessful()
            ->assertSee('Club-Wide Global Search & Command Hub')
            ->assertSee('20 Sections Active')
            ->assertDontSee('Counter POS')
            ->assertSee('Staff Access')
            ->assertSee('Accommodation Deck')
            ->assertSee('Court Schedule')
            ->assertSee('Executive Reports');

        // 2. Cashier sees Counter POS on widget
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->actingAs($cashier);

        Livewire::test(GlobalQuickSearchWidget::class)
            ->assertSuccessful()
            ->assertSee('Counter POS')
            ->assertSee('Accommodation Deck');
    }
}
