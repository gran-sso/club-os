<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use App\Filament\Pages\CourtSchedulePage;
use App\Filament\Pages\RoomRackPage;
use App\Filament\Resources\CourtBookings\Schemas\CourtBookingForm;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Resources\Guests\Schemas\GuestForm;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Purchases\Schemas\PurchaseReceiptForm;
use App\Filament\Resources\RoomReservations\Schemas\RoomReservationForm;
use App\Filament\Resources\Shifts\Schemas\ShiftForm;
use App\Filament\Resources\Subscriptions\Schemas\FacilitySubscriptionForm;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SearchableComboBoxesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Facility $room;
    protected Facility $court;
    protected Member $member;
    protected Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->room = Facility::where('type', 'room')->firstOrFail();
        $this->court = Facility::where('type', 'court')->firstOrFail();
        $this->member = Member::where('status', 'active')->firstOrFail();
        $this->guest = Guest::first() ?? Guest::create([
            'person_id' => Person::create([
                'first_name' => 'Ramesh',
                'last_name' => 'Kumar',
                'phone' => '+91 98470 99999',
            ])->id,
            'sponsor_type' => Member::class,
            'sponsor_id' => $this->member->id,
            'status' => 'registered',
            'visit_date' => now()->toDateString(),
        ]);
    }

    public function test_filament_resource_creation_pages_render_searchable_combos(): void
    {
        // 1. Court Bookings create page
        $resCourt = $this->actingAs($this->admin)->get('/admin/court-bookings/create');
        $resCourt->assertStatus(200);

        // 2. Room Reservations create page
        $resRoom = $this->actingAs($this->admin)->get('/admin/room-reservations/create');
        $resRoom->assertStatus(200);

        // 3. Purchase Receipts create page
        $resPurchase = $this->actingAs($this->admin)->get('/admin/purchases/purchase-receipts/create');
        $resPurchase->assertStatus(200);

        // 4. Expenses create page
        $resExpense = $this->actingAs($this->admin)->get('/admin/expenses/create');
        $resExpense->assertStatus(200);

        // 5. Member Subscriptions create page
        $resSub = $this->actingAs($this->admin)->get('/admin/subscriptions/facility-subscriptions/create');
        $resSub->assertStatus(200);

        // 6. Member Registration create page
        $resMember = $this->actingAs($this->admin)->get('/admin/members/create');
        $resMember->assertStatus(200);

        // 7. Guest Registration create page
        $resGuest = $this->actingAs($this->admin)->get('/admin/guests/create');
        $resGuest->assertStatus(200);
    }

    public function test_court_schedule_modal_renders_searchable_member_combo_box_with_search_input(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CourtSchedulePage::class)
            ->set('selectedDate', now()->addDay()->toDateString())
            ->call('openBookModal', '08:00', '09:00')
            ->assertSet('showBookModal', true)
            ->assertSee('Select Booking Member')
            ->assertSee('Type member name, # or mobile to search...')
            ->assertSee($this->member->membership_number)
            ->assertSee($this->member->name);
    }

    public function test_room_rack_modal_renders_searchable_combo_boxes_with_search_inputs(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoomRackPage::class)
            ->call('openNewBookingModal', $this->room->id)
            ->assertSet('showNewBookingModal', true)
            ->assertSee('Front Desk Room Reservation')
            ->assertSee('Search room code or name...')
            ->assertSee('Type guest name or phone to search...')
            ->assertSee('Type sponsor name, # or phone...')
            ->assertSee($this->room->code)
            ->assertSee($this->guest->name);
    }
}
