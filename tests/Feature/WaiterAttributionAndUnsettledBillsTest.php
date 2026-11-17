<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PosHeldTab;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Models\Waiter;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Integrations\Contracts\ReceiptPrinterInterface;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Tariff;
use App\Livewire\CounterPos;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WaiterAttributionAndUnsettledBillsTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;
    protected Counter $counter;
    protected Shift $shift;
    protected Member $member;
    protected Waiter $waiter;
    protected Tariff $tariff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // 1. Setup staff cashier user with permissions
        $this->staffUser = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->actingAs($this->staffUser);

        // 2. Setup counter and shift
        $this->counter = Counter::where('code', 'COUNTER-1')->firstOrFail();

        $this->shift = Shift::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->staffUser->id,
            'shift_number' => 'SFT-TEST-' . uniqid(),
            'business_date' => now()->toDateString(),
            'status' => ShiftStatus::OPEN,
            'opened_at' => now(),
            'opening_float_paise' => 200000,
        ]);

        // 3. Member with prepaid balance
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->member->wallet->update(['balance_paise' => 200000]);

        // 4. Setup bearer waiter
        $this->waiter = Waiter::firstOrCreate(
            ['code' => 'B01'],
            [
                'name' => 'Rajesh Kumar',
                'phone' => '+91 98470 10001',
                'department_code' => 'bar',
                'is_active' => true,
            ]
        );

        // 5. Setup a bar tariff item
        $this->tariff = Tariff::create([
            'code' => 'BAR-PEG-' . uniqid(),
            'name' => 'Single Malt 30ml Peg',
            'department_code' => 'bar',
            'rate_paise' => 25000,
            'tax_percent' => 5.0,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    public function test_waiter_can_be_attributed_on_draft_bill_and_tips_posted_to_liability_account(): void
    {
        /** @var BillingService $billingService */
        $billingService = app(BillingService::class);

        // 1. Create draft bill attributed to waiter at Table 4
        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [
                [
                    'department_code' => 'bar',
                    'item_type' => 'tariff',
                    'item_id' => $this->tariff->id,
                    'item_name' => $this->tariff->name,
                    'quantity' => 2,
                    'unit_price_paise' => 25000,
                    'tax_percent' => 5.0,
                ]
            ],
            idempotencyKey: 'test_waiter_bill_' . uniqid(),
            userId: $this->staffUser->id,
            waiterId: $this->waiter->id,
            tableNumber: 'Table 4'
        );

        $this->assertEquals($this->waiter->id, $bill->waiter_id);
        $this->assertEquals('Table 4', $bill->table_number);
        $this->assertEquals(BillStatus::DRAFT, $bill->status);

        // 2. Checkout with ₹50 tip (5000 paise)
        $tipPaise = 5000;
        $payablePaise = $bill->net_amount_paise + $tipPaise;

        $checkoutResult = $billingService->checkout(
            bill: $bill,
            tenders: [
                [
                    'tender_type' => TenderType::PREPAID,
                    'amount_paise' => $payablePaise,
                ]
            ],
            userId: $this->staffUser->id,
            tipAmountPaise: $tipPaise
        );

        $bill->refresh();
        $this->assertEquals(BillStatus::PAID, $bill->status);
        $this->assertEquals($tipPaise, $bill->tip_amount_paise);
        $this->assertEquals($payablePaise, $bill->paid_amount_paise);

        // 3. Verify tip is credited to Account 2040 (Staff Tips & Gratuity Payable)
        $tipAccount = \App\Domain\Finance\Models\Account::where('code', '2040')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', [
            'account_id' => $tipAccount->id,
            'credit_paise' => $tipPaise,
        ]);

        // 4. Verify waiter total tips accumulated
        $this->assertEquals($tipPaise, $this->waiter->tipsTotalPaise());

        // 5. Verify receipt formatting includes Bearer, Spot, and Tip
        /** @var ReceiptPrinterInterface $printer */
        $printer = app(ReceiptPrinterInterface::class);
        $receipt = $checkoutResult['receipts'][0];
        $text = $printer->render80mmReceipt($bill, $receipt);

        $this->assertStringContainsString('Bearer:   Rajesh Kumar (B01)', $text);
        $this->assertStringContainsString('Table:    Table 4', $text);
        $this->assertStringContainsString('Staff Tip (Gratuity):                 ₹50.00', $text);
    }

    public function test_unsettled_walkout_bill_deducts_cellar_stock_immediately(): void
    {
        // 1. Create inventory item and link to tariff
        $invItem = InventoryItem::create([
            'sku' => 'SKU-LIQ-' . uniqid(),
            'name' => 'Whisky 750ml Bottle',
            'category' => 'liquor',
            'base_unit' => 'ml',
            'current_stock_qty' => 750.0,
            'current_avg_cost_paise' => 150,
            'reorder_level_qty' => 100.0,
            'is_active' => true,
        ]);

        ServingMapping::create([
            'tariff_id' => $this->tariff->id,
            'inventory_item_id' => $invItem->id,
            'serving_name' => '30ml Peg',
            'serving_qty' => 30.0,
        ]);

        /** @var BillingService $billingService */
        $billingService = app(BillingService::class);

        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [
                [
                    'department_code' => 'bar',
                    'item_type' => 'tariff',
                    'item_id' => $this->tariff->id,
                    'item_name' => $this->tariff->name,
                    'quantity' => 2, // 2 x 30ml = 60ml
                    'unit_price_paise' => 25000,
                    'tax_percent' => 5.0,
                ]
            ],
            idempotencyKey: 'test_walkout_' . uniqid(),
            userId: $this->staffUser->id,
            waiterId: $this->waiter->id,
            tableNumber: 'Bar Stool 2'
        );

        // 2. Member leaves counter without paying -> record unsettled walkout
        $unsettledBill = $billingService->recordUnsettledWalkout(
            bill: $bill,
            reason: 'Member left counter after drink without paying',
            userId: $this->staffUser->id,
            waiterId: $this->waiter->id,
            tableNumber: 'Bar Stool 2'
        );

        $this->assertEquals(BillStatus::UNSETTLED, $unsettledBill->status);
        $this->assertNotNull($unsettledBill->unsettled_at);
        $this->assertEquals('Member left counter after drink without paying', $unsettledBill->unsettled_reason);

        // 3. Verify stock was deducted immediately: 750ml - 60ml = 690ml
        $invItem->refresh();
        $this->assertEquals(690.0, (float) $invItem->current_stock_qty);

        // Verify inventory movement
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $invItem->id,
            'type' => 'sale_issue',
            'quantity' => -60.0,
        ]);

        // 4. Verify settling later does NOT double-deduct inventory
        $billingService->checkout(
            bill: $unsettledBill,
            tenders: [
                [
                    'tender_type' => TenderType::CASH,
                    'amount_paise' => $unsettledBill->net_amount_paise,
                ]
            ],
            userId: $this->staffUser->id
        );

        $invItem->refresh();
        $this->assertEquals(690.0, (float) $invItem->current_stock_qty);
    }

    public function test_counter_pos_locks_ordering_when_member_has_outstanding_unsettled_dues(): void
    {
        /** @var BillingService $billingService */
        $billingService = app(BillingService::class);

        // 1. Create an existing unsettled walk-out bill for member
        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [
                [
                    'department_code' => 'bar',
                    'item_type' => 'tariff',
                    'item_id' => $this->tariff->id,
                    'item_name' => $this->tariff->name,
                    'quantity' => 1,
                    'unit_price_paise' => 25000,
                    'tax_percent' => 5.0,
                ]
            ],
            idempotencyKey: 'test_lockout_' . uniqid(),
            userId: $this->staffUser->id,
            waiterId: $this->waiter->id,
            tableNumber: 'Table 7'
        );

        $billingService->recordUnsettledWalkout(
            bill: $bill,
            reason: 'Left premises without paying',
            userId: $this->staffUser->id
        );

        // 2. Member returns next time to Counter POS
        $component = Livewire::actingAs($this->staffUser)
            ->test(CounterPos::class)
            ->call('selectCustomerById', $this->member->id);

        // Verify POS detects outstanding dues and locks ordering
        $component->assertSet('hasOutstandingUnsettledBills', true)
            ->assertSet('showOutstandingDuesModal', true)
            ->assertSet('outstandingTotalPaise', $bill->net_amount_paise);

        // 3. Verify attempting to add items is strictly blocked
        $component->call('addToCart', $this->tariff->id)
            ->assertSee('Ordering locked! Member has outstanding walk-out dues that must be settled first.')
            ->assertSet('cart', []);

        // 4. Verify attempting to checkout is blocked
        $component->call('initiateCheckout', 'cash')
            ->assertSee('Ordering locked! Member has outstanding walk-out dues that must be settled first.');

        // 5. Cashier settles the outstanding bill via the dues modal with ₹20 tip
        $component->set('settlingBillId', $bill->id)
            ->set('settlingBillTipAmount', 20.00)
            ->set('settlingTenderType', 'prepaid')
            ->call('settleOutstandingBill');

        // Verify bill is now paid
        $bill->refresh();
        $this->assertEquals(BillStatus::PAID, $bill->status);
        $this->assertEquals(2000, $bill->tip_amount_paise);

        // Verify POS ordering is now unlocked!
        $component->assertSet('hasOutstandingUnsettledBills', false)
            ->assertSet('showOutstandingDuesModal', false);

        // 6. Member can now order normally!
        $component->call('addToCart', $this->tariff->id)
            ->assertSet('errorMessage', null);

        $this->assertCount(1, $component->get('cart'));
    }

    public function test_counter_pos_can_record_unsettled_walkout_directly_from_cart(): void
    {
        $component = Livewire::actingAs($this->staffUser)
            ->test(CounterPos::class)
            ->call('selectCustomerById', $this->member->id)
            ->call('addToCart', $this->tariff->id)
            ->set('selectedWaiterId', $this->waiter->id)
            ->set('tableNumber', 'Lounge 3')
            ->call('openUnsettledWalkoutModal')
            ->assertSet('showUnsettledWalkoutModal', true)
            ->set('unsettledWalkoutReason', 'Member called away on emergency before paying')
            ->call('recordUnsettledWalkout');

        // Cart is cleared
        $component->assertSet('cart', [])
            ->assertSet('showUnsettledWalkoutModal', false);

        // Bill created with UNSETTLED status and waiter attribution
        $unsettledBill = Bill::where('customer_id', $this->member->id)
            ->where('status', BillStatus::UNSETTLED)
            ->latest()
            ->first();

        $this->assertNotNull($unsettledBill);
        $this->assertEquals($this->waiter->id, $unsettledBill->waiter_id);
        $this->assertEquals('Lounge 3', $unsettledBill->table_number);
        $this->assertEquals('Member called away on emergency before paying', $unsettledBill->unsettled_reason);
    }

    public function test_counter_pos_can_print_pre_settlement_table_check_for_active_cart_and_held_tab(): void
    {
        // 1. Test Active Cart Table Check
        $component = Livewire::actingAs($this->staffUser)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->call('selectCustomerById', $this->member->id)
            ->call('addToCart', $this->tariff->id)
            ->set('selectedWaiterId', $this->waiter->id)
            ->set('tableNumber', 'Table 4')
            ->call('printCurrentCartTableCheck')
            ->assertSet('showReceiptModal', true)
            ->assertSet('isTableCheckReceipt', true);

        $receiptContent = $component->get('receiptContent');
        $this->assertStringContainsString('TABLE CHECK / PRE-BILL', $receiptContent);
        $this->assertStringContainsString('Table 4', $receiptContent);
        $this->assertStringContainsString('Rajesh Kumar', $receiptContent);
        $this->assertStringContainsString('Single Malt 30ml Peg', $receiptContent);
        $this->assertStringContainsString('Add Bearer Tip', $receiptContent);
        $this->assertStringContainsString('Member Signature', $receiptContent);

        // Cart is retained for floor service
        $this->assertCount(1, $component->get('cart'));

        // 2. Park tab to table
        $component->call('closeReceiptModal')
            ->assertSet('showReceiptModal', false)
            ->assertSet('isTableCheckReceipt', false)
            ->call('holdCurrentCart');

        $tab = PosHeldTab::where('member_id', $this->member->id)->latest()->first();
        $this->assertNotNull($tab);
        $this->assertEquals('Table 4', $tab->table_identifier);

        // 3. Test Held Tab Table Check printing directly from manager / chips
        $component->call('printHeldTabTableCheck', $tab->id)
            ->assertSet('showReceiptModal', true)
            ->assertSet('isTableCheckReceipt', true);

        $heldReceiptContent = $component->get('receiptContent');
        $this->assertStringContainsString('TABLE CHECK / PRE-BILL', $heldReceiptContent);
        $this->assertStringContainsString($tab->tab_number, $heldReceiptContent);
        $this->assertStringContainsString('Table 4', $heldReceiptContent);
        $this->assertStringContainsString('Rajesh Kumar', $heldReceiptContent);
    }

    public function test_counter_pos_modals_can_be_dismissed(): void
    {
        $component = Livewire::actingAs($this->staffUser)
            ->test(CounterPos::class)
            ->call('selectCustomerById', $this->member->id)
            ->call('addToCart', $this->tariff->id);

        // 1. Walkout modal can be opened and closed (e.g. on Esc)
        $component->call('openUnsettledWalkoutModal')
            ->assertSet('showUnsettledWalkoutModal', true)
            ->call('closeUnsettledWalkoutModal')
            ->assertSet('showUnsettledWalkoutModal', false);

        // Cart items are preserved when modal is cancelled
        $this->assertNotEmpty($component->get('cart'));

        // 2. Outstanding dues modal can be opened and closed (e.g. on Esc)
        $component->set('hasOutstandingUnsettledBills', true)
            ->call('openOutstandingDuesModal')
            ->assertSet('showOutstandingDuesModal', true)
            ->call('closeOutstandingDuesModal')
            ->assertSet('showOutstandingDuesModal', false);

        // 3. Hold tab modal can be opened and closed
        $component->call('openHoldTabModal')
            ->assertSet('showHoldTabModal', true)
            ->call('closeHoldTabModal')
            ->assertSet('showHoldTabModal', false);

        // 4. Held tabs manager list modal can be opened and closed
        $component->call('openHeldTabsListModal')
            ->assertSet('showHeldTabsListModal', true)
            ->call('closeHeldTabsListModal')
            ->assertSet('showHeldTabsListModal', false);
    }

    public function test_counter_pos_proactively_prompts_to_resume_when_member_has_active_held_tab(): void
    {
        // 1. Create an active held tab for the member
        $component = Livewire::actingAs($this->staffUser)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->call('selectCustomerById', $this->member->id)
            ->call('addToCart', $this->tariff->id)
            ->set('tabTableIdentifier', 'Table 9')
            ->set('selectedWaiterId', $this->waiter->id)
            ->call('holdCurrentCart');

        // Workspace reset to clean bill
        $component->assertSet('activeHeldTabId', null)
            ->assertSet('cart', []);

        $tab = PosHeldTab::where('member_id', $this->member->id)->open()->first();
        $this->assertNotNull($tab);
        $this->assertEquals('Table 9', $tab->table_identifier);

        // 2. Another sale occurs, then staff selects the same member again
        $component->call('selectCustomerById', $this->member->id);

        // Verify proactive prompt modal is triggered
        $component->assertSet('showExistingTabPromptModal', true)
            ->assertSet('promptExistingTabId', $tab->id)
            ->assertSee('Active Order Detected')
            ->assertSee('Resume Existing Tab?');

        // 3. Staff accepts prompt to resume existing tab (e.g. hits Enter)
        $component->call('resumePromptTab')
            ->assertSet('showExistingTabPromptModal', false)
            ->assertSet('activeHeldTabId', $tab->id);

        // Existing cart items from the tab are restored
        $this->assertCount(1, $component->get('cart'));
        $this->assertEquals($this->tariff->id, $component->get('cart')[0]['tariff_id']);
        $this->assertEquals('Table 9', $component->get('tableNumber'));
        $this->assertEquals($this->waiter->id, $component->get('selectedWaiterId'));

        // 4. Staff adds another item -> it gets added straight to the existing tab
        $component->call('addToCart', $this->tariff->id);
        $this->assertEquals(2, $component->get('cart')[0]['quantity']);

        // 5. Test dismissing the prompt to start a separate fresh bill
        $component->call('resetWorkspaceToNewBill')
            ->call('selectCustomerById', $this->member->id)
            ->assertSet('showExistingTabPromptModal', true)
            ->call('dismissExistingTabPrompt')
            ->assertSet('showExistingTabPromptModal', false)
            ->assertSet('activeHeldTabId', null)
            ->assertSet('cart', []);
    }

    public function test_accompanying_guest_name_is_captured_on_bill_tab_and_receipt(): void
    {
        $component = Livewire::test(CounterPos::class)
            ->set('activeShift', $this->shift)
            ->set('selectedCounterId', $this->counter->id)
            ->call('selectCustomerById', $this->member->id)
            ->call('addToCart', $this->tariff->id)
            ->set('tableNumber', 'Table 4')
            ->set('selectedWaiterId', $this->waiter->id)
            ->set('accompanyingGuestName', 'Suresh Menon');

        // 1. Hold tab and verify accompanying guest is persisted
        $component->call('holdCurrentCart');

        $tab = PosHeldTab::where('member_id', $this->member->id)->open()->first();
        $this->assertNotNull($tab);
        $this->assertEquals('Suresh Menon', $tab->accompanying_guest_name);

        // 2. Load tab and verify component state is restored
        $component->call('loadHeldTab', $tab->id);
        $this->assertEquals('Suresh Menon', $component->get('accompanyingGuestName'));

        // 3. Print table check and verify guest name is printed
        $component->call('printCurrentCartTableCheck', app(ReceiptPrinterInterface::class));
        $this->assertStringContainsString('Guest:    Suresh Menon', $component->get('receiptContent'));

        // 4. Settle checkout and verify Bill has accompanying_guest_name
        $component->call('initiateCheckout', 'cash')
            ->call('confirmCheckout');

        $bill = Bill::latest('id')->first();
        $this->assertNotNull($bill);
        $this->assertEquals('Suresh Menon', $bill->accompanying_guest_name);

        // 5. Verify thermal receipt formatting contains guest name
        $printer = app(ReceiptPrinterInterface::class);
        $receipt = $printer->render80mmReceipt($bill);
        $this->assertStringContainsString('Guest:    Suresh Menon', $receipt);
    }
}
