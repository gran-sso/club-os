<?php

namespace App\Filament\Pages;

use App\Domain\Facility\Exceptions\SlotUnavailableException;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Services\CourtBookingService;
use App\Domain\Facility\Services\SubscriptionService;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CourtSchedulePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations & Front Desk';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Badminton Court Schedule';

    protected static ?string $title = 'Indoor Badminton Court Daily Schedule';

    protected static ?string $slug = 'court-schedule';

    protected string $view = 'filament.pages.court-schedule';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'reception', 'cashier']) || $user->checkPermissionTo('courts.view_schedule')));
    }

    public string $selectedDate = '';
    public array $slotsData = [];
    public ?int $courtFacilityId = null;
    public string $courtName = 'Indoor Wooden Badminton Court';

    // Book Slot Modal
    public bool $showBookModal = false;
    public string $bookStartTime = '';
    public string $bookEndTime = '';
    public ?int $bookMemberId = null;
    public array $entitlementPreview = [];

    // Cancel Modal
    public bool $showCancelModal = false;
    public ?int $cancelBookingId = null;
    public ?string $cancelBookingRef = null;

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->refreshSchedule();
    }

    public function updatedSelectedDate(): void
    {
        $this->refreshSchedule();
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
        $this->refreshSchedule();
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
        $this->refreshSchedule();
    }

    public function goToToday(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->refreshSchedule();
    }

    public function updatedBookMemberId(): void
    {
        $this->checkEntitlement();
    }

    public function checkEntitlement(): void
    {
        if (!$this->bookMemberId) {
            $this->entitlementPreview = [];
            return;
        }

        $member = Member::find($this->bookMemberId);
        if (!$member) {
            $this->entitlementPreview = [];
            return;
        }

        $service = app(SubscriptionService::class);
        $date = Carbon::parse($this->selectedDate);
        $entitlement = $service->checkEntitlement($member, 'badminton', $date);

        $sportsTariff = \App\Domain\Tariff\Models\Tariff::where('code', 'GUEST-SPORTS-DAY')->first()
            ?? \App\Domain\Tariff\Models\Tariff::where('department_code', 'sports')->first();
        $feeRupees = $sportsTariff ? (int) ($sportsTariff->rate_paise / 100) : 300;

        $this->entitlementPreview = [
            'is_plan_covered' => $entitlement['is_plan_covered'] ?? false,
            'plan_name' => $entitlement['plan_name'] ?? 'Pay-Per-Session',
            'fee_rupees' => ($entitlement['is_plan_covered'] ?? false) ? 0 : $feeRupees,
        ];
    }

    public function refreshSchedule(): void
    {
        $court = Facility::where('type', 'court')->first();
        if (!$court) {
            $this->slotsData = [];
            return;
        }

        $this->courtFacilityId = $court->id;
        $this->courtName = $court->name;

        $dateStr = Carbon::parse($this->selectedDate)->toDateString();

        $hours = [
            ['06:00', '07:00'],
            ['07:00', '08:00'],
            ['08:00', '09:00'],
            ['09:00', '10:00'],
            ['10:00', '11:00'],
            ['11:00', '12:00'],
            ['12:00', '13:00'],
            ['13:00', '14:00'],
            ['14:00', '15:00'],
            ['15:00', '16:00'],
            ['16:00', '17:00'],
            ['17:00', '18:00'],
            ['18:00', '19:00'],
            ['19:00', '20:00'],
            ['20:00', '21:00'],
        ];

        $slots = [];

        foreach ($hours as [$start, $end]) {
            $slotStart = Carbon::parse("{$dateStr} {$start}");
            $isPast = $slotStart->isPast();

            $booking = CourtBooking::where('facility_id', $court->id)
                ->where('booking_date', $dateStr)
                ->where('start_time', $start)
                ->where('status', 'confirmed')
                ->with('member')
                ->first();

            $slots[] = [
                'start_time' => $start,
                'end_time' => $end,
                'display_time' => Carbon::createFromFormat('H:i', $start)->format('g:i A') . ' – ' . Carbon::createFromFormat('H:i', $end)->format('g:i A'),
                'is_booked' => (bool) $booking,
                'is_past' => $isPast,
                'booking' => $booking ? [
                    'id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'member_name' => $booking->member?->name ?? 'Member',
                    'membership_number' => $booking->member?->membership_number ?? '—',
                    'member_phone' => $booking->member?->phone ?? '—',
                    'is_plan_covered' => (bool) $booking->is_plan_covered,
                    'status' => $booking->status,
                ] : null,
            ];
        }

        $this->slotsData = $slots;
    }

    public function openBookModal(string $startTime, string $endTime): void
    {
        $slotStart = Carbon::parse("{$this->selectedDate} {$startTime}");
        if ($slotStart->isPast()) {
            Notification::make()
                ->title("Time Slot Expired")
                ->body("Badminton court booking for a past time ({$slotStart->format('d M Y, g:i A')}) is not permitted.")
                ->danger()
                ->send();
            return;
        }

        $this->bookStartTime = $startTime;
        $this->bookEndTime = $endTime;
        $this->bookMemberId = Member::first()?->id;
        $this->checkEntitlement();
        $this->showBookModal = true;
    }

    public function confirmBooking(): void
    {
        if (!$this->courtFacilityId || !$this->bookMemberId) {
            return;
        }

        $slotStart = Carbon::parse("{$this->selectedDate} {$this->bookStartTime}");
        if ($slotStart->isPast()) {
            Notification::make()
                ->title("Booking Blocked")
                ->body("Badminton court booking for a past time ({$slotStart->format('d M Y, g:i A')}) is not permitted.")
                ->danger()
                ->send();
            $this->showBookModal = false;
            return;
        }

        $court = Facility::findOrFail($this->courtFacilityId);
        $member = Member::findOrFail($this->bookMemberId);
        $service = app(CourtBookingService::class);

        try {
            $booking = $service->bookCourt(
                $court,
                $member,
                $this->selectedDate,
                $this->bookStartTime,
                $this->bookEndTime,
                null,
                auth()->user()
            );

            Notification::make()
                ->title("Court Slot Confirmed")
                ->body("Booking {$booking->booking_number} confirmed for {$member->name} ({$this->bookStartTime} – {$this->bookEndTime}).")
                ->success()
                ->send();

            $this->showBookModal = false;
            $this->refreshSchedule();
        } catch (SlotUnavailableException $e) {
            Notification::make()
                ->title("Booking Failed: Slot Unavailable")
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openCancelModal(int $bookingId, string $bookingRef): void
    {
        $this->cancelBookingId = $bookingId;
        $this->cancelBookingRef = $bookingRef;
        $this->showCancelModal = true;
    }

    public function confirmCancel(): void
    {
        if (!$this->cancelBookingId) {
            return;
        }

        $booking = CourtBooking::findOrFail($this->cancelBookingId);
        $service = app(CourtBookingService::class);
        $service->cancelBooking($booking, auth()->user());

        Notification::make()
            ->title("Booking Cancelled")
            ->body("Court slot has been released and is now available for new bookings.")
            ->success()
            ->send();

        $this->showCancelModal = false;
        $this->refreshSchedule();
    }
}

