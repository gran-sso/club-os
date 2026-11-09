<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\PurchaseReceipt;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\InsufficientStockException;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryAndBarTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $manager;
    protected Member $member;
    protected Counter $counter;
    protected Shift $shift;
    protected InventoryService $inventoryService;
    protected BillingService $billingService;
    protected ShiftService $shiftService;
    protected WalletService $walletService;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->inventoryService = app(InventoryService::class);
        $this->billingService = app(BillingService::class);
        $this->shiftService = app(ShiftService::class);
        $this->walletService = app(WalletService::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-2')->firstOrFail(); // Bar Counter

        $this->shift = $this->shiftService->openShift(
            counterId: $this->counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 200000
        );

        $this->supplier = Supplier::create([
            'name' => 'Kerala State Beverages Corporation (KSBC)',
            'code' => 'SUP-KSBC',
            'contact_person' => 'Depot Manager',
            'phone' => '0487-2345678',
            'gstin' => '32AAACK1234F1Z5',
            'is_active' => true,
        ]);
    }

    /**
     * Scenario I01:
     * Receive a 750 ml bottle and sell a 30 ml serving.
     * Available quantity decreases by 30 ml, and the issued cost follows
     * the configured costing/rounding policy.
     */
    public function test_i01_receive_bottle_and_sell_serving_decreases_stock_and_posts_cogs(): void
    {
        // 1. Create inventory item (750ml bottle)
        $item = InventoryItem::create([
            'sku' => 'LIQ-RC-750',
            'name' => 'Royal Challenge Whisky 750ml',
            'department_code' => 'bar',
            'category' => 'whisky',
            'base_unit' => 'ml',
            'current_stock_qty' => 0,
            'current_avg_cost_paise' => 0,
            'is_active' => true,
        ]);

        // 2. Create sellable tariff (30ml peg)
        $tariff = Tariff::create([
            'code' => 'BAR-RC-30ML',
            'department_code' => 'bar',
            'name' => 'Royal Challenge 30ml Peg',
            'rate_paise' => 15000, // ₹150 + 18% GST = ₹177
            'tax_percent' => 18.00,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        // 3. Create serving mapping: 1 peg = 30 ml
        ServingMapping::create([
            'tariff_id' => $tariff->id,
            'inventory_item_id' => $item->id,
            'serving_name' => 'Royal Challenge 30ml Peg',
            'serving_qty' => 30.000,
        ]);

        // 4. Receive 10 bottles = 7,500 ml @ ₹750 per bottle (100 paise per ml)
        $purchase = $this->inventoryService->receivePurchase(
            supplier: $this->supplier,
            invoiceNumber: 'KSBC-INV-1001',
            invoiceDate: '2026-10-17',
            items: [
                [
                    'inventory_item_id' => $item->id,
                    'quantity' => 7500.000,
                    'unit_cost_paise' => 100, // 100 paise = ₹1.00 per ml
                    'tax_percent' => 18.00,
                ]
            ],
            idempotencyKey: 'pur_i01_test',
            shiftId: $this->shift->id,
            userId: $this->manager->id
        );

        $this->assertEquals(7500.000, $item->fresh()->current_stock_qty);
        $this->assertEquals(100, $item->fresh()->current_avg_cost_paise);

        // 5. Sell 1 peg (30 ml) to member via Cash
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
                    'quantity' => 1,
                    'unit_price_paise' => $tariff->rate_paise,
                    'tax_percent' => $tariff->tax_percent,
                ]
            ],
            idempotencyKey: 'bill_i01_test',
            userId: $this->cashier->id
        );

        $this->billingService->checkout(
            bill: $bill,
            tenders: [
                ['tender_type' => TenderType::CASH, 'amount_paise' => 17700]
            ],
            userId: $this->cashier->id
        );

        // Assert: Available stock decreased by exactly 30 ml -> 7,470 ml remaining!
        $this->assertEquals(7470.000, $item->fresh()->current_stock_qty);

        // Assert: Stock issue movement recorded
        $movement = InventoryMovement::where('inventory_item_id', $item->id)
            ->where('type', 'sale_issue')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-30.000, $movement->quantity);
        $this->assertEquals(100, $movement->unit_cost_paise);
        $this->assertEquals(3000, $movement->total_cost_paise); // 30 ml * 100 paise = 3000 paise (₹30.00 COGS)
        $this->assertEquals(7470.000, $movement->balance_after_qty);

        // Assert: COGS Journal entry: Debit 5010 (COGS) ₹30, Credit 1030 (Inventory) ₹30
        $cogsJournal = JournalHeader::where('source_type', 'cogs_issue')
            ->where('source_id', $bill->lines->first()->id)
            ->first();
        $this->assertNotNull($cogsJournal);

        $debitCogs = $cogsJournal->lines()->where('account_id', Account::where('code', '5010')->value('id'))->first();
        $creditInv = $cogsJournal->lines()->where('account_id', Account::where('code', '1030')->value('id'))->first();

        $this->assertEquals(3000, $debitCogs->debit_paise);
        $this->assertEquals(3000, $creditInv->credit_paise);
    }

    /**
     * Scenario I02:
     * Two counters attempt to sell the same last available serving.
     * At most one fulfilment succeeds; stock cannot go negative.
     */
    public function test_i02_concurrent_counters_cannot_sell_same_last_stock(): void
    {
        $item = InventoryItem::create([
            'sku' => 'LIQ-VODKA-30',
            'name' => 'Smirnoff Vodka',
            'department_code' => 'bar',
            'base_unit' => 'ml',
            'current_stock_qty' => 30.000, // Exactly 1 serving remaining!
            'current_avg_cost_paise' => 120,
            'is_active' => true,
        ]);

        $tariff = Tariff::create([
            'code' => 'BAR-VODKA-30ML',
            'department_code' => 'bar',
            'name' => 'Vodka 30ml Peg',
            'rate_paise' => 18000,
            'tax_percent' => 18.00,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        ServingMapping::create([
            'tariff_id' => $tariff->id,
            'inventory_item_id' => $item->id,
            'serving_name' => 'Vodka 30ml Peg',
            'serving_qty' => 30.000,
        ]);

        $bill1 = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_id' => $tariff->id, 'item_name' => $tariff->name, 'quantity' => 1, 'unit_price_paise' => 18000, 'tax_percent' => 18.00]],
            idempotencyKey: 'bill_i02_c1',
            userId: $this->cashier->id
        );

        $bill2 = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_id' => $tariff->id, 'item_name' => $tariff->name, 'quantity' => 1, 'unit_price_paise' => 18000, 'tax_percent' => 18.00]],
            idempotencyKey: 'bill_i02_c2',
            userId: $this->cashier->id
        );

        $success = 0;
        $failed = 0;

        // Counter 1 checkout
        try {
            $this->billingService->checkout(
                bill: $bill1,
                tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => 21240]],
                userId: $this->cashier->id
            );
            $success++;
        } catch (InsufficientStockException $e) {
            $failed++;
        }

        // Counter 2 checkout (last stock already consumed!)
        try {
            $this->billingService->checkout(
                bill: $bill2,
                tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => 21240]],
                userId: $this->cashier->id
            );
            $success++;
        } catch (InsufficientStockException $e) {
            $failed++;
        }

        $this->assertEquals(1, $success);
        $this->assertEquals(1, $failed);
        $this->assertEquals(0.000, $item->fresh()->current_stock_qty); // Cannot be negative!
    }

    /**
     * Scenario I03:
     * Record approved wastage, an unopened return and a refund for a consumed drink.
     * Each has the correct physical quantity/cost and financial treatment;
     * they are not treated identically.
     */
    public function test_i03_wastage_vs_unopened_return_vs_consumed_refund(): void
    {
        $item = InventoryItem::create([
            'sku' => 'LIQ-BEER-650',
            'name' => 'Kingfisher Ultra 650ml Bottle',
            'department_code' => 'bar',
            'base_unit' => 'bottle',
            'current_stock_qty' => 50.000,
            'current_avg_cost_paise' => 18000, // ₹180.00 per bottle
            'is_active' => true,
        ]);

        $tariff = Tariff::create([
            'code' => 'BAR-KF-BOTTLE',
            'department_code' => 'bar',
            'name' => 'KF Ultra Bottle',
            'rate_paise' => 28000, // ₹280.00
            'tax_percent' => 18.00,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        ServingMapping::create([
            'tariff_id' => $tariff->id,
            'inventory_item_id' => $item->id,
            'serving_name' => 'KF Ultra Bottle',
            'serving_qty' => 1.000,
        ]);

        // 1. Record approved breakage / wastage (2 bottles dropped in store)
        $wasteMovement = $this->inventoryService->recordWastage(
            item: $item,
            qty: 2.000,
            reason: 'Dropped during shelf restocking',
            shiftId: $this->shift->id,
            userId: $this->manager->id
        );

        $this->assertEquals(48.000, $item->fresh()->current_stock_qty);
        $this->assertEquals('wastage', $wasteMovement->type);
        $this->assertEquals(36000, $wasteMovement->total_cost_paise); // 2 * 18000 = 36000 (₹360.00)

        // 2. Sell 1 bottle to member
        $bill1 = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_id' => $tariff->id, 'item_name' => $tariff->name, 'quantity' => 1, 'unit_price_paise' => 28000, 'tax_percent' => 18.00]],
            idempotencyKey: 'bill_consumed_drink',
            userId: $this->cashier->id
        );
        $this->billingService->checkout(
            bill: $bill1,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => 33040]],
            userId: $this->cashier->id
        );
        $this->assertEquals(47.000, $item->fresh()->current_stock_qty);

        // Refund for a CONSUMED drink (e.g. customer dissatisfied with temperature) -> returnStock = FALSE
        $this->billingService->refund(
            bill: $bill1,
            amountPaise: 33040,
            reason: 'Beer served warm, customer consumed half',
            approver: $this->manager,
            idempotencyKey: 'refund_consumed_001',
            returnStock: false // Stock is NOT physically returned!
        );

        // Stock MUST REMAIN 47 bottles! No fabricated stock for consumed beer!
        $this->assertEquals(47.000, $item->fresh()->current_stock_qty);

        // 3. Sell another bottle to member
        $bill2 = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_id' => $tariff->id, 'item_name' => $tariff->name, 'quantity' => 1, 'unit_price_paise' => 28000, 'tax_percent' => 18.00]],
            idempotencyKey: 'bill_unopened_return',
            userId: $this->cashier->id
        );
        $this->billingService->checkout(
            bill: $bill2,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => 33040]],
            userId: $this->cashier->id
        );
        $this->assertEquals(46.000, $item->fresh()->current_stock_qty);

        // Refund for an UNOPENED bottle returned to counter -> returnStock = TRUE
        $this->billingService->refund(
            bill: $bill2,
            amountPaise: 33040,
            reason: 'Customer cancelled order before opening bottle',
            approver: $this->manager,
            idempotencyKey: 'refund_unopened_001',
            returnStock: true // Physical bottle placed back into inventory!
        );

        // Stock is restored to 47 bottles!
        $this->assertEquals(47.000, $item->fresh()->current_stock_qty);
        $returnMovement = InventoryMovement::where('inventory_item_id', $item->id)
            ->where('type', 'return_unopened')
            ->first();
        $this->assertNotNull($returnMovement);
        $this->assertEquals(1.000, $returnMovement->quantity);
    }

    /**
     * Scenario I04:
     * New stock arrives at a different cost.
     * Future issue costs reflect moving average, while historical issue costs remain unchanged.
     */
    public function test_i04_moving_weighted_average_costing(): void
    {
        $item = InventoryItem::create([
            'sku' => 'LIQ-GIN-750',
            'name' => 'Bombay Sapphire Gin 750ml',
            'department_code' => 'bar',
            'base_unit' => 'ml',
            'current_stock_qty' => 0,
            'current_avg_cost_paise' => 0,
            'is_active' => true,
        ]);

        $tariff = Tariff::create([
            'code' => 'BAR-GIN-60ML',
            'department_code' => 'bar',
            'name' => 'Gin 60ml Peg',
            'rate_paise' => 35000,
            'tax_percent' => 18.00,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        ServingMapping::create([
            'tariff_id' => $tariff->id,
            'inventory_item_id' => $item->id,
            'serving_name' => 'Gin 60ml Peg',
            'serving_qty' => 60.000,
        ]);

        // 1. First receiving: 1,000 ml @ 100 paise/ml
        $this->inventoryService->receivePurchase(
            supplier: $this->supplier,
            invoiceNumber: 'GIN-PUR-01',
            invoiceDate: '2026-10-17',
            items: [['inventory_item_id' => $item->id, 'quantity' => 1000.000, 'unit_cost_paise' => 100, 'tax_percent' => 0]],
            idempotencyKey: 'gin_p1'
        );
        $this->assertEquals(100, $item->fresh()->current_avg_cost_paise);

        // 2. Issue 1 peg (60ml) @ 100 paise/ml
        $bill1 = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_id' => $tariff->id, 'item_name' => $tariff->name, 'quantity' => 1, 'unit_price_paise' => 35000, 'tax_percent' => 18.00]],
            idempotencyKey: 'bill_gin_1',
            userId: $this->cashier->id
        );
        $this->billingService->checkout(
            bill: $bill1,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => 41300]],
            userId: $this->cashier->id
        );

        // Stock remaining = 940 ml, historical issue cost = 100 paise
        $this->assertEquals(940.000, $item->fresh()->current_stock_qty);
        $issue1 = InventoryMovement::where('inventory_item_id', $item->id)->where('type', 'sale_issue')->first();
        $this->assertEquals(100, $issue1->unit_cost_paise);

        // 3. Second receiving at HIGHER cost: 1,000 ml @ 200 paise/ml
        // Calculation:
        // Existing: 940 ml @ 100 paise = 94,000 paise
        // New: 1,000 ml @ 200 paise = 200,000 paise
        // Total value = 294,000 paise for 1,940 ml
        // New weighted average = round(294,000 / 1,940) = 152 paise/ml!
        $this->inventoryService->receivePurchase(
            supplier: $this->supplier,
            invoiceNumber: 'GIN-PUR-02',
            invoiceDate: '2026-10-17',
            items: [['inventory_item_id' => $item->id, 'quantity' => 1000.000, 'unit_cost_paise' => 200, 'tax_percent' => 0]],
            idempotencyKey: 'gin_p2'
        );

        $freshItem = $item->fresh();
        $this->assertEquals(1940.000, $freshItem->current_stock_qty);
        $this->assertEquals(152, $freshItem->current_avg_cost_paise);

        // 4. Issue next peg (60ml): Future issue cost reflects the new moving average (152 paise/ml)
        $bill2 = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_id' => $tariff->id, 'item_name' => $tariff->name, 'quantity' => 1, 'unit_price_paise' => 35000, 'tax_percent' => 18.00]],
            idempotencyKey: 'bill_gin_2',
            userId: $this->cashier->id
        );
        $this->billingService->checkout(
            bill: $bill2,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => 41300]],
            userId: $this->cashier->id
        );

        $issue2 = InventoryMovement::where('inventory_item_id', $item->id)->where('type', 'sale_issue')->orderByDesc('id')->first();
        $this->assertEquals(152, $issue2->unit_cost_paise);

        // Historical issue cost remains untouched at 100 paise!
        $this->assertEquals(100, $issue1->fresh()->unit_cost_paise);
    }

    /**
     * Scenario I05:
     * A physical count differs from expected stock.
     * Only authorised adjustment with reason changes stock,
     * and the before/after trail remains visible.
     */
    public function test_i05_physical_count_adjustment_with_audit_trail(): void
    {
        $item = InventoryItem::create([
            'sku' => 'LIQ-RUM-750',
            'name' => 'Old Monk Rum 750ml',
            'department_code' => 'bar',
            'base_unit' => 'ml',
            'current_stock_qty' => 1500.000,
            'current_avg_cost_paise' => 80,
            'is_active' => true,
        ]);

        // Physical count reveals 1,440 ml (60 ml unrecorded evaporation/spillage)
        $movement = $this->inventoryService->recordPhysicalCountAdjustment(
            item: $item,
            countedQty: 1440.000,
            reason: 'Monthly bar stock count variance',
            userId: $this->manager->id
        );

        $this->assertEquals(1440.000, $item->fresh()->current_stock_qty);
        $this->assertEquals('adjustment', $movement->type);
        $this->assertEquals(-60.000, $movement->quantity);
        $this->assertStringContainsString('Was 1500.000, Counted 1440.000, Variance -60.000', $movement->notes);
    }
}
