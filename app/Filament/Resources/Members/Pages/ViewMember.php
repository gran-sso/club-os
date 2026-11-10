<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Access\Models\AccessEvent;
use App\Domain\Access\Models\Guest;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Facility\Exceptions\RoomUnavailableException;
use App\Domain\Facility\Exceptions\SlotUnavailableException;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Models\Locker;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Facility\Services\CourtBookingService;
use App\Domain\Facility\Services\RoomReservationService;
use App\Domain\Facility\Services\SubscriptionService;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberNote;
use App\Domain\Membership\Services\CardService;
use App\Domain\Membership\Services\MemberService;
use App\Domain\Prepaid\Models\WalletTransaction;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Models\SystemSetting;
use App\Domain\Tariff\Models\Facility;
use App\Filament\Resources\Members\MemberResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.members.pages.view-member';

    public string $activeTab = 'overview';

    // Top-up Modal State
    public bool $showTopUpModal = false;
    public string $topUpAmount = '';
    public string $topUpTender = 'cash';
    public string $topUpRef = '';
    public string $topUpNotes = '';

    // RFID Card Modal State
    public bool $showCardModal = false;
    public string $newCardNumber = '';
    public string $cardReason = '';

    // Dependent Modal State
    public bool $showDependentModal = false;
    public string $depFirstName = '';
    public string $depLastName = '';
    public string $depPhone = '';
    public string $depRelationship = 'Spouse';
    public bool $depCanSpendWallet = false;

    // Status Change Modal State
    public bool $showStatusModal = false;
    public string $newStatus = 'active';
    public string $statusReason = '';

    // Invoice Details Modal State
    public bool $showInvoiceModal = false;
    public ?int $selectedBillId = null;
    public ?array $selectedInvoice = null;

    // Direct Court Booking Modal State
    public bool $showBookCourtModal = false;
    public ?int $bookCourtFacilityId = null;
    public string $bookCourtDate = '';
    public string $bookCourtStartTime = '06:00';
    public string $bookCourtEndTime = '07:00';
    public bool $courtIsPlanCovered = false;

    // Direct Room Reservation Modal State
    public bool $showBookRoomModal = false;
    public ?int $bookRoomFacilityId = null;
    public string $bookRoomCheckIn = '';
    public string $bookRoomCheckOut = '';
    public int $bookRoomRateRupees = 1500;
    public int $bookRoomDepositRupees = 1500;
    public string $bookRoomGuestName = '';
    public string $bookRoomNotes = '';

    // Affiliated Club Introduction Letter Modal State
    public bool $showIntroLetterModal = false;
    public string $letterDestinationClub = 'Trivandrum Club, Vazhuthacaud, Thiruvananthapuram';
    public string $letterVisitStart = '';
    public string $letterVisitEnd = '';
    public string $letterAccompanying = '';
    public string $letterPurpose = 'Personal & family recreation during visit to Thiruvananthapuram';
    public bool $showLetterViewModal = false;
    public ?array $generatedLetter = null;

    // Confidential Committee / Staff Notes Modal State
    public bool $showAddNoteModal = false;
    public string $noteCategory = 'general';
    public string $noteContent = '';

    // Club Locker Allocation Modal State
    public bool $showAssignLockerModal = false;
    public ?int $selectedLockerId = null;
    public int $lockerDepositRupees = 1000;
    public string $lockerKeyNumber = '';
    public string $lockerNotes = '';

    // Annual Membership Renewal Modal State
    public bool $showRenewModal = false;
    public int $renewalYears = 1;
    public int $renewalFeeRupees = 5000;
    public int $renewalAdmissionFeeRupees = 0;
    public bool $collectAdmissionFee = false;
    public string $renewalTender = 'prepaid';
    public string $renewalNotes = 'Annual Membership Subscription Renewal';

    // Bills Filter
    public string $billSearch = '';
    public string $billDepartmentFilter = 'all';

    protected $queryString = [
        'activeTab' => ['except' => 'overview'],
    ];

    public function getTitle(): string | Htmlable
    {
        /** @var Member $record */
        $record = $this->getRecord();
        return "Member Profile — {$record->name} (#{$record->membership_number})";
    }

    public function setActiveTab(string $tab): void
    {
        $validTabs = ['overview', 'wallet', 'bills', 'facilities', 'access', 'dependents'];
        if (in_array($tab, $validTabs, true)) {
            $this->activeTab = $tab;
        }
    }

    public function printStatement(): void
    {
        $this->activeTab = 'wallet';
        $this->js('setTimeout(() => window.print(), 150)');
    }

    protected function getHeaderActions(): array
    {
        /** @var Member $record */
        $record = $this->getRecord();

        return [
            Action::make('edit')
                ->label('Edit Profile')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->url(fn () => MemberResource::getUrl('edit', ['record' => $record])),

            Action::make('back')
                ->label('All Members')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => MemberResource::getUrl('index')),
        ];
    }

    // Modal Triggers
    public function openTopUpModal(): void
    {
        $this->topUpAmount = '';
        $this->topUpTender = 'cash';
        $this->topUpRef = '';
        $this->topUpNotes = '';
        $this->showTopUpModal = true;
    }

    public function closeTopUpModal(): void
    {
        $this->showTopUpModal = false;
    }

    public function processTopUp(WalletService $walletService): void
    {
        /** @var Member $record */
        $record = $this->getRecord();

        $amount = (float) $this->topUpAmount;
        if ($amount <= 0) {
            Notification::make()
                ->title('Invalid Recharge Amount')
                ->body('Please enter a recharge amount greater than ₹0.00.')
                ->danger()
                ->send();
            return;
        }

        $amountPaise = (int) round($amount * 100);
        $tender = match ($this->topUpTender) {
            'digital', 'upi', 'card' => TenderType::DIGITAL,
            default => TenderType::CASH,
        };

        $idempotencyKey = 'rcg_' . $record->id . '_' . time() . '_' . Str::random(8);
        $activeShift = Shift::where('status', 'open')->first();

        try {
            $result = $walletService->recharge(
                wallet: $record->wallet,
                amountPaise: $amountPaise,
                tenderType: $tender,
                idempotencyKey: $idempotencyKey,
                shiftId: $activeShift?->id,
                providerRef: $this->topUpRef ?: 'Admin Top-Up (' . ucfirst($this->topUpTender) . ')',
                userId: auth()->id()
            );

            $this->showTopUpModal = false;
            $this->record = $record->fresh(['wallet', 'person', 'category']);

            Notification::make()
                ->title('Prepaid Wallet Recharged')
                ->body("₹" . number_format($amount, 2) . " credited to {$record->name}'s account. New Balance: ₹" . number_format($record->fresh()->wallet->balance_paise / 100, 2))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Recharge Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openCardModal(): void
    {
        /** @var Member $record */
        $record = $this->getRecord();
        $this->newCardNumber = '';
        $this->cardReason = $record->activeCard ? 'Lost / Damaged Card Replacement' : 'Initial Card Issuance';
        $this->showCardModal = true;
    }

    public function closeCardModal(): void
    {
        $this->showCardModal = false;
    }

    public function processCardIssue(CardService $cardService): void
    {
        /** @var Member $record */
        $record = $this->getRecord();

        $cardNum = trim($this->newCardNumber);
        if (empty($cardNum)) {
            Notification::make()
                ->title('Missing Card UID')
                ->body('Please scan or enter a physical RFID Card UID.')
                ->danger()
                ->send();
            return;
        }

        try {
            if ($record->activeCard) {
                $newCard = $cardService->replaceCard($record->activeCard, $cardNum, $this->cardReason);
                $msg = "Old card blocked. New card {$newCard->card_number} issued. Wallet ledger balance is preserved.";
            } else {
                $newCard = $cardService->issueCard($record, $cardNum, $this->cardReason);
                $msg = "RFID card {$newCard->card_number} issued to {$record->name}.";
            }

            $this->showCardModal = false;
            $this->record = $record->fresh(['cards', 'activeCard']);

            Notification::make()
                ->title('RFID Card Registered')
                ->body($msg)
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Card Issuance Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openDependentModal(): void
    {
        $this->depFirstName = '';
        $this->depLastName = '';
        $this->depPhone = '';
        $this->depRelationship = 'Spouse';
        $this->depCanSpendWallet = false;
        $this->showDependentModal = true;
    }

    public function closeDependentModal(): void
    {
        $this->showDependentModal = false;
    }

    public function processDependentRegister(MemberService $memberService): void
    {
        /** @var Member $record */
        $record = $this->getRecord();

        if (empty(trim($this->depFirstName))) {
            Notification::make()
                ->title('Missing Dependent Name')
                ->body('Please enter the dependent\'s first name.')
                ->danger()
                ->send();
            return;
        }

        try {
            $dep = $memberService->registerDependent(
                $record,
                [
                    'first_name' => trim($this->depFirstName),
                    'last_name' => trim($this->depLastName) ?: null,
                    'phone' => trim($this->depPhone) ?: null,
                ],
                $this->depRelationship,
                (bool) $this->depCanSpendWallet
            );

            $this->showDependentModal = false;
            $this->record = $record->fresh(['dependents.person']);

            Notification::make()
                ->title('Family Dependent Registered')
                ->body("{$dep->person->full_name} ({$dep->relationship}) linked to Member #{$record->membership_number}.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Registration Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleDependentWalletDebit(int $dependentId): void
    {
        $dep = Dependent::where('member_id', $this->getRecord()->id)->findOrFail($dependentId);
        $dep->can_spend_wallet = !$dep->can_spend_wallet;
        $dep->save();

        $this->record = $this->getRecord()->fresh(['dependents.person']);

        $statusText = $dep->can_spend_wallet ? 'AUTHORIZED for Counter Debits' : 'RESTRICTED (Primary Member Authorization Required)';
        Notification::make()
            ->title('Dependent Wallet Permission Updated')
            ->body("{$dep->person?->full_name} is now {$statusText}.")
            ->info()
            ->send();
    }

    public function openStatusModal(): void
    {
        /** @var Member $record */
        $record = $this->getRecord();
        $this->newStatus = $record->status instanceof \BackedEnum ? $record->status->value : (string) $record->status;
        $this->statusReason = '';
        $this->showStatusModal = true;
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
    }

    public function processStatusChange(): void
    {
        /** @var Member $record */
        $record = $this->getRecord();

        if ($this->newStatus !== 'active' && empty(trim($this->statusReason))) {
            Notification::make()
                ->title('Audit Reason Required')
                ->body('Please provide an administrative reason when changing status away from Active.')
                ->danger()
                ->send();
            return;
        }

        try {
            $record->transitionStatus($this->newStatus, $this->statusReason);
            $this->showStatusModal = false;
            $this->record = $record->fresh();

            Notification::make()
                ->title('Membership Status Updated')
                ->body("Member #{$record->membership_number} status changed to " . ucfirst($this->newStatus) . ".")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Status Update Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openInvoiceModal(int $billId): void
    {
        $bill = Bill::with(['lines', 'receipts', 'shift', 'creator'])->find($billId);
        if (!$bill) {
            return;
        }

        $this->selectedBillId = $billId;
        $this->selectedInvoice = [
            'id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'business_date' => $bill->business_date?->format('d M Y') ?? $bill->created_at->format('d M Y'),
            'created_at' => $bill->created_at->format('d M Y, H:i'),
            'department_code' => $bill->department_code,
            'status' => $bill->status instanceof \BackedEnum ? $bill->status->value : (string) $bill->status,
            'gross_rupees' => $bill->gross_amount,
            'discount_rupees' => round(($bill->discount_amount_paise ?? 0) / 100, 2),
            'tax_rupees' => $bill->tax_amount,
            'net_rupees' => $bill->net_amount,
            'paid_rupees' => round(($bill->paid_amount_paise ?? 0) / 100, 2),
            'cashier' => $bill->creator?->name ?? 'Front Desk Staff',
            'lines' => $bill->lines->map(fn ($line) => [
                'item_name' => $line->item_name,
                'quantity' => (float) $line->quantity,
                'unit_price' => $line->unit_price,
                'tax_percent' => (float) $line->tax_percent,
                'tax_amount' => round(($line->tax_amount_paise ?? 0) / 100, 2),
                'line_total' => $line->line_total,
            ])->toArray(),
            'receipts' => $bill->receipts->map(fn ($rcpt) => [
                'receipt_number' => $rcpt->receipt_number,
                'tender_type' => $rcpt->tender_type instanceof \BackedEnum ? $rcpt->tender_type->value : (string) $rcpt->tender_type,
                'amount' => round($rcpt->amount_paise / 100, 2),
                'reference' => $rcpt->provider_reference,
                'timestamp' => $rcpt->created_at->format('d M Y, H:i'),
            ])->toArray(),
        ];

        $this->showInvoiceModal = true;
    }

    public function closeInvoiceModal(): void
    {
        $this->showInvoiceModal = false;
        $this->selectedBillId = null;
        $this->selectedInvoice = null;
    }

    public function getWhatsAppShareUrl(): string
    {
        /** @var Member $member */
        $member = $this->getRecord();
        $rawPhone = preg_replace('/[^0-9]/', '', $member->phone ?? '');
        if (strlen($rawPhone) === 10) {
            $rawPhone = '91' . $rawPhone;
        }

        $balance = number_format(($member->wallet?->balance_paise ?? 0) / 100, 2);
        $clubName = config('club.name', 'Malabar Club');
        $location = config('club.location', 'Vadakkencherry');

        $text = "Namaskaram {$member->name},\n\nThis is an official update from {$clubName}, {$location}.\n\nMember ID: #{$member->membership_number}\nCategory: " . ($member->category?->name ?? 'Standard') . "\nPrepaid Wallet Balance: ₹{$balance}\nStrict No-Credit Active.\n\nThank you for your valued patronage.";

        return "https://wa.me/{$rawPhone}?text=" . rawurlencode($text);
    }

    // Direct Court Booking Methods
    public function openBookCourtModal(): void
    {
        $court = Facility::where('type', 'court')->first();
        $this->bookCourtFacilityId = $court?->id;
        $this->bookCourtDate = now()->toDateString();
        $this->bookCourtStartTime = '06:00';
        $this->bookCourtEndTime = '07:00';
        $this->checkCourtPlanEntitlement();
        $this->showBookCourtModal = true;
    }

    public function closeBookCourtModal(): void
    {
        $this->showBookCourtModal = false;
    }

    public function checkCourtPlanEntitlement(): void
    {
        /** @var Member $member */
        $member = $this->getRecord();
        $service = app(SubscriptionService::class);
        $entitlement = $service->checkEntitlement($member, 'badminton', Carbon::parse($this->bookCourtDate ?: now()->toDateString()));
        $this->courtIsPlanCovered = (bool) ($entitlement['is_plan_covered'] ?? false);
    }

    public function processDirectCourtBooking(CourtBookingService $service): void
    {
        /** @var Member $member */
        $member = $this->getRecord();
        $court = Facility::findOrFail($this->bookCourtFacilityId);

        $slotStart = Carbon::parse("{$this->bookCourtDate} {$this->bookCourtStartTime}");
        if ($slotStart->isPast()) {
            Notification::make()
                ->title('Booking Blocked')
                ->body("Cannot book a badminton court slot for a past time ({$slotStart->format('d M Y, g:i A')}).")
                ->danger()
                ->send();
            return;
        }

        try {
            $booking = $service->bookCourt(
                court: $court,
                member: $member,
                bookingDate: $this->bookCourtDate,
                startTime: $this->bookCourtStartTime,
                endTime: $this->bookCourtEndTime,
                user: auth()->user()
            );

            $this->showBookCourtModal = false;
            $this->record = $member->fresh(['courtBookings.facility']);

            Notification::make()
                ->title('Badminton Court Slot Confirmed')
                ->body("Booking {$booking->booking_number} reserved for {$member->name} on " . Carbon::parse($this->bookCourtDate)->format('d M Y') . " ({$this->bookCourtStartTime} - {$this->bookCourtEndTime}).")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Booking Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // Direct Room Reservation Methods
    public function openBookRoomModal(): void
    {
        $room = Facility::where('type', 'room')->where('is_active', true)->first();
        $this->bookRoomFacilityId = $room?->id;
        $this->bookRoomCheckIn = now()->toDateString();
        $this->bookRoomCheckOut = now()->addDay()->toDateString();
        $this->bookRoomRateRupees = 1500;
        $this->bookRoomDepositRupees = 1500;
        $this->bookRoomGuestName = $this->getRecord()->name;
        $this->bookRoomNotes = '';
        $this->showBookRoomModal = true;
    }

    public function closeBookRoomModal(): void
    {
        $this->showBookRoomModal = false;
    }

    public function processDirectRoomBooking(RoomReservationService $service): void
    {
        /** @var Member $member */
        $member = $this->getRecord();

        if (!$this->bookRoomFacilityId) {
            Notification::make()->title('Room Required')->body('Please select an accommodation room.')->danger()->send();
            return;
        }

        $checkIn = Carbon::parse($this->bookRoomCheckIn);
        $checkOut = Carbon::parse($this->bookRoomCheckOut);
        if ($checkOut->lte($checkIn)) {
            Notification::make()->title('Invalid Stay Duration')->body('Check-out date must be strictly after check-in date.')->danger()->send();
            return;
        }

        try {
            $res = $service->createReservation([
                'facility_id' => $this->bookRoomFacilityId,
                'sponsor_member_id' => $member->id,
                'payer_type' => Member::class,
                'payer_id' => $member->id,
                'check_in_date' => $this->bookRoomCheckIn,
                'check_out_date' => $this->bookRoomCheckOut,
                'rate_per_night_paise' => (int) round($this->bookRoomRateRupees * 100),
                'paid_amount_paise' => (int) round($this->bookRoomDepositRupees * 100),
                'notes' => $this->bookRoomNotes ?: 'Reserved directly from Member Profile by staff',
            ], auth()->user());

            $this->showBookRoomModal = false;
            $this->record = $member->fresh(['roomReservations.facility']);

            Notification::make()
                ->title('Room Reserved Successfully')
                ->body("Reservation {$res->reservation_number} confirmed for {$member->name} in {$res->facility->name} ({$checkIn->format('d M')} - {$checkOut->format('d M Y')}).")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Reservation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // Affiliated Club Introduction Letter Methods
    public function openIntroLetterModal(): void
    {
        $this->letterDestinationClub = 'Trivandrum Club, Vazhuthacaud, Thiruvananthapuram';
        $this->letterVisitStart = now()->addDays(2)->toDateString();
        $this->letterVisitEnd = now()->addDays(9)->toDateString();
        $this->letterAccompanying = 'Spouse & Dependents';
        $this->letterPurpose = 'Personal & family recreation during visit to Thiruvananthapuram';
        $this->showIntroLetterModal = true;
    }

    public function closeIntroLetterModal(): void
    {
        $this->showIntroLetterModal = false;
    }

    public function generateIntroLetter(): void
    {
        /** @var Member $member */
        $member = $this->getRecord();

        if ($member->status !== MemberStatus::ACTIVE) {
            Notification::make()
                ->title('Letter Prohibited')
                ->body("Introduction letters can only be issued to members in Active standing. Current status is '" . ucfirst($member->status->value) . "'.")
                ->danger()
                ->send();
            return;
        }

        $letterRef = 'MC-REC-' . date('Y') . '-' . strtoupper(Str::random(6));

        $this->generatedLetter = [
            'reference_number' => $letterRef,
            'date' => now()->format('d F Y'),
            'destination_club' => trim($this->letterDestinationClub),
            'member_name' => $member->name,
            'membership_number' => $member->membership_number,
            'category_name' => $member->category?->name ?? 'Ordinary Member',
            'joined_date' => $member->joined_at ? $member->joined_at->format('d M Y') : 'Founding Member',
            'valid_until' => $member->valid_until ? $member->valid_until->format('d M Y') : 'Lifetime',
            'visit_start' => Carbon::parse($this->letterVisitStart)->format('d M Y'),
            'visit_end' => Carbon::parse($this->letterVisitEnd)->format('d M Y'),
            'accompanying' => trim($this->letterAccompanying),
            'purpose' => trim($this->letterPurpose),
            'secretary_name' => auth()->user()?->name ?? 'Honorary Secretary',
        ];

        $this->showIntroLetterModal = false;
        $this->showLetterViewModal = true;

        Notification::make()
            ->title('Introduction Letter Generated')
            ->body("Official letter {$letterRef} issued for visit to {$this->letterDestinationClub}.")
            ->success()
            ->send();
    }

    public function closeLetterViewModal(): void
    {
        $this->showLetterViewModal = false;
        $this->generatedLetter = null;
    }

    // Confidential Committee / Staff Notes Methods
    public function openAddNoteModal(): void
    {
        $this->noteCategory = 'general';
        $this->noteContent = '';
        $this->showAddNoteModal = true;
    }

    public function closeAddNoteModal(): void
    {
        $this->showAddNoteModal = false;
    }

    public function processAddNote(): void
    {
        /** @var Member $member */
        $member = $this->getRecord();

        if (empty(trim($this->noteContent))) {
            Notification::make()
                ->title('Note Required')
                ->body('Please enter administrative note remarks.')
                ->danger()
                ->send();
            return;
        }

        MemberNote::create([
            'member_id' => $member->id,
            'created_by_user_id' => auth()->id(),
            'category' => $this->noteCategory,
            'note' => trim($this->noteContent),
        ]);

        $this->showAddNoteModal = false;
        $this->record = $member->fresh(['committeeNotes.author']);

        Notification::make()
            ->title('Administrative Note Recorded')
            ->body('Internal note recorded for managing committee and secretarial audit.')
            ->success()
            ->send();
    }

    // Gate Access Security Methods
    public function blockActiveCard(): void
    {
        /** @var Member $member */
        $member = $this->getRecord();
        $activeCard = $member->activeCard;

        if (!$activeCard) {
            Notification::make()->title('No Active Card')->body('This member does not have an active RFID card to block.')->warning()->send();
            return;
        }

        $activeCard->update([
            'status' => CardStatus::BLOCKED,
            'blocked_at' => now(),
            'notes' => 'Emergency access block initiated by admin from Member Profile',
        ]);

        MemberNote::create([
            'member_id' => $member->id,
            'created_by_user_id' => auth()->id(),
            'category' => 'disciplinary',
            'note' => "RFID Card {$activeCard->card_number} was blocked immediately via Turnstile Access Control.",
        ]);

        $this->record = $member->fresh(['activeCard', 'cards', 'committeeNotes.author']);

        Notification::make()
            ->title('Gate Access Revoked')
            ->body("Card {$activeCard->card_number} has been blocked from all biometric turnstiles and readers.")
            ->danger()
            ->send();
    }

    public function unblockCard(int $cardId): void
    {
        /** @var Member $member */
        $member = $this->getRecord();
        $card = Card::where('id', $cardId)->where('cardable_type', Member::class)->where('cardable_id', $member->id)->firstOrFail();

        if ($member->activeCard && $member->activeCard->id !== $card->id) {
            Notification::make()->title('Card Conflict')->body('Another card is already active. Replace the card instead.')->warning()->send();
            return;
        }

        $card->update([
            'status' => CardStatus::ACTIVE,
            'blocked_at' => null,
        ]);

        MemberNote::create([
            'member_id' => $member->id,
            'created_by_user_id' => auth()->id(),
            'category' => 'general',
            'note' => "RFID Card {$card->card_number} access privileges were restored by admin.",
        ]);

        $this->record = $member->fresh(['activeCard', 'cards', 'committeeNotes.author']);

        Notification::make()
            ->title('Gate Access Restored')
            ->body("Card {$card->card_number} is now active.")
            ->success()
            ->send();
    }

    // Club Locker Allocation Methods
    public function openAssignLockerModal(): void
    {
        $firstAvailable = Locker::where('status', 'available')->first();
        $this->selectedLockerId = $firstAvailable?->id;
        $this->lockerDepositRupees = $firstAvailable ? (int) $firstAvailable->deposit_rupees : 1000;
        $this->lockerKeyNumber = $firstAvailable?->key_number ?? '';
        $this->lockerNotes = 'Assigned from Member Profile';
        $this->showAssignLockerModal = true;
    }

    public function closeAssignLockerModal(): void
    {
        $this->showAssignLockerModal = false;
    }

    public function processAssignLocker(): void
    {
        /** @var Member $member */
        $member = $this->getRecord();

        if (!$this->selectedLockerId) {
            Notification::make()->title('Locker Required')->body('Please select an available locker.')->danger()->send();
            return;
        }

        $locker = Locker::findOrFail($this->selectedLockerId);
        if ($locker->status !== 'available') {
            Notification::make()->title('Locker Unavailable')->body("Locker {$locker->locker_number} is not available.")->danger()->send();
            return;
        }

        $locker->update([
            'status' => 'occupied',
            'assigned_member_id' => $member->id,
            'assigned_at' => now(),
            'allocated_until' => now()->addYear(),
            'deposit_amount_paise' => (int) round($this->lockerDepositRupees * 100),
            'key_number' => $this->lockerKeyNumber ?: $locker->key_number,
            'notes' => $this->lockerNotes ?: $locker->notes,
        ]);

        MemberNote::create([
            'member_id' => $member->id,
            'created_by_user_id' => auth()->id(),
            'category' => 'general',
            'note' => "Club Locker {$locker->locker_number} ({$locker->zone}) assigned to member. Key #{$locker->key_number}, Deposit ₹{$this->lockerDepositRupees}.",
        ]);

        $this->showAssignLockerModal = false;
        $this->record = $member->fresh(['lockers', 'committeeNotes.author']);

        Notification::make()
            ->title('Locker Assigned')
            ->body("Locker {$locker->locker_number} ({$locker->zone}) successfully assigned to {$member->name}.")
            ->success()
            ->send();
    }

    public function vacateLocker(int $lockerId): void
    {
        /** @var Member $member */
        $member = $this->getRecord();
        $locker = Locker::where('id', $lockerId)->where('assigned_member_id', $member->id)->firstOrFail();

        $lockerNumber = $locker->locker_number;
        $depositRupees = $locker->deposit_rupees;

        $locker->update([
            'status' => 'available',
            'assigned_member_id' => null,
            'assigned_at' => null,
            'allocated_until' => null,
            'notes' => "Vacated by {$member->name} on " . now()->format('d M Y'),
        ]);

        MemberNote::create([
            'member_id' => $member->id,
            'created_by_user_id' => auth()->id(),
            'category' => 'general',
            'note' => "Club Locker {$lockerNumber} surrendered by member. Key returned. Security deposit refundable: ₹{$depositRupees}.",
        ]);

        $this->record = $member->fresh(['lockers', 'committeeNotes.author']);

        Notification::make()
            ->title('Locker Surrendered')
            ->body("Locker {$lockerNumber} vacated and returned to available inventory.")
            ->success()
            ->send();
    }

    // Annual Membership Renewal Methods
    public function openRenewModal(): void
    {
        /** @var Member $member */
        $member = $this->getRecord();
        $feePaise = $member->category?->annual_fee_paise ?? 500000;
        $this->renewalYears = 1;
        $this->renewalFeeRupees = (int) round($feePaise / 100);

        $admissionPaise = $member->admission_fee_paise ?: ($member->category?->admission_fee_paise ?? 0);
        $this->renewalAdmissionFeeRupees = (int) round($admissionPaise / 100);
        $this->collectAdmissionFee = $member->isAdmissionFeePending();

        $totalPaise = $feePaise + ($this->collectAdmissionFee ? $admissionPaise : 0);
        $walletBal = $member->wallet?->balance_paise ?? 0;
        $this->renewalTender = ($walletBal >= $totalPaise) ? 'prepaid' : 'cash';

        if ($this->collectAdmissionFee && $member->fee_status === 'pending') {
            $this->renewalNotes = 'One-Time Admission Fee & Annual Subscription Dues Settlement';
        } elseif ($this->collectAdmissionFee) {
            $this->renewalNotes = 'One-Time Admission Fee Settlement';
        } else {
            $this->renewalNotes = 'Annual Subscription Renewal (' . date('Y') . '-' . (date('Y') + 1) . ')';
        }
        
        $this->showRenewModal = true;
    }

    public function closeRenewModal(): void
    {
        $this->showRenewModal = false;
    }

    public function processRenewMembership(WalletService $walletService): void
    {
        /** @var Member $member */
        $member = $this->getRecord();

        $annualPaise = (int) round($this->renewalFeeRupees * 100);
        $admissionPaise = $this->collectAdmissionFee ? (int) round($this->renewalAdmissionFeeRupees * 100) : 0;
        $totalPaise = $annualPaise + $admissionPaise;

        if ($totalPaise <= 0) {
            Notification::make()->title('Invalid Amount')->body('Please specify a positive fee amount to settle.')->danger()->send();
            return;
        }

        $receiptNumber = null;

        // If paying by prepaid wallet, enforce sufficient funds!
        if ($this->renewalTender === 'prepaid') {
            $availablePaise = ($member->wallet?->balance_paise ?? 0) - ($member->wallet?->reserved_paise ?? 0);
            if ($availablePaise < $totalPaise) {
                Notification::make()
                    ->title('Insufficient Prepaid Balance')
                    ->body(sprintf("Settlement requires ₹%.2f, but available prepaid balance is only ₹%.2f. Overdrafts or negative balances are not permitted.", $totalPaise / 100, $availablePaise / 100))
                    ->danger()
                    ->send();
                return;
            }

            try {
                $notes = [];
                if ($admissionPaise > 0) {
                    $notes[] = sprintf("One-time admission fee: ₹%.2f", $admissionPaise / 100);
                }
                if ($annualPaise > 0) {
                    $notes[] = sprintf("Annual subscription dues: ₹%.2f (%d yr)", $annualPaise / 100, $this->renewalYears);
                }
                $noteString = implode(', ', $notes);

                $walletService->debit(
                    wallet: $member->wallet,
                    amountPaise: $totalPaise,
                    referenceType: 'membership_renewal',
                    referenceId: $member->id,
                    notes: $noteString,
                    userId: auth()->id()
                );
            } catch (\Exception $e) {
                Notification::make()->title('Payment Failed')->body($e->getMessage())->danger()->send();
                return;
            }
        } else {
            $tender = match ($this->renewalTender) {
                'digital' => TenderType::DIGITAL,
                'bank_transfer' => TenderType::BANK_TRANSFER,
                'cheque' => TenderType::CHEQUE,
                default => TenderType::CASH,
            };

            $yearMonth = now()->format('Ym');
            $receiptCount = PaymentReceipt::where('receipt_number', 'LIKE', "RCP-{$yearMonth}-%")->count() + 1;
            $receiptNumber = sprintf("RCP-%s-%05d", $yearMonth, $receiptCount);

            PaymentReceipt::create([
                'receipt_number' => $receiptNumber,
                'shift_id' => null,
                'bill_id' => null,
                'wallet_transaction_id' => null,
                'customer_type' => Member::class,
                'customer_id' => $member->id,
                'tender_type' => $tender,
                'amount_paise' => $totalPaise,
                'provider_name' => 'secretariat_manual',
                'provider_reference' => "MEM-FEE-{$member->membership_number}",
                'provider_status' => 'confirmed',
                'idempotency_key' => (string) Str::uuid(),
                'confirmed_at' => now(),
                'created_by_user_id' => auth()->id(),
            ]);
        }

        // If collecting admission fee
        if ($this->collectAdmissionFee && $admissionPaise > 0) {
            $member->admission_fee_status = 'paid';
            $member->admission_fee_paise = $admissionPaise;
            $member->admission_fee_paid_at = now();
            if ($receiptNumber) {
                $member->admission_fee_receipt_number = $receiptNumber;
            }
        }

        // Extend validity if annual dues were paid
        if ($annualPaise > 0) {
            $baseDate = ($member->valid_until && $member->valid_until->isFuture()) ? $member->valid_until : now();
            $newValidUntil = $baseDate->copy()->addYears($this->renewalYears)->endOfDay();

            $member->valid_until = $newValidUntil;
            $member->fee_status = 'paid';
            $member->last_fee_paid_at = now();
            if ($receiptNumber) {
                $member->last_fee_receipt_number = $receiptNumber;
            }
        } elseif ($member->admission_fee_status === 'paid' && !$member->valid_until) {
            $member->valid_until = now()->addYear()->endOfDay();
            $member->fee_status = 'paid';
        }

        if ($member->status === MemberStatus::EXPIRED && ($member->valid_until && $member->valid_until->isFuture())) {
            $member->status = MemberStatus::ACTIVE;
        }
        $member->save();

        $receiptNote = $receiptNumber ? " (Receipt #{$receiptNumber})" : "";
        MemberNote::create([
            'member_id' => $member->id,
            'created_by_user_id' => auth()->id(),
            'category' => 'general',
            'note' => sprintf(
                "Statutory fees settled: Total ₹%.2f (Admission: ₹%.2f, Annual Dues: ₹%.2f). Tender: %s%s.",
                $totalPaise / 100,
                $admissionPaise / 100,
                $annualPaise / 100,
                ucfirst($this->renewalTender),
                $receiptNote
            ),
        ]);

        $this->showRenewModal = false;
        $this->record = $member->fresh(['wallet', 'committeeNotes.author']);

        Notification::make()
            ->title('Statutory Fees Settled')
            ->body(sprintf("Total ₹%.2f recorded for %s (#%s). Membership active%s.", 
                $totalPaise / 100, 
                $member->name, 
                $member->membership_number, 
                $member->valid_until ? ' until ' . $member->valid_until->format('d M Y') : ''
            ))
            ->success()
            ->send();
    }

    public function getViewData(): array
    {
        /** @var Member $member */
        $member = $this->getRecord()->load([
            'person',
            'category',
            'activeCard',
            'cards',
            'wallet',
            'lockers',
            'dependents.person',
            'sponsoredGuests.person',
        ]);

        $wallet = $member->wallet;
        $balanceRupees = ($wallet?->balance_paise ?? 0) / 100;
        $reservedRupees = ($wallet?->reserved_paise ?? 0) / 100;
        $availableRupees = max(0, $balanceRupees - $reservedRupees);

        // Lifetime KPI Statistics
        $totalRechargePaise = 0;
        $totalSpendPaise = 0;
        if ($wallet) {
            $totalRechargePaise = (int) WalletTransaction::where('wallet_account_id', $wallet->id)
                ->where('type', 'recharge')
                ->sum('amount_paise');

            $totalSpendPaise = (int) abs(WalletTransaction::where('wallet_account_id', $wallet->id)
                ->whereIn('type', ['debit', 'counter_debit', 'bill_payment'])
                ->where('amount_paise', '<', 0)
                ->sum('amount_paise'));
        }

        $totalBillsCount = $member->bills()->count();
        $totalPaidBillsPaise = (int) $member->bills()->where('status', 'paid')->sum('net_amount_paise');

        // Recent Wallet Ledger
        $walletEntries = $wallet ? $wallet->entries()->latest()->take(50)->get() : collect();

        // Bills Query with search & department filter
        $billsQuery = $member->bills()->with(['lines', 'receipts'])->latest();
        if ($this->billDepartmentFilter !== 'all') {
            $billsQuery->where('department_code', $this->billDepartmentFilter);
        }
        if (!empty(trim($this->billSearch))) {
            $term = '%' . trim($this->billSearch) . '%';
            $billsQuery->where('bill_number', 'like', $term);
        }
        $bills = $billsQuery->take(50)->get();

        // Facilities Data
        $subscriptions = $member->subscriptions()->with('tariff')->latest()->get();
        $courtBookings = $member->courtBookings()->with('facility')->latest()->take(30)->get();
        $roomReservations = $member->roomReservations()->with(['facility', 'guest'])->latest()->take(20)->get();
        $assignedLockers = $member->lockers()->get();
        $facilitiesCount = $subscriptions->count() + $courtBookings->count() + $roomReservations->count() + $assignedLockers->count();

        // Dependents & Sponsored Guests
        $dependents = $member->dependents()->with('person')->get();
        $sponsoredGuests = $member->sponsoredGuests()->with(['person', 'passes'])->latest()->take(30)->get();

        // Confidential Committee & Staff Notes
        $committeeNotes = $member->committeeNotes()->with('author')->get();

        // Facility Options for Direct Bookings & Lockers
        $availableRooms = Facility::where('type', 'room')->where('is_active', true)->get();
        $courtFacility = Facility::where('type', 'court')->first();
        $availableLockers = Locker::where('status', 'available')->get();

        // Gate & Turnstile Access Events
        $cardNumbers = $member->cards->pluck('card_number')->filter()->all();
        $accessEvents = AccessEvent::where(function ($q) use ($member, $cardNumbers) {
            $q->where('person_id', $member->person_id);
            if (!empty($cardNumbers)) {
                $q->orWhereIn('card_number', $cardNumbers);
            }
        })->latest('recorded_at')->take(50)->get();

        // One-Time Admission Fee Info
        $admissionFeePaise = $member->admission_fee_paise ?: ($member->category?->admission_fee_paise ?? 0);
        $admissionFeeRupees = round($admissionFeePaise / 100, 2);
        $isPaid = $member->hasPaidAdmissionFee();
        $admissionStatus = $isPaid ? 'paid' : ($member->admission_fee_status ?? 'pending');

        $admissionFeeInfo = [
            'admission_fee_rupees' => $admissionFeeRupees,
            'status' => $admissionStatus,
            'is_paid' => $isPaid,
            'paid_at_formatted' => $member->admission_fee_paid_at ? $member->admission_fee_paid_at->format('d M Y') : ($isPaid && $member->joined_at ? $member->joined_at->format('d M Y') : 'Pending Payment'),
            'receipt_number' => $member->admission_fee_receipt_number,
        ];

        // Annual Dues Status Info
        $annualFeePaise = $member->category?->annual_fee_paise ?? 500000;
        $annualFeeRupees = round($annualFeePaise / 100, 2);
        $daysRemaining = $member->valid_until ? (int) now()->diffInDays($member->valid_until, false) : 9999;
        
        $feeStatus = $member->fee_status ?? ($member->valid_until ? 'paid' : 'pending');
        
        $duesStatus = 'active';
        if ($feeStatus === 'pending') {
            $duesStatus = 'fee_pending';
        } elseif ($member->valid_until && $member->valid_until->isPast()) {
            $duesStatus = 'expired';
        } elseif ($daysRemaining <= 30) {
            $duesStatus = 'expiring_soon';
        }

        $annualDuesInfo = [
            'annual_fee_rupees' => $annualFeeRupees,
            'fee_status' => $feeStatus,
            'status' => $duesStatus,
            'days_remaining' => $daysRemaining,
            'valid_until_formatted' => $member->valid_until ? $member->valid_until->format('d M Y') : ($feeStatus === 'pending' ? 'Pending Settlement' : 'Continuous / Lifetime'),
            'last_renewed_formatted' => $member->last_fee_paid_at ? $member->last_fee_paid_at->format('d M Y') : ($feeStatus === 'paid' && $member->joined_at ? $member->joined_at->format('d M Y') : 'Unrecorded / Pending'),
            'last_receipt_number' => $member->last_fee_receipt_number,
        ];

        // F&B Consumption Analytics
        $memberBillIds = $member->bills->pluck('id');
        $departmentSpend = [
            'restaurant' => 0.0,
            'bar' => 0.0,
            'sports' => 0.0,
            'card_room' => 0.0,
        ];
        if ($memberBillIds->isNotEmpty()) {
            $deptAggregates = BillLine::whereIn('bill_id', $memberBillIds)
                ->select('department_code', DB::raw('SUM(line_total_paise) as total_paise'))
                ->groupBy('department_code')
                ->get();

            foreach ($deptAggregates as $agg) {
                $departmentSpend[$agg->department_code] = round($agg->total_paise / 100, 2);
            }
        }

        $favoriteItems = collect();
        if ($memberBillIds->isNotEmpty()) {
            $favoriteItems = BillLine::whereIn('bill_id', $memberBillIds)
                ->select('item_name', 'department_code', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(line_total_paise) as total_paise'))
                ->groupBy('item_name', 'department_code')
                ->orderByDesc('total_qty')
                ->take(5)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->item_name,
                    'department' => $row->department_code,
                    'quantity' => (float) $row->total_qty,
                    'total_rupees' => round($row->total_paise / 100, 2),
                ]);
        }

        return [
            'member' => $member,
            'balanceRupees' => $balanceRupees,
            'reservedRupees' => $reservedRupees,
            'availableRupees' => $availableRupees,
            'totalRechargeRupees' => $totalRechargePaise / 100,
            'totalSpendRupees' => $totalSpendPaise / 100,
            'totalBillsCount' => $totalBillsCount,
            'totalPaidBillsRupees' => $totalPaidBillsPaise / 100,
            'facilitiesCount' => $facilitiesCount,
            'walletEntries' => $walletEntries,
            'bills' => $bills,
            'subscriptions' => $subscriptions,
            'courtBookings' => $courtBookings,
            'roomReservations' => $roomReservations,
            'assignedLockers' => $assignedLockers,
            'availableLockers' => $availableLockers,
            'accessEvents' => $accessEvents,
            'admissionFeeInfo' => $admissionFeeInfo,
            'annualDuesInfo' => $annualDuesInfo,
            'departmentSpend' => $departmentSpend,
            'favoriteItems' => $favoriteItems,
            'dependents' => $dependents,
            'sponsoredGuests' => $sponsoredGuests,
            'committeeNotes' => $committeeNotes,
            'availableRooms' => $availableRooms,
            'courtFacility' => $courtFacility,
            'whatsappUrl' => $this->getWhatsAppShareUrl(),
            'lostCardFee' => (float) SystemSetting::get('lost_card_fee', 150.00),
        ];
    }
}

