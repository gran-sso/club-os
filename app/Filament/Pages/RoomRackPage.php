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

    protected static ?string $navigationLabel = 'Accommodation Deck';

    protected static ?string $title = 'Accommodation Deck';

    protected static ?string $slug = 'room-rack';

    protected string $view = 'filament.pages.room-rack';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'reception', 'cashier']) || $user->hasPermissionTo('rooms.view_rack')));
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
            // Check active maintenance block today
            $maintenance = FacilityMaintenanceBlock::where('facility_id', $room->id)
                ->where('status', 'active')
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>', $dateStr)
                ->first();

            // Check reservation active or checking in today
            $activeReservation = RoomReservation::where('facility_id', $room->id)
                ->whereIn('status', ['reserved', 'checked_in'])
                ->where('check_in_date', '<=', $dateStr)
                ->where('check_out_date', '>', $dateStr)
                ->with(['guest.person', 'sponsorMember.person'])
                ->first();

            // Next upcoming reservation if currently vacant
            $upcomingReservation = null;
            if (!$activeReservation) {
                $upcomingReservation = RoomReservation::where('facility_id', $room->id)
                    ->where('status', 'reserved')
                    ->where('check_in_date', '>=', $dateStr)
                    ->orderBy('check_in_date')
                    ->with(['guest.person', 'sponsorMember.person'])
                    ->first();
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

                $res = RoomReservation::where('facility_id', $room->id)
                    ->whereIn('status', ['reserved', 'checked_in'])
                    ->where('check_in_date', '<=', $fDateStr)
                    ->where('check_out_date', '>', $fDateStr)
                    ->first();

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

            // Determine primary display status
            $displayStatus = 'vacant_ready';
            $currentHousekeeping = $activeReservation?->housekeeping_status ?? 'clean';

            if ($maintenance) {
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

            $roomsData[] = [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'capacity' => $room->capacity,
                'status' => $displayStatus,
                'housekeeping_status' => $currentHousekeeping,
                'active_reservation' => $activeReservation ? [
                    'id' => $activeReservation->id,
                    'reservation_number' => $activeReservation->reservation_number,
                    'guest_name' => $activeReservation->guest?->name ?? $activeReservation->sponsorMember?->name ?? 'Guest',
                    'guest_phone' => $activeReservation->guest?->phone ?? $activeReservation->sponsorMember?->phone ?? '—',
                    'sponsor_name' => $activeReservation->sponsorMember?->name,
                    'check_in_date' => $activeReservation->check_in_date->format('d M Y'),
                    'check_out_date' => $activeReservation->check_out_date->format('d M Y'),
                    'rate_per_night_rupees' => $activeReservation->rate_per_night_paise / 100,
                    'total_charge_rupees' => $activeReservation->total_charge_paise / 100,
                    'paid_amount_rupees' => $activeReservation->paid_amount_paise / 100,
                    'balance_rupees' => max(0, ($activeReservation->total_charge_paise - $activeReservation->paid_amount_paise) / 100),
                    'is_fully_paid' => $activeReservation->isFullyPaid(),
                    'status' => $activeReservation->status,
                    'housekeeping_status' => $activeReservation->housekeeping_status,
                ] : null,
                'upcoming_reservation' => $upcomingReservation ? [
                    'guest_name' => $upcomingReservation->guest?->name ?? $upcomingReservation->sponsorMember?->name ?? 'Guest',
                    'check_in_date' => $upcomingReservation->check_in_date->format('d M Y'),
                ] : null,
                'maintenance_reason' => $maintenance?->reason,
                'forecast' => $forecastStrip,
            ];
        }

        $this->roomsData = $roomsData;
    }

    public function openCheckIn(int $reservationId): void
    {
        $res = RoomReservation::with(['facility', 'guest.person', 'sponsorMember.person'])->findOrFail($reservationId);
        $this->activeReservationId = $res->id;
        $this->activeReservationData = [
            'id' => $res->id,
            'reservation_number' => $res->reservation_number,
            'room_name' => $res->facility->name,
            'guest_name' => $res->guest?->name ?? $res->sponsorMember?->name ?? 'Guest',
            'check_in_date' => $res->check_in_date->format('d M Y'),
            'check_out_date' => $res->check_out_date->format('d M Y'),
            'total_rupees' => $res->total_charge_paise / 100,
            'paid_rupees' => $res->paid_amount_paise / 100,
            'unpaid_rupees' => max(0, ($res->total_charge_paise - $res->paid_amount_paise) / 100),
            'is_fully_paid' => $res->isFullyPaid(),
            'housekeeping_status' => $res->housekeeping_status,
        ];

        $this->showCheckInModal = true;
    }

    public function confirmCheckIn(): void
    {
        if (!$this->activeReservationId) {
            return;
        }

        try {
            $service = app(RoomReservationService::class);
            $res = RoomReservation::findOrFail($this->activeReservationId);
            $service->checkIn($res, auth()->user());

            Notification::make()
                ->title("Check-In Successful")
                ->body("Guest checked in to {$res->facility->name}. Room is now occupied.")
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

    public function openCheckOut(int $reservationId): void
    {
        $res = RoomReservation::with(['facility', 'guest.person', 'sponsorMember.person'])->findOrFail($reservationId);
        $this->activeReservationId = $res->id;
        $this->activeReservationData = [
            'id' => $res->id,
            'reservation_number' => $res->reservation_number,
            'room_name' => $res->facility->name,
            'guest_name' => $res->guest?->name ?? $res->sponsorMember?->name ?? 'Guest',
            'check_in_date' => $res->check_in_date->format('d M Y'),
            'check_out_date' => $res->check_out_date->format('d M Y'),
            'total_rupees' => $res->total_charge_paise / 100,
            'paid_rupees' => $res->paid_amount_paise / 100,
        ];

        $this->showCheckOutModal = true;
    }

    public function confirmCheckOut(): void
    {
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
        $room = Facility::findOrFail($roomId);
        $this->selectedRoomId = $room->id;
        $this->selectedRoomName = $room->name;
        $this->housekeepingStatus = $currentStatus;
        $this->showHousekeepingModal = true;
    }

    public function confirmHousekeeping(): void
    {
        if (!$this->selectedRoomId) {
            return;
        }

        // Find active reservation or latest reservation for this room
        $reservation = RoomReservation::where('facility_id', $this->selectedRoomId)
            ->whereIn('status', ['reserved', 'checked_in'])
            ->first();

        if ($reservation) {
            $service = app(RoomReservationService::class);
            $service->setHousekeepingStatus($reservation, $this->housekeepingStatus);
        } else {
            // Update latest checked_out reservation
            $latest = RoomReservation::where('facility_id', $this->selectedRoomId)->latest()->first();
            if ($latest) {
                $latest->update(['housekeeping_status' => $this->housekeepingStatus]);
            }
        }

        Notification::make()
            ->title("Housekeeping Updated")
            ->body("Room {$this->selectedRoomName} marked as " . ucfirst($this->housekeepingStatus) . ".")
            ->success()
            ->send();

        $this->showHousekeepingModal = false;
        $this->refreshRooms();
    }

    public function openPaymentModal(int $reservationId): void
    {
        $res = RoomReservation::findOrFail($reservationId);
        $this->activeReservationId = $res->id;
        $unpaidPaise = max(0, $res->total_charge_paise - $res->paid_amount_paise);
        $this->paymentAmountRupees = (int) ($unpaidPaise / 100);
        $this->paymentMode = 'cash';
        $this->showPaymentModal = true;
    }

    public function confirmAdvancePayment(): void
    {
        if (!$this->activeReservationId || $this->paymentAmountRupees <= 0) {
            return;
        }

        $service = app(RoomReservationService::class);
        $res = RoomReservation::findOrFail($this->activeReservationId);
        $amountPaise = $this->paymentAmountRupees * 100;
        $service->recordPayment($res, $amountPaise, auth()->user());

        $receipt = $this->processAccommodationBilling(
            $res,
            $amountPaise,
            $this->paymentMode,
            'Accommodation balance payment for ' . $res->reservation_number
        );
        $receiptNote = $receipt ? " • Receipt #{$receipt->receipt_number} issued to ledger" : "";

        Notification::make()
            ->title("Payment Recorded")
            ->body("Advance payment of ₹" . number_format($this->paymentAmountRupees, 2) . " credited to reservation {$res->reservation_number}{$receiptNote}.")
            ->success()
            ->send();

        $this->showPaymentModal = false;
        $this->refreshRooms();
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
        $selectedFacility = $facilityId 
            ? Facility::find($facilityId) 
            : Facility::where('type', 'room')->where('is_active', true)->first();

        $tariff = null;
        if ($selectedFacility) {
            $tariff = \App\Domain\Tariff\Models\Tariff::where('code', 'ROOM-' . strtoupper($selectedFacility->code))->first()
                ?? \App\Domain\Tariff\Models\Tariff::where('department_code', 'accommodation')->first();
        }
        $rateRupees = $tariff ? (int) ($tariff->rate_paise / 100) : 2500;

        $firstGuest = Guest::first();
        $firstMember = Member::where('status', 'active')->first();

        $this->newBooking = [
            'facility_id' => $selectedFacility?->id,
            'guest_type' => $firstGuest ? 'guest' : 'member',
            'guest_id' => $firstGuest?->id,
            'member_id' => $firstMember?->id,
            'sponsor_member_id' => $firstMember?->id,
            'check_in_date' => $this->selectedDate,
            'check_out_date' => Carbon::parse($this->selectedDate)->addDay()->toDateString(),
            'rate_per_night_rupees' => $rateRupees,
            'paid_amount_rupees' => $rateRupees,
            'payment_mode' => 'cash',
            'notes' => 'Walk-in booking via Accommodation Deck',
        ];

        $this->showNewBookingModal = true;
    }

    public function confirmNewBooking(): void
    {
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

            $guestId = $this->newBooking['guest_type'] === 'guest' ? $this->newBooking['guest_id'] : null;
            $sponsorMemberId = $this->newBooking['sponsor_member_id'] ?? null;

            $paidAmountPaise = (int) ($this->newBooking['paid_amount_rupees'] * 100);

            $reservation = $service->createReservation([
                'facility_id' => $this->newBooking['facility_id'],
                'guest_id' => $guestId,
                'sponsor_member_id' => $sponsorMemberId,
                'payer_type' => $this->newBooking['guest_type'] === 'guest' ? Guest::class : Member::class,
                'payer_id' => $this->newBooking['guest_type'] === 'guest' ? $guestId : $this->newBooking['member_id'],
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

        try {
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
                return null;
            }

            // 3. Determine payer/customer
            $customer = $reservation->guest ?? $reservation->sponsorMember;
            if (!$customer && $reservation->payer_type && $reservation->payer_id) {
                $customer = app($reservation->payer_type)->find($reservation->payer_id);
            }
            if (!$customer) {
                $customer = Member::first() ?? Guest::first();
            }

            // If paying via wallet, payer must be a member
            if ($paymentMode === 'wallet' && !($customer instanceof Member)) {
                $customer = $reservation->sponsorMember ?? Member::first();
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
                'upi' => TenderType::DIGITAL_UPI,
                'wallet' => TenderType::PREPAID_WALLET,
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
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Accommodation auto-billing error: ' . $e->getMessage());
            return null;
        }
    }
}

