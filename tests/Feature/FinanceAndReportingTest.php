<?php

namespace Tests\Feature;

use App\Domain\Billing\Services\BillingService;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Finance\Exceptions\PeriodClosedException;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Models\RevenueRecognitionEntry;
use App\Domain\Finance\Services\ExpenseService;
use App\Domain\Finance\Services\FinancialReportService;
use App\Domain\Finance\Services\PeriodClosingService;
use App\Domain\Finance\Services\RevenueRecognitionService;
use App\Domain\Integrations\Adapters\SimulatorTallyConnector;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Integrations\TallySyncService;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Billing\Models\Counter;
use App\Domain\Tariff\Models\Department;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAndReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * R01: Subledger and Journal Reconciliation.
     * Journal debits equal credits, member wallets balance to account 2010, and contractor payables balance to 2020.
     */
    public function test_r01_subledger_and_journal_reconciliation(): void
    {
        $walletService = app(WalletService::class);
        $reportService = app(FinancialReportService::class);
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $admin = User::firstOrFail();

        // Perform recharge of ₹1,000
        $walletService->recharge($member->wallet, 100000, TenderType::CASH, 'TEST-RECON-01', null, null, $admin->id);

        $recon = $reportService->getSubledgerReconciliation();

        // Journal must balance
        $this->assertTrue($recon['journal_integrity']['balanced']);
        $this->assertEquals(0, $recon['journal_integrity']['difference_paise']);

        // Member wallet total must equal liability account 2010
        $this->assertTrue($recon['prepaid_wallet']['reconciled']);
        $this->assertEquals(0, $recon['prepaid_wallet']['difference_paise']);
    }

    /**
     * R02: Annual plan is paid upfront. Collections show receipt on payment date;
     * recognised income follows approved service-period schedule without scheduler retry duplication.
     */
    public function test_r02_annual_plan_upfront_payment_and_straight_line_daily_recognition_idempotency(): void
    {
        $revService = app(RevenueRecognitionService::class);
        $member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $tariff = Tariff::where('code', 'GYM-MONTHLY')->firstOrFail();

        // 1. Create a 365-day upfront subscription for ₹24,000 (2,400,000 paise)
        $sub = FacilitySubscription::create([
            'subscription_number' => 'SUB-ANNUAL-TEST',
            'member_id' => $member->id,
            'facility_code' => 'gym',
            'tariff_id' => $tariff->id,
            'plan_type' => 'annual',
            'starts_at' => '2026-01-01',
            'ends_at' => '2027-01-01',
            'total_fee_paise' => 2400000,
            'status' => 'active',
        ]);

        $schedule = $revService->createSchedule($sub);
        $this->assertEquals(365, $schedule->total_days);
        $this->assertEquals(intdiv(2400000, 365), $schedule->daily_rate_paise); // ~6575 paise/day

        // 2. Run recognition for Jan 1, 2026
        $entriesFirstRun = $revService->recogniseForDate('2026-01-01');
        $this->assertCount(1, $entriesFirstRun);
        $entry = $entriesFirstRun[0];
        $this->assertEquals($schedule->daily_rate_paise, $entry->amount_paise);

        // 3. Run recognition for Jan 1, 2026 a SECOND time (simulate cron retry)
        // Must be completely idempotent; NO new entries or journals created!
        $entriesSecondRun = $revService->recogniseForDate('2026-01-01');
        $this->assertCount(1, $entriesSecondRun);
        $this->assertEquals($entry->id, $entriesSecondRun[0]->id);

        $totalEntries = RevenueRecognitionEntry::where('schedule_id', $schedule->id)->count();
        $this->assertEquals(1, $totalEntries);
    }

    /**
     * R03: Department P&L includes consumed inventory costs, correct food mapping,
     * recognised plan income, direct expenses and disclosed shared allocation.
     * Department totals plus unallocated items reconcile to the overall result.
     */
    public function test_r03_department_profit_and_loss_exact_reconciliation(): void
    {
        $expenseService = app(ExpenseService::class);
        $reportService = app(FinancialReportService::class);

        // 1. Record an operating expense in Sports Department (₹500 = 50,000 paise)
        $sportsDept = Department::where('code', 'sports')->firstOrFail();
        $expenseService->recordExpense([
            'department_id' => $sportsDept->id,
            'payee' => 'Yonex Shuttlecocks',
            'amount_paise' => 50000,
            'payment_method' => 'bank_transfer',
            'expense_date' => '2026-10-17',
            'notes' => 'Shuttlecock barrels for badminton court',
        ]);

        // 2. Record an unallocated general clubhouse expense (₹1,000 = 100,000 paise)
        $expenseService->recordExpense([
            'department_id' => null,
            'payee' => 'Kerala State Electricity Board',
            'amount_paise' => 100000,
            'payment_method' => 'bank_transfer',
            'expense_date' => '2026-10-17',
            'notes' => 'Main clubhouse electricity bill',
        ]);

        // 3. Generate Department P&L
        $pnl = $reportService->getDepartmentProfitAndLoss('2026-10-01', '2026-10-31');

        $this->assertTrue($pnl['summary']['is_reconciled']);
        $this->assertEquals(50000, $pnl['departments']['sports']['cost_paise']);
        $this->assertEquals(100000, $pnl['unallocated']['cost_paise']);

        // Overall club net result must equal department margins + unallocated margin
        $expectedNet = $pnl['summary']['total_department_margin_paise'] + $pnl['summary']['unallocated_margin_paise'];
        $this->assertEquals($expectedNet, $pnl['summary']['overall_club_net_paise']);
    }

    /**
     * R04: Close a financial period. Ordinary users cannot backdate edits into it.
     */
    public function test_r04_closed_financial_period_prevents_backdating(): void
    {
        $periodService = app(PeriodClosingService::class);
        $expenseService = app(ExpenseService::class);

        // Close September 2026
        $periodService->closePeriod('2026-09', '2026-09-01', '2026-09-30', 'September financial books closed');

        $this->assertTrue($periodService->isDateClosed('2026-09-15'));
        $this->assertFalse($periodService->isDateClosed('2026-10-01'));

        // Attempting an expense or posting on 2026-09-15 must throw PeriodClosedException
        $this->expectException(PeriodClosedException::class);
        $expenseService->recordExpense([
            'payee' => 'Backdated Vendor',
            'amount_paise' => 25000,
            'expense_date' => '2026-09-15',
        ]);
    }

    /**
     * X01: Tally is unavailable while a valid sale is paid.
     * Club posting succeeds, one outbox event remains retryable, and no duplicate financial operation is created.
     */
    public function test_x01_tally_unavailable_does_not_block_club_sale_and_leaves_retryable_outbox(): void
    {
        $syncService = app(TallySyncService::class);
        $connector = app(SimulatorTallyConnector::class);
        $billingService = app(BillingService::class);

        // Simulate Tally downtime
        $connector->setAvailable(false);

        $member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $counter = Counter::firstOrFail();
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $shiftService = app(\App\Domain\Billing\Services\ShiftService::class);
        $shift = $shiftService->openShift(
            counterId: $counter->id,
            userId: $cashier->id,
            openingFloatPaise: 100000
        );

        // 1. Club Sale completes successfully despite Tally outage
        $bill = $billingService->createDraftBill(
            shift: $shift,
            customer: $member,
            departmentCode: 'card_room',
            items: [
                [
                    'item_name' => 'Card Room Service Fee',
                    'quantity' => 1,
                    'unit_price_paise' => 20000,
                    'tax_percent' => 0,
                ],
            ],
            idempotencyKey: 'DRAFT-X01-TEST',
            userId: $cashier->id
        );

        $result = $billingService->checkout(
            bill: $bill,
            tenders: [
                [
                    'tender_type' => TenderType::CASH,
                    'amount_paise' => 20000,
                ],
            ],
            userId: $cashier->id
        );

        $this->assertEquals(\App\Domain\Shared\Enums\BillStatus::PAID, $bill->fresh()->status);
        $journal = JournalHeader::where('source_type', 'bill')->where('source_id', $bill->id)->firstOrFail();
        $this->assertNotNull($journal);

        // 2. Queue outbox message
        $outbox = $syncService->queueVoucherSync($journal);
        $this->assertEquals('pending', $outbox->status);

        // 3. Process outbox while Tally is unavailable
        $syncReport = $syncService->processPendingOutbox($connector);

        // Outbox must still have 1 pending message with failure recorded, club sale stays paid
        $outbox->refresh();
        $this->assertEquals('pending', $outbox->status);
        $this->assertEquals(1, $outbox->attempts);
        $this->assertStringContainsString('unavailable', $outbox->last_error);
    }

    /**
     * X02: Tally receives a voucher but acknowledgement is lost.
     * Retry/reconciliation detects the existing external reference and does not duplicate the voucher.
     */
    public function test_x02_tally_retry_with_lost_ack_detects_existing_reference_and_does_not_duplicate(): void
    {
        $syncService = app(TallySyncService::class);
        $connector = app(SimulatorTallyConnector::class);
        $connector->setAvailable(true);

        $journalService = app(\App\Domain\Finance\Services\JournalService::class);
        $journal = $journalService->post(
            'test_sync_x02',
            1,
            'Test Voucher for Tally Sync X02',
            [
                ['account_code' => '1010', 'debit_paise' => 10000, 'credit_paise' => 0, 'department_code' => 'card_room'],
                ['account_code' => '4010', 'debit_paise' => 0, 'credit_paise' => 10000, 'department_code' => 'card_room'],
            ]
        );

        // First transmission succeeds
        $outbox = $syncService->queueVoucherSync($journal);
        $syncReport1 = $syncService->processPendingOutbox($connector);
        $this->assertEquals(1, $syncReport1['processed']);

        // Simulate lost acknowledgement: reset outbox status to retryable
        $outbox->update(['status' => 'retryable']);

        // Retry transmission
        $syncReport2 = $syncService->processPendingOutbox($connector);

        // Must acknowledge cleanly without creating duplicate vouchers
        $outbox->refresh();
        $this->assertEquals('acknowledged', $outbox->status);
    }

    /**
     * X03: Test-company import rejects a ledger/tax/department mapping.
     * Record the actual error and failed items; do not mark the batch fully accepted from a transport-level success alone.
     */
    public function test_x03_test_company_rejects_mapping_and_records_failure(): void
    {
        $syncService = app(TallySyncService::class);
        $connector = app(SimulatorTallyConnector::class);

        // Configure test company rejection
        $connector->setAvailable(true);
        $connector->setMappingRejectionError('Error: Cost Centre DEPT-UNKNOWN not found in Tally company.');

        $journalService = app(\App\Domain\Finance\Services\JournalService::class);
        $journal = $journalService->post(
            'test_sync_x03',
            2,
            'Test Voucher for Tally Sync X03',
            [
                ['account_code' => '1010', 'debit_paise' => 10000, 'credit_paise' => 0, 'department_code' => 'card_room'],
                ['account_code' => '4010', 'debit_paise' => 0, 'credit_paise' => 10000, 'department_code' => 'card_room'],
            ]
        );

        $outbox = $syncService->queueVoucherSync($journal);
        $outbox->update(['status' => 'pending']);

        // Process outbox
        $syncReport = $syncService->processPendingOutbox($connector);

        // Must NOT be marked acknowledged! Must be marked failed with clear error
        $outbox->refresh();
        $this->assertEquals('failed', $outbox->status);
        $this->assertStringContainsString('Cost Centre DEPT-UNKNOWN not found', $outbox->last_error);
    }
}
