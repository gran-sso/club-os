<?php

namespace App\Livewire;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\PromotionService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Integrations\Contracts\ReceiptPrinterInterface;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\CardService;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Billing\Models\PosHeldTab;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Tariff;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CounterPos extends Component
{
    // Shift state
    public ?Shift $activeShift = null;
    public int $selectedCounterId = 1;
    public float $openingFloat = 2000.00;
    public float $closingCashCounted = 0.00;
    public ?string $shiftNotes = null;

    // Customer & Search state
    public string $searchQuery = '';
    public array $customerSearchResults = [];
    public int $highlightedCustomerIndex = 0;
    public ?Member $selectedMember = null;
    public ?Card $activeCard = null;
    public int $memberWalletBalancePaise = 0;

    // Resident Room Guest state
    public ?RoomReservation $selectedRoomReservation = null;
    public ?Guest $selectedGuest = null;
    public string $customerType = 'member'; // 'member' or 'room_guest'

    // Held Tabs / Running Bar Tabs State
    public ?int $activeHeldTabId = null;
    public ?string $tabTableIdentifier = null; // e.g. "Table 4", "Bar 2"
    public ?string $tabNotes = null;
    public bool $showHoldTabModal = false;
    public bool $showHeldTabsListModal = false;
    public string $heldTabSearch = '';

    // View Mode: 'grid' (Touch Cards) or 'compact' (Searchable List)
    public string $viewMode = 'grid';

    // Item Search state
    public string $itemSearch = '';
    public array $itemSearchResults = [];
    public int $highlightedItemIndex = 0;

    // Multiple Serving Modal state
    public bool $showServingModal = false;
    public ?int $servingInventoryItemId = null;
    public string $servingItemName = '';
    public string $servingItemStockText = '';
    public array $servingItemOptions = [];
    public int $highlightedServingIndex = 0;

    // Cart & Sales state
    public array $cart = [];
    public float $cartGross = 0.00;
    public float $cartDiscount = 0.00;
    public float $cartTax = 0.00;
    public float $cartNet = 0.00;

    // Recharge state
    public float $rechargeAmount = 1000.00;
    public string $rechargeTender = 'cash';
    public ?string $rechargeRef = null;

    // Guest Registration state for guest passes
    public bool $isGuestSale = false;
    public string $guestFirstName = '';
    public string $guestLastName = '';
    public string $guestPhone = '';

    // Payment Confirmation Modal & Idempotency state
    public bool $showPaymentConfirmModal = false;
    public ?string $pendingTenderType = null;
    public bool $isProcessingPayment = false;

    // Receipt Modal state
    public bool $showReceiptModal = false;
    public string $receiptContent = '';
    public ?string $lastReceiptNumber = null;
    public bool $resetCustomerOnReceiptClose = false;

    // Notifications
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless((bool) auth('web')->user()?->hasClubPermission('counter.operate'), 403, 'Access denied. You do not have Counter POS access.');

