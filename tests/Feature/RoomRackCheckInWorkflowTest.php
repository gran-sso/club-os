<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Facility\Services\RoomReservationService;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Facility;
use App\Filament\Pages\RoomRackPage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomRackCheckInWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_room_check_in_with_balance_payment_via_cash(): void
    {
        $admin = User::where('email', 'admin@malabarclub.com')->firstOrFail();
        $this->actingAs($admin);

        $room = Facility::where('code', 'ROOM-101')->firstOrFail();
        $member = Member::firstOrFail();

        // Create reservation with ₹500 advance on ₹1,500 total charge (₹1,000 balance unpaid)
        $service = app(RoomReservationService::class);
        $res = $service->createReservation([
            'facility_id' => $room->id,
            'payer_type' => Member::class,
            'payer_id' => $member->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'rate_per_night_paise' => 150000,
            'paid_amount_paise' => 50000,
            'housekeeping_status' => 'clean',
        ], $admin);

        $this->assertFalse($res->isFullyPaid());
        $this->assertEquals(50000, $res->paid_amount_paise);
        $this->assertEquals(150000, $res->total_charge_paise);

        // Open RoomRackPage and test check-in with balance payment
        Livewire::test(RoomRackPage::class)
            ->call('openCheckIn', $res->id)
            ->assertSet('activeReservationId', $res->id)
            ->assertSet('paymentAmountRupees', 1000)
            ->set('paymentMode', 'cash')
            ->call('confirmCheckInWithPayment')
            ->assertHasNoErrors();

        // Verify reservation updated to checked_in and fully paid
        $res->refresh();
        $this->assertEquals('checked_in', $res->status);
        $this->assertEquals(150000, $res->paid_amount_paise);
        $this->assertTrue($res->isFullyPaid());
        $this->assertNotNull($res->checked_in_at);

        // Verify Bill and PaymentReceipt generated
        $receipt = PaymentReceipt::where('customer_id', $member->id)->latest()->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(100000, $receipt->amount_paise);
        $this->assertEquals(TenderType::CASH, $receipt->tender_type);
        $this->assertEquals('confirmed', $receipt->provider_status);

        $bill = Bill::find($receipt->bill_id);
        $this->assertNotNull($bill);
        $this->assertEquals(100000, $bill->net_amount_paise);
        $this->assertEquals('paid', $bill->status->value);
    }

    public function test_room_check_in_with_balance_payment_via_digital_upi(): void
    {
        $admin = User::where('email', 'admin@malabarclub.com')->firstOrFail();
        $this->actingAs($admin);

        $room = Facility::where('code', 'ROOM-102')->firstOrFail();
        $member = Member::firstOrFail();

        $service = app(RoomReservationService::class);
        $res = $service->createReservation([
            'facility_id' => $room->id,
            'payer_type' => Member::class,
            'payer_id' => $member->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'rate_per_night_paise' => 150000,
            'paid_amount_paise' => 0, // Unpaid ₹1,500
            'housekeeping_status' => 'clean',
        ], $admin);

        $this->assertFalse($res->isFullyPaid());

        Livewire::test(RoomRackPage::class)
            ->call('openCheckIn', $res->id)
            ->assertSet('paymentAmountRupees', 1500)
            ->set('paymentMode', 'upi')
            ->call('confirmCheckInWithPayment')
            ->assertHasNoErrors();

        $res->refresh();
        $this->assertEquals('checked_in', $res->status);
        $this->assertEquals(150000, $res->paid_amount_paise);
        $this->assertTrue($res->isFullyPaid());

        $receipt = PaymentReceipt::where('customer_id', $member->id)->latest()->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(150000, $receipt->amount_paise);
        $this->assertEquals(TenderType::DIGITAL, $receipt->tender_type);
    }

    public function test_check_in_blocked_if_payment_amount_less_than_unpaid_balance(): void
    {
        $admin = User::where('email', 'admin@malabarclub.com')->firstOrFail();
        $this->actingAs($admin);

        $room = Facility::where('code', 'ROOM-103')->firstOrFail();
        $member = Member::firstOrFail();

        $service = app(RoomReservationService::class);
        $res = $service->createReservation([
            'facility_id' => $room->id,
            'payer_type' => Member::class,
            'payer_id' => $member->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'rate_per_night_paise' => 150000,
            'paid_amount_paise' => 50000, // Balance due: ₹1,000
            'housekeeping_status' => 'clean',
        ], $admin);

        // Attempting to check in paying only ₹300 of ₹1,000 balance must be blocked
        Livewire::test(RoomRackPage::class)
            ->call('openCheckIn', $res->id)
            ->set('paymentAmountRupees', 300)
            ->call('confirmCheckInWithPayment');

        $res->refresh();
        $this->assertEquals('reserved', $res->status);
        $this->assertEquals(50000, $res->paid_amount_paise);
        $this->assertFalse($res->isFullyPaid());
    }

    public function test_checked_in_room_with_checkout_today_still_shows_occupied_on_room_rack_and_pulse_widget(): void
    {
        $reception = \App\Models\User::where('email', 'reception@malabarclub.local')->firstOrFail();
        $room = \App\Domain\Tariff\Models\Facility::where('type', 'room')->orderBy('code')->firstOrFail();
        $member = \App\Domain\Membership\Models\Member::where('membership_number', 'MC-001')->firstOrFail();
        $guest = \App\Domain\Access\Models\Guest::firstOrFail();

        $res = \App\Domain\Facility\Models\RoomReservation::create([
            'reservation_number' => 'RES-DUE-TODAY-01',
            'facility_id' => $room->id,
            'guest_id' => $guest->id,
            'sponsor_member_id' => $member->id,
            'payer_type' => \App\Domain\Access\Models\Guest::class,
            'payer_id' => $guest->id,
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->toDateString(),
            'rate_per_night_paise' => 250000,
            'total_charge_paise' => 250000,
            'paid_amount_paise' => 250000,
            'status' => 'reserved',
            'housekeeping_status' => 'clean',
            'created_by_user_id' => $reception->id,
        ]);

        app(\App\Domain\Facility\Services\RoomReservationService::class)->checkIn($res, $reception);

        $component = \Livewire\Livewire::actingAs($reception)
            ->test(\App\Filament\Pages\RoomRackPage::class)
            ->assertSee('OCCUPIED')
            ->assertSee('Check-Out Due Today')
            ->assertSee('Check-Out Guest');

        $roomData = collect($component->get('roomsData'))->firstWhere('id', $room->id);
        $this->assertNotNull($roomData);
        $this->assertEquals('occupied', $roomData['status']);
        $this->assertTrue($roomData['active_reservation']['is_checkout_due_today']);
        $this->assertFalse($roomData['active_reservation']['is_overstay']);
        $this->assertEquals('occupied', $roomData['forecast'][0]['status']);

        $this->actingAs($reception);
        $pulseData = (new \App\Filament\Widgets\FrontDeskPulseWidget())->getViewData();
        $pulseRoom = collect($pulseData['roomStatuses'])->firstWhere('id', $room->id);
        $this->assertEquals('occupied', $pulseRoom['status']);
        $this->assertEquals($guest->name, $pulseRoom['occupant']);
    }

    public function test_overstaying_checked_in_room_past_checkout_date_stays_occupied_with_overstay_warning(): void
    {
        $reception = \App\Models\User::where('email', 'reception@malabarclub.local')->firstOrFail();
        $room = \App\Domain\Tariff\Models\Facility::where('type', 'room')->orderBy('code')->firstOrFail();
        $member = \App\Domain\Membership\Models\Member::where('membership_number', 'MC-001')->firstOrFail();

        $res = \App\Domain\Facility\Models\RoomReservation::create([
            'reservation_number' => 'RES-OVERSTAY-01',
            'facility_id' => $room->id,
            'sponsor_member_id' => $member->id,
            'payer_type' => \App\Domain\Membership\Models\Member::class,
            'payer_id' => $member->id,
            'check_in_date' => now()->subDays(3)->toDateString(),
            'check_out_date' => now()->subDay()->toDateString(),
            'rate_per_night_paise' => 250000,
            'total_charge_paise' => 500000,
            'paid_amount_paise' => 500000,
            'status' => 'reserved',
            'housekeeping_status' => 'clean',
            'created_by_user_id' => $reception->id,
        ]);

        app(\App\Domain\Facility\Services\RoomReservationService::class)->checkIn($res, $reception);

        $component = \Livewire\Livewire::actingAs($reception)
            ->test(\App\Filament\Pages\RoomRackPage::class)
            ->assertSee('OCCUPIED')
            ->assertSee('Overdue Check-Out')
            ->assertSee('Check-Out Guest');

        $roomData = collect($component->get('roomsData'))->firstWhere('id', $room->id);
        $this->assertEquals('occupied', $roomData['status']);
        $this->assertTrue($roomData['active_reservation']['is_overstay']);
        $this->assertFalse($roomData['active_reservation']['is_checkout_due_today']);

        $this->actingAs($reception);
        $pulseData = (new \App\Filament\Widgets\FrontDeskPulseWidget())->getViewData();
        $pulseRoom = collect($pulseData['roomStatuses'])->firstWhere('id', $room->id);
        $this->assertEquals('occupied', $pulseRoom['status']);
    }

    public function test_back_to_back_checkin_blocked_while_current_guest_is_still_checked_in_and_room_rack_surfaces_arrival(): void
    {
        $reception = \App\Models\User::where('email', 'reception@malabarclub.local')->firstOrFail();
        $room = \App\Domain\Tariff\Models\Facility::where('type', 'room')->orderBy('code')->firstOrFail();
        $member = \App\Domain\Membership\Models\Member::where('membership_number', 'MC-001')->firstOrFail();
        $guest = \App\Domain\Access\Models\Guest::firstOrFail();

        $currentStay = \App\Domain\Facility\Models\RoomReservation::create([
            'reservation_number' => 'RES-CURRENT-STAY',
            'facility_id' => $room->id,
            'sponsor_member_id' => $member->id,
            'payer_type' => \App\Domain\Membership\Models\Member::class,
            'payer_id' => $member->id,
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->toDateString(),
            'rate_per_night_paise' => 250000,
            'total_charge_paise' => 250000,
            'paid_amount_paise' => 250000,
            'status' => 'reserved',
            'housekeeping_status' => 'clean',
            'created_by_user_id' => $reception->id,
        ]);

        $service = app(\App\Domain\Facility\Services\RoomReservationService::class);
        $service->checkIn($currentStay, $reception);

        $arrivingToday = \App\Domain\Facility\Models\RoomReservation::create([
            'reservation_number' => 'RES-ARRIVING-TODAY',
            'facility_id' => $room->id,
            'guest_id' => $guest->id,
            'sponsor_member_id' => $member->id,
            'payer_type' => \App\Domain\Access\Models\Guest::class,
            'payer_id' => $guest->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'rate_per_night_paise' => 250000,
            'total_charge_paise' => 250000,
            'paid_amount_paise' => 250000,
            'status' => 'reserved',
            'housekeeping_status' => 'clean',
            'created_by_user_id' => $reception->id,
        ]);

        $component = \Livewire\Livewire::actingAs($reception)
            ->test(\App\Filament\Pages\RoomRackPage::class)
            ->assertSee('OCCUPIED')
            ->assertSee('Arriving Today:');

        $roomData = collect($component->get('roomsData'))->firstWhere('id', $room->id);
        $this->assertEquals('occupied', $roomData['status']);
        $this->assertNotNull($roomData['upcoming_reservation']);
        $this->assertTrue($roomData['upcoming_reservation']['is_arriving_on_selected_date']);
        $this->assertEquals($guest->name, $roomData['upcoming_reservation']['guest_name']);

        $this->expectException(\App\Domain\Facility\Exceptions\FacilityNotReadyException::class);
        $service->checkIn($arrivingToday, $reception);
    }

    public function test_room_shows_dirty_immediately_after_checkout_and_returns_ready_after_housekeeping_clean(): void
    {
        $reception = \App\Models\User::where('email', 'reception@malabarclub.local')->firstOrFail();
        $room = \App\Domain\Tariff\Models\Facility::where('type', 'room')->orderBy('code')->firstOrFail();
        $member = \App\Domain\Membership\Models\Member::where('membership_number', 'MC-001')->firstOrFail();

        $res = \App\Domain\Facility\Models\RoomReservation::create([
            'reservation_number' => 'RES-CHECKOUT-DIRTY-01',
            'facility_id' => $room->id,
            'sponsor_member_id' => $member->id,
            'payer_type' => \App\Domain\Membership\Models\Member::class,
            'payer_id' => $member->id,
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->toDateString(),
            'rate_per_night_paise' => 250000,
            'total_charge_paise' => 250000,
            'paid_amount_paise' => 250000,
            'status' => 'reserved',
            'housekeeping_status' => 'clean',
            'created_by_user_id' => $reception->id,
        ]);

        app(\App\Domain\Facility\Services\RoomReservationService::class)->checkIn($res, $reception);

        $component = \Livewire\Livewire::actingAs($reception)
            ->test(\App\Filament\Pages\RoomRackPage::class)
            ->call('openCheckOut', $res->id)
            ->call('confirmCheckOut');

        $res->refresh();
        $this->assertEquals('checked_out', $res->status);
        $this->assertEquals('dirty', $res->housekeeping_status);

        $dirtyRoomData = collect($component->get('roomsData'))->firstWhere('id', $room->id);
        $this->assertEquals('vacant_dirty', $dirtyRoomData['status']);
        $component->assertSee('DIRTY')->assertSee('Needs Cleaning');

        $this->actingAs($reception);
        $dirtyPulse = collect((new \App\Filament\Widgets\FrontDeskPulseWidget())->getViewData()['roomStatuses'])->firstWhere('id', $room->id);
        $this->assertEquals('cleaning', $dirtyPulse['status']);

        $component
            ->call('openHousekeeping', $room->id, 'dirty')
            ->set('housekeepingStatus', 'clean')
            ->call('confirmHousekeeping');

        $cleanRoomData = collect($component->get('roomsData'))->firstWhere('id', $room->id);
        $this->assertEquals('vacant_ready', $cleanRoomData['status']);
        $this->assertEquals('clean', $cleanRoomData['housekeeping_status']);

        $readyPulse = collect((new \App\Filament\Widgets\FrontDeskPulseWidget())->getViewData()['roomStatuses'])->firstWhere('id', $room->id);
        $this->assertEquals('available', $readyPulse['status']);
    }
}
