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
}
