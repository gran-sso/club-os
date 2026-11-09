<?php

namespace Tests\Feature;

use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Services\CourtBookingService;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use App\Filament\Pages\CourtSchedulePage;
use App\Filament\Resources\CourtBookings\Pages\CreateCourtBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class PastCourtBookingRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Facility $court;
    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->court = Facility::where('type', 'court')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
    }

    public function test_court_booking_service_blocks_booking_for_past_date(): void
    {
        $service = app(CourtBookingService::class);
        $yesterday = now()->subDay()->toDateString();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot book a badminton court slot for a past time');

        $service->bookCourt(
            court: $this->court,
            member: $this->member,
            bookingDate: $yesterday,
            startTime: '10:00:00',
            endTime: '11:00:00'
        );
    }

    public function test_court_booking_service_blocks_past_slot_earlier_today(): void
    {
        Carbon::setTestNow('2026-11-09 14:30:00');

        $service = app(CourtBookingService::class);
        $today = '2026-11-09';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot book a badminton court slot for a past time');

        // Attempt to book 08:00 AM slot when current time is 14:30 (2:30 PM)
        $service->bookCourt(
            court: $this->court,
            member: $this->member,
            bookingDate: $today,
            startTime: '08:00:00',
            endTime: '09:00:00'
        );
    }

    public function test_court_booking_service_allows_booking_for_future_slot(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');

        $service = app(CourtBookingService::class);
        $today = '2026-11-09';

        // 17:00 (5:00 PM) is in the future relative to 10:00 AM
        $booking = $service->bookCourt(
            court: $this->court,
            member: $this->member,
            bookingDate: $today,
            startTime: '17:00:00',
            endTime: '18:00:00'
        );

        $this->assertNotNull($booking);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('17:00:00', $booking->start_time);
    }

    public function test_court_schedule_page_marks_past_slots_accurately(): void
    {
        Carbon::setTestNow('2026-11-09 12:30:00');

        Livewire::actingAs($this->admin)
            ->test(CourtSchedulePage::class)
            ->set('selectedDate', '2026-11-09')
            ->assertSet('slotsData', function (array $slots) {
                // Slots starting before 12:30 should be past
                $slot06 = collect($slots)->firstWhere('start_time', '06:00');
                $slot11 = collect($slots)->firstWhere('start_time', '11:00');
                $slot12 = collect($slots)->firstWhere('start_time', '12:00');
                // Slots starting after 12:30 should be future
                $slot14 = collect($slots)->firstWhere('start_time', '14:00');
                $slot18 = collect($slots)->firstWhere('start_time', '18:00');

                return $slot06['is_past'] === true
                    && $slot11['is_past'] === true
                    && $slot12['is_past'] === true
                    && $slot14['is_past'] === false
                    && $slot18['is_past'] === false;
            });
    }

    public function test_court_schedule_page_blocks_open_book_modal_for_past_slot(): void
    {
        Carbon::setTestNow('2026-11-09 15:00:00');

        Livewire::actingAs($this->admin)
            ->test(CourtSchedulePage::class)
            ->set('selectedDate', '2026-11-09')
            ->call('openBookModal', '08:00', '09:00') // Past slot
            ->assertSet('showBookModal', false); // Modal should NOT open
    }

    public function test_court_schedule_page_allows_open_book_modal_for_future_slot(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');

        Livewire::actingAs($this->admin)
            ->test(CourtSchedulePage::class)
            ->set('selectedDate', '2026-11-09')
            ->call('openBookModal', '16:00', '17:00') // Future slot
            ->assertSet('showBookModal', true); // Modal opens
    }

    public function test_court_schedule_page_blocks_confirm_booking_for_past_slot(): void
    {
        Carbon::setTestNow('2026-11-09 16:00:00');

        Livewire::actingAs($this->admin)
            ->test(CourtSchedulePage::class)
            ->set('selectedDate', '2026-11-09')
            ->set('bookStartTime', '07:00')
            ->set('bookEndTime', '08:00')
            ->set('bookMemberId', $this->member->id)
            ->call('confirmBooking')
            ->assertSet('showBookModal', false);

        // Verify no booking was created
        $this->assertFalse(
            CourtBooking::where('booking_date', '2026-11-09')
                ->where('start_time', '07:00')
                ->exists()
        );
    }

    public function test_court_schedule_view_renders_booking_closed_for_past_slots(): void
    {
        Carbon::setTestNow('2026-11-09 14:00:00');

        $response = $this->actingAs($this->admin)->get('/admin/court-schedule');
        $response->assertStatus(200);

        // Past slots have Time Expired and Booking Closed button
        $response->assertSee('Time Expired');
        $response->assertSee('Booking Closed');
        $response->assertSee('Book This Slot'); // Future slots have Book This Slot button
    }

    public function test_filament_resource_form_rejects_past_time_slot(): void
    {
        Carbon::setTestNow('2026-11-09 15:00:00');

        Livewire::actingAs($this->admin)
            ->test(CreateCourtBooking::class)
            ->fillForm([
                'facility_id' => $this->court->id,
                'member_id' => $this->member->id,
                'booking_date' => '2026-11-09',
                'start_time' => '07:00:00', // Past slot
                'is_plan_covered' => true,
                'status' => 'confirmed',
            ])
            ->call('create')
            ->assertHasFormErrors(['start_time']);
    }

    public function test_filament_resource_form_accepts_future_time_slot(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');

        Livewire::actingAs($this->admin)
            ->test(CreateCourtBooking::class)
            ->fillForm([
                'facility_id' => $this->court->id,
                'member_id' => $this->member->id,
                'booking_date' => '2026-11-09',
                'start_time' => '18:00:00', // Future slot
                'is_plan_covered' => true,
                'status' => 'confirmed',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(
            CourtBooking::where('booking_date', '2026-11-09')
                ->where('start_time', '18:00:00')
                ->exists()
        );
    }
}

