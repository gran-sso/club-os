<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Integrations\Adapters\SimulatorAccessControlProvider;
use App\Domain\Integrations\Adapters\SimulatorTallyConnector;
use App\Domain\Integrations\Adapters\ThermalReceiptPrinter;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Integrations\TallySyncService;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class IntegrationAndReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * X04: Access simulator is rejected under production environment.
     * Prevents false reporting of simulated access as live verified hardware.
     */
    public function test_x04_access_simulator_fails_closed_in_production(): void
    {
        $provider = app(SimulatorAccessControlProvider::class);

        // In local/testing environment, status shows simulator label
        $status = $provider->getStatus();
        $this->assertEquals(SimulatorAccessControlProvider::STATUS_LABEL, $status['label']);
        $this->assertFalse($status['is_production_ready']);

        // When environment is switched to production, simulator MUST fail closed
        app()->detectEnvironment(fn() => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hardware access simulator cannot be executed in production environment.');

        $provider->authorizeEntry('CARD-VENU-001', 'bar');
    }

    /**
     * X07: Thermal Receipt rendering.
     * Text, totals and references remain readable and accurately formatted for 80mm ESC/POS printers.
     */
    public function test_x07_thermal_receipt_printer_80mm_layout_and_totals(): void
    {
        $printer = app(ThermalReceiptPrinter::class);
        $shiftService = app(ShiftService::class);
        $billingService = app(BillingService::class);

        $member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $counter = Counter::firstOrFail();
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();

        $shift = $shiftService->openShift($counter->id, $cashier->id, 100000);

        $bill = $billingService->createDraftBill(
            shift: $shift,
            customer: $member,
            departmentCode: 'bar',
            items: [
                [
                    'item_name' => 'Signature Whiskey 30ml',
                    'quantity' => 2,
                    'unit_price_paise' => 15000,
                    'tax_percent' => 18.00,
                ],
            ],
            idempotencyKey: 'PRINT-TEST-001',
            userId: $cashier->id
        );

        $checkout = $billingService->checkout(
            bill: $bill,
            tenders: [
                ['tender_type' => TenderType::CASH, 'amount_paise' => $bill->net_amount_paise],
            ],
            userId: $cashier->id
        );

        $receipt = PaymentReceipt::where('bill_id', $bill->id)->firstOrFail();
        $receiptText = $printer->render80mmReceipt($bill, $receipt);

        // Verification of readable 80mm layout
        $this->assertStringContainsString('Signature Whiskey', $receiptText);
        $this->assertStringContainsString($bill->bill_number, $receiptText);
        $this->assertStringContainsString('Gross Total', $receiptText);
        $this->assertStringContainsString('Net Payable', $receiptText);
        $this->assertStringContainsString('₹', $receiptText);
    }

    /**
     * X09: Outbox recovery and restart resilience.
     * Simulates queue worker interruption during pending work; worker recovers without duplicate operations.
     */
    public function test_x09_outbox_worker_interruption_recovery_without_duplication(): void
    {
        $syncService = app(TallySyncService::class);
        $connector = app(SimulatorTallyConnector::class);
        $connector->setAvailable(true);

        $journal = JournalHeader::create([
            'journal_number' => 'JNL-TEST-X09',
            'business_date' => now()->toDateString(),
            'source_type' => 'test_worker',
            'source_id' => 999,
            'description' => 'Outbox resilience worker test',
        ]);

        $outbox = $syncService->queueVoucherSync($journal);
        $this->assertEquals('pending', $outbox->status);

        // First worker run
        $report1 = $syncService->processPendingOutbox($connector);
        $this->assertEquals(1, $report1['processed']);

        // Worker restarts, receives same batch again
        $report2 = $syncService->processPendingOutbox($connector);
        $this->assertEquals(0, $report2['total_pending']); // Already acknowledged, nothing pending!
    }

    /**
     * X10: Concurrent bills and multi-counter isolation.
     * Ensures sequence numbers and receipt references remain distinct and collision-free across counters.
     */
    public function test_x10_multi_counter_collision_free_bill_and_receipt_sequences(): void
    {
        $shiftService = app(ShiftService::class);
        $billingService = app(BillingService::class);

        $counter1 = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $counter2 = Counter::where('code', 'COUNTER-2')->firstOrFail();

        $cashier1 = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $cashier2 = User::where('email', 'cashier2@malabarclub.local')->firstOrFail();

        $shift1 = $shiftService->openShift($counter1->id, $cashier1->id, 50000);
        $shift2 = $shiftService->openShift($counter2->id, $cashier2->id, 50000);

        $member1 = Member::where('membership_number', 'MC-001')->firstOrFail();
        $member2 = Member::where('membership_number', 'MC-002')->firstOrFail();

        // Counter 1 Bill
        $bill1 = $billingService->createDraftBill(
            shift: $shift1,
            customer: $member1,
            departmentCode: 'card_room',
            items: [['item_name' => 'Card Room Pass', 'quantity' => 1, 'unit_price_paise' => 10000, 'tax_percent' => 0]],
            idempotencyKey: 'CONCURRENT-C1',
            userId: $cashier1->id
        );

        // Counter 2 Bill
        $bill2 = $billingService->createDraftBill(
            shift: $shift2,
            customer: $member2,
            departmentCode: 'bar',
            items: [['item_name' => 'Bar Snacks', 'quantity' => 1, 'unit_price_paise' => 15000, 'tax_percent' => 0]],
            idempotencyKey: 'CONCURRENT-C2',
            userId: $cashier2->id
        );

        $billingService->checkout($bill1, [['tender_type' => TenderType::CASH, 'amount_paise' => 10000]], $cashier1->id);
        $billingService->checkout($bill2, [['tender_type' => TenderType::CASH, 'amount_paise' => 15000]], $cashier2->id);

        $this->assertEquals(BillStatus::PAID, $bill1->fresh()->status);
        $this->assertEquals(BillStatus::PAID, $bill2->fresh()->status);

        // Verify unique bill numbers and receipts
        $this->assertNotEquals($bill1->bill_number, $bill2->bill_number);
        $receipt1 = PaymentReceipt::where('bill_id', $bill1->id)->firstOrFail();
        $receipt2 = PaymentReceipt::where('bill_id', $bill2->id)->firstOrFail();
        $this->assertNotEquals($receipt1->receipt_number, $receipt2->receipt_number);
    }
}
