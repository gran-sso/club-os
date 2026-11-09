<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Models\ContractorSettlementBatch;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Services\FinancialReportService;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Pages\FinancialReportsPage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExecutiveReportingHubTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
    }

    public function test_admin_and_manager_can_access_reporting_hub(): void
    {
        Livewire::actingAs($this->admin)->test(FinancialReportsPage::class)->assertSuccessful();
        Livewire::actingAs($this->manager)->test(FinancialReportsPage::class)->assertSuccessful();
    }

    public function test_cashier_without_permission_is_denied(): void
    {
        $this->actingAs($this->cashier);
        $response = $this->get('/admin/financial-reports');
        $response->assertStatus(403);
    }

    public function test_sales_and_collections_by_department_and_counter(): void
    {
        $service = app(FinancialReportService::class);
        $counter = Counter::firstOrCreate(['code' => 'BAR-01'], ['name' => 'Main Bar Counter', 'department_code' => 'bar']);

        $shift = Shift::create([
            'shift_number' => 'SH-TEST-001',
            'counter_id' => $counter->id,
            'user_id' => $this->admin->id,
            'business_date' => now()->toDateString(),
            'opened_at' => now(),
            'opening_float_paise' => 500000,
            'expected_cash_paise' => 500000,
            'status' => ShiftStatus::OPEN,
        ]);

        $member = Member::firstOrFail();

        // 1. Create a paid Bar bill for ₹500
        $bill = Bill::create([
            'bill_number' => 'BILL-TEST-BAR',
            'shift_id' => $shift->id,
            'customer_type' => Member::class,
            'customer_id' => $member->id,
            'department_code' => 'bar',
            'status' => BillStatus::PAID,
            'business_date' => now()->toDateString(),
            'gross_amount_paise' => 50000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 2500,
            'net_amount_paise' => 52500,
            'paid_amount_paise' => 52500,
            'created_by_user_id' => $this->admin->id,
            'idempotency_key' => 'IDEM-TEST-BAR-01',
            'idempotency_hash' => hash('sha256', 'IDEM-TEST-BAR-01'),
        ]);

        // Add confirmed payment receipt
        PaymentReceipt::create([
            'receipt_number' => 'REC-TEST-BAR',
            'shift_id' => $shift->id,
            'bill_id' => $bill->id,
            'customer_type' => Member::class,
            'customer_id' => $member->id,
            'tender_type' => TenderType::CASH,
            'amount_paise' => 52500,
            'provider_name' => 'cash',
            'provider_status' => 'confirmed',
            'idempotency_key' => 'IDEM-REC-BAR-01',
            'created_by_user_id' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $report = $service->getDepartmentAndCounterSalesReport(now()->toDateString(), now()->toDateString());

        $this->assertArrayHasKey('departments', $report);
        $this->assertArrayHasKey('counters', $report);
        $this->assertArrayHasKey('collections', $report);
        $this->assertGreaterThanOrEqual(52500, $report['departments']['bar']['net_paise']);
        $this->assertGreaterThanOrEqual(1, $report['departments']['bar']['bills_count']);
        $this->assertGreaterThanOrEqual(52500, $report['collections']['total_cash_inflow_paise']);
    }

    public function test_income_and_expenditure_statement(): void
    {
        $service = app(FinancialReportService::class);

        $account = \App\Domain\Finance\Models\Account::where('code', '5030')->first() ?? \App\Domain\Finance\Models\Account::firstOrFail();

        // Add an approved operational expense voucher of ₹12,000 for electricity
        Expense::create([
            'expense_number' => 'EXP-2026-0001',
            'voucher_number' => 'EXP-TEST-001',
            'expense_date' => now()->toDateString(),
            'account_id' => $account->id,
            'payee' => 'KSEB Electricity Board',
            'category' => 'utilities',
            'amount_paise' => 1200000,
            'payment_method' => 'bank_transfer',
            'status' => 'approved',
            'notes' => 'Clubhouse generator and electricity charges',
            'created_by_user_id' => $this->admin->id,
        ]);

        $report = $service->getIncomeAndExpenditureReport(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertArrayHasKey('incomes', $report);
        $this->assertArrayHasKey('expenditures', $report);
        $this->assertArrayHasKey('net_operating_surplus_paise', $report);
        $this->assertGreaterThanOrEqual(1200000, $report['expenditures']['total_expenditure_paise']);
    }

    public function test_daily_cash_and_digital_till_reconciliation(): void
    {
        $service = app(FinancialReportService::class);
        $counter = Counter::firstOrCreate(['code' => 'DINING-01'], ['name' => 'Dining Counter', 'department_code' => 'food']);

        $shift = Shift::create([
            'shift_number' => 'SH-TEST-RECON',
            'counter_id' => $counter->id,
            'user_id' => $this->admin->id,
            'business_date' => now()->toDateString(),
            'opened_at' => now()->subHours(4),
            'closed_at' => now(),
            'opening_float_paise' => 200000,
            'closing_cash_counted_paise' => 450000,
            'expected_cash_paise' => 450000,
            'discrepancy_paise' => 0,
            'status' => ShiftStatus::CLOSED,
        ]);

        $report = $service->getDailyCashAndDigitalReconciliation(now()->toDateString(), now()->toDateString());

        $this->assertArrayHasKey('shifts', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertGreaterThanOrEqual(1, $report['summary']['total_shifts']);
        $this->assertGreaterThanOrEqual(200000, $report['summary']['total_opening_float_paise']);
    }

    public function test_food_contractor_settlement_report(): void
    {
        $service = app(FinancialReportService::class);
        $contractor = Contractor::firstOrCreate(['code' => 'CONT-TEST-01'], ['name' => 'Malabar Caterers', 'contact_person' => 'Chef Raman', 'phone' => '9847012345']);

        ContractorSettlementBatch::create([
            'batch_number' => 'SETTLE-TEST-001',
            'contractor_id' => $contractor->id,
            'business_date' => now()->toDateString(),
            'cutoff_at' => now(),
            'total_food_sales_gross_paise' => 1500000,
            'total_food_sales_tax_paise' => 75000,
            'total_food_sales_net_paise' => 1425000,
            'total_adjustments_paise' => 0,
            'net_payable_paise' => 1425000,
            'paid_amount_paise' => 1000000,
            'residual_payable_paise' => 425000,
            'status' => 'partially_paid',
            'payment_reference' => 'NEFT-TEST-9988',
        ]);

        $report = $service->getFoodContractorSettlementReport(now()->toDateString(), now()->toDateString());

        $this->assertArrayHasKey('batches', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertGreaterThanOrEqual(1425000, $report['summary']['total_net_payable_paise']);
        $this->assertGreaterThanOrEqual(425000, $report['summary']['current_outstanding_payable_paise']);
    }

    public function test_bar_and_stock_consumption_report(): void
    {
        $service = app(FinancialReportService::class);

        $item = InventoryItem::firstOrCreate(['sku' => 'LIQ-RUM-01'], [
            'name' => 'Old Monk XXX Rum (750ml)',
            'department_code' => 'bar',
            'category' => 'Rum',
            'base_unit' => 'ml',
            'current_stock_qty' => 3750,
            'current_avg_cost_paise' => 120, // ₹1.20 per ml
            'reorder_level' => 1500,
            'is_active' => true,
        ]);

        // Add a sales consumption movement of 60ml
        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'type' => 'sale',
            'quantity' => 60,
            'unit_cost_paise' => 120,
            'total_cost_paise' => 7200,
            'balance_after_qty' => 3690,
            'avg_cost_after_paise' => 120,
            'created_at' => now(),
        ]);

        $report = $service->getBarAndStockReport(now()->toDateString(), now()->toDateString());

        $this->assertArrayHasKey('items', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertGreaterThanOrEqual(1, $report['summary']['total_items_count']);
        $this->assertGreaterThanOrEqual(7200, $report['summary']['period_cogs_paise']);
    }

    public function test_sports_facility_income_report(): void
    {
        $service = app(FinancialReportService::class);
        $member = Member::firstOrFail();
        $tariff = Tariff::where('department_code', 'sports')->first() ?? Tariff::firstOrFail();

        FacilitySubscription::create([
            'subscription_number' => 'SUB-TEST-SPORTS-01',
            'member_id' => $member->id,
            'facility_code' => 'badminton',
            'tariff_id' => $tariff->id,
            'plan_type' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'total_fee_paise' => 150000,
            'status' => 'active',
            'created_at' => now(),
        ]);

        $report = $service->getSportsFacilityIncomeReport(now()->toDateString(), now()->toDateString());

        $this->assertArrayHasKey('gym', $report);
        $this->assertArrayHasKey('badminton', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertGreaterThanOrEqual(150000, $report['badminton']['subscription_revenue_paise']);
        $this->assertGreaterThanOrEqual(150000, $report['summary']['total_sports_revenue_paise']);
    }

    public function test_outstanding_payables_and_exceptions_report(): void
    {
        $service = app(FinancialReportService::class);
        $report = $service->getOutstandingPayablesAndExceptionsReport();

        $this->assertArrayHasKey('member_credit', $report);
        $this->assertTrue($report['member_credit']['is_zero_credit_compliant']);
        $this->assertEquals(0, $report['member_credit']['overdrawn_accounts_count']);
        $this->assertArrayHasKey('contractor_payables', $report);
        $this->assertArrayHasKey('general_ledger', $report);
    }

    public function test_livewire_tab_switching_and_presets(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialReportsPage::class)
            ->assertSet('activeTab', 'sales')
            ->call('setActiveTab', 'income_expenditure')
            ->assertSet('activeTab', 'income_expenditure')
            ->call('setActiveTab', 'contractor')
            ->assertSet('activeTab', 'contractor')
            ->call('setPreset', 'today')
            ->assertSet('startDate', now()->toDateString())
            ->assertSet('endDate', now()->toDateString())
            ->call('setPreset', 'this_month')
            ->assertSet('startDate', now()->startOfMonth()->toDateString())
            ->assertSet('endDate', now()->endOfMonth()->toDateString());
    }

    public function test_export_csv_download(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialReportsPage::class)
            ->set('activeTab', 'sales')
            ->call('exportCsv')
            ->assertFileDownloaded("malabar_club_report_sales_" . now()->startOfMonth()->toDateString() . "_to_" . now()->endOfMonth()->toDateString() . ".csv");
    }
}
