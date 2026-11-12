<?php

namespace Tests\Feature;

use App\Domain\Access\Models\AccessEvent;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Models\Locker;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberNote;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberProfileViewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();

        $category = MembershipCategory::firstOrFail();

        $person = Person::create([
            'first_name' => 'Kishore',
            'last_name' => 'Warrier',
            'phone' => '+91 98470 12345',
            'email' => 'kishore.warrier@malabarclub.local',
            'id_proof_type' => 'Aadhaar Card',
            'id_proof_number' => '1234 5678 9012',
            'notes' => 'Vadakkencherry Town, Palakkad',
        ]);

        $this->member = Member::create([
            'membership_number' => 'MC-KW-01',
            'person_id' => $person->id,
            'membership_category_id' => $category->id,
            'status' => MemberStatus::ACTIVE,
            'joined_at' => '2025-01-15',
            'valid_until' => '2028-12-31',
        ]);

        WalletAccount::create([
            'walletable_type' => Member::class,
            'walletable_id' => $this->member->id,
            'balance_paise' => 500000, // ₹5,000.00
            'reserved_paise' => 0,
            'currency' => 'INR',
            'status' => 'active',
            'version' => 1,
        ]);

        Card::create([
            'card_number' => 'CARD-KW-001',
            'cardable_type' => Member::class,
            'cardable_id' => $this->member->id,
            'status' => 'active',
            'issued_at' => now(),
            'notes' => 'Primary Access Card',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/members/' . $this->member->id);
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_access_member_profile_view(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/members/' . $this->member->id);
        $response->assertStatus(200);
        $response->assertSee('Kishore Warrier');
        $response->assertSee('MC-KW-01');
        $response->assertSee('CARD-KW-001');
        $response->assertSee('5,000.00');
        $response->assertSee('Overview & KYC');
        $response->assertSee('Prepaid Wallet Ledger');
        $response->assertSee('Invoices & Bills');
        $response->assertSee('Sports & Facilities');
        $response->assertSee('Family & Guests');
    }

    public function test_manager_can_access_member_profile_view(): void
    {
        $response = $this->actingAs($this->manager)->get('/admin/members/' . $this->member->id);
        $response->assertStatus(200);
        $response->assertSee('Kishore Warrier');
        $response->assertSee('MC-KW-01');
    }

    public function test_member_profile_displays_kyc_and_classification(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/members/' . $this->member->id);
        $response->assertStatus(200);
        $response->assertSee('ID Proof &amp; Personal Records', false);
        $response->assertSee('1234 5678 9012');
        $response->assertSee('Vadakkencherry Town, Palakkad');
        $response->assertSee('Membership Classification & Access');
    }

    public function test_tab_switching_via_livewire(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->assertSet('activeTab', 'overview')
            ->call('setActiveTab', 'wallet')
            ->assertSet('activeTab', 'wallet')
            ->call('setActiveTab', 'bills')
            ->assertSet('activeTab', 'bills')
            ->call('setActiveTab', 'facilities')
            ->assertSet('activeTab', 'facilities')
            ->call('setActiveTab', 'dependents')
            ->assertSet('activeTab', 'dependents');
    }

    public function test_top_up_wallet_from_profile_view_credits_balance(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('topUpAmount', '1500')
            ->set('topUpTender', 'cash')
            ->set('topUpRef', 'Admin Desk Top-up')
            ->call('processTopUp')
            ->assertHasNoErrors();

        $this->member->refresh();
        $this->assertEquals(650000, $this->member->wallet->balance_paise); // ₹5,000 + ₹1,500 = ₹6,500
    }

    public function test_rfid_card_replacement_updates_card_on_profile(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('newCardNumber', 'CARD-KW-REPLACE-99')
            ->set('cardReason', 'Lost physical card at tennis court')
            ->call('processCardIssue')
            ->assertHasNoErrors();

        $this->member->refresh();
        $this->assertEquals('CARD-KW-REPLACE-99', $this->member->activeCard->card_number);

        // Previous card should be marked replaced
        $oldCard = Card::where('card_number', 'CARD-KW-001')->firstOrFail();
        $this->assertEquals('replaced', $oldCard->status->value);
    }

    public function test_dependent_registration_and_wallet_authorization_toggle(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('depFirstName', 'Sunitha')
            ->set('depLastName', 'Warrier')
            ->set('depRelationship', 'Spouse')
            ->set('depPhone', '+91 98470 54321')
            ->set('depCanSpendWallet', false)
            ->call('processDependentRegister')
            ->assertHasNoErrors();

        $dep = Dependent::where('member_id', $this->member->id)->firstOrFail();
        $this->assertEquals('Sunitha Warrier', $dep->person->full_name);
        $this->assertFalse((bool) $dep->can_spend_wallet);

        // Toggle authorization on
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('toggleDependentWalletDebit', $dep->id);

        $dep->refresh();
        $this->assertTrue((bool) $dep->can_spend_wallet);
    }

    public function test_status_change_modal_transitions_member_status(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('newStatus', 'suspended')
            ->set('statusReason', 'Disciplinary committee pending hearing')
            ->call('processStatusChange')
            ->assertHasNoErrors();

        $this->member->refresh();
        $this->assertEquals(MemberStatus::SUSPENDED, $this->member->status);
    }

    public function test_invoice_details_modal_loads_itemized_data(): void
    {
        // Create a bill for this member
        $bill = Bill::create([
            'bill_number' => 'INV-TEST-KW-01',
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'department_code' => 'restaurant',
            'status' => BillStatus::PAID,
            'business_date' => now()->toDateString(),
            'gross_amount_paise' => 45000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 2250,
            'net_amount_paise' => 47250,
            'paid_amount_paise' => 47250,
            'idempotency_key' => 'idemp_test_kw_01',
            'created_by_user_id' => $this->admin->id,
        ]);

        BillLine::create([
            'bill_id' => $bill->id,
            'department_code' => 'restaurant',
            'item_type' => 'food',
            'item_id' => 1,
            'item_name' => 'Kerala Chicken Biryani',
            'quantity' => 2,
            'unit_price_paise' => 22500,
            'tax_percent' => 5.0,
            'tax_amount_paise' => 2250,
            'line_total_paise' => 47250,
        ]);

        PaymentReceipt::create([
            'receipt_number' => 'RCP-TEST-KW-01',
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'bill_id' => $bill->id,
            'tender_type' => TenderType::PREPAID,
            'amount_paise' => 47250,
            'status' => 'settled',
            'confirmed_at' => now(),
            'idempotency_key' => 'idemp_rcp_kw_01',
            'created_by_user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('openInvoiceModal', $bill->id)
            ->assertSet('showInvoiceModal', true)
            ->assertSet('selectedBillId', $bill->id)
            ->assertSee('INV-TEST-KW-01')
            ->assertSee('Kerala Chicken Biryani')
            ->assertSee('472.50');
    }

    public function test_whatsapp_share_url_generation(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id]);

        /** @var ViewMember $instance */
        $instance = $component->instance();
        $url = $instance->getWhatsAppShareUrl();

        $this->assertStringStartsWith('https://wa.me/919847012345?text=', $url);
        $this->assertStringContainsString(rawurlencode('MC-KW-01'), $url);
        $this->assertStringContainsString(rawurlencode('5,000.00'), $url);
    }

    public function test_add_confidential_committee_note(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('openAddNoteModal')
            ->assertSet('showAddNoteModal', true)
            ->set('noteCategory', 'disciplinary')
            ->set('noteContent', 'Member attended committee hearing regarding card replacement.')
            ->call('processAddNote')
            ->assertSet('showAddNoteModal', false)
            ->assertHasNoErrors();

        $note = MemberNote::where('member_id', $this->member->id)->firstOrFail();
        $this->assertEquals('disciplinary', $note->category);
        $this->assertEquals('Member attended committee hearing regarding card replacement.', $note->note);
        $this->assertEquals($this->admin->id, $note->created_by_user_id);
    }

    public function test_generate_affiliated_club_introduction_letter(): void
    {
        $comp = Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('openIntroLetterModal')
            ->assertSet('showIntroLetterModal', true)
            ->set('letterDestinationClub', 'Bangalore Club, Residency Road, Bengaluru')
            ->set('letterVisitStart', '2026-12-01')
            ->set('letterVisitEnd', '2026-12-05')
            ->set('letterAccompanying', 'Spouse only')
            ->set('letterPurpose', 'Holiday stay and dining')
            ->call('generateIntroLetter')
            ->assertSet('showIntroLetterModal', false)
            ->assertSet('showLetterViewModal', true)
            ->assertHasNoErrors();

        /** @var ViewMember $instance */
        $instance = $comp->instance();
        $letter = $instance->generatedLetter;

        $this->assertNotNull($letter);
        $this->assertEquals('Bangalore Club, Residency Road, Bengaluru', $letter['destination_club']);
        $this->assertEquals('Kishore Warrier', $letter['member_name']);
        $this->assertEquals('MC-KW-01', $letter['membership_number']);
        $this->assertStringStartsWith('MC-REC-2026-', $letter['reference_number']);
    }

    public function test_generate_introduction_letter_prohibited_for_suspended_member(): void
    {
        $this->member->update(['status' => MemberStatus::SUSPENDED]);

        $comp = Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('letterDestinationClub', 'Trivandrum Club')
            ->set('letterVisitStart', '2026-12-01')
            ->set('letterVisitEnd', '2026-12-05')
            ->call('generateIntroLetter')
            ->assertSet('showLetterViewModal', false);

        /** @var ViewMember $instance */
        $instance = $comp->instance();
        $this->assertNull($instance->generatedLetter);
    }

    public function test_direct_court_booking_from_member_profile(): void
    {
        $court = Facility::where('type', 'court')->firstOrFail();
        $futureDate = now()->addDays(2)->toDateString();

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('openBookCourtModal')
            ->assertSet('showBookCourtModal', true)
            ->set('bookCourtFacilityId', $court->id)
            ->set('bookCourtDate', $futureDate)
            ->set('bookCourtStartTime', '07:00')
            ->set('bookCourtEndTime', '08:00')
            ->call('processDirectCourtBooking')
            ->assertSet('showBookCourtModal', false)
            ->assertHasNoErrors();

        $booking = CourtBooking::where('member_id', $this->member->id)
            ->where('booking_date', $futureDate)
            ->firstOrFail();

        $this->assertEquals($court->id, $booking->facility_id);
        $this->assertEquals('07:00:00', Carbon::parse($booking->start_time)->toTimeString());
        $this->assertEquals('08:00:00', Carbon::parse($booking->end_time)->toTimeString());
    }

    public function test_direct_room_reservation_from_member_profile(): void
    {
        $room = Facility::where('type', 'room')->firstOrFail();
        $checkIn = now()->addDays(3)->toDateString();
        $checkOut = now()->addDays(5)->toDateString();

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('openBookRoomModal')
            ->assertSet('showBookRoomModal', true)
            ->set('bookRoomFacilityId', $room->id)
            ->set('bookRoomCheckIn', $checkIn)
            ->set('bookRoomCheckOut', $checkOut)
            ->set('bookRoomRateRupees', 2000)
            ->set('bookRoomDepositRupees', 2000)
            ->set('bookRoomNotes', 'Front-desk VIP member direct booking')
            ->call('processDirectRoomBooking')
            ->assertSet('showBookRoomModal', false)
            ->assertHasNoErrors();

        $reservation = RoomReservation::where('sponsor_member_id', $this->member->id)
            ->where('facility_id', $room->id)
            ->firstOrFail();

        $this->assertEquals($checkIn, $reservation->check_in_date->toDateString());
        $this->assertEquals($checkOut, $reservation->check_out_date->toDateString());
        $this->assertEquals(200000, $reservation->rate_per_night_paise);
        $this->assertEquals(400000, $reservation->total_charge_paise); // 2 nights * 200000
    }

    public function test_access_tab_switching_and_turnstile_event_rendering(): void
    {
        AccessEvent::create([
            'person_id' => $this->member->person_id,
            'card_number' => 'CARD-KW-001',
            'facility_code' => 'Main Gate Turnstile',
            'event_type' => 'granted',
            'reason' => 'Active membership verified',
            'device_id' => 'TURNSTILE-01',
            'recorded_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('setActiveTab', 'access')
            ->assertSet('activeTab', 'access')
            ->assertSee('Main Gate Turnstile')
            ->assertSee('Granted')
            ->assertSee('TURNSTILE-01');
    }

    public function test_instant_emergency_gate_access_block(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('blockActiveCard')
            ->assertHasNoErrors();

        $card = Card::where('card_number', 'CARD-KW-001')->firstOrFail();
        $this->assertEquals(CardStatus::BLOCKED, $card->status);
        $this->assertNotNull($card->blocked_at);

        $this->member->refresh();
        $this->assertNull($this->member->activeCard);

        $note = MemberNote::where('member_id', $this->member->id)->where('category', 'disciplinary')->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('blocked', $note->note);
    }

    public function test_restore_gate_access_unblock(): void
    {
        // First block the card
        $card = Card::where('card_number', 'CARD-KW-001')->firstOrFail();
        $card->update(['status' => CardStatus::BLOCKED, 'blocked_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('unblockCard', $card->id)
            ->assertHasNoErrors();

        $card->refresh();
        $this->assertEquals(CardStatus::ACTIVE, $card->status);
        $this->assertNull($card->blocked_at);

        $this->member->refresh();
        $this->assertNotNull($this->member->activeCard);
        $this->assertEquals('CARD-KW-001', $this->member->activeCard->card_number);
    }

    public function test_assign_club_locker_from_profile(): void
    {
        $locker = Locker::where('status', 'available')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('openAssignLockerModal')
            ->assertSet('showAssignLockerModal', true)
            ->set('selectedLockerId', $locker->id)
            ->set('lockerDepositRupees', 1200)
            ->set('lockerKeyNumber', 'KEY-LK-KW-01')
            ->set('lockerNotes', 'Locker for badminton gear')
            ->call('processAssignLocker')
            ->assertSet('showAssignLockerModal', false)
            ->assertHasNoErrors();

        $locker->refresh();
        $this->assertEquals('occupied', $locker->status);
        $this->assertEquals($this->member->id, $locker->assigned_member_id);
        $this->assertEquals(120000, $locker->deposit_amount_paise);
        $this->assertEquals('KEY-LK-KW-01', $locker->key_number);

        $this->member->refresh();
        $this->assertCount(1, $this->member->lockers);
    }

    public function test_vacate_and_surrender_club_locker(): void
    {
        $locker = Locker::where('status', 'available')->firstOrFail();
        $locker->update([
            'status' => 'occupied',
            'assigned_member_id' => $this->member->id,
            'assigned_at' => now(),
            'deposit_amount_paise' => 100000,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('vacateLocker', $locker->id)
            ->assertHasNoErrors();

        $locker->refresh();
        $this->assertEquals('available', $locker->status);
        $this->assertNull($locker->assigned_member_id);

        $note = MemberNote::where('member_id', $this->member->id)->latest()->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('surrendered', $note->note);
    }

    public function test_annual_membership_renewal_via_prepaid_wallet(): void
    {
        $oldValidUntil = $this->member->valid_until->copy();

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->call('openRenewModal')
            ->assertSet('showRenewModal', true)
            ->set('renewalYears', 1)
            ->set('renewalFeeRupees', 2000)
            ->set('renewalTender', 'prepaid')
            ->call('processRenewMembership')
            ->assertSet('showRenewModal', false)
            ->assertHasNoErrors();

        $this->member->refresh();
        $this->assertEquals(300000, $this->member->wallet->balance_paise); // ₹5000 - ₹2000 = ₹3000
        $this->assertEquals($oldValidUntil->addYear()->toDateString(), $this->member->valid_until->toDateString());
    }

    public function test_annual_membership_renewal_enforces_no_credit_rule_d02(): void
    {
        // Reduce wallet balance to ₹500
        $this->member->wallet->update(['balance_paise' => 50000]);
        $oldValidUntil = $this->member->valid_until->copy();

        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('renewalYears', 1)
            ->set('renewalFeeRupees', 2500)
            ->set('renewalTender', 'prepaid')
            ->call('processRenewMembership');

        $this->member->refresh();
        // Balance and validity should be unchanged
        $this->assertEquals(50000, $this->member->wallet->balance_paise);
        $this->assertEquals($oldValidUntil->toDateString(), $this->member->valid_until->toDateString());
    }

    public function test_fnb_consumption_analytics_and_favorite_items_aggregation(): void
    {
        $bill = Bill::create([
            'bill_number' => 'INV-ANALYTICS-01',
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'department_code' => 'restaurant',
            'status' => BillStatus::PAID,
            'business_date' => now()->toDateString(),
            'gross_amount_paise' => 75000,
            'tax_amount_paise' => 3750,
            'net_amount_paise' => 78750,
            'paid_amount_paise' => 78750,
            'idempotency_key' => 'idemp_analytics_01',
            'created_by_user_id' => $this->admin->id,
        ]);

        BillLine::create([
            'bill_id' => $bill->id,
            'department_code' => 'restaurant',
            'item_type' => 'food',
            'item_id' => 10,
            'item_name' => 'Fish Curry Meals',
            'quantity' => 3,
            'unit_price_paise' => 25000,
            'tax_percent' => 5.0,
            'tax_amount_paise' => 3750,
            'line_total_paise' => 78750,
        ]);

        $test = Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id]);

        /** @var ViewMember $instance */
        $instance = $test->instance();
        $viewData = $instance->getViewData();

        $this->assertArrayHasKey('departmentSpend', $viewData);
        $this->assertEquals(787.50, $viewData['departmentSpend']['restaurant']);

        $this->assertArrayHasKey('favoriteItems', $viewData);
        $this->assertTrue($viewData['favoriteItems']->contains('name', 'Fish Curry Meals'));
    }

    public function test_kyc_document_vault_links_and_security_restrictions(): void
    {
        // 1. Unauthenticated gets redirected to login
        $guestRes = $this->get('/documents/members/' . $this->member->id . '/id_proof.jpg');
        $guestRes->assertRedirect('/login');

        // 2. Cashier role without document clearance gets 403 Forbidden
        $cashierRes = $this->actingAs($this->cashier)->get('/documents/members/' . $this->member->id . '/id_proof.jpg');
        $cashierRes->assertStatus(403);

        // 3. Admin can access with private non-cached headers
        $adminRes = $this->actingAs($this->admin)->get('/documents/members/' . $this->member->id . '/id_proof.jpg');
        $adminRes->assertStatus(200);
        $adminRes->assertHeader('Pragma', 'no-cache');
    }

    public function test_print_statement_action_switches_to_wallet_tab(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('activeTab', 'overview')
            ->call('printStatement')
            ->assertSet('activeTab', 'wallet');
    }

    public function test_intro_letter_view_isolates_print_output_by_hiding_main_profile_content(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('activeTab', 'wallet')
            ->set('letterDestinationClub', 'Gymkhana Club, Bangalore')
            ->set('letterVisitStart', now()->addDays(5)->format('Y-m-d'))
            ->set('letterVisitEnd', now()->addDays(8)->format('Y-m-d'))
            ->call('generateIntroLetter')
            ->assertSet('showLetterViewModal', true)
            ->assertSee('printable-intro-letter')
            ->assertSee('print:hidden');
    }
}
