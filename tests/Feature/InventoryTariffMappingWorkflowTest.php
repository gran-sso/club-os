<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Resources\Inventory\Pages\ListInventoryItems;
use App\Filament\Resources\Tariffs\Pages\CreateTariff;
use App\Filament\Resources\Tariffs\Pages\EditTariff;
use App\Filament\Resources\Tariffs\Pages\ListTariffs;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryTariffMappingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cashier;

    protected InventoryService $inventoryService;

    protected BillingService $billingService;

    protected ShiftService $shiftService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->inventoryService = app(InventoryService::class);
        $this->billingService = app(BillingService::class);
        $this->shiftService = app(ShiftService::class);
    }

    public function test_create_tariff_links_inventory_item_and_deducts_stock_on_pos_sale_regardless_of_menu_name_typo(): void
    {
        $whisky = InventoryItem::create([
            'sku' => 'LIQ-GLEN-750ML',
            'name' => 'Glenmorangie 10Yr Single Malt 750ml',
            'department_code' => 'bar',
            'category' => 'liquor',
            'base_unit' => 'ml',
            'current_stock_qty' => 750.000,
            'current_avg_cost_paise' => 500, // ₹5.00 per ml
            'reorder_level' => 1500,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CreateTariff::class)
            ->fillForm([
                'code' => 'BAR-GLEN-60',
                'name' => 'Glanmorangie 10yr Larg Pegg (Typo)',
                'department_code' => 'bar',
                'rate_paise' => 650.00,
                'tax_percent' => '18.00',
                'track_inventory' => true,
                'serving_inventory_item_id' => $whisky->id,
                'serving_name' => '60 ml (Large Peg)',
                'serving_qty' => 60,
                'effective_from' => '2026-11-12',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tariff = Tariff::where('code', 'BAR-GLEN-60')->first();
        $this->assertNotNull($tariff);
        $this->assertEquals(65000, $tariff->rate_paise);

        $mapping = ServingMapping::where('tariff_id', $tariff->id)->first();
        $this->assertNotNull($mapping);
        $this->assertSame($whisky->id, $mapping->inventory_item_id);
        $this->assertEquals('60 ml (Large Peg)', $mapping->serving_name);
        $this->assertEquals(60.000, (float) $mapping->serving_qty);

        // End-to-end POS sale of 2 units deducts 120 ml & posts ₹600 COGS despite typo in tariff name
        $barCounter = Counter::where('code', 'COUNTER-2')->firstOrFail();
        $shift = $this->shiftService->openShift($barCounter->id, $this->cashier->id, 100000);
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();

        $bill = $this->billingService->createDraftBill(
            shift: $shift,
            customer: $member,
            departmentCode: 'bar',
            items: [[
                'department_code' => 'bar',
                'item_type' => 'tariff',
                'item_id' => $tariff->id,
                'item_name' => $tariff->name,
                'quantity' => 2,
                'unit_price_paise' => $tariff->rate_paise,
                'tax_percent' => $tariff->tax_percent,
            ]],
            idempotencyKey: 'bill_typo_link_pos_1',
            userId: $this->cashier->id,
        );

        // 2 * 65000 = 130000 + 18% GST (23400) = 153400 paise
        $this->assertEquals(153400, $bill->net_amount_paise);
        $this->billingService->checkout(
            bill: $bill,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => $bill->net_amount_paise]],
            userId: $this->cashier->id,
        );

        $this->assertEquals(630.000, (float) $whisky->fresh()->current_stock_qty);

        $movement = InventoryMovement::where('inventory_item_id', $whisky->id)
            ->where('type', 'sale_issue')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-120.000, (float) $movement->quantity);
        $this->assertEquals(60000, $movement->total_cost_paise);
    }

    public function test_edit_tariff_hydrates_updates_and_unlinks_serving_mapping(): void
    {
        $beer = InventoryItem::create([
            'sku' => 'BEER-KF-CAN',
            'name' => 'Kingfisher Ultra 330ml Can',
            'department_code' => 'bar',
            'category' => 'beer_wine',
            'base_unit' => 'can',
            'current_stock_qty' => 24,
            'current_avg_cost_paise' => 12000,
            'reorder_level' => 6,
            'is_active' => true,
        ]);

        $whisky = InventoryItem::create([
            'sku' => 'LIQ-JW-RED',
            'name' => 'Johnnie Walker Red Label 750ml',
            'department_code' => 'bar',
            'category' => 'liquor',
            'base_unit' => 'ml',
            'current_stock_qty' => 750,
            'current_avg_cost_paise' => 250,
            'reorder_level' => 750,
            'is_active' => true,
        ]);

        $tariff = $this->inventoryService->createTariffWithServingMapping(
            tariffData: [
                'code' => 'BAR-BEER-CAN-01',
                'department_code' => 'bar',
                'name' => 'Kingfisher Can 330ml',
                'rate_paise' => 25000,
                'tax_percent' => '18.00',
                'effective_from' => '2026-11-12',
                'is_active' => true,
            ],
            mappingData: [
                'inventory_item_id' => $beer->id,
                'serving_name' => '1 Can',
                'serving_qty' => 1,
            ],
            actor: $this->admin,
        );

        // Verify EditTariff hydrates mapping fields and allows switching item + qty
        Livewire::actingAs($this->admin)
            ->test(EditTariff::class, ['record' => $tariff->getKey()])
            ->assertFormSet([
                'track_inventory' => true,
                'serving_inventory_item_id' => $beer->id,
                'serving_name' => '1 Can',
                'serving_qty' => 1,
            ])
            ->fillForm([
                'serving_inventory_item_id' => $whisky->id,
                'serving_name' => '90 ml Pour',
                'serving_qty' => 90,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, ServingMapping::where('tariff_id', $tariff->id)->count());
        $updated = ServingMapping::where('tariff_id', $tariff->id)->firstOrFail();
        $this->assertSame($whisky->id, $updated->inventory_item_id);
        $this->assertEquals(90.000, (float) $updated->serving_qty);

        // Toggle stock tracking OFF -> unlinks cleanly
        Livewire::actingAs($this->admin)
            ->test(EditTariff::class, ['record' => $tariff->getKey()])
            ->fillForm([
                'track_inventory' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, ServingMapping::where('tariff_id', $tariff->id)->count());
    }

    public function test_create_tariff_without_stock_link_creates_no_serving_mapping(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateTariff::class)
            ->fillForm([
                'code' => 'ROOM-SUITE-01',
                'name' => 'Executive Suite Night Stay',
                'department_code' => 'accommodation',
                'rate_paise' => 4500.00,
                'tax_percent' => '12.00',
                'track_inventory' => false,
                'effective_from' => '2026-11-12',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tariff = Tariff::where('code', 'ROOM-SUITE-01')->firstOrFail();
        $this->assertNull($tariff->servingMapping);
    }

    public function test_inventory_service_validates_serving_mapping_inputs_and_permissions(): void
    {
        $activeItem = InventoryItem::create([
            'sku' => 'LIQ-TEST-ACTIVE',
            'name' => 'Active Spirit',
            'department_code' => 'bar',
            'category' => 'liquor',
            'base_unit' => 'ml',
            'current_stock_qty' => 750,
            'current_avg_cost_paise' => 100,
            'reorder_level' => 100,
            'is_active' => true,
        ]);

        $inactiveItem = InventoryItem::create([
            'sku' => 'LIQ-TEST-INACTIVE',
            'name' => 'Discontinued Spirit',
            'department_code' => 'bar',
            'category' => 'liquor',
            'base_unit' => 'ml',
            'current_stock_qty' => 0,
            'current_avg_cost_paise' => 100,
            'reorder_level' => 100,
            'is_active' => false,
        ]);

        $tariff = Tariff::create([
            'code' => 'TRF-VAL-01',
            'department_code' => 'bar',
            'name' => 'Validation Test Item',
            'rate_paise' => 20000,
            'tax_percent' => '18.00',
            'effective_from' => '2026-11-12',
            'is_active' => true,
        ]);

        // 1. Unauthorized actor rejected
        try {
            $this->inventoryService->syncTariffServingMapping($tariff, $activeItem->id, '30 ml', 30, $this->cashier);
            $this->fail('Expected UnauthorizedActionException for cashier without tariffs.manage');
        } catch (UnauthorizedActionException $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
        }

        // 2. Quantity that rounds to zero at 3 decimals rejected
        try {
            $this->inventoryService->syncTariffServingMapping($tariff, $activeItem->id, 'Tiny', 0.0001, $this->admin);
            $this->fail('Expected InvalidArgumentException for sub-0.001 quantity');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('greater than zero', $e->getMessage());
        }

        // 3. Empty serving name rejected
        try {
            $this->inventoryService->syncTariffServingMapping($tariff, $activeItem->id, '   ', 30, $this->admin);
            $this->fail('Expected InvalidArgumentException for empty serving description');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('description', strtolower($e->getMessage()));
        }

        // 4. Newly linking inactive stock item rejected
        try {
            $this->inventoryService->syncTariffServingMapping($tariff, $inactiveItem->id, '30 ml', 30, $this->admin);
            $this->fail('Expected InvalidArgumentException for inactive stock item');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('inactive', strtolower($e->getMessage()));
        }
    }

    public function test_add_selling_price_action_inside_inventory_items_creates_linked_tariff_and_enforces_rbac(): void
    {
        $wine = InventoryItem::create([
            'sku' => 'WINE-SULA-750',
            'name' => 'Sula Shiraz Cabernet 750ml Bottle',
            'department_code' => 'bar',
            'category' => 'beer_wine',
            'base_unit' => 'bottle',
            'current_stock_qty' => 12.000,
            'current_avg_cost_paise' => 85000,
            'reorder_level' => 3,
            'is_active' => true,
        ]);

        // Accountant has tariffs.view + stock.view, NOT tariffs.manage -> cannot see Add Selling Price
        $accountant = User::where('email', 'accountant@malabarclub.local')->firstOrFail();

        Livewire::actingAs($accountant)
            ->test(ListInventoryItems::class)
            ->assertTableActionVisible('sellingPrices', $wine)
            ->assertTableActionHidden('addSellingPrice', $wine);

        // Admin creates two linked selling prices directly from Inventory Items (Full Bottle + Glass/Peg)
        Livewire::actingAs($this->admin)
            ->test(ListInventoryItems::class)
            ->assertTableActionVisible('addSellingPrice', $wine)
            ->callTableAction('addSellingPrice', $wine, [
                'code' => 'TAR-WINE-SULA-BTL',
                'name' => 'Sula Shiraz Cabernet (Full Bottle)',
                'department_code' => 'bar',
                'serving_name' => 'Full Bottle',
                'serving_qty' => 1,
                'rate_paise' => 1800.00,
                'tax_percent' => '18.00',
                'effective_from' => '2026-11-12',
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors()
            ->callTableAction('addSellingPrice', $wine, [
                'code' => 'TAR-WINE-SULA-30',
                'name' => 'Sula Shiraz Cabernet (150ml Glass)',
                'department_code' => 'bar',
                'serving_name' => '150 ml Glass (0.20 bottle)',
                'serving_qty' => 0.2,
                'rate_paise' => 400.00,
                'tax_percent' => '18.00',
                'effective_from' => '2026-11-12',
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $wine->servingMappings()->count());
        $btlTariff = Tariff::where('code', 'TAR-WINE-SULA-BTL')->firstOrFail();
        $glassTariff = Tariff::where('code', 'TAR-WINE-SULA-30')->firstOrFail();
        $this->assertEquals(180000, $btlTariff->rate_paise);
        $this->assertEquals(1.000, (float) $btlTariff->servingMapping->serving_qty);
        $this->assertEquals(0.200, (float) $glassTariff->servingMapping->serving_qty);
    }

    public function test_unlinked_filters_identify_missing_stock_links_and_unlisted_inventory(): void
    {
        $unlinkedStock = InventoryItem::create([
            'sku' => 'UNLINKED-ITEM-01',
            'name' => 'Unlisted Tonic Water 250ml',
            'department_code' => 'bar',
            'category' => 'beverages',
            'base_unit' => 'can',
            'current_stock_qty' => 30,
            'current_avg_cost_paise' => 3000,
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        $linkedStock = InventoryItem::create([
            'sku' => 'LINKED-ITEM-01',
            'name' => 'Linked Soda 300ml',
            'department_code' => 'bar',
            'category' => 'beverages',
            'base_unit' => 'can',
            'current_stock_qty' => 30,
            'current_avg_cost_paise' => 2000,
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        $unlinkedBarTariff = Tariff::create([
            'code' => 'BAR-UNLINKED-99',
            'department_code' => 'bar',
            'name' => 'Mystery Cocktail Missing Link',
            'rate_paise' => 30000,
            'tax_percent' => '18.00',
            'effective_from' => '2026-11-12',
            'is_active' => true,
        ]);

        $linkedBarTariff = $this->inventoryService->createTariffWithServingMapping(
            tariffData: [
                'code' => 'BAR-LINKED-99',
                'department_code' => 'bar',
                'name' => 'Linked Club Soda',
                'rate_paise' => 6000,
                'tax_percent' => '18.00',
                'effective_from' => '2026-11-12',
                'is_active' => true,
            ],
            mappingData: [
                'inventory_item_id' => $linkedStock->id,
                'serving_name' => '1 Can',
                'serving_qty' => 1,
            ],
            actor: $this->admin,
        );

        Livewire::actingAs($this->admin)
            ->test(ListTariffs::class)
            ->filterTable('unlinked_bar', true)
            ->assertCanSeeTableRecords([$unlinkedBarTariff])
            ->assertCanNotSeeTableRecords([$linkedBarTariff]);

        Livewire::actingAs($this->admin)
            ->test(ListInventoryItems::class)
            ->filterTable('unlinked_menu', true)
            ->assertCanSeeTableRecords([$unlinkedStock])
            ->assertCanNotSeeTableRecords([$linkedStock]);
    }
}
