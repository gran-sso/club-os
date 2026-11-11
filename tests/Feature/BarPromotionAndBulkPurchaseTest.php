<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PosHeldTab;
use App\Domain\Billing\Models\Promotion;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\PromotionService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Services\FinancialReportService;
use App\Domain\Integrations\Contracts\ReceiptPrinterInterface;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\PurchaseReceipt;
use App\Domain\Inventory\Models\PurchaseReceiptLine;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Models\SystemSetting;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Livewire\CounterPos;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BarPromotionAndBulkPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected Member $member;
    protected Counter $counter;
    protected Shift $shift;
    protected Supplier $supplier;
    protected InventoryService $inventoryService;
    protected BillingService $billingService;
    protected ShiftService $shiftService;
    protected PromotionService $promotionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->inventoryService = app(InventoryService::class);
        $this->billingService = app(BillingService::class);
        $this->shiftService = app(ShiftService::class);
        $this->promotionService = app(PromotionService::class);

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-2')->firstOrFail();

        $this->shift = $this->shiftService->openShift(
            counterId: $this->counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 200000
        );

        $this->supplier = Supplier::create([
            'name' => 'Kerala State Beverages Corporation (KSBC)',
            'code' => 'SUP-KSBC-PROMO',
            'contact_person' => 'Depot Manager',
            'phone' => '0484-2345678',
            'gstin' => '32AAACK1234F1Z5',
            'is_active' => true,
        ]);
    }

    public function test_bulk_liquor_purchase_with_supplier_cash_discount_updates_weighted_avg_cost_and_journal(): void
    {
        $item = InventoryItem::create([
            'sku' => 'LIQ-BULK-CASE',
            'name' => 'Signature Reserve Whisky 750ml',
            'department_code' => 'bar',
            'category' => 'whisky',
            'base_unit' => 'ml',
            'current_stock_qty' => 0,
            'current_avg_cost_paise' => 0,
            'is_active' => true,
        ]);

        // 1 case = 12 bottles x 750ml = 9,000ml.
        // Gross = ₹18,000.00 (1,800,000 paise); Supplier cash discount for 6 bottles = ₹9,000.00 (900,000 paise).
        // Net cost = ₹9,000.00 across 9,000ml => exact 100 paise/ml (₹1.00/ml).
        $receipt = $this->inventoryService->receivePurchase(
            supplier: $this->supplier,
            invoiceNumber: 'KSBC-BULK-001',
            invoiceDate: now()->toDateString(),
            items: [
                [
                    'inventory_item_id' => $item->id,
                    'quantity' => 9000.000,
                    'unit_cost_paise' => 200,
                    'line_gross_paise' => 1800000,
                    'discount_paise' => 900000,
                    'tax_percent' => 0.00,
                    'scheme_notes' => '1 case (12 bottles) less 6-bottle cash discount',
                ],
            ],
            idempotencyKey: 'pur_bulk_case_001',
            shiftId: $this->shift->id,
            userId: $this->manager->id
        );

        $item->refresh();
        $receipt->refresh();
        $line = PurchaseReceiptLine::where('purchase_receipt_id', $receipt->id)->firstOrFail();

        // All 12 bottles entered physical stock; weighted avg reflects net discounted cost
        $this->assertEquals(9000.000, (float) $item->current_stock_qty);
        $this->assertEquals(100, $item->current_avg_cost_paise);

        // Receipt & line snapshots preserve net taxable stock value, supplier discount, and net payable cleanly
        $this->assertEquals(900000, $receipt->total_gross_paise);
        $this->assertEquals(900000, $receipt->total_discount_paise);
        $this->assertEquals(900000, $receipt->total_net_paise);
        $this->assertEquals(200, $line->gross_unit_cost_paise);
        $this->assertEquals(900000, $line->discount_paise);
        $this->assertEquals(100, $line->unit_cost_paise);
        $this->assertEquals(900000, $line->line_total_paise);

        // Balanced GL journal matches net paid cost (Debit 1030 Inventory ₹9,000, Credit 1020 Bank Clearing ₹9,000)
        $journal = JournalHeader::where('source_type', 'purchase_receipt')
            ->where('source_id', $receipt->id)
            ->firstOrFail();
        $invAccId = Account::where('code', '1030')->value('id');
        $bankAccId = Account::where('code', '1020')->value('id');

        $this->assertEquals(900000, $journal->lines()->where('account_id', $invAccId)->value('debit_paise'));
        $this->assertEquals(900000, $journal->lines()->where('account_id', $bankAccId)->value('credit_paise'));

        // Next batch at regular price (9,000ml @ 200 paise/ml) merges weighted average to 150 paise/ml
        $this->inventoryService->receivePurchase(
            supplier: $this->supplier,
            invoiceNumber: 'KSBC-BULK-002',
            invoiceDate: now()->toDateString(),
            items: [
                [
                    'inventory_item_id' => $item->id,
                    'quantity' => 9000.000,
                    'unit_cost_paise' => 200,
                    'tax_percent' => 0.00,
                ],
            ],
            idempotencyKey: 'pur_bulk_case_002',
            shiftId: $this->shift->id,
            userId: $this->manager->id
        );

        $item->refresh();
        $this->assertEquals(18000.000, (float) $item->current_stock_qty);
        $this->assertEquals(150, $item->current_avg_cost_paise);
    }

    public function test_buy_two_get_one_free_checkout_charges_net_deducts_three_pegs_posts_balanced_journal_and_prints_receipt(): void
    {
        $item = InventoryItem::create([
            'sku' => 'LIQ-PROMO-WHISKY',
            'name' => 'Malabar Malt 750ml',
            'department_code' => 'bar',
            'category' => 'whisky',
            'base_unit' => 'ml',
            'current_stock_qty' => 0,
            'current_avg_cost_paise' => 0,
            'is_active' => true,
        ]);

        $this->inventoryService->receivePurchase(
            supplier: $this->supplier,
            invoiceNumber: 'KSBC-PROMO-101',
            invoiceDate: now()->toDateString(),
            items: [
                [
                    'inventory_item_id' => $item->id,
                    'quantity' => 1200.000,
                    'unit_cost_paise' => 100, // ₹1.00/ml net cost
                    'tax_percent' => 0.00,
                ],
            ],
            idempotencyKey: 'pur_promo_101',
            shiftId: $this->shift->id,
            userId: $this->manager->id
        );

        $tariff = Tariff::create([
            'code' => 'BAR-MALT-30',
            'department_code' => 'bar',
            'name' => 'Malabar Malt 30ml Peg',
            'rate_paise' => 20000, // ₹200 per peg
            'tax_percent' => 18.00,
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        ServingMapping::create([
            'tariff_id' => $tariff->id,
            'inventory_item_id' => $item->id,
            'serving_name' => '30ml Peg',
            'serving_qty' => 30.000,
        ]);

        $promo = Promotion::create([
            'code' => 'MALT-2PLUS1',
            'name' => 'Malabar Malt — Buy 2 Pegs Get 1 Free',
            'type' => Promotion::TYPE_BUY_X_GET_Y,
            'buy_qty' => 2,
            'get_qty' => 1,
            'discount_percent' => 100.00,
            'customer_scope' => Promotion::SCOPE_MEMBERS_ONLY,
            'effective_from' => now()->subDay()->toDateString(),
            'max_free_units' => 150,
            'is_active' => true,
            'created_by_user_id' => $this->manager->id,
        ]);
        $promo->tariffs()->attach($tariff->id);

        // Order 3 pegs -> 2 paid (₹400), 1 free (₹200 discount), 18% GST on ₹400 = ₹72, Net = ₹472
        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [
                [
                    'department_code' => 'bar',
                    'item_type' => 'tariff',
                    'item_id' => $tariff->id,
                    'item_name' => $tariff->name,
                    'quantity' => 3,
                    'unit_price_paise' => 20000,
                    'tax_percent' => 18.00,
                    'expected_discount_paise' => 20000,
                ],
            ],
            idempotencyKey: 'bill_2plus1_001',
            userId: $this->cashier->id
        );

        $this->assertEquals(60000, $bill->gross_amount_paise);
        $this->assertEquals(20000, $bill->discount_amount_paise);
        $this->assertEquals(7200, $bill->tax_amount_paise);
        $this->assertEquals(47200, $bill->net_amount_paise);

        $line = $bill->lines()->firstOrFail();
        $this->assertEquals($promo->id, $line->promotion_id);
        $this->assertEquals($promo->name, $line->promotion_name);
        $this->assertEquals(3.00, (float) $line->quantity);
        $this->assertEquals(1.00, (float) $line->free_quantity);
        $this->assertEquals(60000, $line->gross_amount_paise);
        $this->assertEquals(20000, $line->discount_amount_paise);
        $this->assertEquals(7200, $line->tax_amount_paise);
        $this->assertEquals(47200, $line->line_total_paise);

        $checkout = $this->billingService->checkout(
            bill: $bill,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => 47200]],
            userId: $this->cashier->id
        );

        // Inventory deducts ALL 3 poured pegs = 90 ml (1200 - 90 = 1110 ml)
        $item->refresh();
        $this->assertEquals(1110.000, (float) $item->current_stock_qty);

        $movement = InventoryMovement::where('inventory_item_id', $item->id)
            ->where('type', 'sale_issue')
            ->firstOrFail();
        $this->assertEquals(-90.000, (float) $movement->quantity);
        $this->assertEquals(9000, $movement->total_cost_paise);
        $this->assertStringContainsString('promo free', $movement->notes);

        // Quota redeemed count incremented atomically
        $promo->refresh();
        $this->assertEquals(1, $promo->redeemed_free_units);

        // GL Revenue Journal is balanced: Debit Cash (1010) 47200, Credit Bar Revenue (4020) 40000, Credit GST (2030) 7200
        $saleJournal = JournalHeader::where('source_type', 'bill')
            ->where('source_id', $bill->id)
            ->firstOrFail();
        $cashAcc = Account::where('code', '1010')->value('id');
        $barRevAcc = Account::where('code', '4020')->value('id');
        $gstAcc = Account::where('code', '2030')->value('id');

        $this->assertEquals(47200, $saleJournal->lines()->where('account_id', $cashAcc)->sum('debit_paise'));
        $this->assertEquals(40000, $saleJournal->lines()->where('account_id', $barRevAcc)->sum('credit_paise'));
        $this->assertEquals(7200, $saleJournal->lines()->where('account_id', $gstAcc)->sum('credit_paise'));
        $this->assertEquals(
            $saleJournal->lines()->sum('debit_paise'),
            $saleJournal->lines()->sum('credit_paise')
        );

        // COGS Journal covers ALL 90ml poured (9000 paise)
        $cogsJournal = JournalHeader::where('source_type', 'cogs_issue')
            ->where('source_id', $line->id)
            ->firstOrFail();
        $cogsAcc = Account::where('code', '5010')->value('id');
        $invAcc = Account::where('code', '1030')->value('id');
        $this->assertEquals(9000, $cogsJournal->lines()->where('account_id', $cogsAcc)->sum('debit_paise'));
        $this->assertEquals(9000, $cogsJournal->lines()->where('account_id', $invAcc)->sum('credit_paise'));

        // Receipt clearly displays Offer line and Promo Discount savings
        $printer = app(ReceiptPrinterInterface::class);
        $receiptText = $printer->render80mmReceipt($bill->fresh(['lines']), $checkout['receipts'][0]);
        $this->assertStringContainsString('Offer:', $receiptText);
        $this->assertStringContainsString('Promo Discount:', $receiptText);
        $this->assertStringContainsString('-200.00', $receiptText);
        $this->assertStringContainsString('472.00', $receiptText);

        // Executive reports recognize real sale_issue COGS
        $reportService = app(FinancialReportService::class);
        $barReport = $reportService->getBarAndStockReport(now()->toDateString(), now()->toDateString());
        $this->assertGreaterThanOrEqual(9000, $barReport['summary']['period_cogs_paise']);

        $ieReport = $reportService->getIncomeAndExpenditureReport(now()->toDateString(), now()->toDateString());
        $this->assertGreaterThanOrEqual(9000, $ieReport['expenditures']['bar_cogs_paise']);
    }

    public function test_counter_pos_livewire_promo_cta_held_tabs_guest_exclusion_and_stock_guard(): void
    {
        SystemSetting::set('allow_zero_stock_sales', false, 'boolean');

        $item = InventoryItem::create([
            'sku' => 'LIQ-POS-GIN',
            'name' => 'Botanical Gin 750ml',
            'department_code' => 'bar',
            'category' => 'gin',
            'base_unit' => 'ml',
            'current_stock_qty' => 240.000, // Enough for 8 x 30ml pegs initially
            'current_avg_cost_paise' => 120,
            'is_active' => true,
        ]);

        $tariff = Tariff::create([
            'code' => 'BAR-GIN-30',
            'department_code' => 'bar',
            'name' => 'Botanical Gin 30ml Peg',
            'rate_paise' => 20000, // ₹200
            'tax_percent' => 18.00,
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        ServingMapping::create([
            'tariff_id' => $tariff->id,
            'inventory_item_id' => $item->id,
            'serving_name' => '30ml Peg',
            'serving_qty' => 30.000,
        ]);

        $promo = Promotion::create([
            'code' => 'GIN-2PLUS1',
            'name' => 'Gin Happy Offer — Buy 2 Get 1 Free',
            'type' => Promotion::TYPE_BUY_X_GET_Y,
            'buy_qty' => 2,
            'get_qty' => 1,
            'discount_percent' => 100.00,
            'customer_scope' => Promotion::SCOPE_MEMBERS_ONLY,
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);
        $promo->tariffs()->attach($tariff->id);

        $pos = Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->set('itemSearch', 'Botanical Gin')
            ->assertSet('itemSearchResults.0.promo_badge', 'Buy 2 Get 1 Free');

        // 1. Add 2 pegs: promo CTA shown, no free peg prematurely added or charged yet
        $pos->call('addToCart', $tariff->id)
            ->call('addToCart', $tariff->id)
            ->assertSet('cart.0.quantity', 2)
            ->assertSet('cart.0.promo_cta_add_qty', 1)
            ->assertSet('cartGross', 400.00)
            ->assertSet('cartDiscount', 0.00)
            ->assertSet('cartTax', 72.00)
            ->assertSet('cartNet', 472.00)
            ->assertSee('+1 Free Offer Available');

        // 2. Click 1-click "+1 Free Offer Available" button -> becomes 3 pegs (2 Paid + 1 Free)
        $pos->call('claimPromoFreeItem', 0)
            ->assertSet('cart.0.quantity', 3)
            ->assertSet('cart.0.free_quantity', 1)
            ->assertSet('cartGross', 600.00)
            ->assertSet('cartDiscount', 200.00)
            ->assertSet('cartTax', 72.00)
            ->assertSet('cartNet', 472.00)
            ->assertSee('Promotional Discount');

        // 3. Switch to Day Guest sale -> members-only offer immediately excluded
        $pos->set('isGuestSale', true)
            ->assertSet('cartDiscount', 0.00)
            ->assertSet('cartNet', 708.00)
            ->set('isGuestSale', false)
            ->assertSet('cartDiscount', 200.00)
            ->assertSet('cartNet', 472.00);

        // 4. Held Tab / Running Bar Tab across 3 separate rounds -> promo applies cleanly upon reaching 3
        $pos->call('clearCart')
            ->call('addToCart', $tariff->id) // Round 1: 1 peg
            ->call('openHoldTabModal')
            ->set('tabTableIdentifier', 'Bar Table 7')
            ->call('holdCurrentCart');

        $tab = PosHeldTab::where('shift_id', $this->shift->id)->where('status', 'open')->firstOrFail();

        $pos->call('loadHeldTab', $tab->id)
            ->call('addToCart', $tariff->id) // Round 2: 2 pegs -> CTA appears, park again
            ->call('openHoldTabModal')
            ->call('holdCurrentCart');

        $tab->refresh();
        $this->assertEquals(47200, $tab->net_amount_paise);

        $pos->call('loadHeldTab', $tab->id)
            ->call('claimPromoFreeItem', 0) // Round 3: claim 3rd free peg & settle
            ->assertSet('cartDiscount', 200.00)
            ->assertSet('cartNet', 472.00)
            ->call('initiateCheckout', 'cash')
            ->call('confirmCheckout')
            ->assertSet('errorMessage', null);

        // 90ml deducted (240 - 90 = 150ml); lower stock to exactly 60ml (2 pegs remaining)
        $item->update(['current_stock_qty' => 60.000]);

        // 5. Cumulative stock check: cashier adds 2 pegs (60ml); clicking "+1 Free" must be blocked cleanly!
        $pos->call('closeReceiptModal')
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->call('addToCart', $tariff->id)
            ->call('addToCart', $tariff->id)
            ->assertSet('cart.0.quantity', 2)
            ->assertSet('cart.0.promo_cta_add_qty', 1)
            ->call('claimPromoFreeItem', 0)
            ->assertSet('cart.0.quantity', 2)
            ->assertSee('Insufficient stock');
    }

    public function test_protected_guest_tariff_tamper_resistance_quota_cap_and_filament_resources(): void
    {
        $guestTariff = Tariff::where('code', 'GUEST-BAR-ENTRY')->firstOrFail();
        $this->assertTrue($this->promotionService->isTariffProtectedFromPromotion($guestTariff));

        // Even if someone links a promo in DB, guest entry fees remain protected
        $badPromo = Promotion::create([
            'code' => 'BAD-GUEST-FREE',
            'name' => 'Invalid Guest Offer',
            'type' => Promotion::TYPE_BUY_X_GET_Y,
            'buy_qty' => 1,
            'get_qty' => 1,
            'discount_percent' => 100.00,
            'customer_scope' => Promotion::SCOPE_ALL,
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);
        $badPromo->tariffs()->attach($guestTariff->id);

        $guestEval = $this->promotionService->evaluateCart([
            [
                'item_type' => 'tariff',
                'item_id' => $guestTariff->id,
                'quantity' => 2,
                'unit_price_paise' => $guestTariff->rate_paise,
                'tax_percent' => $guestTariff->tax_percent,
            ],
        ], $this->member);
        $this->assertEquals(0, $guestEval['discount_paise']);

        // Tampered expected_discount_paise must throw RuntimeException
        $barTariff = Tariff::create([
            'code' => 'BAR-RUM-30',
            'department_code' => 'bar',
            'name' => 'Dark Rum 30ml Peg',
            'rate_paise' => 15000,
            'tax_percent' => 18.00,
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $cappedPromo = Promotion::create([
            'code' => 'RUM-CAP-1',
            'name' => 'Limited Rum Buy 2 Get 1 Free',
            'type' => Promotion::TYPE_BUY_X_GET_Y,
            'buy_qty' => 2,
            'get_qty' => 1,
            'discount_percent' => 100.00,
            'customer_scope' => Promotion::SCOPE_ALL,
            'effective_from' => now()->subDay()->toDateString(),
            'max_free_units' => 1,
            'is_active' => true,
        ]);
        $cappedPromo->tariffs()->attach($barTariff->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [
                [
                    'department_code' => 'bar',
                    'item_type' => 'tariff',
                    'item_id' => $barTariff->id,
                    'item_name' => $barTariff->name,
                    'quantity' => 3,
                    'unit_price_paise' => 15000,
                    'tax_percent' => 18.00,
                    'expected_discount_paise' => 45000, // Client spoofed 100% discount on all 3 instead of 15000!
                ],
            ],
            idempotencyKey: 'bill_tamper_001',
            userId: $this->cashier->id
        );
    }

    public function test_promo_quota_exhaustion_blocks_checkout_and_filament_permissions_work(): void
    {
        $barTariff = Tariff::create([
            'code' => 'BAR-BEER-CAN',
            'department_code' => 'bar',
            'name' => 'Craft Beer Can',
            'rate_paise' => 15000,
            'tax_percent' => 18.00,
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $promo = Promotion::create([
            'code' => 'BEER-LAST-1',
            'name' => 'Craft Beer 2+1 Last Unit',
            'type' => Promotion::TYPE_BUY_X_GET_Y,
            'buy_qty' => 2,
            'get_qty' => 1,
            'discount_percent' => 100.00,
            'customer_scope' => Promotion::SCOPE_ALL,
            'effective_from' => now()->subDay()->toDateString(),
            'max_free_units' => 1,
            'is_active' => true,
        ]);
        $promo->tariffs()->attach($barTariff->id);

        // Two counters draft bills while last free unit appears available
        $draftA = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [[
                'department_code' => 'bar',
                'item_type' => 'tariff',
                'item_id' => $barTariff->id,
                'item_name' => $barTariff->name,
                'quantity' => 3,
                'unit_price_paise' => 15000,
                'tax_percent' => 18.00,
            ]],
            idempotencyKey: 'bill_quota_a',
            userId: $this->cashier->id
        );

        $draftB = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [[
                'department_code' => 'bar',
                'item_type' => 'tariff',
                'item_id' => $barTariff->id,
                'item_name' => $barTariff->name,
                'quantity' => 3,
                'unit_price_paise' => 15000,
                'tax_percent' => 18.00,
            ]],
            idempotencyKey: 'bill_quota_b',
            userId: $this->cashier->id
        );

        // Bill A checks out first -> succeeds, consumes the single free unit
        $this->billingService->checkout(
            bill: $draftA,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => $draftA->net_amount_paise]],
            userId: $this->cashier->id
        );
        $this->assertEquals(1, $promo->fresh()->redeemed_free_units);

        // Bill B tries to check out with the already-exhausted promo -> cleanly rejected!
        $blocked = false;
        try {
            $this->billingService->checkout(
                bill: $draftB,
                tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => $draftB->net_amount_paise]],
                userId: $this->cashier->id
            );
        } catch (\InvalidArgumentException $e) {
            $blocked = str_contains($e->getMessage(), 'maximum free-unit quota');
        }
        $this->assertTrue($blocked, 'Second checkout exceeding promotional free-unit quota must be rejected.');

        // Filament authorization & creation check
        $this->actingAs($this->cashier)->get('/admin/promotions')->assertStatus(403);
        $this->actingAs($this->admin)->get('/admin/promotions')->assertStatus(200);

        Livewire::actingAs($this->admin)
            ->test(CreatePromotion::class)
            ->fillForm([
                'code' => 'FILAMENT-2PLUS1',
                'name' => 'Filament Whisky Promo',
                'buy_qty' => 2,
                'get_qty' => 1,
                'discount_percent' => 100,
                'customer_scope' => Promotion::SCOPE_MEMBERS_ONLY,
                'effective_from' => now()->toDateString(),
                'tariffs' => [$barTariff->id],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('promotions', [
            'code' => 'FILAMENT-2PLUS1',
            'buy_qty' => 2,
            'get_qty' => 1,
        ]);
    }
}
