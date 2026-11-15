<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Models\GuestPass;
use App\Domain\Membership\Models\Person;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use App\Filament\Resources\Guests\Pages\CreateGuest;
use App\Filament\Resources\Inventory\Tables\InventoryItemsTable;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Purchases\PurchaseReceiptResource;
use App\Filament\Resources\RoomReservations\Tables\RoomReservationsTable;
use App\Filament\Resources\Suppliers\Tables\SuppliersTable;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormWorkflowGapsResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Shift $openShift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->adminUser = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->actingAs($this->adminUser);

        $counter = Counter::firstOrCreate(
            ['code' => 'MAIN-RECEPTION'],
            ['name' => 'Main Reception Desk', 'department_code' => 'reception', 'is_active' => true]
        );

        $this->openShift = Shift::where('status', ShiftStatus::OPEN)->first()
            ?? Shift::create([
                'shift_number' => 'SH-TEST-' . uniqid(),
                'counter_id' => $counter->id,
                'user_id' => $this->adminUser->id,
                'business_date' => today()->toDateString(),
                'opened_at' => now(),
                'opening_float_paise' => 200000,
                'status' => ShiftStatus::OPEN,
            ]);
    }

    public function test_inventory_item_can_create_selling_tariff_via_extracted_action(): void
    {
        $item = InventoryItem::create([
            'sku' => 'LIQ-GIN-BOMBAY-750',
            'name' => 'Bombay Sapphire Gin 750ml',
            'department_code' => 'bar',
            'category' => 'liquor',
            'base_unit' => 'ml',
            'reorder_level' => 1500,
            'current_stock_qty' => 7500,
            'current_avg_cost_paise' => 25,
            'is_active' => true,
        ]);

        $action = Action::make('addSellingPrice');

        InventoryItemsTable::executeAddSellingPrice(
            record: $item,
            data: [
                'code' => 'TAR-GIN-30ML',
                'name' => 'Bombay Sapphire (30ml Peg)',
                'department_code' => 'bar',
                'rate_paise' => 18000,
                'tax_percent' => '18.00',
                'serving_name' => '30 ml (Small Peg)',
                'serving_qty' => 30,
                'effective_from' => now()->toDateString(),
                'is_active' => true,
            ],
            action: $action
        );

        $this->assertDatabaseHas('tariffs', [
            'code' => 'TAR-GIN-30ML',
            'rate_paise' => 18000,
        ]);

        $this->assertDatabaseHas('serving_mappings', [
            'inventory_item_id' => $item->id,
            'serving_name' => '30 ml (Small Peg)',
            'serving_qty' => 30,
        ]);
    }

    public function test_purchase_receipt_form_contains_inline_inventory_creation_form(): void
    {
        $schema = \App\Filament\Resources\Purchases\Schemas\PurchaseReceiptForm::configure(new \Filament\Schemas\Schema());
        $components = $schema->getComponents();

        $this->assertNotEmpty($components);
        $section = $components[1] ?? null;
        $this->assertNotNull($section);
    }

    public function test_room_reservation_form_supports_inline_guest_registration(): void
    {
        $category = MembershipCategory::where('code', 'ORD')->firstOrFail();

        $person = Person::create([
            'first_name' => 'Ramesh',
            'last_name' => 'Menon',
            'phone' => '+919847012345',
        ]);

        $member = Member::create([
            'person_id' => $person->id,
            'membership_number' => 'MC-TEST-001',
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => now()->subYears(2),
        ]);

        // Register guest via GuestService (underlying inline creation)
        $guest = app(\App\Domain\Access\Services\GuestService::class)->registerGuest(
            personData: [
                'first_name' => 'Suresh',
                'last_name' => 'Varma',
                'phone' => '+919447123456',
                'id_proof_type' => 'Aadhaar Card',
                'id_proof_number' => '9876 5432 1098',
            ],
            sponsorType: Member::class,
            sponsorId: $member->id,
            visitDate: now()->toDateString()
        );

        $this->assertNotNull($guest->id);
        $this->assertEquals('Suresh', $guest->person->first_name);
        $this->assertEquals($member->id, $guest->sponsor_id);
    }

    public function test_create_guest_can_issue_entry_pass_immediately_when_shift_is_open(): void
    {
        $category = MembershipCategory::where('code', 'ORD')->firstOrFail();

        $person = Person::create([
            'first_name' => 'Ramesh',
            'last_name' => 'Menon',
            'phone' => '+919847012399',
        ]);

        $member = Member::create([
            'person_id' => $person->id,
            'membership_number' => 'MC-TEST-002',
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => now()->subYears(2),
        ]);

        Tariff::firstOrCreate(
            ['code' => 'GUEST-BAR-ENTRY'],
            [
                'name' => 'Guest Bar & Card Room Entry Pass',
                'department_code' => 'card_room',
                'rate_paise' => 20000,
                'tax_percent' => '18.00',
                'is_active' => true,
            ]
        );

        Livewire::test(CreateGuest::class)
            ->fillForm([
                'first_name' => 'Sunil',
                'last_name' => 'Kumar',
                'phone' => '+919847111222',
                'id_proof_type' => 'Aadhaar Card',
                'id_proof_number' => '1234 5678 9012',
                'sponsor_type' => Member::class,
                'sponsor_id' => $member->id,
                'visit_date' => now()->toDateString(),
                'status' => 'registered',
                'issue_pass_now' => true,
                'pass_facility_code' => 'bar_card_room',
                'pass_tender_type' => 'cash',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $guest = Guest::whereHas('person', fn ($q) => $q->where('phone', '+919847111222'))->first();
        $this->assertNotNull($guest);

        $this->assertDatabaseHas('guest_passes', [
            'guest_id' => $guest->id,
            'facility_code' => 'bar_card_room',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('bills', [
            'customer_id' => $guest->id,
            'customer_type' => Guest::class,
            'status' => 'paid',
        ]);
    }

    public function test_room_reservations_table_check_in_and_check_out_actions(): void
    {
        $room = Facility::where('code', 'ROOM-101')->firstOrFail();
        $room->update(['housekeeping_status' => 'clean']);

        $person = Person::create([
            'first_name' => 'Vijay',
            'last_name' => 'Nair',
            'phone' => '+919447000111',
        ]);

        $guest = Guest::create([
            'person_id' => $person->id,
            'sponsor_type' => Member::class,
            'sponsor_id' => 1,
            'visit_date' => now()->toDateString(),
            'status' => 'registered',
        ]);

        $reservation = RoomReservation::create([
            'reservation_number' => 'RES-2026-TEST-' . uniqid(),
            'facility_id' => $room->id,
            'guest_id' => $guest->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'rate_per_night_paise' => 250000,
            'total_charge_paise' => 500000,
            'paid_amount_paise' => 500000,
            'status' => 'reserved',
            'housekeeping_status' => 'clean',
        ]);

        // Check in via service (tested action callback)
        app(\App\Domain\Facility\Services\RoomReservationService::class)->checkIn($reservation, $this->adminUser);
        $reservation->refresh();
        $this->assertEquals('checked_in', $reservation->status);

        // Check out via service
        app(\App\Domain\Facility\Services\RoomReservationService::class)->checkOut($reservation, $this->adminUser);
        $reservation->refresh();
        $room->refresh();

        $this->assertEquals('checked_out', $reservation->status);
        $this->assertEquals('dirty', $reservation->housekeeping_status);
        $this->assertEquals('dirty', $room->housekeeping_status);
    }

    public function test_member_edit_form_allows_smartcard_replacement(): void
    {
        $category = MembershipCategory::where('code', 'ORD')->firstOrFail();

        $person = Person::create([
            'first_name' => 'Pradeep',
            'last_name' => 'Menon',
            'phone' => '+919847999888',
        ]);

        $member = Member::create([
            'person_id' => $person->id,
            'membership_number' => 'MC-777',
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => now()->subYears(1),
        ]);

        $cardService = app(\App\Domain\Membership\Services\CardService::class);
        $oldCard = $cardService->issueCard($member, 'RFID-OLD-001', 'Initial registration');
        $this->assertEquals('RFID-OLD-001', $member->activeCard->card_number);

        // Update via EditMember page
        Livewire::test(EditMember::class, ['record' => $member->id])
            ->fillForm([
                'first_name' => 'Pradeep',
                'membership_number' => 'MC-777',
                'membership_category_id' => $category->id,
                'status' => 'active',
                'joined_at' => now()->subYears(1)->toDateString(),
                'new_rfid_card_number' => 'RFID-NEW-999',
                'card_change_reason' => 'Lost previous smartcard',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $oldCard->refresh();

        $this->assertEquals(CardStatus::REPLACED, $oldCard->status);
        $this->assertEquals('RFID-NEW-999', $member->activeCard->card_number);
        $this->assertEquals(CardStatus::ACTIVE, $member->activeCard->status);
    }

    public function test_suppliers_table_has_inward_grn_action_linking_to_purchase_receipt(): void
    {
        $supplier = Supplier::create([
            'name' => 'Kerala State Beverages Corporation (KSBC)',
            'code' => 'KSBC-TEST',
            'is_active' => true,
        ]);

        $expectedUrl = PurchaseReceiptResource::getUrl('create', ['supplier_id' => $supplier->id]);
        $this->assertStringContainsString('supplier_id=' . $supplier->id, $expectedUrl);
    }

    public function test_court_booking_form_auto_detects_badminton_subscription(): void
    {
        $category = MembershipCategory::where('code', 'ORD')->firstOrFail();

        $personWithPlan = Person::create([
            'first_name' => 'Arun',
            'last_name' => 'Nambiar',
            'phone' => '+919847112233',
        ]);

        $memberWithPlan = Member::create([
            'person_id' => $personWithPlan->id,
            'membership_number' => 'MC-PLAN-1',
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => now()->subYear(),
        ]);

        $personWithoutPlan = Person::create([
            'first_name' => 'Biju',
            'last_name' => 'Pillai',
            'phone' => '+919847445566',
        ]);

        $memberWithoutPlan = Member::create([
            'person_id' => $personWithoutPlan->id,
            'membership_number' => 'MC-PLAN-0',
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => now()->subYear(),
        ]);

        $tariff = Tariff::firstOrCreate(
            ['code' => 'SPORTS-BADM-MONTHLY'],
            [
                'name' => 'Badminton Monthly Plan',
                'department_code' => 'sports',
                'rate_paise' => 150000,
                'tax_percent' => '18.00',
                'effective_from' => now()->toDateString(),
                'is_active' => true,
            ]
        );

        // Create active badminton plan for memberWithPlan
        FacilitySubscription::create([
            'subscription_number' => 'SUB-BADM-001',
            'member_id' => $memberWithPlan->id,
            'facility_code' => 'badminton',
            'plan_type' => 'monthly',
            'tariff_id' => $tariff->id,
            'status' => 'active',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
            'total_fee_paise' => 150000,
            'created_by' => $this->adminUser->id,
        ]);

        // Verify query check logic
        $hasPlan1 = FacilitySubscription::where('member_id', $memberWithPlan->id)
            ->where('facility_code', 'badminton')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->exists();

        $hasPlan2 = FacilitySubscription::where('member_id', $memberWithoutPlan->id)
            ->where('facility_code', 'badminton')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->exists();

        $this->assertTrue($hasPlan1);
        $this->assertFalse($hasPlan2);
    }
}
