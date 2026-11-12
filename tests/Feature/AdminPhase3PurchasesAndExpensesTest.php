<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\JournalLine;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\PurchaseReceipt;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Tariff\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhase3PurchasesAndExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_purchase_receipt_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/purchases/purchase-receipts/create');
        $response->assertStatus(200);
        $response->assertSee('Supplier &amp; Bill Details', false);
        $response->assertSee('Items Received on This Bill');
    }

    public function test_admin_can_create_purchase_receipt_and_update_stock_and_journal(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        // Ensure supplier exists
        $supplier = Supplier::firstOrCreate(
            ['code' => 'KSBC-01'],
            [
                'name' => 'Kerala State Beverages Corporation (KSBC)',
                'contact_person' => 'Wholesale Depot Manager',
                'phone' => '+91 484 2345678',
                'gstin' => '32AAACK1234F1Z5',
                'is_active' => true,
            ]
        );

        // Ensure inventory item exists
        $item = InventoryItem::firstOrCreate(
            ['sku' => 'LIQ-AMRUT-750'],
            [
                'name' => 'Amrut Indian Single Malt 750ml',
                'department_code' => 'bar',
                'category' => 'liquor',
                'base_unit' => 'ml',
                'current_stock_qty' => 0,
                'current_avg_cost_paise' => 0,
                'reorder_level' => 1500,
                'is_active' => true,
            ]
        );

        $initialStock = $item->current_stock_qty;

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Purchases\Pages\CreatePurchaseReceipt::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'KSBC-INV-TEST-9901',
                'invoice_date' => '2026-11-08',
                'notes' => '10 bottles received in good condition',
                'lines' => [
                    [
                        'inventory_item_id' => $item->id,
                        'quantity' => 7500, // 7500 ml
                        'unit_cost_paise' => 4.50, // ₹4.50 per ml -> 450 paise
                        'tax_percent' => '0.00',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $receipt = PurchaseReceipt::where('invoice_number', 'KSBC-INV-TEST-9901')->first();
        $this->assertNotNull($receipt);
        // 7500 ml * 450 paise = 3,375,000 paise = ₹33,750.00
        $this->assertEquals(3375000, $receipt->total_gross_paise);
        $this->assertEquals(3375000, $receipt->total_net_paise);

        // Stock quantity should be incremented
        $item->refresh();
        $this->assertEquals($initialStock + 7500, (float) $item->current_stock_qty);
        $this->assertEquals(450, $item->current_avg_cost_paise);

        // Financial Journal must be posted: Debit Inventory Stock (1030) 3375000, Credit Bank Clearing (1020) 3375000
        $stockAccount = Account::where('code', '1030')->firstOrFail();
        $stockDebit = JournalLine::where('account_id', $stockAccount->id)
            ->where('debit_paise', 3375000)
            ->first();
        $this->assertNotNull($stockDebit, 'Debit to Inventory Stock (1030) must exist for ₹33,750.00');

        $bankAccount = Account::where('code', '1020')->firstOrFail();
        $bankCredit = JournalLine::where('account_id', $bankAccount->id)
            ->where('credit_paise', 3375000)
            ->first();
        $this->assertNotNull($bankCredit, 'Credit to Bank Clearing (1020) must exist for ₹33,750.00');
    }

    public function test_admin_can_access_expense_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/expenses/create');
        $response->assertStatus(200);
        $response->assertSee('Expense Head &amp; Departmental Allocation', false);
        $response->assertSee('Disbursement &amp; Payment Details', false);
    }

    public function test_admin_can_record_expense_and_post_journal(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $account = Account::where('code', '5030')->firstOrFail(); // Operating & Administrative Expenses
        $department = Department::first();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Expenses\Pages\CreateExpense::class)
            ->fillForm([
                'account_id' => $account->id,
                'department_id' => $department?->id,
                'expense_date' => '2026-11-08',
                'payee' => 'Kerala State Electricity Board (KSEB)',
                'amount_paise' => 14500.00, // ₹14,500.00 -> 1,450,000 paise
                'payment_method' => 'bank_transfer',
                'document_reference' => 'UTR-AXIS-20261108-9876',
                'notes' => 'Clubhouse electricity bill for October 2026',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::where('payee', 'Kerala State Electricity Board (KSEB)')->first();
        $this->assertNotNull($expense);
        $this->assertEquals(1450000, $expense->amount_paise, '₹14,500.00 must be stored as 1450000 paise');
        $this->assertEquals('bank_transfer', $expense->payment_method);
        $this->assertEquals('paid', $expense->status);

        // Journal must be posted: Debit Expense 5030 for 1450000 paise, Credit Bank 1020 for 1450000 paise
        $expenseDebit = JournalLine::where('account_id', $account->id)
            ->where('debit_paise', 1450000)
            ->first();
        $this->assertNotNull($expenseDebit, 'Debit to Operating Expenses (5030) must exist for ₹14,500.00');

        $bankAccount = Account::where('code', '1020')->firstOrFail();
        $bankCredit = JournalLine::where('account_id', $bankAccount->id)
            ->where('credit_paise', 1450000)
            ->first();
        $this->assertNotNull($bankCredit, 'Credit to Bank Clearing (1020) must exist for ₹14,500.00');
    }
}
