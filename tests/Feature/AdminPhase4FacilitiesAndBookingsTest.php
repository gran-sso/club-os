<?php

namespace Tests\Feature;

use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Finance\Models\RevenueRecognitionSchedule;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhase4FacilitiesAndBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_facility_subscription_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/subscriptions/facility-subscriptions/create');
        $response->assertStatus(200);
        $response->assertSee('Member &amp; Sports Facility Selection', false);
        $response->assertSee('Validity Period &amp; Subscription Pricing', false);
    }

    public function test_admin_can_create_sports_subscription_and_revenue_schedule(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();

        $tariff = Tariff::firstOrCreate(
            ['code' => 'SPORTS-BADMINTON-M'],
            [
                'name' => 'Badminton Monthly Membership Plan',
                'department_code' => 'sports',
                'rate_paise' => 150000, // ₹1,500.00
                'tax_percent' => 0.00,
                'effective_from' => '2026-10-01',
                'is_active' => true,
            ]
        );

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Subscriptions\Pages\CreateFacilitySubscription::class)
            ->fillForm([
                'member_id' => $member->id,
                'facility_code' => 'badminton',
                'plan_type' => 'monthly',
                'tariff_id' => $tariff->id,
                'starts_at' => '2026-11-08',
                'ends_at' => '2026-12-08',
                'total_fee_paise' => 1500.00, // ₹1,500.00
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sub = FacilitySubscription::where('member_id', $member->id)
            ->where('facility_code', 'badminton')
            ->latest('id')
            ->first();

        $this->assertNotNull($sub);
        $this->assertEquals(150000, $sub->total_fee_paise, '₹1,500.00 must be stored as 150000 paise');
        $this->assertEquals('monthly', $sub->plan_type);

        // Revenue recognition schedule must be created (Scenario R02 & D14)
        $schedule = RevenueRecognitionSchedule::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($schedule);
        $this->assertEquals(150000, $schedule->total_amount_paise);
        $this->assertEquals('2026-11-08', $schedule->service_start_date->toDateString());
    }

    public function test_admin_can_access_room_reservation_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/room-reservations/create');
        $response->assertStatus(200);
        $response->assertSee('Room &amp; Occupant Identification', false);
        $response->assertSee('Stay Schedule &amp; Pricing (Indian Club Tariff)', false);
    }

    public function test_admin_can_create_room_reservation(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $room = Facility::where('type', 'room')->firstOrFail();
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\RoomReservations\Pages\CreateRoomReservation::class)
            ->fillForm([
                'facility_id' => $room->id,
                'sponsor_member_id' => $member->id,
                'check_in_date' => '2026-11-20',
                'check_out_date' => '2026-11-23', // 3 nights
                'rate_per_night_paise' => 2500.00, // ₹2,500.00 -> 250,000 paise
                'paid_amount_paise' => 7500.00, // ₹7,500.00
                'status' => 'reserved',
                'housekeeping_status' => 'clean',
                'notes' => 'Executive room booking with advance payment',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $res = RoomReservation::where('facility_id', $room->id)
            ->where('check_in_date', '2026-11-20')
            ->first();

        $this->assertNotNull($res);
        $this->assertEquals(250000, $res->rate_per_night_paise);
        $this->assertEquals(750000, $res->total_charge_paise, '3 nights * ₹2,500 = ₹7,500 (750000 paise)');
        $this->assertEquals(750000, $res->paid_amount_paise);
        $this->assertEquals('reserved', $res->status);
        $this->assertEquals('clean', $res->housekeeping_status);
    }

    public function test_admin_can_access_court_booking_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/court-bookings/create');
        $response->assertStatus(200);
        $response->assertSee('Court &amp; Member Selection', false);
        $response->assertSee('Time Slot &amp; Scheduling (60-Min Slots)', false);
    }

    public function test_admin_can_create_court_booking_slot(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $court = Facility::where('type', 'court')->firstOrFail();
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\CourtBookings\Pages\CreateCourtBooking::class)
            ->fillForm([
                'facility_id' => $court->id,
                'member_id' => $member->id,
                'booking_date' => '2026-11-25',
                'start_time' => '07:00:00',
                'is_plan_covered' => true,
                'status' => 'confirmed',
                'notes' => 'Early morning singles practice session',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = CourtBooking::where('facility_id', $court->id)
            ->where('booking_date', '2026-11-25')
            ->where('start_time', '07:00:00')
            ->first();

        $this->assertNotNull($booking);
        $this->assertEquals('08:00:00', $booking->end_time);
        $this->assertEquals($member->id, $booking->member_id);
        $this->assertEquals('confirmed', $booking->status);
    }
}