        $this->openingFloat = (float) \App\Domain\Shared\Models\SystemSetting::get('default_opening_float', 2000.00);
        $this->refreshActiveShift();
    }

    public function refreshActiveShift(): void
    {
        $this->activeShift = Shift::where('user_id', auth()->id())
            ->where('status', ShiftStatus::OPEN)
            ->with(['counter', 'cashMovements', 'paymentReceipts'])
            ->first();
    }

    public function openShift(ShiftService $shiftService): void
    {
        $this->clearMessages();

        if (! auth('web')->user()?->hasClubPermission('counter.operate')) {
            $this->errorMessage = 'Access denied. Counter shift operations require counter.operate permission.';
            return;
        }

        try {
            $this->activeShift = $shiftService->openShift(
                counterId: $this->selectedCounterId,
                userId: auth()->id(),
                openingFloatPaise: (int) round($this->openingFloat * 100),
                notes: $this->shiftNotes
            );
            $this->successMessage = "Shift {$this->activeShift->shift_number} opened successfully with ₹" . number_format($this->openingFloat, 2) . " opening cash.";
            $this->dispatch('focus-customer-search');
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function closeShift(ShiftService $shiftService): void
    {
        $this->clearMessages();

        if (! auth('web')->user()?->hasClubPermission('counter.operate')) {
            $this->errorMessage = 'Access denied. Closing shifts requires counter.operate permission.';
            return;
        }

        if (!$this->activeShift) {
            $this->errorMessage = "No active shift to close.";
            return;
        }

        $openTabsCount = PosHeldTab::forShift($this->activeShift->id)->open()->count();
        if ($openTabsCount > 0) {
            $this->errorMessage = "Cannot close shift: There are {$openTabsCount} unsettled running bar tab(s). Settle or void all open tabs before shift reconciliation.";
            return;
        }

        try {
            $closed = $shiftService->closeShift(
                shift: $this->activeShift,
                closingCashCountedPaise: (int) round($this->closingCashCounted * 100),
                notes: $this->shiftNotes
            );

            $this->successMessage = "Shift {$closed->shift_number} closed. Expected: ₹" . number_format($closed->expected_cash_paise / 100, 2) . ", Counted: ₹" . number_format($closed->closing_cash_counted_paise / 100, 2) . ", Discrepancy: ₹" . number_format($closed->discrepancy_paise / 100, 2);
            $this->activeShift = null;
            $this->resetMember();
            $this->clearCart();
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ==========================================
    // Customer Search & Identification
    // ==========================================

    public function updatedSearchQuery(string $value): void
    {
        $query = trim($value);
        if (strlen($query) < 1) {
            $this->customerSearchResults = [];
            $this->highlightedCustomerIndex = 0;
            return;
        }

        $results = [];

        // 1. Search Active Checked-In Room Occupants (Resident Guests & Staying Members)
        $cleanNum = preg_replace('/[^0-9]/', '', $query);
        $roomReservations = RoomReservation::with(['facility', 'guest.person', 'sponsorMember.person', 'sponsorMember.wallet'])
            ->where('status', 'checked_in')
            ->where(function ($q) use ($query, $cleanNum) {
                $q->whereHas('facility', function ($f) use ($query, $cleanNum) {
                    $f->where('code', 'LIKE', "%{$query}%")
                        ->orWhere('name', 'LIKE', "%{$query}%");
                    if (!empty($cleanNum)) {
                        $f->orWhere('code', 'LIKE', "%{$cleanNum}%");
                    }
                })
                ->orWhereHas('guest.person', function ($p) use ($query) {
                    $p->where('first_name', 'LIKE', "%{$query}%")
                        ->orWhere('last_name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', 'LIKE', "{$query}%");
                })
                ->orWhereHas('sponsorMember.person', function ($p) use ($query) {
                    $p->where('first_name', 'LIKE', "%{$query}%")
                        ->orWhere('last_name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', 'LIKE', "{$query}%");
                });
            })
            ->take(3)
            ->get();

        foreach ($roomReservations as $rRes) {
            $guestName = $rRes->guest?->name ?? $rRes->sponsorMember?->name ?? 'Resident Guest';
            $results[] = [
                'type' => 'room_guest',
                'id' => $rRes->id,
                'room_code' => $rRes->facility->code,
                'room_name' => $rRes->facility->name,
                'guest_name' => $guestName,
                'phone' => $rRes->guest?->phone ?? $rRes->sponsorMember?->phone ?? '—',
                'sponsor_name' => $rRes->sponsorMember ? ($rRes->sponsorMember->name . ' (' . $rRes->sponsorMember->membership_number . ')') : 'Direct Booking',
                'card_number' => $rRes->facility->code,
                'membership_number' => 'ROOM ' . str_replace('ROOM-', '', $rRes->facility->code),
                'name' => "{$guestName} ({$rRes->facility->name})",
                'category' => 'Resident Room Guest',
                'status' => 'checked_in',
                'balance_paise' => (int) ($rRes->sponsorMember?->wallet?->balance_paise ?? 0),
            ];
        }

        // 2. Search Members by card, membership_number, name, or phone
        $members = Member::with(['person', 'category', 'wallet', 'activeCard'])
            ->where('membership_number', 'LIKE', "{$query}%")
            ->orWhereHas('person', function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_name', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "{$query}%");
            })
            ->orWhereHas('cards', function ($q) use ($query) {
                $q->where('card_number', 'LIKE', "{$query}%");
            })
            ->take(5)
            ->get();

        foreach ($members as $m) {
            $results[] = [
                'type' => 'member',
                'id' => $m->id,
                'membership_number' => $m->membership_number,
                'name' => ($m->person->first_name ?? '') . ' ' . ($m->person->last_name ?? ''),
                'phone' => $m->person->phone ?? '',
                'card_number' => $m->activeCard?->card_number ?? 'No Card',
                'category' => $m->category->name ?? 'Standard',
                'status' => $m->status->value,
                'balance_paise' => (int) ($m->wallet?->balance_paise ?? 0),
            ];
        }

        $this->customerSearchResults = $results;
        $this->highlightedCustomerIndex = 0;
    }

    public function navigateCustomerResults(string $direction): void
    {
        $count = count($this->customerSearchResults);
        if ($count === 0) {
            return;
        }

        if ($direction === 'down') {
            $this->highlightedCustomerIndex = ($this->highlightedCustomerIndex + 1) % $count;
        } elseif ($direction === 'up') {
            $this->highlightedCustomerIndex = ($this->highlightedCustomerIndex - 1 + $count) % $count;
        }
    }

    public function selectHighlightedCustomer(): void
    {
        if (isset($this->customerSearchResults[$this->highlightedCustomerIndex])) {
            $match = $this->customerSearchResults[$this->highlightedCustomerIndex];
            if (($match['type'] ?? 'member') === 'room_guest') {
                $this->selectRoomGuest($match['id']);
            } else {
                $this->selectCustomerById($match['id']);
            }
        } else {
            $this->searchMember(app(CardService::class));
        }
    }

    public function selectCustomerById(int $memberId): void
    {
        $this->clearMessages();
        $member = Member::find($memberId);
        if ($member) {
            $this->selectedRoomReservation = null;
            $this->selectedGuest = null;
            $this->customerType = 'member';
            $this->selectMemberRecord($member);
            $this->customerSearchResults = [];
            $this->dispatch('focus-item-search');
        }
    }

    public function selectRoomGuest(int $reservationId): void
    {
        $this->clearMessages();
        $res = RoomReservation::with(['facility', 'guest.person', 'sponsorMember.wallet', 'sponsorMember.person', 'sponsorMember.category'])->find($reservationId);
        if ($res) {
            $this->selectedRoomReservation = $res;
            $this->customerType = 'room_guest';
            $this->selectedGuest = $res->guest;
            $this->selectedMember = $res->sponsorMember;
            $this->activeCard = null;
            $this->memberWalletBalancePaise = (int) ($res->sponsorMember?->wallet?->balance_paise ?? 0);
            $guestName = $res->guest?->name ?? $res->sponsorMember?->name ?? 'Guest';
            $this->searchQuery = "{$res->facility->name} - {$guestName}";
            $this->customerSearchResults = [];
            $this->recalculateCart();
            $this->dispatch('focus-item-search');
        }
    }

    public function searchMember(CardService $cardService): void
    {
        $this->clearMessages();
        $query = trim($this->searchQuery);

        if (empty($query)) {
            $this->resetMember();
            return;
        }

        // If results dropdown has highlighted item, use it
        if (!empty($this->customerSearchResults) && isset($this->customerSearchResults[$this->highlightedCustomerIndex])) {
            $match = $this->customerSearchResults[$this->highlightedCustomerIndex];
            if (($match['type'] ?? 'member') === 'room_guest') {
                $this->selectRoomGuest($match['id']);
            } else {
                $this->selectCustomerById($match['id']);
            }
            return;
        }

        // 1. Try find exact card first
        $card = $cardService->findCard($query);
        if ($card && $card->cardable instanceof Member) {
            $this->selectedRoomReservation = null;
            $this->selectedGuest = null;
            $this->customerType = 'member';
            $this->selectMemberRecord($card->cardable, $card);
            $this->customerSearchResults = [];
            $this->dispatch('focus-item-search');
            return;
        }

        // 2. Try match checked-in room number or resident guest
        $cleanNum = preg_replace('/[^0-9]/', '', $query);
        $roomRes = RoomReservation::with(['facility', 'guest.person', 'sponsorMember.wallet', 'sponsorMember.person'])
            ->where('status', 'checked_in')
            ->where(function ($q) use ($query, $cleanNum) {
                $q->whereHas('facility', function ($f) use ($query, $cleanNum) {
                    $f->where('code', $query)
                        ->orWhere('code', 'ROOM-' . $query)
                        ->orWhere('name', 'LIKE', "%{$query}%");
                    if (!empty($cleanNum)) {
                        $f->orWhere('code', 'LIKE', "%{$cleanNum}%");
                    }
                })
                ->orWhereHas('guest.person', function ($p) use ($query) {
                    $p->where('first_name', 'LIKE', "%{$query}%")
                        ->orWhere('last_name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', $query);
                })
                ->orWhereHas('sponsorMember.person', function ($p) use ($query) {
                    $p->where('first_name', 'LIKE', "%{$query}%")
                        ->orWhere('last_name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', $query);
                });
            })
            ->first();

        if ($roomRes) {
            $this->selectRoomGuest($roomRes->id);
            $this->customerSearchResults = [];
            $this->dispatch('focus-item-search');
            return;
        }

        // 3. Exact or partial search by membership number, name, phone
        $member = Member::where('membership_number', $query)
            ->orWhereHas('person', function ($q) use ($query) {
                $q->where('phone', $query)
                    ->orWhere('first_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_name', 'LIKE', "%{$query}%");
            })
            ->first();

        if ($member) {
            $this->selectedRoomReservation = null;
            $this->selectedGuest = null;
            $this->customerType = 'member';
            $this->selectMemberRecord($member);
            $this->customerSearchResults = [];
            $this->dispatch('focus-item-search');
        } else {
            $this->errorMessage = "No active member or checked-in room guest found matching '{$query}'.";
            $this->resetMember();
        }
    }

    protected function selectMemberRecord(Member $member, ?Card $card = null): void
    {
        $this->selectedMember = $member->load(['person', 'category', 'wallet', 'dependents.person']);
        $this->activeCard = $card ?? $member->activeCard;
        $this->memberWalletBalancePaise = (int) ($member->wallet?->balance_paise ?? 0);
        $this->searchQuery = $member->membership_number;
        $this->recalculateCart();
    }

    public function resetMember(): void
    {
        $this->selectedMember = null;
        $this->selectedRoomReservation = null;
        $this->selectedGuest = null;
        $this->customerType = 'member';
        $this->activeCard = null;
        $this->memberWalletBalancePaise = 0;
        $this->searchQuery = '';
        $this->customerSearchResults = [];
        $this->highlightedCustomerIndex = 0;
        $this->recalculateCart();
        $this->dispatch('focus-customer-search');
    }

    public function updatedIsGuestSale(): void
    {
        $this->recalculateCart();
    }

    // ==========================================
    // View Mode Switcher
    // ==========================================

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['grid', 'compact'], true)) {
            $this->viewMode = $mode;
        }
    }

    // ==========================================
    // Item Search & Multiple Serving Support
    // ==========================================

    public function updatedItemSearch(string $value): void
    {
        $query = trim($value);
        if (strlen($query) < 1) {
            $this->itemSearchResults = [];
            $this->highlightedItemIndex = 0;
            return;
        }

        $results = [];
        $allowZeroStock = $this->allowZeroStockSales();
        $promoService = $this->promotionService();
        $promoCustomer = $this->getPromotionCustomer();
        $promoContext = $this->getPromotionContext();

        // 1. Search Inventory Items (which may have multiple servings or single serving)
        $inventoryItems = InventoryItem::with(['servingMappings.tariff'])
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('sku', 'LIKE', "{$query}%")
                    ->orWhere('barcode', 'LIKE', "{$query}%");
            })
            ->take(8)
            ->get();

        foreach ($inventoryItems as $invItem) {
            $mappings = $invItem->servingMappings->filter(fn($m) => $m->tariff && $m->tariff->is_active);
            $count = $mappings->count();

            if ($count > 1) {
                // Item has MULTIPLE serving sizes (e.g. 30ml, 60ml, Bottle)
                $minRate = $mappings->min(fn($m) => $m->tariff->rate_paise);
                $servingNames = $mappings->pluck('serving_name')->implode(', ');
                $servingOptions = $mappings->map(function (ServingMapping $sm) use ($invItem, $promoService, $promoCustomer, $promoContext) {
                    $taxPaise = (int) round(($sm->tariff->rate_paise * $sm->tariff->tax_percent) / 100);
                    $promoBadge = $promoService->getOfferBadgeForTariff($sm->tariff->id, $promoCustomer, null, $promoContext);
                    return [
                        'tariff_id' => $sm->tariff->id,
                        'code' => $sm->tariff->code,
                        'name' => $sm->tariff->name,
                        'serving_name' => $sm->serving_name,
                        'serving_qty' => $sm->serving_qty,
                        'base_unit' => $invItem->base_unit,
                        'rate_paise' => $sm->tariff->rate_paise,
                        'tax_percent' => (float) $sm->tariff->tax_percent,
                        'net_rate_paise' => $sm->tariff->rate_paise + $taxPaise,
                        'promo_badge' => $promoBadge,
                    ];
                })->values()->toArray();

                $promoBadges = collect($servingOptions)->pluck('promo_badge')->filter()->unique()->values()->all();

                $results[] = [
                    'type' => 'inventory_parent',
                    'inventory_item_id' => $invItem->id,
                    'tariff_id' => null,
                    'code' => $invItem->sku,
                    'barcode' => $invItem->barcode,
                    'name' => $invItem->name,
                    'department_code' => $invItem->department_code,
                    'rate_display' => 'From ₹' . number_format($minRate / 100, 2),
                    'serving_display' => "Multiple ({$count} units: {$servingNames})",
                    'stock_qty' => $invItem->current_stock_qty,
                    'base_unit' => $invItem->base_unit,
                    'is_in_stock' => $allowZeroStock || ($invItem->current_stock_qty > 0),
                    'has_multiple_servings' => true,
                    'serving_options' => $servingOptions,
                    'promo_badge' => !empty($promoBadges) ? implode(' • ', $promoBadges) : null,
                ];
            } elseif ($count === 1) {
                // Single serving inventory item (e.g. Beer bottle)
                $sm = $mappings->first();
                $tariff = $sm->tariff;
                $taxPaise = (int) round(($tariff->rate_paise * $tariff->tax_percent) / 100);

                $results[] = [
                    'type' => 'single_tariff',
                    'inventory_item_id' => $invItem->id,
                    'tariff_id' => $tariff->id,
                    'code' => $tariff->code,
                    'barcode' => $tariff->barcode ?? $invItem->barcode,
                    'name' => $tariff->name,
                    'department_code' => $tariff->department_code,
                    'rate_display' => '₹' . number_format(($tariff->rate_paise + $taxPaise) / 100, 2),
                    'serving_display' => $sm->serving_name,
                    'stock_qty' => $invItem->current_stock_qty,
                    'base_unit' => $invItem->base_unit,
                    'is_in_stock' => $allowZeroStock || ($invItem->current_stock_qty >= $sm->serving_qty),
                    'has_multiple_servings' => false,
                    'serving_options' => [],
                    'promo_badge' => $promoService->getOfferBadgeForTariff($tariff->id, $promoCustomer, null, $promoContext),
                ];
            }
        }

        // 2. Search Direct Tariffs (Food, Sports, Accommodation, Guest Passes, etc.)
        $tariffs = Tariff::with('servingMapping.inventoryItem')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('code', 'LIKE', "{$query}%")
                    ->orWhere('barcode', 'LIKE', "{$query}%");
            })
            ->take(8)
            ->get();

        foreach ($tariffs as $tariff) {
            // If already included via inventory item, check
            $mapping = $tariff->servingMapping;
            $invItem = $mapping?->inventoryItem;

            if ($invItem) {
                // Check how many servings the parent inventory item has
                $allMappings = ServingMapping::where('inventory_item_id', $invItem->id)->get();
                if ($allMappings->count() > 1) {
                    // This tariff belongs to a multi-serving item!
                    // Ensure the parent is present in results
                    $alreadyPresent = collect($results)->contains(fn($r) => ($r['inventory_item_id'] ?? null) === $invItem->id);
                    if (!$alreadyPresent) {
                        $minRate = Tariff::whereIn('id', $allMappings->pluck('tariff_id'))->min('rate_paise') ?? $tariff->rate_paise;
                        $servingOptions = $allMappings->map(function (ServingMapping $sm) use ($invItem, $promoService, $promoCustomer, $promoContext) {
                            $t = $sm->tariff;
                            $taxPaise = (int) round(($t->rate_paise * $t->tax_percent) / 100);
                            return [
                                'tariff_id' => $t->id,
                                'code' => $t->code,
                                'name' => $t->name,
                                'serving_name' => $sm->serving_name,
                                'serving_qty' => $sm->serving_qty,
                                'base_unit' => $invItem->base_unit,
                                'rate_paise' => $t->rate_paise,
                                'tax_percent' => (float) $t->tax_percent,
                                'net_rate_paise' => $t->rate_paise + $taxPaise,
                                'promo_badge' => $promoService->getOfferBadgeForTariff($t->id, $promoCustomer, null, $promoContext),
                            ];
                        })->values()->toArray();

                        $promoBadges = collect($servingOptions)->pluck('promo_badge')->filter()->unique()->values()->all();

                        $results[] = [
                            'type' => 'inventory_parent',
                            'inventory_item_id' => $invItem->id,
                            'tariff_id' => null,
                            'code' => $invItem->sku,
                            'barcode' => $invItem->barcode ?? $tariff->barcode,
                            'name' => $invItem->name,
                            'department_code' => $invItem->department_code,
                            'rate_display' => 'From ₹' . number_format($minRate / 100, 2),
                            'serving_display' => "Multiple ({$allMappings->count()} units: " . $allMappings->pluck('serving_name')->implode(', ') . ")",
                            'stock_qty' => $invItem->current_stock_qty,
                            'base_unit' => $invItem->base_unit,
                            'is_in_stock' => $allowZeroStock || ($invItem->current_stock_qty > 0),
                            'has_multiple_servings' => true,
                            'serving_options' => $servingOptions,
                            'promo_badge' => !empty($promoBadges) ? implode(' • ', $promoBadges) : null,
                        ];
                    }
                    continue;
                }
            }

            // Single direct tariff or single-serving item
            $alreadyPresent = collect($results)->contains(fn($r) => ($r['tariff_id'] ?? null) === $tariff->id);
            if ($alreadyPresent) {
                continue;
            }

            $taxPaise = (int) round(($tariff->rate_paise * $tariff->tax_percent) / 100);
            $stockQty = $invItem ? $invItem->current_stock_qty : null;
            $baseUnit = $invItem ? $invItem->base_unit : 'unit';
            $isInStock = $invItem ? ($allowZeroStock || ($invItem->current_stock_qty >= ($mapping->serving_qty ?? 1))) : true;

            $results[] = [
                'type' => 'single_tariff',
                'inventory_item_id' => $invItem?->id,
                'tariff_id' => $tariff->id,
                'code' => $tariff->code,
                'barcode' => $tariff->barcode,
                'name' => $tariff->name,
                'department_code' => $tariff->department_code,
                'rate_display' => '₹' . number_format(($tariff->rate_paise + $taxPaise) / 100, 2),
                'serving_display' => $mapping?->serving_name ?? 'Standard Unit',
                'stock_qty' => $stockQty,
                'base_unit' => $baseUnit,
                'is_in_stock' => $isInStock,
                'has_multiple_servings' => false,
                'serving_options' => [],
                'promo_badge' => $promoService->getOfferBadgeForTariff($tariff->id, $promoCustomer, null, $promoContext),
            ];
        }

        $this->itemSearchResults = array_slice($results, 0, 8);
        $this->highlightedItemIndex = 0;
    }

    public function navigateItemResults(string $direction): void
    {
        $count = count($this->itemSearchResults);
        if ($count === 0) {
            return;
        }

        if ($direction === 'down') {
            $this->highlightedItemIndex = ($this->highlightedItemIndex + 1) % $count;
        } elseif ($direction === 'up') {
            $this->highlightedItemIndex = ($this->highlightedItemIndex - 1 + $count) % $count;
        }
    }

    public function selectHighlightedItem(): void
    {
        if (empty($this->itemSearchResults) || !isset($this->itemSearchResults[$this->highlightedItemIndex])) {
            return;
        }

        $item = $this->itemSearchResults[$this->highlightedItemIndex];

        if (!empty($item['has_multiple_servings'])) {
            $this->openServingModalForItem($item);
        } else {
            $this->addToCart((int) $item['tariff_id']);
            $this->itemSearch = '';
            $this->itemSearchResults = [];
            $this->highlightedItemIndex = 0;
            $this->dispatch('focus-item-search');
        }
    }

    public function openServingModalForItem(array $item): void
    {
        $this->clearMessages();
        $this->servingInventoryItemId = $item['inventory_item_id'] ?? null;
        $this->servingItemName = $item['name'];
        $this->servingItemStockText = ($item['stock_qty'] !== null)
            ? "Available: {$item['stock_qty']} {$item['base_unit']}"
            : "Available";
        $this->servingItemOptions = $item['serving_options'] ?? [];
        $this->highlightedServingIndex = 0;
        $this->showServingModal = true;
    }

    public function openServingModalForInventoryItem(int $inventoryItemId): void
    {
        $this->clearMessages();
        $invItem = InventoryItem::with(['servingMappings.tariff'])->find($inventoryItemId);
        if (!$invItem) {
            return;
        }

        $promoService = $this->promotionService();
        $promoCustomer = $this->getPromotionCustomer();
        $promoContext = $this->getPromotionContext();

        $mappings = $invItem->servingMappings->filter(fn($m) => $m->tariff && $m->tariff->is_active);
        $servingOptions = $mappings->map(function (ServingMapping $sm) use ($invItem, $promoService, $promoCustomer, $promoContext) {
            $taxPaise = (int) round(($sm->tariff->rate_paise * $sm->tariff->tax_percent) / 100);
            return [
                'tariff_id' => $sm->tariff->id,
                'code' => $sm->tariff->code,
                'name' => $sm->tariff->name,
                'serving_name' => $sm->serving_name,
                'serving_qty' => $sm->serving_qty,
                'base_unit' => $invItem->base_unit,
                'rate_paise' => $sm->tariff->rate_paise,
                'tax_percent' => (float) $sm->tariff->tax_percent,
                'net_rate_paise' => $sm->tariff->rate_paise + $taxPaise,
                'promo_badge' => $promoService->getOfferBadgeForTariff($sm->tariff->id, $promoCustomer, null, $promoContext),
            ];
        })->values()->toArray();

        $this->servingInventoryItemId = $invItem->id;
        $this->servingItemName = $invItem->name;
        $this->servingItemStockText = "Available: {$invItem->current_stock_qty} {$invItem->base_unit}";
        $this->servingItemOptions = $servingOptions;
        $this->highlightedServingIndex = 0;
        $this->showServingModal = true;
    }

    public function navigateServingOptions(string $direction): void
    {
        $count = count($this->servingItemOptions);
        if ($count === 0) {
            return;
        }

        if ($direction === 'down') {
            $this->highlightedServingIndex = ($this->highlightedServingIndex + 1) % $count;
        } elseif ($direction === 'up') {
            $this->highlightedServingIndex = ($this->highlightedServingIndex - 1 + $count) % $count;
        }
    }

    public function selectHighlightedServing(): void
    {
        if (isset($this->servingItemOptions[$this->highlightedServingIndex])) {
            $option = $this->servingItemOptions[$this->highlightedServingIndex];
            $this->selectServingAndAddToCart($option['tariff_id']);
        }
    }

    public function selectServingAndAddToCart(int $tariffId): void
    {
        $this->addTariffToCartInternal($tariffId, true);
        $this->closeServingModal();
        $this->itemSearch = '';
        $this->itemSearchResults = [];
        $this->dispatch('focus-item-search');
    }

    public function closeServingModal(): void
    {
        $this->showServingModal = false;
        $this->servingInventoryItemId = null;
        $this->servingItemOptions = [];
        $this->highlightedServingIndex = 0;
        $this->dispatch('focus-item-search');
    }

    // ==========================================
    // Cart & Sales Operations
    // ==========================================

    public function addToCart(int $tariffId): void
    {
        $this->addTariffToCartInternal($tariffId, false);
    }

    protected function addTariffToCartInternal(int $tariffId, bool $skipServingModalCheck = false): void
    {
        $this->clearMessages();
        $tariff = Tariff::with('servingMapping.inventoryItem')->find($tariffId);
        if (!$tariff || !$tariff->is_active) {
            $this->errorMessage = "Tariff not available or inactive.";
            return;
        }

        // Check if item has multiple servings and was invoked from touch card
        $mapping = $tariff->servingMapping;
        if ($mapping && $mapping->inventoryItem) {
            $invItem = $mapping->inventoryItem;
            $allowZeroStock = $this->allowZeroStockSales();

            // Check if item is completely out of stock first (only when zero stock sales are disallowed)
            if (!$allowZeroStock && $invItem->current_stock_qty <= 0) {
                $this->errorMessage = sprintf(
                    "Item '%s' is out of stock (Available: %.2f %s). Cannot add to bill.",
                    $invItem->name,
                    $invItem->current_stock_qty,
                    $invItem->base_unit
                );
                return;
            }

            $otherMappings = ServingMapping::where('inventory_item_id', $invItem->id)->count();

            // If user clicked a multi-serving item without serving modal
            if (!$skipServingModalCheck && $otherMappings > 1 && !$this->showServingModal) {
                $this->openServingModalForInventoryItem($invItem->id);
                return;
            }
        }

        // Check cumulative stock availability across existing cart + this increment
        $existingQty = 0;
        foreach ($this->cart as $existingItem) {
            if ((int) ($existingItem['tariff_id'] ?? 0) === $tariffId) {
                $existingQty += (int) ($existingItem['quantity'] ?? 0);
            }
        }

        if (!$this->canAccommodateTariffQuantity($tariffId, $existingQty + 1)) {
            return;
        }

        // Check if already in cart -> increment quantity
        foreach ($this->cart as &$item) {
            if ($item['tariff_id'] === $tariffId) {
                $item['quantity'] += 1;
                $this->recalculateCart();
                $this->dispatch('focus-item-search');
                return;
            }
        }

        // Default quantity to ONE
        $this->cart[] = [
            'tariff_id' => $tariff->id,
            'code' => $tariff->code,
            'name' => $tariff->name,
            'serving_name' => $mapping?->serving_name ?? 'Unit',
            'department_code' => $tariff->department_code,
            'quantity' => 1,
            'unit_price_paise' => $tariff->rate_paise,
            'tax_percent' => (float) $tariff->tax_percent,
        ];

        if (str_contains(strtolower($tariff->name), 'guest')) {
            $this->isGuestSale = true;
        }

        $this->recalculateCart();
        $this->dispatch('focus-item-search');
    }

    public function setCartQuantity(int $index, $quantity): void
    {
        $this->clearMessages();
        $qty = (int) $quantity;
        if (isset($this->cart[$index])) {
            if ($qty <= 0) {
                $this->removeFromCart($index);
            } else {
                $tariffId = (int) ($this->cart[$index]['tariff_id'] ?? 0);
                if (!$this->canAccommodateTariffQuantity($tariffId, $qty)) {
                    return;
                }
                $this->cart[$index]['quantity'] = $qty;
                $this->recalculateCart();
            }
        }
        $this->dispatch('focus-item-search');
    }

    public function updateQuantity(int $index, int $delta): void
    {
        $this->clearMessages();
        if (isset($this->cart[$index])) {
            $targetQty = (int) $this->cart[$index]['quantity'] + $delta;
            if ($targetQty <= 0) {
                unset($this->cart[$index]);
                $this->cart = array_values($this->cart);
                $this->recalculateCart();
            } else {
                $tariffId = (int) ($this->cart[$index]['tariff_id'] ?? 0);
                if ($delta > 0 && !$this->canAccommodateTariffQuantity($tariffId, $targetQty)) {
                    return;
                }
                $this->cart[$index]['quantity'] = $targetQty;
                $this->recalculateCart();
            }
        }
    }

    public function claimPromoFreeItem(int $index): void
    {
        $this->clearMessages();
        if (!isset($this->cart[$index])) {
            return;
        }

        $addQty = (int) ($this->cart[$index]['promo_cta_add_qty'] ?? 0);
        if ($addQty <= 0) {
            $this->recalculateCart();
            $addQty = (int) ($this->cart[$index]['promo_cta_add_qty'] ?? 0);
        }

        if ($addQty <= 0) {
            return;
        }

        $targetQty = (int) $this->cart[$index]['quantity'] + $addQty;
        $tariffId = (int) ($this->cart[$index]['tariff_id'] ?? 0);
        if (!$this->canAccommodateTariffQuantity($tariffId, $targetQty)) {
            return;
        }

        $this->cart[$index]['quantity'] = $targetQty;
        $this->recalculateCart();
        $this->dispatch('focus-item-search');
    }

    public function removeFromCart(int $index): void
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
            $this->recalculateCart();
        }
        $this->dispatch('focus-item-search');
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->isGuestSale = false;
        $this->clearMessages();
        $this->recalculateCart();
        $this->dispatch('focus-item-search');
    }

    protected function recalculateCart(): void
    {
        if (empty($this->cart)) {
            $this->cartGross = 0.00;
            $this->cartDiscount = 0.00;
            $this->cartTax = 0.00;
            $this->cartNet = 0.00;
            return;
        }

        $evaluation = $this->promotionService()->evaluateCart(
            lines: $this->cart,
            customer: $this->getPromotionCustomer(),
            context: $this->getPromotionContext()
        );

        $this->cart = $evaluation['lines'];
        $this->cartGross = $evaluation['gross_paise'] / 100.0;
        $this->cartDiscount = $evaluation['discount_paise'] / 100.0;
        $this->cartTax = $evaluation['tax_paise'] / 100.0;
        $this->cartNet = $evaluation['net_paise'] / 100.0;
    }

    protected function promotionService(): PromotionService
    {
        return app(PromotionService::class);
    }

    /**
     * @return array{is_room_resident: bool, is_guest_sale: bool}
     */
    protected function getPromotionContext(): array
    {
        return [
            'is_room_resident' => $this->selectedRoomReservation !== null || $this->customerType === 'room_guest',
            'is_guest_sale' => $this->isGuestSale,
        ];
    }

    protected function getPromotionCustomer(): ?\Illuminate\Database\Eloquent\Model
    {
        if ($this->selectedRoomReservation) {
            return $this->selectedGuest ?? $this->selectedMember ?? $this->selectedRoomReservation->sponsorMember;
        }

        return $this->selectedMember;
    }

    protected function canAccommodateTariffQuantity(int $tariffId, int|float $targetLineQty): bool
    {
        if ($this->allowZeroStockSales()) {
            return true;
        }

        $tariff = Tariff::with('servingMapping.inventoryItem')->find($tariffId);
        $mapping = $tariff?->servingMapping;
        if (!$mapping || !$mapping->inventoryItem) {
            return true;
        }

        $invItem = $mapping->inventoryItem;
        if ((float) $invItem->current_stock_qty <= 0) {
            $this->errorMessage = sprintf(
                "Item '%s' is out of stock (Available: %.2f %s). Cannot add to bill.",
                $invItem->name,
                $invItem->current_stock_qty,
                $invItem->base_unit
            );
            return false;
        }

        $otherTariffIds = collect($this->cart)
            ->pluck('tariff_id')
            ->filter(fn ($id) => (int) $id !== $tariffId)
            ->unique()
            ->values();

        $otherMappings = $otherTariffIds->isNotEmpty()
            ? ServingMapping::whereIn('tariff_id', $otherTariffIds)
                ->where('inventory_item_id', $invItem->id)
                ->get()
                ->keyBy('tariff_id')
            : collect();

        $otherQtyRequired = 0.0;
        foreach ($this->cart as $cartLine) {
            $lineTariffId = (int) ($cartLine['tariff_id'] ?? 0);
            if ($lineTariffId !== $tariffId && isset($otherMappings[$lineTariffId])) {
                $otherQtyRequired += (float) $cartLine['quantity'] * (float) $otherMappings[$lineTariffId]->serving_qty;
            }
        }

        $requiredQty = $otherQtyRequired + ((float) $targetLineQty * (float) $mapping->serving_qty);

        if (round((float) $invItem->current_stock_qty, 4) < round($requiredQty, 4)) {
            $this->errorMessage = sprintf(
                "Insufficient stock for '%s' (Available: %.2f %s, Required: %.2f %s).",
                $invItem->name,
                $invItem->current_stock_qty,
                $invItem->base_unit,
                $requiredQty,
                $invItem->base_unit
            );
            return false;
        }

        return true;
    }

    // ==========================================
    // Running Bar & Table Tabs (Held Bills)
    // ==========================================

    public function openHoldTabModal(): void
    {
        $this->clearMessages();

        if (!$this->activeShift) {
            $this->errorMessage = "Cannot park bill without an active open shift.";
            return;
        }

        if (empty($this->cart)) {
            $this->errorMessage = "Cannot park an empty bill. Add items first.";
            return;
        }

        if (!$this->selectedMember && !$this->selectedRoomReservation) {
            $this->errorMessage = "Member-First Policy: Please identify a member or room guest before parking the tab.";
            $this->dispatch('focus-customer-search');
            return;
        }

        // If tab is already active, update it directly
        if ($this->activeHeldTabId) {
            $this->holdCurrentCart();
            return;
        }

        $this->showHoldTabModal = true;
    }

    public function closeHoldTabModal(): void
    {
        $this->showHoldTabModal = false;
        $this->dispatch('focus-item-search');
    }

    public function openHeldTabsListModal(): void
    {
        $this->showHeldTabsListModal = true;
    }

    public function closeHeldTabsListModal(): void
    {
        $this->showHeldTabsListModal = false;
    }

    public function holdCurrentCart(): void
    {
        $this->clearMessages();

        if (!$this->activeShift) {
            $this->errorMessage = "Cannot park bill without an active open shift.";
            return;
        }

        if (empty($this->cart)) {
            $this->errorMessage = "Cannot park an empty bill.";
            return;
        }

        if (!$this->selectedMember && !$this->selectedRoomReservation) {
            $this->errorMessage = "Member-First Policy: Please select a member or room guest to park the tab.";
            $this->dispatch('focus-customer-search');
            return;
        }

        $maxTabs = (int) config('club.pos.max_held_tabs', 50);
        $currentOpenCount = PosHeldTab::forShift($this->activeShift->id)->open()->count();

        if (!$this->activeHeldTabId && $currentOpenCount >= $maxTabs) {
            $this->errorMessage = "Cannot hold bill: Limit of {$maxTabs} concurrent active tabs reached on this shift.";
            return;
        }

        $grossPaise = (int) round($this->cartGross * 100);
        $discountPaise = (int) round($this->cartDiscount * 100);
        $taxPaise = (int) round($this->cartTax * 100);
        $netPaise = (int) round($this->cartNet * 100);

        $customerName = $this->selectedRoomReservation
            ? ($this->selectedGuest?->name ?? $this->selectedRoomReservation->facility->name . ' Resident')
            : ($this->selectedMember->name ?? 'Member');

        $customerIdentifier = $this->selectedRoomReservation
            ? $this->selectedRoomReservation->facility->code
            : ($this->selectedMember->membership_number ?? 'MEMBER');

        if ($this->activeHeldTabId) {
            $tab = PosHeldTab::find($this->activeHeldTabId);
            if ($tab && $tab->status === 'open') {
                $tab->update([
                    'table_identifier' => $this->tabTableIdentifier,
                    'notes' => $this->tabNotes,
                    'cart_items' => $this->cart,
                    'gross_amount_paise' => $grossPaise,
                    'discount_amount_paise' => $discountPaise,
                    'tax_amount_paise' => $taxPaise,
                    'net_amount_paise' => $netPaise,
                ]);

                $this->successMessage = "Tab #{$tab->tab_number} for {$customerName} updated (₹" . number_format($this->cartNet, 2) . ").";
            }
        } else {
            $nextSeq = PosHeldTab::where('shift_id', $this->activeShift->id)->count() + 1;
            $tabNumber = sprintf('TAB-%02d', $nextSeq);

            $tab = PosHeldTab::create([
                'shift_id' => $this->activeShift->id,
                'counter_id' => $this->selectedCounterId,
                'tab_number' => $tabNumber,
                'customer_type' => $this->customerType,
                'member_id' => $this->selectedMember?->id,
                'room_reservation_id' => $this->selectedRoomReservation?->id,
                'guest_id' => $this->selectedGuest?->id,
                'customer_name' => $customerName,
                'customer_identifier' => $customerIdentifier,
                'table_identifier' => $this->tabTableIdentifier,
                'notes' => $this->tabNotes,
                'cart_items' => $this->cart,
                'gross_amount_paise' => $grossPaise,
                'discount_amount_paise' => $discountPaise,
                'tax_amount_paise' => $taxPaise,
                'net_amount_paise' => $netPaise,
                'status' => 'open',
                'created_by' => auth()->id(),
            ]);

            $this->successMessage = "Tab #{$tab->tab_number} parked for {$customerName} (₹" . number_format($this->cartNet, 2) . ").";
        }

        $this->showHoldTabModal = false;
        $this->resetWorkspaceToNewBill();
    }

    public function loadHeldTab(int $tabId): void
    {
        $this->clearMessages();

        $tab = PosHeldTab::with(['member.person', 'member.wallet', 'member.activeCard', 'member.category', 'roomReservation.facility', 'guest.person'])
            ->find($tabId);

        if (!$tab || $tab->status !== 'open') {
            $this->errorMessage = "Held tab is no longer active or was settled.";
            return;
        }

        // If current cart is dirty and not this tab, auto-save if on another tab or block
        if (!empty($this->cart) && $this->activeHeldTabId !== $tabId) {
            if ($this->activeHeldTabId) {
                $currentTab = PosHeldTab::find($this->activeHeldTabId);
                if ($currentTab && $currentTab->status === 'open') {
                    $currentTab->update([
                        'cart_items' => $this->cart,
                        'gross_amount_paise' => (int) round($this->cartGross * 100),
                        'discount_amount_paise' => (int) round($this->cartDiscount * 100),
                        'tax_amount_paise' => (int) round($this->cartTax * 100),
                        'net_amount_paise' => (int) round($this->cartNet * 100),
                    ]);
                }
            } else {
                $this->errorMessage = "Please park (<kbd>F6</kbd>) or clear your current bill before switching tabs.";
                return;
            }
        }

        $this->activeHeldTabId = $tab->id;
        $this->tabTableIdentifier = $tab->table_identifier;
        $this->tabNotes = $tab->notes;
        $this->customerType = $tab->customer_type;

        if ($tab->customer_type === 'room_guest' && $tab->roomReservation) {
            $this->selectedRoomReservation = $tab->roomReservation;
            $this->selectedGuest = $tab->guest;
            $this->selectedMember = $tab->roomReservation->sponsorMember;
            $this->activeCard = null;
            $this->memberWalletBalancePaise = (int) ($this->selectedMember?->wallet?->balance_paise ?? 0);
            $this->searchQuery = "{$tab->roomReservation->facility->name} - {$tab->customer_name}";
        } elseif ($tab->member) {
            $this->selectedRoomReservation = null;
            $this->selectedGuest = null;
            $this->customerType = 'member';
            $this->selectMemberRecord($tab->member, $tab->member->activeCard);
            $this->searchQuery = $tab->member->membership_number;
        }

        $this->cart = $tab->cart_items ?? [];
        $this->isGuestSale = collect($this->cart)->contains(
            fn ($item) => str_contains(strtolower((string) ($item['name'] ?? '')), 'guest')
        );
        $this->recalculateCart();
        $this->showHeldTabsListModal = false;
        $this->successMessage = "Loaded Tab #{$tab->tab_number}: {$tab->customer_name} ({$tab->formatted_net_amount}).";
        $this->dispatch('focus-item-search');
    }

    public function voidActiveHeldTab(?string $reason = null): void
    {
        if (!$this->activeHeldTabId) {
            return;
        }

        $tab = PosHeldTab::find($this->activeHeldTabId);
        if ($tab && $tab->status === 'open') {
            $tab->update([
                'status' => 'voided',
                'notes' => trim(($tab->notes ?? '') . " [Voided: " . ($reason ?: 'Cancelled by staff') . "]"),
            ]);
            $this->successMessage = "Tab #{$tab->tab_number} for {$tab->customer_name} has been voided.";
        }

        $this->resetWorkspaceToNewBill();
    }

    public function resetWorkspaceToNewBill(): void
    {
        $this->activeHeldTabId = null;
        $this->tabTableIdentifier = null;
        $this->tabNotes = null;
        $this->resetMember();
        $this->cart = [];
        $this->isGuestSale = false;
        $this->recalculateCart();
        $this->dispatch('focus-customer-search');
    }

    // ==========================================
    // Checkout & Payment Confirmation
    // ==========================================

    public function initiateCheckout(string $tenderTypeStr): void
    {
        $this->clearMessages();

        if (!$this->activeShift) {
            $this->errorMessage = "Cannot checkout without an open counter shift.";
            return;
        }

        if (empty($this->cart)) {
            $this->errorMessage = "Cart is empty. Add items to checkout.";
            return;
        }

        $tenderType = TenderType::tryFrom($tenderTypeStr);
        if (!$tenderType) {
            $this->errorMessage = "Invalid payment tender type.";
            return;
        }

        $totalPaise = (int) round($this->cartNet * 100);

        // Pre-validation for Prepaid Wallet: Strict No-Credit
        if ($tenderType === TenderType::PREPAID) {
            if (!$this->selectedMember) {
                $this->errorMessage = "Please select a member card to pay using Prepaid Wallet.";
                $this->dispatch('focus-customer-search');
                return;
            }

            if ($this->memberWalletBalancePaise < $totalPaise) {
                $this->errorMessage = sprintf(
                    "Insufficient prepaid balance (Available: ₹%.2f, Required: ₹%.2f). No credit permitted.",
                    $this->memberWalletBalancePaise / 100.0,
                    $totalPaise / 100.0
                );
                return;
            }
        }

        // Guest validation
        if ($this->isGuestSale && !$this->selectedMember && empty($this->guestFirstName)) {
            $this->errorMessage = "A sponsoring member or guest registration is required for guest entry.";
            return;
        }

        // Open Confirmation Dialog
        $this->pendingTenderType = $tenderTypeStr;
        $this->showPaymentConfirmModal = true;
    }

    public function confirmCheckout(
        BillingService $billingService,
        GuestService $guestService,
        ReceiptPrinterInterface $printer
    ): void {
        $this->clearMessages();

        if (! auth('web')->user()?->hasClubPermission('sales.checkout')) {
            $this->showPaymentConfirmModal = false;
            $this->errorMessage = 'Access denied. Completing POS checkouts requires sales.checkout permission.';
            return;
        }

        // 1. Double submit & race condition safeguard
        if ($this->isProcessingPayment) {
            return;
        }

        if (!$this->pendingTenderType) {
            $this->showPaymentConfirmModal = false;
            return;
        }

        $this->isProcessingPayment = true;

        try {
            if (!$this->activeShift) {
                throw new \RuntimeException("Cannot checkout without an open counter shift.");
            }

            if (empty($this->cart)) {
                throw new \RuntimeException("Cart is empty.");
            }

            $tenderType = TenderType::from($this->pendingTenderType);
            $totalPaise = (int) round($this->cartNet * 100);

            // Re-verify prepaid balance
            if ($tenderType === TenderType::PREPAID) {
                if (!$this->selectedMember) {
                    throw new \RuntimeException("Please select a member to pay using Prepaid Wallet.");
                }

                if ($this->memberWalletBalancePaise < $totalPaise) {
                    throw new \RuntimeException(sprintf(
                        "Insufficient prepaid balance (Available: ₹%.2f, Required: ₹%.2f). No credit permitted.",
                        $this->memberWalletBalancePaise / 100.0,
                        $totalPaise / 100.0
                    ));
                }
            }

            // Determine customer & bill narration
            $customer = null;
            $notes = '';

            if ($this->selectedRoomReservation) {
                $customer = $this->selectedGuest ?? $this->selectedMember ?? Member::first();
                $guestName = $this->selectedGuest?->name ?? $this->selectedRoomReservation->guest?->name ?? $this->selectedRoomReservation->sponsorMember?->name ?? 'Resident Guest';
                $notes = "Counter sale for {$this->selectedRoomReservation->facility->name} (Resident: {$guestName})";
            } elseif ($this->isGuestSale && !empty($this->guestFirstName)) {
                if (! auth('web')->user()?->hasClubPermission('guests.register')) {
                    throw new \RuntimeException("Access denied. Registering guests or issuing guest passes requires guests.register permission.");
                }
                if (!$this->selectedMember) {
                    throw new \RuntimeException("A sponsoring member is required for guest entry.");
                }
                $guest = $guestService->registerGuest(
                    personData: [
                        'first_name' => $this->guestFirstName,
                        'last_name' => $this->guestLastName ?: null,
                        'phone' => $this->guestPhone ?: null,
                    ],
                    sponsorType: Member::class,
                    sponsorId: $this->selectedMember->id
                );
                $customer = $guest;
            } else {
                $customer = $this->selectedMember;
            }

            if (!$customer) {
                throw new \RuntimeException("Please select a customer, room guest, or provide guest registration details.");
            }

            $billItems = [];
            $primaryDept = $this->cart[0]['department_code'] ?? 'bar';

            foreach ($this->cart as $item) {
                $itemPayload = [
                    'department_code' => $item['department_code'],
                    'item_type' => 'tariff',
                    'item_id' => $item['tariff_id'],
                    'item_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price_paise' => $item['unit_price_paise'],
                    'tax_percent' => $item['tax_percent'],
                ];

                if (!empty($item['discount_amount_paise'])) {
                    $itemPayload['expected_discount_paise'] = (int) $item['discount_amount_paise'];
                }

                $billItems[] = $itemPayload;
            }

            // Server-side unique idempotency key
            $idempotencyKey = 'bill_' . $this->activeShift->id . '_' . time() . '_' . Str::random(8);

            $bill = $billingService->createDraftBill(
                shift: $this->activeShift,
                customer: $customer,
                departmentCode: $primaryDept,
                items: $billItems,
                idempotencyKey: $idempotencyKey,
                notes: $notes,
                userId: auth()->id(),
                promotionContext: $this->getPromotionContext()
            );

            $checkoutResult = $billingService->checkout(
                bill: $bill,
                tenders: [
                    [
                        'tender_type' => $tenderType,
                        'amount_paise' => $bill->net_amount_paise,
                    ]
                ],
                userId: auth()->id()
            );

            // Refresh balances
            if ($this->selectedMember) {
                $this->selectedMember->load('wallet');
                $this->memberWalletBalancePaise = (int) ($this->selectedMember->wallet?->balance_paise ?? 0);
            }

            $this->refreshActiveShift();

            $receipts = collect($checkoutResult['receipts'] ?? PaymentReceipt::where('bill_id', $bill->id)->get());
            $firstReceipt = $receipts->first();
            $this->lastReceiptNumber = $bill->bill_number;
            $this->receiptContent = $printer->render80mmReceipt($bill, $firstReceipt);

            $this->showPaymentConfirmModal = false;
            $this->pendingTenderType = null;
            $this->resetCustomerOnReceiptClose = true;
            $this->showReceiptModal = true;

            // Settle active held tab if applicable
            if ($this->activeHeldTabId) {
                $tab = PosHeldTab::find($this->activeHeldTabId);
                if ($tab && $tab->status === 'open') {
                    $tab->update([
                        'status' => 'settled',
                        'settled_at' => now(),
                        'settled_bill_id' => $bill->id,
                    ]);
                }
                $this->activeHeldTabId = null;
                $this->tabTableIdentifier = null;
                $this->tabNotes = null;
            }

            $this->successMessage = "Bill {$bill->bill_number} settled successfully via {$tenderType->label()}! Total: ₹" . number_format($this->cartNet, 2);
            $this->clearCart();
            $this->guestFirstName = '';
            $this->guestLastName = '';
            $this->guestPhone = '';
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->showPaymentConfirmModal = false;
        } finally {
            $this->isProcessingPayment = false;
        }
    }

    public function closePaymentConfirmModal(): void
    {
        $this->showPaymentConfirmModal = false;
        $this->pendingTenderType = null;
        $this->dispatch('focus-item-search');
    }

    // Direct legacy checkout wrapper (for backward compatibility)
    public function checkout(
        string $tenderTypeStr,
        BillingService $billingService,
        GuestService $guestService,
        ReceiptPrinterInterface $printer
    ): void {
        $this->initiateCheckout($tenderTypeStr);
        if ($this->showPaymentConfirmModal) {
            $this->confirmCheckout($billingService, $guestService, $printer);
        }
    }

    public function closeReceiptModal(): void
    {
        $this->showReceiptModal = false;
        $this->receiptContent = '';
        if ($this->resetCustomerOnReceiptClose) {
            $this->resetWorkspaceToNewBill();
            $this->resetCustomerOnReceiptClose = false;
        } else {
            $this->dispatch('focus-item-search');
        }
    }

    // ==========================================
    // Wallet Top-Up
    // ==========================================

    public function rechargeWallet(WalletService $walletService, ReceiptPrinterInterface $printer): void
    {
        $this->clearMessages();

        if (! auth('web')->user()?->hasClubPermission('wallet.recharge')) {
            $this->errorMessage = 'Access denied. Prepaid wallet recharges require wallet.recharge permission.';
            return;
        }

        if (!$this->activeShift) {
            $this->errorMessage = "Cannot recharge wallet without an open counter shift.";
            return;
        }

        if (!$this->selectedMember || !$this->selectedMember->wallet) {
            $this->errorMessage = "Please select an active member first.";
            return;
        }

        $amountPaise = (int) round($this->rechargeAmount * 100);
        if ($amountPaise <= 0) {
            $this->errorMessage = "Recharge amount must be greater than zero.";
            return;
        }

        $tender = $this->rechargeTender === 'digital' ? TenderType::DIGITAL : TenderType::CASH;
        $idempotencyKey = 'rcg_' . $this->selectedMember->id . '_' . time() . '_' . Str::random(8);

        try {
            $result = $walletService->recharge(
                wallet: $this->selectedMember->wallet,
                amountPaise: $amountPaise,
                tenderType: $tender,
                idempotencyKey: $idempotencyKey,
                shiftId: $this->activeShift->id,
                providerRef: $this->rechargeRef,
                userId: auth()->id()
            );

            $this->memberWalletBalancePaise = $result['wallet']->balance_paise;
            $this->selectedMember->load('wallet');
            $this->refreshActiveShift();

            $receipt = $result['receipt'];
            $this->lastReceiptNumber = $receipt->receipt_number;
            $this->receiptContent = $printer->render80mmReceipt(null, $receipt);
            $this->showReceiptModal = true;

            $this->successMessage = "Recharge of ₹" . number_format($this->rechargeAmount, 2) . " confirmed! Updated Balance: ₹" . number_format($this->memberWalletBalancePaise / 100, 2);
            $this->rechargeRef = null;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function allowZeroStockSales(): bool
    {
        return (bool) \App\Domain\Shared\Models\SystemSetting::get('allow_zero_stock_sales', false);
    }

    public function render()
    {
        $counters = Counter::where('is_active', true)->get();
        $tariffs = Tariff::where('is_active', true)->orderBy('department_code')->get();

        $heldTabs = $this->activeShift
            ? PosHeldTab::forShift($this->activeShift->id)
                ->open()
                ->when(!empty($this->heldTabSearch), function ($q) {
                    $search = trim($this->heldTabSearch);
                    $q->where(function ($sub) use ($search) {
                        $sub->where('customer_name', 'LIKE', "%{$search}%")
                            ->orWhere('customer_identifier', 'LIKE', "%{$search}%")
                            ->orWhere('table_identifier', 'LIKE', "%{$search}%")
                            ->orWhere('tab_number', 'LIKE', "%{$search}%");
                    });
                })
                ->orderBy('updated_at', 'desc')
                ->get()
            : collect();

        return view('livewire.counter-pos', [
            'counters' => $counters,
            'tariffs' => $tariffs,
            'heldTabs' => $heldTabs,
        ]);
    }
}
