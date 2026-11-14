<?php

namespace App\Filament\Pages;

use App\Domain\Access\Models\Guest;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Facility\Exceptions\FacilityNotReadyException;
use App\Domain\Facility\Exceptions\PaymentRequiredException;
use App\Domain\Facility\Exceptions\RoomUnavailableException;
use App\Domain\Facility\Models\FacilityMaintenanceBlock;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Facility\Services\RoomReservationService;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoomRackPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations & Front Desk';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Guest Rooms (101–105)';

    protected static ?string $title = 'Guest Rooms (101–105)';

    protected static ?string $slug = 'room-rack';

    protected string $view = 'filament.pages.room-rack';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('rooms.view_rack'));
    }

    public string $selectedDate = '';
    public array $roomsData = [];
    public array $forecastDates = [];

    // Modals state
    public bool $showCheckInModal = false;
    public bool $showCheckOutModal = false;
    public bool $showHousekeepingModal = false;
    public bool $showNewBookingModal = false;
    public bool $showPaymentModal = false;

    public ?int $activeReservationId = null;
    public ?array $activeReservationData = null;

    public ?int $selectedRoomId = null;
    public string $selectedRoomName = '';
    public string $housekeepingStatus = 'clean';
    public string $maintenanceReason = 'Scheduled maintenance / repair';
    public string $maintenanceEndDate = '';

    // Payment modal state
    public int $paymentAmountRupees = 0;
    public string $paymentMode = 'cash';

    // New Booking form state
    public array $newBooking = [
        'facility_id' => null,
        'guest_type' => 'guest', // 'guest' or 'member'
        'guest_id' => null,
        'member_id' => null,
        'sponsor_member_id' => null,
        'check_in_date' => '',
        'check_out_date' => '',
        'rate_per_night_rupees' => 1500,
        'paid_amount_rupees' => 1500,
        'payment_mode' => 'cash', // 'cash', 'upi', 'wallet'
        'notes' => '',
    ];

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->refreshRooms();
    }

    public function updatedSelectedDate(): void
    {
        $this->refreshRooms();
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
        $this->refreshRooms();
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
        $this->refreshRooms();
    }

    public function goToToday(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->refreshRooms();
    }

    public function refreshRooms(): void
    {
        $date = Carbon::parse($this->selectedDate)->startOfDay();
        $dateStr = $date->toDateString();
        $todayStr = now()->toDateString();
        $isViewingPastOrToday = $dateStr <= $todayStr;

        // 7-day forecast header
        $this->forecastDates = [];
        for ($i = 0; $i < 7; $i++) {
            $day = (clone $date)->addDays($i);
            $this->forecastDates[] = [
                'date' => $day->toDateString(),
                'day' => $day->format('D'),
                'label' => $day->format('d M'),
                'is_today' => $day->isToday(),
            ];
        }

        $rooms = Facility::where('type', 'room')->orderBy('code')->get();
        $roomsData = [];

        foreach ($rooms as $room) {
            // Check active maintenance block for selected date
            $maintenance = FacilityMaintenanceBlock::where('facility_id', $room->id)
                ->where('status', 'active')
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>', $dateStr)
                ->first();

            // Any guest still checked in on or before the selected date physically occupies
            // the room until Reception completes Check-Out (including checkout due today & overstays).
            $occupiedReservation = null;
            if ($isViewingPastOrToday) {
                $occupiedReservation = RoomReservation::where('facility_id', $room->id)
                    ->where('status', 'checked_in')
                    ->where('check_in_date', '<=', $dateStr)
                    ->orderByDesc('check_in_date')
                    ->orderByDesc('id')
                    ->with(['guest.person', 'sponsorMember.person', 'payer'])
                    ->first();
            }

            $activeReservation = $occupiedReservation ?? RoomReservation::where('facility_id', $room->id)
                ->whereIn('status', ['reserved', 'checked_in'])
                ->where('check_in_date', '<=', $dateStr)
                ->where('check_out_date', '>', $dateStr)
                ->orderByRaw("CASE WHEN status = 'checked_in' THEN 0 ELSE 1 END")
                ->orderBy('check_in_date')
                ->with(['guest.person', 'sponsorMember.person', 'payer'])
                ->first();

            // Next arriving/upcoming reserved booking (including back-to-back check-ins on check-out day)
            $upcomingReservation = null;
            if (!$activeReservation || $activeReservation->status === 'checked_in') {
                $upcomingQuery = RoomReservation::where('facility_id', $room->id)
                    ->where('status', 'reserved')
                    ->where('check_out_date', '>', $dateStr)
                    ->orderBy('check_in_date')
                    ->with(['guest.person', 'sponsorMember.person', 'payer']);

                if ($activeReservation) {
                    $upcomingQuery->where('id', '!=', $activeReservation->id);
                }

                $upcomingReservation = $upcomingQuery->first();
            }

            // 7-day occupancy strip
            $forecastStrip = [];
            foreach ($this->forecastDates as $fDate) {
                $fDateStr = $fDate['date'];

                $isMaint = FacilityMaintenanceBlock::where('facility_id', $room->id)
                    ->where('status', 'active')
                    ->where('start_date', '<=', $fDateStr)
                    ->where('end_date', '>', $fDateStr)
                    ->exists();

                $res = null;
                if ($fDate['is_today']) {
                    $res = RoomReservation::where('facility_id', $room->id)
                        ->where('status', 'checked_in')
                        ->where('check_in_date', '<=', $fDateStr)
                        ->orderByDesc('check_in_date')
                        ->orderByDesc('id')
                        ->first();
                }

                if (!$res) {
                    $res = RoomReservation::where('facility_id', $room->id)
                        ->whereIn('status', ['reserved', 'checked_in'])
                        ->where('check_in_date', '<=', $fDateStr)
                        ->where('check_out_date', '>', $fDateStr)
                        ->orderByRaw("CASE WHEN status = 'checked_in' THEN 0 ELSE 1 END")
                        ->first();
                }

                $status = 'free';
                if ($isMaint) {
                    $status = 'maintenance';
                } elseif ($res) {
                    $status = $res->status === 'checked_in' ? 'occupied' : 'reserved';
                }

                $forecastStrip[] = [
                    'date' => $fDateStr,
                    'status' => $status,
                ];
            }

            $latestCheckedOut = RoomReservation::where('facility_id', $room->id)
                ->where('status', 'checked_out')
                ->orderByDesc('checked_out_at')
                ->orderByDesc('id')
                ->first();

            // Determine primary display status & physical housekeeping state
            $displayStatus = 'vacant_ready';
            $currentHousekeeping = 'clean';

            if ($activeReservation && $activeReservation->status === 'checked_in') {
                $currentHousekeeping = $activeReservation->housekeeping_status ?? $room->housekeeping_status ?? 'clean';
            } elseif ($date->isToday()) {
                if (($room->housekeeping_status ?? 'clean') === 'dirty'
                    || $activeReservation?->housekeeping_status === 'dirty'
                    || $latestCheckedOut?->housekeeping_status === 'dirty') {
                    $currentHousekeeping = 'dirty';
                }
            } elseif ($activeReservation) {
                $currentHousekeeping = $activeReservation->housekeeping_status ?? 'clean';
            }

            if (!$room->is_active || $maintenance) {
                $displayStatus = 'maintenance';
            } elseif ($activeReservation) {
                if ($activeReservation->status === 'checked_in') {
                    $displayStatus = 'occupied';
                } else {
                    $displayStatus = 'reserved';
                }
            } elseif ($currentHousekeeping === 'dirty') {
                $displayStatus = 'vacant_dirty';
            }

            $checkOutDateStr = $activeReservation?->check_out_date?->toDateString();
            $isCheckedIn = $activeReservation?->status === 'checked_in';
            $isCheckoutDueToday = $isCheckedIn && $checkOutDateStr === $todayStr;
            $isOverstay = $isCheckedIn && $checkOutDateStr !== null && $checkOutDateStr < $todayStr;

            $roomsData[] = [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'capacity' => $room->capacity,
                'status' => $displayStatus,
                'housekeeping_status' => $currentHousekeeping,
                'is_inactive' => !$room->is_active,
                'has_maintenance_block' => $maintenance !== null,
                'active_reservation' => $activeReservation ? [
                    'id' => $activeReservation->id,
                    'reservation_number' => $activeReservation->reservation_number,
                    'occupant_type' => $activeReservation->occupant_type,
                    'occupant_badge' => $activeReservation->occupant_badge,
                    'guest_name' => $activeReservation->occupant_name,
                    'guest_phone' => $activeReservation->occupant_phone ?? '—',
                    'sponsor_name' => $activeReservation->guest ? $activeReservation->sponsorMember?->name : null,
                    'check_in_date' => $activeReservation->check_in_date->format('d M Y'),
                    'check_out_date' => $activeReservation->check_out_date->format('d M Y'),
                    'rate_per_night_rupees' => $activeReservation->rate_per_night_paise / 100,
                    'total_charge_rupees' => $activeReservation->total_charge_paise / 100,
                    'paid_amount_rupees' => $activeReservation->paid_amount_paise / 100,
                    'balance_rupees' => max(0, ($activeReservation->total_charge_paise - $activeReservation->paid_amount_paise) / 100),
                    'is_fully_paid' => $activeReservation->isFullyPaid(),
                    'is_checkout_due_today' => $isCheckoutDueToday,
                    'is_overstay' => $isOverstay,
                    'status' => $activeReservation->status,
                    'housekeeping_status' => $activeReservation->housekeeping_status,
                ] : null,
                'upcoming_reservation' => $upcomingReservation ? [
                    'id' => $upcomingReservation->id,
                    'reservation_number' => $upcomingReservation->reservation_number,
                    'occupant_type' => $upcomingReservation->occupant_type,
                    'occupant_badge' => $upcomingReservation->occupant_badge,
                    'guest_name' => $upcomingReservation->occupant_name,
                    'guest_phone' => $upcomingReservation->occupant_phone ?? '—',
                    'sponsor_name' => $upcomingReservation->guest ? $upcomingReservation->sponsorMember?->name : null,
                    'check_in_date' => $upcomingReservation->check_in_date->format('d M Y'),
                    'is_arriving_on_selected_date' => $upcomingReservation->check_in_date->toDateString() <= $dateStr,
                ] : null,
                'maintenance_reason' => !$room->is_active
                    ? 'Room deactivated in Master Setup (Rooms & Facilities).'
                    : $maintenance?->reason,
                'maintenance_until' => $maintenance?->end_date?->format('d M Y'),
                'forecast' => $forecastStrip,
            ];
        }

        $this->roomsData = $roomsData;
    }

    public function openCheckIn(int $reservationId): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        $res = RoomReservation::with(['facility', 'guest.person', 'sponsorMember.person', 'payer'])->findOrFail($reservationId);
        $this->activeReservationId = $res->id;
        $unpaidPaise = max(0, $res->total_charge_paise - $res->paid_amount_paise);
        $this->paymentAmountRupees = (int) ($unpaidPaise / 100);
        $this->paymentMode = 'cash';

        $this->activeReservationData = [
            'id' => $res->id,
            'reservation_number' => $res->reservation_number,
            'room_name' => $res->facility->name,
            'guest_name' => $res->occupant_name,
            'occupant_type' => $res->occupant_type,
            'occupant_badge' => $res->occupant_badge,
            'check_in_date' => $res->check_in_date->format('d M Y'),
            'check_out_date' => $res->check_out_date->format('d M Y'),
            'total_rupees' => $res->total_charge_paise / 100,
            'paid_rupees' => $res->paid_amount_paise / 100,
            'unpaid_rupees' => $unpaidPaise / 100,
            'is_fully_paid' => $res->isFullyPaid(),
            'housekeeping_status' => $res->housekeeping_status,
        ];

        $this->showCheckInModal = true;
    }

    public function confirmCheckIn(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        if (!$this->activeReservationId) {
            return;
        }

        try {
            $service = app(RoomReservationService::class);
            $res = RoomReservation::findOrFail($this->activeReservationId);
            $service->checkIn($res, auth()->user());

            Notification::make()
                ->title("Check-In Successful")
                ->body("Guest checked in to {$res->facility->name}. Key handed over. Room is now occupied.")
                ->success()
                ->send();

            $this->showCheckInModal = false;
            $this->refreshRooms();
        } catch (PaymentRequiredException $e) {
            Notification::make()
                ->title("Check-In Blocked: Payment Required")
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (FacilityNotReadyException $e) {
            Notification::make()
                ->title("Check-In Blocked: Room Not Ready")
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }

    /**
     * Atomically settle outstanding balance and check in the guest with key issue.
     */
    public function confirmCheckInWithPayment(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        if (!$this->activeReservationId) {
            return;
        }

        try {
            $service = app(RoomReservationService::class);
            $res = RoomReservation::findOrFail($this->activeReservationId);

            $unpaidPaise = max(0, $res->total_charge_paise - $res->paid_amount_paise);
            $amountPaise = (int) round($this->paymentAmountRupees * 100);

            if ($amountPaise <= 0) {
                Notification::make()
                    ->title("Invalid Payment Amount")
                    ->body("Payment amount must be greater than zero.")
                    ->danger()
                    ->send();
                return;
            }

            if ($amountPaise < $unpaidPaise) {
                Notification::make()
                    ->title("Check-In Blocked: Full Payment Required")
                    ->body("Club policy strictly prohibits credit check-ins. The full balance of ₹" . number_format($unpaidPaise / 100, 2) . " must be collected before issuing the key.")
                    ->danger()
                    ->send();
                return;
            }

            // Cap to unpaid balance to avoid accidental overbilling
            $amountPaise = min($amountPaise, $unpaidPaise);

            // 1. Process accommodation billing receipt first
            $receipt = $this->processAccommodationBilling(
                $res,
                $amountPaise,
                $this->paymentMode,
                'Full balance settlement on check-in for ' . $res->reservation_number
            );

            // 2. Record payment towards reservation
            $service->recordPayment($res, $amountPaise, auth()->user());
            $res->refresh();

            // 3. Complete check-in and issue key
            $service->checkIn($res, auth()->user());

            $receiptNote = $receipt ? " • Receipt #{$receipt->receipt_number} issued to ledger" : "";

            Notification::make()
                ->title("Check-In Successful & Key Issued")
                ->body("Balance of ₹" . number_format($amountPaise / 100, 2) . " collected via " . strtoupper($this->paymentMode) . "{$receiptNote}. Guest checked in to {$res->facility->name}.")
                ->success()
                ->send();

            $this->showCheckInModal = false;
            $this->refreshRooms();
        } catch (FacilityNotReadyException $e) {
            Notification::make()
                ->title("Check-In Blocked: Room Not Ready")
                ->body($e->getMessage())
                ->warning()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title("Check-In Processing Error")
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openCheckOut(int $reservationId): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        $res = RoomReservation::with(['facility', 'guest.person', 'sponsorMember.person', 'payer'])->findOrFail($reservationId);
        $this->activeReservationId = $res->id;
        $this->activeReservationData = [
            'id' => $res->id,
            'reservation_number' => $res->reservation_number,
            'room_name' => $res->facility->name,
            'guest_name' => $res->occupant_name,
            'occupant_type' => $res->occupant_type,
            'occupant_badge' => $res->occupant_badge,
            'check_in_date' => $res->check_in_date->format('d M Y'),
            'check_out_date' => $res->check_out_date->format('d M Y'),
            'total_rupees' => $res->total_charge_paise / 100,
            'paid_rupees' => $res->paid_amount_paise / 100,
        ];

        $this->showCheckOutModal = true;
    }

    public function confirmCheckOut(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        if (!$this->activeReservationId) {
            return;
        }

        $service = app(RoomReservationService::class);
        $res = RoomReservation::findOrFail($this->activeReservationId);
        $service->checkOut($res, auth()->user());

        Notification::make()
            ->title("Check-Out Completed")
            ->body("Guest checked out from {$res->facility->name}. Room has been set to Dirty for housekeeping cleaning.")
            ->success()
            ->send();

        $this->showCheckOutModal = false;
        $this->refreshRooms();
    }

    public function openHousekeeping(int $roomId, string $currentStatus): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        $room = Facility::findOrFail($roomId);

        if (! $room->is_active) {
            Notification::make()
                ->title('Room Disabled in Facility Master')
                ->body("Re-enable {$room->name} under Club Master Setup -> Rooms & Facilities to resume operations.")
                ->warning()
                ->send();

            return;
        }

        $dateStr = Carbon::parse($this->selectedDate)->toDateString();
        $activeBlock = FacilityMaintenanceBlock::where('facility_id', $room->id)
            ->where('status', 'active')
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>', $dateStr)
            ->first();

        $this->resetValidation();
        $this->selectedRoomId = $room->id;
        $this->selectedRoomName = $room->name;
        $this->housekeepingStatus = in_array($currentStatus, ['clean', 'dirty', 'maintenance'], true)
            ? $currentStatus
            : 'clean';
        $this->maintenanceReason = $activeBlock?->reason ?? 'Scheduled maintenance / repair';
        $this->maintenanceEndDate = $activeBlock?->end_date?->toDateString()
            ?? Carbon::parse($dateStr)->addDay()->toDateString();
        $this->showHousekeepingModal = true;
    }

    public function confirmHousekeeping(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        if (!$this->selectedRoomId) {
            return;
        }

        $rules = [
            'housekeepingStatus' => 'required|in:clean,dirty,maintenance',
        ];
        if ($this->housekeepingStatus === 'maintenance') {
            $rules['maintenanceReason'] = 'nullable|string|max:200';
            $rules['maintenanceEndDate'] = 'required|date|after:' . $this->selectedDate;
        }

        $this->validate($rules);

        try {
            app(RoomReservationService::class)->updateRoomOperationalStatus(
                facilityId: $this->selectedRoomId,
                targetStatus: $this->housekeepingStatus,
                dateStr: $this->selectedDate,
                maintenanceReason: $this->maintenanceReason,
                maintenanceEndDate: $this->maintenanceEndDate,
                user: auth()->user(),
            );

            $statusLabel = match ($this->housekeepingStatus) {
                'clean' => 'Clean & Ready',
                'dirty' => 'Needs Cleaning (Dirty)',
                'maintenance' => 'Under Maintenance (until ' . Carbon::parse($this->maintenanceEndDate)->format('d M Y') . ')',
                default => ucfirst($this->housekeepingStatus),
            };

            Notification::make()
                ->title('Room Status Updated')
                ->body("Room {$this->selectedRoomName} is now {$statusLabel}.")
                ->success()
                ->send();

            $this->showHousekeepingModal = false;
            $this->refreshRooms();
        } catch (FacilityNotReadyException|RoomUnavailableException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Cannot Change Room Status')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openPaymentModal(int $reservationId): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        $res = RoomReservation::findOrFail($reservationId);
        $this->activeReservationId = $res->id;
        $unpaidPaise = max(0, $res->total_charge_paise - $res->paid_amount_paise);
        $this->paymentAmountRupees = (int) ($unpaidPaise / 100);
        $this->paymentMode = 'cash';
        $this->showCheckInModal = false;
        $this->showPaymentModal = true;
    }

    public function confirmAdvancePayment(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        if (!$this->activeReservationId || $this->paymentAmountRupees <= 0) {
            return;
        }

        try {
            $service = app(RoomReservationService::class);
            $res = RoomReservation::findOrFail($this->activeReservationId);
            $amountPaise = (int) round($this->paymentAmountRupees * 100);

            // 1. Process accommodation billing & receipt
            $receipt = $this->processAccommodationBilling(
                $res,
                $amountPaise,
                $this->paymentMode,
                'Accommodation advance/balance payment for ' . $res->reservation_number
            );

            // 2. Record payment on reservation
            $service->recordPayment($res, $amountPaise, auth()->user());
            $res->refresh();

            $receiptNote = $receipt ? " • Receipt #{$receipt->receipt_number} issued to ledger" : "";

            Notification::make()
                ->title("Payment Recorded")
                ->body("Payment of ₹" . number_format($this->paymentAmountRupees, 2) . " credited to reservation {$res->reservation_number}{$receiptNote}.")
                ->success()
                ->send();

            $this->showPaymentModal = false;

            // If check-in modal is open, refresh activeReservationData in real time
            if ($this->showCheckInModal && $this->activeReservationData) {
                $unpaidPaise = max(0, $res->total_charge_paise - $res->paid_amount_paise);
                $this->paymentAmountRupees = (int) ($unpaidPaise / 100);
                $this->activeReservationData['paid_rupees'] = $res->paid_amount_paise / 100;
                $this->activeReservationData['unpaid_rupees'] = $unpaidPaise / 100;
                $this->activeReservationData['is_fully_paid'] = $res->isFullyPaid();
            }

            $this->refreshRooms();
        } catch (\Throwable $e) {
            Notification::make()
                ->title("Payment Processing Error")
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updatedNewBookingFacilityId($facilityId): void
    {
        $facility = Facility::find($facilityId);
        if ($facility) {
            $tariff = \App\Domain\Tariff\Models\Tariff::where('code', 'ROOM-' . strtoupper($facility->code))->first()
                ?? \App\Domain\Tariff\Models\Tariff::where('department_code', 'accommodation')->first();
            if ($tariff) {
                $rate = (int) ($tariff->rate_paise / 100);
                $this->newBooking['rate_per_night_rupees'] = $rate;
                $this->newBooking['paid_amount_rupees'] = $rate;
            }
        }
    }

    public function openNewBookingModal(?int $facilityId = null): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        $selectedFacility = $facilityId 
            ? Facility::find($facilityId) 
            : Facility::where('type', 'room')->where('is_active', true)->first();

        $tariff = null;
        if ($selectedFacility) {
            $tariff = \App\Domain\Tariff\Models\Tariff::where('code', 'ROOM-' . strtoupper($selectedFacility->code))->first()
                ?? \App\Domain\Tariff\Models\Tariff::where('department_code', 'accommodation')->first();
        }
        $rateRupees = $tariff ? (int) ($tariff->rate_paise / 100) : 2500;

        $this->newBooking = [
            'facility_id' => $selectedFacility?->id,
            'guest_type' => 'guest',
            'guest_id' => null,
            'member_id' => null,
            'sponsor_member_id' => null,
            'check_in_date' => $this->selectedDate,
            'check_out_date' => Carbon::parse($this->selectedDate)->addDay()->toDateString(),
            'rate_per_night_rupees' => $rateRupees,
            'paid_amount_rupees' => $rateRupees,
            'payment_mode' => 'cash',
            'notes' => '',
        ];

        $this->showNewBookingModal = true;
    }

    public function confirmNewBooking(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('rooms.manage_reservations'), 403);
        $rules = [
            'newBooking.facility_id' => 'required|exists:facilities,id',
            'newBooking.check_in_date' => 'required|date',
            'newBooking.check_out_date' => 'required|date|after:newBooking.check_in_date',
            'newBooking.rate_per_night_rupees' => 'required|numeric|min:0',
            'newBooking.paid_amount_rupees' => 'required|numeric|min:0',
        ];

        if (($this->newBooking['guest_type'] ?? '') === 'guest') {
            $rules['newBooking.guest_id'] = 'required|exists:guests,id';
        } else {
            $rules['newBooking.member_id'] = 'required|exists:members,id';
        }

        $this->validate($rules);

        try {
            $service = app(RoomReservationService::class);

            $isMemberBooking = ($this->newBooking['guest_type'] ?? '') === 'member';
            $guestId = $isMemberBooking ? null : ($this->newBooking['guest_id'] ?? null);
            $sponsorMemberId = $isMemberBooking
                ? ($this->newBooking['member_id'] ?? null)
                : ($this->newBooking['sponsor_member_id'] ?? null);

            $paidAmountPaise = (int) ($this->newBooking['paid_amount_rupees'] * 100);

            $reservation = $service->createReservation([
                'facility_id' => $this->newBooking['facility_id'],
                'guest_id' => $guestId,
                'sponsor_member_id' => $sponsorMemberId,
                'payer_type' => $isMemberBooking ? Member::class : Guest::class,
                'payer_id' => $isMemberBooking ? $this->newBooking['member_id'] : $guestId,
                'check_in_date' => $this->newBooking['check_in_date'],
                'check_out_date' => $this->newBooking['check_out_date'],
                'rate_per_night_paise' => (int) ($this->newBooking['rate_per_night_rupees'] * 100),
                'paid_amount_paise' => $paidAmountPaise,
                'housekeeping_status' => 'clean',
                'notes' => $this->newBooking['notes'],
            ], auth()->user());

            $receipt = null;
            if ($paidAmountPaise > 0) {
                $receipt = $this->processAccommodationBilling(
                    $reservation,
                    $paidAmountPaise,
                    $this->newBooking['payment_mode'] ?? 'cash',
                    'Stay advance on reservation ' . $reservation->reservation_number
                );
            }

            $receiptNote = $receipt ? " • Receipt #{$receipt->receipt_number} posted to ledger" : "";

            Notification::make()
                ->title("Reservation Created")
                ->body("Reservation {$reservation->reservation_number} confirmed for Room {$reservation->facility->name}{$receiptNote}.")
                ->success()
                ->send();

            $this->showNewBookingModal = false;
            $this->refreshRooms();
        } catch (RoomUnavailableException $e) {
            Notification::make()
                ->title("Room Unavailable")
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title("Reservation Creation Error")
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Process financial billing for accommodation stay/advance.
     * Generates an official Bill, PaymentReceipt, and double-entry journal (1010/1020 -> 4030).
     */
    protected function processAccommodationBilling(
        RoomReservation $reservation,
        int $amountPaise,
        string $paymentMode = 'cash',
        string $notes = ''
    ): ?PaymentReceipt {
        if ($amountPaise <= 0) {
            return null;
        }

        $user = auth()->user() ?? User::first();

        // 1. Locate active shift for user, or Counter-1 (Reception), or any active shift
        $shift = Shift::where('user_id', $user->id)->where('status', ShiftStatus::OPEN)->first()
            ?? Shift::where('status', ShiftStatus::OPEN)->whereHas('counter', fn($q) => $q->where('code', 'COUNTER-1'))->first()
            ?? Shift::where('status', ShiftStatus::OPEN)->first();

        // 2. Auto-open a front-desk shift on Counter-1 if no shift is currently open
        if (!$shift) {
            $receptionCounter = Counter::where('code', 'COUNTER-1')->first() ?? Counter::first();
            if ($receptionCounter) {
                $shift = app(ShiftService::class)->openShift(
                    counterId: $receptionCounter->id,
                    userId: $user->id,
                    openingFloatPaise: 0,
                    notes: 'Front Desk Accommodation Deck auto-shift'
                );
            }
        }

        if (!$shift) {
            throw new \RuntimeException("No active shift available at the front desk to process the accommodation receipt.");
        }

        // 3. Determine payer/customer
        $customer = null;
        if ($reservation->payer_type && $reservation->payer_id) {
            $customer = app($reservation->payer_type)->find($reservation->payer_id);
        }
        if (!$customer) {
            $customer = $reservation->guest ?? $reservation->sponsorMember;
        }
        if (!$customer) {
            $customer = Member::first() ?? Guest::first();
        }

        // If paying via wallet, payer must be a member
        if (in_array($paymentMode, ['wallet', 'prepaid'])) {
            if (!($customer instanceof Member)) {
                $customer = $reservation->sponsorMember;
            }
            if (!$customer || !($customer instanceof Member)) {
                throw new \InvalidArgumentException("Prepaid wallet payments require an active sponsoring Club Member.");
            }
        }

        $billingService = app(BillingService::class);
        $facility = $reservation->facility;

        $bill = $billingService->createDraftBill(
            shift: $shift,
            customer: $customer,
            departmentCode: 'accommodation',
            items: [
                [
                    'item_name' => "Room Stay - {$facility->name} ({$reservation->reservation_number})",
                    'quantity' => 1,
                    'unit_price_paise' => $amountPaise,
                    'tax_percent' => 0,
                ]
            ],
            idempotencyKey: 'res_bill_' . $reservation->id . '_' . time() . '_' . Str::random(6),
            notes: $notes ?: "Accommodation charge for {$reservation->reservation_number}",
            userId: $user->id
        );

        $tenderType = match ($paymentMode) {
            'upi', 'digital' => TenderType::DIGITAL,
            'wallet', 'prepaid' => TenderType::PREPAID,
            default => TenderType::CASH,
        };

        $result = $billingService->checkout(
            bill: $bill,
            tenders: [
                [
                    'tender_type' => $tenderType,
                    'amount_paise' => $amountPaise,
                ]
            ],
            userId: $user->id
        );

        return $result['receipts'][0] ?? null;
    }
}

