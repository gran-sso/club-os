<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Services\ContractorSettlementService;
use App\Domain\Facility\Exceptions\PaymentRequiredException;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Facility\Services\CourtBookingService;
use App\Domain\Facility\Services\RoomReservationService;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\CardService;
use App\Domain\Tariff\Models\Facility;
use App\Filament\Pages\RoomRackPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FrontDeskAndOperationalWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Member $member;
    protected Facility $room;
    protected Facility $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->member = Member::firstOrFail();
        $this->room = Facility::where('type', 'room')->firstOrFail();
        $this->court = Facility::where('type', 'court')->firstOrFail();
    }

    public function test_room_rack_and_court_schedule_pages_accessible_to_staff(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/room-rack');
        $response->assertStatus(200);
        $response->assertSee('Guest Rooms (101–105)');
        $response->assertSee('Deluxe Room 101');

        $response = $this->actingAs($this->admin)->get('/admin/court-schedule');
        $response->assertStatus(200);
        $response->assertSee('Indoor Badminton Court Daily Schedule');
        $response->assertSee('6:00 AM – 7:00 AM');
    }

    public function test_counter_cashier_can_access_room_rack_and_court_schedule(): void
    {
        $responseCashier = $this->actingAs($this->cashier)->get('/admin/room-rack');
        $responseCashier->assertStatus(200);
        $responseCashier->assertSee('Guest Rooms (101–105)');

        $responseCourt = $this->actingAs($this->cashier)->get('/admin/court-schedule');
        $responseCourt->assertStatus(200);
        $responseCourt->assertSee('Indoor Badminton Court Daily Schedule');
    }

    public function test_admin_can_open_and_confirm_room_booking_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoomRackPage::class)
            ->call('openNewBookingModal', $this->room->id)
            ->assertSet('showNewBookingModal', true)
            ->assertSet('newBooking.facility_id', $this->room->id)
            ->assertSet('newBooking.member_id', null)
            ->assertSet('newBooking.guest_id', null)
            ->assertSee('Front Desk Room Reservation')
            ->set('newBooking.guest_type', 'member')
            ->set('newBooking.member_id', $this->member->id)
            ->call('confirmNewBooking')
            ->assertSet('showNewBookingModal', false)
            ->assertHasNoErrors();
    }

    public function test_room_check_in_enforces_advance_payment_and_cleanliness(): void
    {
        $roomService = app(RoomReservationService::class);

        // 1. Unpaid reservation cannot check in
        $unpaidRes = $roomService->createReservation([
            'facility_id' => $this->room->id,
            'sponsor_member_id' => $this->member->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'rate_per_night_paise' => 150000,
            'paid_amount_paise' => 0, // Unpaid!
            'housekeeping_status' => 'clean',
        ], $this->admin);

        try {
            $roomService->checkIn($unpaidRes, $this->admin);
            $this->fail('Expected PaymentRequiredException when checking in unpaid room reservation');
        } catch (PaymentRequiredException $e) {
            $this->assertStringContainsString('requires full payment', $e->getMessage());
        }

        // 2. Pay remaining balance
        $roomService->recordPayment($unpaidRes, 150000, $this->admin);
        $this->assertTrue($unpaidRes->fresh()->isFullyPaid());

        // 3. Check-in now succeeds
        $checkedIn = $roomService->checkIn($unpaidRes->fresh(), $this->admin);
        $this->assertEquals('checked_in', $checkedIn->status);
        $this->assertNotNull($checkedIn->checked_in_at);

        // 4. Check-out frees room and marks it dirty
        $checkedOut = $roomService->checkOut($checkedIn, $this->admin);
        $this->assertEquals('checked_out', $checkedOut->status);
        $this->assertEquals('dirty', $checkedOut->housekeeping_status);
    }

    public function test_badminton_court_slot_booking_and_cancellation(): void
    {
        $courtService = app(CourtBookingService::class);
        $bookingDate = now()->addDays(2)->toDateString();

        $booking = $courtService->bookCourt(
            court: $this->court,
            member: $this->member,
            bookingDate: $bookingDate,
            startTime: '08:00',
            endTime: '09:00',
            user: $this->admin
        );

        $this->assertNotNull($booking);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertFalse($courtService->isSlotAvailable($this->court, $bookingDate, '08:00'));

        // Cancel slot
        $cancelled = $courtService->cancelBooking($booking, $this->admin);
        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertTrue($courtService->isSlotAvailable($this->court, $bookingDate, '08:00'));
    }

    public function test_contractor_settlement_batch_generation_approval_and_payout(): void
    {
        $settlementService = app(ContractorSettlementService::class);
        $contractor = Contractor::firstOrFail();
        $today = now()->toDateString();

        $batch = $settlementService->createSettlementBatch($contractor, $today, $this->admin->id);
        $this->assertNotNull($batch);
        $this->assertEquals('draft', $batch->status);

        // Approve batch
        $approved = $settlementService->approveSettlementBatch($batch, $this->admin);
        $this->assertEquals('approved', $approved->status);

        // Record payout if payable exists
        if ($approved->net_payable_paise > 0) {
            $paid = $settlementService->confirmSettlementPayment(
                $approved,
                $approved->net_payable_paise,
                'NEFT: UTR-MALABAR-FOOD-991',
                $this->admin->id
            );
            $this->assertEquals('paid', $paid->status);
            $this->assertEquals(0, $paid->residual_payable_paise);
        }
    }

    public function test_member_card_replacement_preserves_authoritative_ledger_balance(): void
    {
        $cardService = app(CardService::class);
        $initialBalance = $this->member->wallet->balance_paise;
        $activeCard = $this->member->activeCard;

        if (!$activeCard) {
            $activeCard = $cardService->issueCard($this->member, 'CARD-INIT-001', 'Initial card');
        }

        $newCard = $cardService->replaceCard($activeCard, 'CARD-REPLACE-999', 'Damaged chip');

        $this->assertEquals('replaced', $activeCard->fresh()->status->value);
        $this->assertEquals('active', $newCard->status->value);
        $this->assertEquals($initialBalance, $this->member->fresh()->wallet->balance_paise);
    }
}
