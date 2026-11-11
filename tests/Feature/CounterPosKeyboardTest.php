<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Enums\MemberStatus;
use App\Domain\Tariff\Models\Tariff;
use App\Livewire\CounterPos;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CounterPosKeyboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Counter $counter;
    protected Shift $shift;
    protected Member $memberWithFunds;
    protected Member $memberZeroBalance;
    protected Tariff $singleItemTariff;
    protected Tariff $whisky30;
    protected Tariff $whisky60;
    protected Tariff $whiskyBottle;
    protected InventoryItem $whiskyItem;
    protected InventoryItem $outOfStockItem;
    protected Tariff $outOfStockTariff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-1')->firstOrFail();

        /** @var ShiftService $shiftService */
        $shiftService = app(ShiftService::class);
        $this->shift = $shiftService->openShift(
            counterId: $this->counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 200000 // ₹2,000 float
        );

        // Member with funds
        $this->memberWithFunds = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->memberWithFunds->wallet->update(['balance_paise' => 500000]); // ₹5,000.00

        // Member with 0 balance
        $this->memberZeroBalance = Member::where('membership_number', 'MC-003')->firstOrFail();
        $this->memberZeroBalance->wallet->update(['balance_paise' => 0]); // ₹0.00

        // Single serving item: Beer
        $this->singleItemTariff = Tariff::where('code', 'BAR-BEER-KF')->firstOrFail();

        // Multi-serving whisky
        $this->whiskyItem = InventoryItem::where('sku', 'BAR-SIG-750')->firstOrFail();
        $this->whisky30 = Tariff::where('code', 'BAR-WHISKY-30ML')->firstOrFail();
        $this->whisky60 = Tariff::where('code', 'BAR-WHISKY-60ML')->firstOrFail();
        $this->whiskyBottle = Tariff::where('code', 'BAR-WHISKY-BOTTLE')->firstOrFail();

        // Out of stock item: Vodka
        $this->outOfStockItem = InventoryItem::where('sku', 'BAR-VOD-750')->firstOrFail();
        $this->outOfStockTariff = Tariff::where('code', 'BAR-VOD-30ML')->firstOrFail();
    }

    public function test_customer_identification_by_card_number(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(CounterPos::class)
            ->set('searchQuery', 'CARD-VENU-001')
            ->call('searchMember')
            ->assertSet('selectedMember.id', $this->memberWithFunds->id)
            ->assertSet('memberWalletBalancePaise', 500000)
            ->assertDispatched('focus-item-search');
    }

    public function test_customer_identification_by_membership_number(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(CounterPos::class)
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->assertSet('selectedMember.id', $this->memberWithFunds->id)
            ->assertSet('memberWalletBalancePaise', 500000);
    }

    public function test_customer_identification_by_name_and_phone(): void
    {
        $this->actingAs($this->cashier);

        // By first name
        Livewire::test(CounterPos::class)
            ->set('searchQuery', 'Venu')
            ->call('searchMember')
            ->assertSet('selectedMember.id', $this->memberWithFunds->id);

        // By phone number
        Livewire::test(CounterPos::class)
            ->set('searchQuery', '9895011111')
            ->call('searchMember')
            ->assertSet('selectedMember.id', $this->memberWithFunds->id);
    }

    public function test_customer_live_search_autocomplete_and_arrow_navigation(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(CounterPos::class)
            ->set('searchQuery', 'MC-')
            ->assertCount('customerSearchResults', 5)
            ->call('navigateCustomerResults', 'down')
            ->assertSet('highlightedCustomerIndex', 1)
            ->call('navigateCustomerResults', 'up')
            ->assertSet('highlightedCustomerIndex', 0)
            ->call('selectHighlightedCustomer')
            ->assertSet('selectedMember.id', $this->memberWithFunds->id);
    }

    public function test_item_search_by_name_code_and_barcode(): void
    {
        $this->actingAs($this->cashier);

        // Search by name
        $t1 = Livewire::test(CounterPos::class)->set('itemSearch', 'Kingfisher');
        $this->assertNotEmpty($t1->get('itemSearchResults'));
        $t1->assertSeeHtml('BAR-BEER-KF');

        // Search by item code
        $t2 = Livewire::test(CounterPos::class)->set('itemSearch', 'BAR-BEER-KF');
        $this->assertNotEmpty($t2->get('itemSearchResults'));
        $t2->assertSeeHtml('Kingfisher Premium Beer 650ml');

        // Search by barcode
        $t3 = Livewire::test(CounterPos::class)->set('itemSearch', '890123400001');
        $this->assertNotEmpty($t3->get('itemSearchResults'));
        $t3->assertSeeHtml('Kingfisher Premium Beer 650ml');
    }

    public function test_multi_serving_item_requires_explicit_serving_selection(): void
    {
        $this->actingAs($this->cashier);

        // Searching for multi-serving Whisky item
        $test = Livewire::test(CounterPos::class)
            ->set('itemSearch', 'Signature')
            ->call('selectHighlightedItem')
            ->assertSet('showServingModal', true)
            ->assertSet('servingInventoryItemId', $this->whiskyItem->id)
            ->assertCount('servingItemOptions', 3);

        // Verify that 30 ml, 60 ml, and bottle are distinguished
        $options = $test->get('servingItemOptions');
        $servingNames = array_column($options, 'serving_name');
        $this->assertContains('30 ml (Small Peg)', $servingNames);
        $this->assertContains('60 ml (Large Peg)', $servingNames);
        $this->assertContains('Full Bottle (750ml)', $servingNames);

        // Select the 60ml option
        $test->call('selectServingAndAddToCart', $this->whisky60->id)
            ->assertSet('showServingModal', false)
            ->assertCount('cart', 1)
            ->assertSet('cart.0.tariff_id', $this->whisky60->id)
            ->assertSet('cart.0.quantity', 1)
            ->assertDispatched('focus-item-search');
    }

    public function test_default_quantity_is_one_and_supports_keyboard_editing_and_removal(): void
    {
        $this->actingAs($this->cashier);

        $test = Livewire::test(CounterPos::class)
            ->call('addToCart', $this->singleItemTariff->id)
            ->assertCount('cart', 1)
            ->assertSet('cart.0.quantity', 1);

        // Edit quantity to 3
        $test->call('setCartQuantity', 0, 3)
            ->assertSet('cart.0.quantity', 3)
            ->assertDispatched('focus-item-search');

        // Update quantity by +1
        $test->call('updateQuantity', 0, 1)
            ->assertSet('cart.0.quantity', 4);

        // Setting quantity to 0 removes line
        $test->call('setCartQuantity', 0, 0)
            ->assertCount('cart', 0);

        // Add again and test removeFromCart
        $test->call('addToCart', $this->singleItemTariff->id)
            ->assertCount('cart', 1)
            ->call('removeFromCart', 0)
            ->assertCount('cart', 0);
    }

    public function test_view_mode_switching_between_touch_cards_and_compact_list(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(CounterPos::class)
            ->assertSet('viewMode', 'grid')
            ->call('setViewMode', 'compact')
            ->assertSet('viewMode', 'compact')
            ->call('setViewMode', 'grid')
            ->assertSet('viewMode', 'grid');
    }

    public function test_end_to_end_keyboard_multi_item_sale_with_prepaid_payment(): void
    {
        $this->actingAs($this->cashier);

        $initialBalance = $this->memberWithFunds->wallet->balance_paise; // 500,000 paise

        $test = Livewire::test(CounterPos::class)
            // 1. Identify customer
            ->set('searchQuery', 'CARD-VENU-001')
            ->call('searchMember')
            ->assertSet('selectedMember.id', $this->memberWithFunds->id)

            // 2. Add first item (Beer)
            ->call('addToCart', $this->singleItemTariff->id)

            // 3. Add second item (Whisky 60ml via serving selection)
            ->call('selectServingAndAddToCart', $this->whisky60->id)

            // 4. Edit quantity of first item
            ->call('setCartQuantity', 0, 2)
            ->assertCount('cart', 2)

            // 5. Initiate Checkout via Prepaid tender [F8]
            ->call('initiateCheckout', 'prepaid')
            ->assertSet('showPaymentConfirmModal', true)
            ->assertSet('pendingTenderType', 'prepaid')

            // 6. Confirm checkout [Enter]
            ->call('confirmCheckout')
            ->assertSet('showPaymentConfirmModal', false)
            ->assertSet('showReceiptModal', true)
            ->assertCount('cart', 0);

        $this->assertNotEmpty($test->get('receiptContent'));

        // 7. Close receipt [Esc/Enter] and reset for next customer
        $test->call('closeReceiptModal')
            ->assertSet('showReceiptModal', false)
            ->assertSet('selectedMember', null)
            ->assertDispatched('focus-customer-search');

        // Verify balance decreased strictly on server ledger
        $updatedBalance = $this->memberWithFunds->wallet->fresh()->balance_paise;
        $this->assertLessThan($initialBalance, $updatedBalance);

        // Verify bill is settled
        $latestBill = Bill::latest()->first();
        $this->assertEquals('paid', $latestBill->status->value);
    }

    public function test_insufficient_prepaid_balance_rejected_with_no_credit(): void
    {
        $this->actingAs($this->cashier);

        $test = Livewire::test(CounterPos::class)
            // Select member with 0 balance
            ->set('searchQuery', 'MC-003')
            ->call('searchMember')
            ->assertSet('selectedMember.id', $this->memberZeroBalance->id)
            ->assertSet('memberWalletBalancePaise', 0)

            // Add item
            ->call('addToCart', $this->singleItemTariff->id)

            // Attempt Prepaid checkout
            ->call('initiateCheckout', 'prepaid')
            ->assertSet('showPaymentConfirmModal', false);

        $this->assertNotNull($test->get('errorMessage'));
        $test->assertSeeHtml('Insufficient prepaid balance');
    }

    public function test_out_of_stock_item_cannot_be_added_to_bill(): void
    {
        $this->actingAs($this->cashier);

        // Vodka has 0 stock
        $test = Livewire::test(CounterPos::class)
            ->call('addToCart', $this->outOfStockTariff->id)
            ->assertCount('cart', 0);

        $this->assertNotNull($test->get('errorMessage'));
        $test->assertSeeHtml('is out of stock');
    }

    public function test_out_of_stock_item_can_be_added_and_settled_when_allow_zero_stock_sales_enabled(): void
    {
        $this->actingAs($this->cashier);

        // Turn ON allow_zero_stock_sales in system settings
        \App\Domain\Shared\Models\SystemSetting::set('allow_zero_stock_sales', '1', 'billing');

        // Vodka initially has 0 stock
        $this->assertEquals(0, (float) $this->outOfStockItem->fresh()->current_stock_qty);

        $test = Livewire::test(CounterPos::class)
            // 1. Select member with funds
            ->set('searchQuery', 'CARD-VENU-001')
            ->call('searchMember')
            ->assertSet('selectedMember.id', $this->memberWithFunds->id)

            // 2. Add zero stock item to bill via serving selection - must succeed silently without errors
            ->call('selectServingAndAddToCart', $this->outOfStockTariff->id)
            ->assertSet('errorMessage', null)
            ->assertCount('cart', 1)

            // 3. Complete checkout via prepaid tender
            ->call('initiateCheckout', 'prepaid')
            ->assertSet('showPaymentConfirmModal', true)
            ->call('confirmCheckout')
            ->assertSet('showReceiptModal', true);

        // 4. Verify stock tracked into negative on inventory ledger
        $updatedVodka = $this->outOfStockItem->fresh();
        $this->assertLessThan(0, (float) $updatedVodka->current_stock_qty);

        // 5. Verify immutable inventory movement recorded with negative balance
        $movement = \App\Domain\Inventory\Models\InventoryMovement::where('inventory_item_id', $this->outOfStockItem->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertEquals('sale_issue', $movement->type);
        $this->assertLessThan(0, (float) $movement->quantity);
        $this->assertLessThan(0, (float) $movement->balance_after_qty);
    }

    public function test_zero_stock_pos_search_add_increment_and_checkout_work_with_database_cache(): void
    {
        $this->actingAs($this->cashier);

        config([
            'cache.default' => 'database',
            'cache.serializable_classes' => false,
        ]);
        app('cache')->forgetDriver('database');

        \App\Domain\Shared\Models\SystemSetting::set('allow_zero_stock_sales', '1', 'billing');

        // Warm database cache once before mounting CounterPos to mirror production cache hits
        $this->assertTrue(\App\Domain\Shared\Models\SystemSetting::getBool('allow_zero_stock_sales', false));

        $test = Livewire::test(CounterPos::class)
            ->set('searchQuery', 'CARD-VENU-001')
            ->call('searchMember')
            ->set('itemSearch', 'Vodka')
            ->assertSet('itemSearchResults.0.is_in_stock', true)
            ->call('selectServingAndAddToCart', $this->outOfStockTariff->id)
            ->assertSet('errorMessage', null)
            ->assertCount('cart', 1)
            ->call('updateQuantity', 0, 1)
            ->assertSet('errorMessage', null)
            ->assertSet('cart.0.quantity', 2)
            ->call('initiateCheckout', 'prepaid')
            ->assertSet('showPaymentConfirmModal', true)
            ->call('confirmCheckout')
            ->assertSet('showReceiptModal', true);

        $this->assertLessThan(0, (float) $this->outOfStockItem->fresh()->current_stock_qty);
    }

    public function test_payment_failure_does_not_lose_draft_cart(): void
    {
        $this->actingAs($this->cashier);

        $test = Livewire::test(CounterPos::class)
            ->call('addToCart', $this->singleItemTariff->id)
            ->assertCount('cart', 1)
            ->call('initiateCheckout', 'cash')
            ->assertSet('showPaymentConfirmModal', true)
            // User cancels modal via Esc
            ->call('closePaymentConfirmModal')
            ->assertSet('showPaymentConfirmModal', false)
            ->assertCount('cart', 1); // Cart is preserved!
    }

    public function test_repeated_submission_is_prevented(): void
    {
        $this->actingAs($this->cashier);

        $test = Livewire::test(CounterPos::class)
            ->set('searchQuery', 'CARD-VENU-001')
            ->call('searchMember')
            ->call('addToCart', $this->singleItemTariff->id)
            ->call('initiateCheckout', 'cash');

        // Simulate concurrent execution: while isProcessingPayment is true, confirmCheckout is a no-op
        $test->set('isProcessingPayment', true)
            ->call('confirmCheckout')
            ->assertSet('showPaymentConfirmModal', true); // Still open, didn't double-charge
    }

    public function test_clear_cart_empties_cart_and_resets_messages(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(CounterPos::class)
            ->call('addToCart', $this->singleItemTariff->id)
            ->assertCount('cart', 1)
            ->call('clearCart')
            ->assertCount('cart', 0)
            ->assertSet('isGuestSale', false)
            ->assertSet('errorMessage', null)
            ->assertSet('successMessage', null)
            ->assertDispatched('focus-item-search');
    }

    public function test_keyboard_shortcut_blade_view_contains_all_key_bindings(): void
    {
        $this->actingAs($this->cashier);

        $view = Livewire::test(CounterPos::class)
            ->assertSeeHtml('handleGlobalKeydown(e)')
            ->assertSeeHtml("e.key === 'F2'")
            ->assertSeeHtml("e.key === 'F3'")
            ->assertSeeHtml("e.key === 'F4'")
            ->assertSeeHtml("e.key === 'F8'")
            ->assertSeeHtml("e.key === 'F9'")
            ->assertSeeHtml("e.key === 'F10'")
            ->assertSeeHtml("e.key === 'Escape'")
            ->assertSeeHtml('wire.clearCart()')
            ->assertSeeHtml("e.key === 'Delete'");
    }

    public function test_error_dialog_box_renders_and_dismisses(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(CounterPos::class)
            ->set('errorMessage', 'Insufficient prepaid balance for this order.')
            ->assertSeeHtml('role="alertdialog"')
            ->assertSeeHtml('Transaction Alert')
            ->assertSee('Insufficient prepaid balance for this order.')
            ->assertSeeHtml('Acknowledge & Continue')
            ->set('errorMessage', null)
            ->assertDontSeeHtml('role="alertdialog"');
    }
}
