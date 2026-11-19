<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Integrations\Adapters\ThermalReceiptPrinter;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Tariff;
use App\Livewire\CounterPos;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftTransactionDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $admin;
    protected Member $member;
    protected Counter $counter;
    protected Tariff $tariff;
    protected ShiftService $shiftService;
    protected ThermalReceiptPrinter $printer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $this->tariff = Tariff::where('code', 'GUEST-BAR-ENTRY')->firstOrFail();

        $this->shiftService = app(ShiftService::class);
        $this->printer = app(ThermalReceiptPrinter::class);
    }

    public function test_shift_service_calculates_comprehensive_transaction_details_accurately(): void
    {
        // 1. Open shift with opening float of ₹1,000 (100,000 paise)
        $shift = $this->shiftService->openShift(
            counterId: $this->counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 100000
        );

        // 2. Create paid bills with various tenders: Cash, UPI
        // Bill 1: Cash Bill ₹500 (50,000 paise)
        $cashBill = Bill::create([
            'bill_number' => 'BILL-TEST-CASH',
            'shift_id' => $shift->id,
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'department_code' => 'BAR',
            'status' => BillStatus::PAID,
            'business_date' => now(),
            'created_by_user_id' => $this->cashier->id,
            'gross_amount_paise' => 50000,
            'tax_amount_paise' => 0,
            'discount_amount_paise' => 0,
            'net_amount_paise' => 50000,
            'tip_amount_paise' => 0,
            'paid_amount_paise' => 50000,
            'idempotency_key' => 'IDEMP-TEST-CASH',
            'idempotency_hash' => hash('sha256', 'IDEMP-TEST-CASH'),
        ]);

        BillLine::create([
            'bill_id' => $cashBill->id,
            'department_code' => 'BAR',
            'item_type' => Tariff::class,
            'item_id' => $this->tariff->id,
            'item_name' => 'Bar Drinks',
            'quantity' => 1,
            'unit_price_paise' => 50000,
            'gross_amount_paise' => 50000,
            'tax_percent' => 0,
            'tax_amount_paise' => 0,
            'discount_amount_paise' => 0,
            'line_total_paise' => 50000,
        ]);

        PaymentReceipt::create([
            'receipt_number' => 'RCPT-TEST-CASH',
            'bill_id' => $cashBill->id,
            'shift_id' => $shift->id,
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'tender_type' => TenderType::CASH,
            'amount_paise' => 50000,
            'provider_name' => 'Cash Drawer',
            'provider_status' => 'confirmed',
            'idempotency_key' => 'IDEMP-RCPT-CASH',
            'confirmed_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]);

        // Bill 2: Digital UPI Bill ₹300 (30,000 paise) + ₹20 tip
        $upiBill = Bill::create([
            'bill_number' => 'BILL-TEST-UPI',
            'shift_id' => $shift->id,
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'department_code' => 'FOOD',
            'status' => BillStatus::PAID,
            'business_date' => now(),
            'created_by_user_id' => $this->cashier->id,
            'gross_amount_paise' => 30000,
            'tax_amount_paise' => 0,
            'discount_amount_paise' => 0,
            'net_amount_paise' => 30000,
            'tip_amount_paise' => 2000,
            'paid_amount_paise' => 32000,
            'idempotency_key' => 'IDEMP-TEST-UPI',
            'idempotency_hash' => hash('sha256', 'IDEMP-TEST-UPI'),
        ]);

        BillLine::create([
            'bill_id' => $upiBill->id,
            'department_code' => 'food',
            'item_type' => Tariff::class,
            'item_id' => $this->tariff->id,
            'item_name' => 'Food Items',
            'quantity' => 1,
            'unit_price_paise' => 30000,
            'gross_amount_paise' => 30000,
            'tax_percent' => 0,
            'tax_amount_paise' => 0,
            'discount_amount_paise' => 0,
            'line_total_paise' => 30000,
        ]);

        PaymentReceipt::create([
            'receipt_number' => 'RCPT-TEST-UPI',
            'bill_id' => $upiBill->id,
            'shift_id' => $shift->id,
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'tender_type' => TenderType::DIGITAL,
            'amount_paise' => 32000,
            'provider_name' => 'UPI',
            'provider_status' => 'confirmed',
            'idempotency_key' => 'IDEMP-RCPT-UPI',
            'confirmed_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]);

        // 3. Add a cash drop of ₹400 (40,000 paise) to safe
        $this->shiftService->recordCashMovement(
            shift: $shift,
            type: 'cash_drop',
            amountPaise: 40000,
            notes: 'Mid-shift safe drop',
            userId: $this->admin->id
        );

        // 4. Fetch shift transaction details
        $details = $this->shiftService->getShiftTransactionDetails($shift);

        // Assertions
        $this->assertEquals($shift->shift_number, $details['shift_number']);
        $this->assertEquals($this->counter->name, $details['counter_name']);
        $this->assertEquals(2, $details['billed_turnover']['total_bills']);

        // Turnover (net)
        $this->assertEquals(80000, $details['billed_turnover']['net_paise']); // 500 + 300 = 800

        // Tenders
        $this->assertEquals(50000, $details['tender_breakdown']['cash_sales_paise']);
        $this->assertEquals(32000, $details['tender_breakdown']['digital_sales_paise']);

        // Drawer
        // Opening (1,000) + Cash Sales (500) - Drop (400) = Expected (1,100) = 110,000 paise
        $this->assertEquals(100000, $details['drawer_reconciliation']['opening_float_paise']);
        $this->assertEquals(50000, $details['drawer_reconciliation']['cash_sales_paise']);
        $this->assertEquals(40000, $details['drawer_reconciliation']['cash_drops_paise']);
        $this->assertEquals(110000, $details['drawer_reconciliation']['expected_cash_paise']);

        // Contractor turnover split
        $this->assertEquals(30000, $details['billed_turnover']['food_contractor_paise']);
        $this->assertEquals(50000, $details['billed_turnover']['club_bar_paise']);

        // Lists
        $this->assertCount(2, $details['bills']);
        $this->assertCount(1, $details['movements']);
    }

    public function test_thermal_receipt_printer_renders_professional_shift_handover_slip(): void
    {
        $shift = $this->shiftService->openShift(
            counterId: $this->counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 150000
        );

        $details = $this->shiftService->getShiftTransactionDetails($shift);
        $slip = $this->printer->render80mmShiftHandoverSlip($details);

        // Verify friendly, professional club labels and absence of jargon
        $this->assertStringContainsString('Malabar Club', $slip);
        $this->assertStringContainsString('SHIFT CLOSING & HANDOVER SUMMARY', $slip);
        $this->assertStringContainsString('COLLECTIONS & TURNOVER BREAKDOWN', $slip);
        $this->assertStringContainsString('PHYSICAL CASH DRAWER RECONCILIATION', $slip);
        $this->assertStringContainsString('Outgoing Cashier', $slip);
        $this->assertStringContainsString('Relieving Staff / Mgr', $slip);

        // Strictly verify absence of un-friendly technical jargon
        $this->assertStringNotContainsString('Z-Report', $slip);
        $this->assertStringNotContainsString('X-Report', $slip);
        $this->assertStringNotContainsString('Z REPORT', $slip);
        $this->assertStringNotContainsString('X REPORT', $slip);
    }

    public function test_counter_pos_livewire_can_open_and_close_shift_summary_modal_and_print_slip(): void
    {
        $component = Livewire::actingAs($this->cashier)
            ->test(CounterPos::class)
            ->set('selectedCounterId', $this->counter->id)
            ->set('openingFloat', 1000.00)
            ->call('openShift');

        // Check active shift status and bottom bar
        $component->assertSeeHtml('Shift Summary');

        // Open shift summary modal
        $component->call('openShiftSummaryModal')
            ->assertSet('showShiftSummaryModal', true)
            ->assertSeeHtml('Shift Activity &amp; Summary')
            ->assertSeeHtml('Billed Turnover')
            ->assertSeeHtml('Expected Cash')
            ->assertSeeHtml('Print Handover Slip');

        // Print summary slip
        $component->call('printCurrentShiftSummarySlip')
            ->assertSet('showShiftSummaryModal', false)
            ->assertSet('showReceiptModal', true)
            ->assertSet('isShiftHandoverSlip', true)
            ->assertSeeHtml('Shift Handover Summary Slip');

        // Close receipt modal
        $component->call('closeReceiptModal')
            ->assertSet('showReceiptModal', false)
            ->assertSet('isShiftHandoverSlip', false);

        // Close Shift
        $component->set('closingCashCounted', 1000.00)
            ->call('closeShift')
            ->assertSet('showReceiptModal', true)
            ->assertSet('isShiftHandoverSlip', true)
            ->assertSeeHtml('Shift Handover Summary Slip');
    }

    public function test_admin_can_view_shift_summary_modal_and_print_slip_action_on_shifts_table(): void
    {
        $shift = $this->shiftService->openShift(
            counterId: $this->counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 200000
        );

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Shifts\Pages\ListShifts::class)
            ->assertTableActionExists('viewSummary')
            ->assertTableActionExists('printHandoverSlip')
            ->mountTableAction('viewSummary', $shift)
            ->assertSuccessful();
    }
}
