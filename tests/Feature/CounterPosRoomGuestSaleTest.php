<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Facility\Services\RoomReservationService;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Livewire\CounterPos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CounterPosRoomGuestSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pos_can_search_and_sell_to_checked_in_room_guest_via_cash(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.com')->firstOrFail();
        $this->actingAs($cashier);

        // 1. Open a cashier shift on Bar Counter
        $barCounter = Counter::where('code', 'COUNTER-2')->firstOrFail();
        $shift = app(ShiftService::class)->openShift(
            counterId: $barCounter->id,
            userId: $cashier->id,
            openingFloatPaise: 200000,
            notes: 'Test Bar Shift'
        );

        // 2. Check in a guest to Room 101
        $member = Member::firstOrFail();
        $guestService = app(GuestService::class);
        $guest = $guestService->registerGuest([
            'first_name' => 'Rahul',
            'last_name' => 'Verma',
            'phone' => '9847199999',
        ], Member::class, $member->id);

        $room = Facility::where('code', 'ROOM-101')->firstOrFail();
        $reservationService = app(RoomReservationService::class);
        $res = $reservationService->createReservation([
            'facility_id' => $room->id,
            'guest_id' => $guest->id,
            'sponsor_member_id' => $member->id,
            'payer_type' => Guest::class,
            'payer_id' => $guest->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'rate_per_night_paise' => 150000,
            'paid_amount_paise' => 150000,
            'housekeeping_status' => 'clean',
        ], $cashier);

        $reservationService->checkIn($res, $cashier);
        $res->refresh();
        $this->assertEquals('checked_in', $res->status);

        // 3. In POS, search for "101"
        $tariff = Tariff::where('department_code', 'food')->first() 
            ?? Tariff::where('department_code', 'bar')->first();

        Livewire::test(CounterPos::class)
            ->set('searchQuery', '101')
            ->assertSee('Deluxe Room 101')
            ->assertSee('Rahul Verma')
            ->call('selectRoomGuest', $res->id)
            ->assertSet('customerType', 'room_guest')
            ->assertSet('selectedGuest.id', $guest->id)
            ->call('addToCart', $tariff->id)
            ->call('initiateCheckout', 'cash')
            ->call('confirmCheckout')
            ->assertHasNoErrors();

        // 4. Verify Bill and PaymentReceipt generated for the guest with room attribution
        $bill = Bill::where('customer_type', Guest::class)
            ->where('customer_id', $guest->id)
            ->latest()
            ->first();

        $this->assertNotNull($bill);
        $this->assertEquals('paid', $bill->status->value);
        $this->assertStringContainsString('Deluxe Room 101', $bill->notes);

        $receipt = PaymentReceipt::where('bill_id', $bill->id)->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(TenderType::CASH, $receipt->tender_type);
        $this->assertEquals('confirmed', $receipt->provider_status);
    }

    public function test_pos_can_search_and_sell_to_checked_in_room_guest_via_digital_upi(): void
    {
        $cashier = User::where('email', 'cashier1@malabarclub.com')->firstOrFail();
        $this->actingAs($cashier);

        $counter = Counter::firstOrFail();
        $shift = app(ShiftService::class)->openShift(
            counterId: $counter->id,
            userId: $cashier->id,
            openingFloatPaise: 200000,
            notes: 'Test Shift'
        );

        $member = Member::firstOrFail();
        $guest = app(GuestService::class)->registerGuest([
            'first_name' => 'Amit',
            'last_name' => 'Sharma',
            'phone' => '9895012345',
        ], Member::class, $member->id);

        $room = Facility::where('code', 'ROOM-102')->firstOrFail();
        $reservationService = app(RoomReservationService::class);
        $res = $reservationService->createReservation([
            'facility_id' => $room->id,
            'guest_id' => $guest->id,
            'sponsor_member_id' => $member->id,
            'payer_type' => Guest::class,
            'payer_id' => $guest->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'rate_per_night_paise' => 150000,
            'paid_amount_paise' => 150000,
            'housekeeping_status' => 'clean',
        ], $cashier);

        $reservationService->checkIn($res, $cashier);

        $tariff = Tariff::where('department_code', 'bar')->first() 
            ?? Tariff::where('department_code', 'food')->first();

        Livewire::test(CounterPos::class)
            ->set('searchQuery', 'Amit')
            ->call('selectRoomGuest', $res->id)
            ->call('addToCart', $tariff->id)
            ->call('initiateCheckout', 'digital')
            ->call('confirmCheckout')
            ->assertHasNoErrors();

        $receipt = PaymentReceipt::where('customer_id', $guest->id)->latest()->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(TenderType::DIGITAL, $receipt->tender_type);
    }
}

