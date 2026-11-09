<?php

namespace App\Livewire;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Integrations\Contracts\ReceiptPrinterInterface;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\CardService;
use App\Domain\Prepaid\Services\WalletService;
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

        if (!$this->activeShift) {
            $this->errorMessage = "No active shift to close.";
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

        // Search by card, membership_number, name, or phone
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
            ->take(6)
            ->get();

        $this->customerSearchResults = $members->map(function (Member $m) {
            return [
                'id' => $m->id,
                'membership_number' => $m->membership_number,
                'name' => ($m->person->first_name ?? '') . ' ' . ($m->person->last_name ?? ''),
                'phone' => $m->person->phone ?? '',
                'card_number' => $m->activeCard?->card_number ?? 'No Card',
                'category' => $m->category->name ?? 'Standard',
                'status' => $m->status->value,
                'balance_paise' => (int) ($m->wallet?->balance_paise ?? 0),
            ];
        })->toArray();

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
            $this->selectCustomerById($this->customerSearchResults[$this->highlightedCustomerIndex]['id']);
        } else {
            $this->searchMember(app(CardService::class));
        }
    }

    public function selectCustomerById(int $memberId): void
    {
        $this->clearMessages();
        $member = Member::find($memberId);
        if ($member) {
            $this->selectMemberRecord($member);
            $this->customerSearchResults = [];
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
            $this->selectCustomerById($this->customerSearchResults[$this->highlightedCustomerIndex]['id']);
            return;
        }

        // 1. Try find exact card first
        $card = $cardService->findCard($query);
        if ($card && $card->cardable instanceof Member) {
            $this->selectMemberRecord($card->cardable, $card);
            $this->customerSearchResults = [];
            $this->dispatch('focus-item-search');
            return;
        }

        // 2. Exact or partial search by membership number, name, phone
        $member = Member::where('membership_number', $query)
            ->orWhereHas('person', function ($q) use ($query) {
                $q->where('phone', $query)
                    ->orWhere('first_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_name', 'LIKE', "%{$query}%");
            })
            ->first();

        if ($member) {
            $this->selectMemberRecord($member);
            $this->customerSearchResults = [];
            $this->dispatch('focus-item-search');
        } else {
            $this->errorMessage = "No active member found matching '{$query}'.";
            $this->resetMember();
        }
    }

    protected function selectMemberRecord(Member $member, ?Card $card = null): void
    {
        $this->selectedMember = $member->load(['person', 'category', 'wallet', 'dependents.person']);
        $this->activeCard = $card ?? $member->activeCard;
        $this->memberWalletBalancePaise = (int) ($member->wallet?->balance_paise ?? 0);
        $this->searchQuery = $member->membership_number;
    }

    public function resetMember(): void
    {
        $this->selectedMember = null;
        $this->activeCard = null;
        $this->memberWalletBalancePaise = 0;
        $this->searchQuery = '';
        $this->customerSearchResults = [];
        $this->highlightedCustomerIndex = 0;
        $this->dispatch('focus-customer-search');
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
                $servingOptions = $mappings->map(function (ServingMapping $sm) use ($invItem) {
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
                    ];
                })->values()->toArray();

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
                    'is_in_stock' => $invItem->current_stock_qty > 0,
                    'has_multiple_servings' => true,
                    'serving_options' => $servingOptions,
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
                    'is_in_stock' => $invItem->current_stock_qty >= $sm->serving_qty,
                    'has_multiple_servings' => false,
                    'serving_options' => [],
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
                        $servingOptions = $allMappings->map(function (ServingMapping $sm) use ($invItem) {
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
                            ];
                        })->values()->toArray();

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
                            'is_in_stock' => $invItem->current_stock_qty > 0,
                            'has_multiple_servings' => true,
                            'serving_options' => $servingOptions,
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
            $isInStock = $invItem ? ($invItem->current_stock_qty >= ($mapping->serving_qty ?? 1)) : true;

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

        $mappings = $invItem->servingMappings->filter(fn($m) => $m->tariff && $m->tariff->is_active);
        $servingOptions = $mappings->map(function (ServingMapping $sm) use ($invItem) {
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

            // Check if item is completely out of stock first
            if ($invItem->current_stock_qty <= 0) {
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

            // Check stock availability
            $requiredQty = (float) $mapping->serving_qty;
            if ($invItem->current_stock_qty < $requiredQty) {
                $this->errorMessage = sprintf(
                    "Item '%s' is out of stock (Available: %.2f %s, Required: %.2f %s). Cannot add to bill.",
                    $invItem->name,
                    $invItem->current_stock_qty,
                    $invItem->base_unit,
                    $requiredQty,
                    $invItem->base_unit
                );
                return;
            }
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
        $qty = (int) $quantity;
        if (isset($this->cart[$index])) {
            if ($qty <= 0) {
                $this->removeFromCart($index);
            } else {
                $this->cart[$index]['quantity'] = $qty;
                $this->recalculateCart();
            }
        }
        $this->dispatch('focus-item-search');
    }

    public function updateQuantity(int $index, int $delta): void
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity'] += $delta;
            if ($this->cart[$index]['quantity'] <= 0) {
                unset($this->cart[$index]);
                $this->cart = array_values($this->cart);
            }
            $this->recalculateCart();
        }
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
        $gross = 0;
        $tax = 0;

        foreach ($this->cart as $item) {
            $lineSubtotal = (int) round($item['quantity'] * $item['unit_price_paise']);
            $lineTax = (int) round(($lineSubtotal * $item['tax_percent']) / 100);
            $gross += $lineSubtotal;
            $tax += $lineTax;
        }

        $this->cartGross = $gross / 100.0;
        $this->cartTax = $tax / 100.0;
        $this->cartNet = ($gross + $tax) / 100.0;
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

            // Determine customer
            $customer = $this->selectedMember;
            if ($this->isGuestSale && !empty($this->guestFirstName)) {
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
            } elseif (!$customer) {
                throw new \RuntimeException("Please select a customer or provide guest registration details.");
            }

            $billItems = [];
            $primaryDept = $this->cart[0]['department_code'] ?? 'bar';

            foreach ($this->cart as $item) {
                $billItems[] = [
                    'department_code' => $item['department_code'],
                    'item_type' => 'tariff',
                    'item_id' => $item['tariff_id'],
                    'item_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price_paise' => $item['unit_price_paise'],
                    'tax_percent' => $item['tax_percent'],
                ];
            }

            // Server-side unique idempotency key
            $idempotencyKey = 'bill_' . $this->activeShift->id . '_' . time() . '_' . Str::random(8);

            $bill = $billingService->createDraftBill(
                shift: $this->activeShift,
                customer: $customer,
                departmentCode: $primaryDept,
                items: $billItems,
                idempotencyKey: $idempotencyKey,
                userId: auth()->id()
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
            $this->resetMember();
            $this->resetCustomerOnReceiptClose = false;
            $this->dispatch('focus-customer-search');
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

    public function render()
    {
        $counters = Counter::where('is_active', true)->get();
        $tariffs = Tariff::where('is_active', true)->orderBy('department_code')->get();

        return view('livewire.counter-pos', [
            'counters' => $counters,
            'tariffs' => $tariffs,
        ]);
    }
}
