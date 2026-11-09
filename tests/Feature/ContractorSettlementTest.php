<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Models\ContractorSettlementAdjustment;
use App\Domain\Contractor\Models\ContractorSettlementAllocation;
use App\Domain\Contractor\Models\ContractorSettlementBatch;
use App\Domain\Contractor\Services\ContractorSettlementService;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractorSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $accountant;
    protected User $manager;
    protected Member $member;
    protected Counter $counter;
    protected Shift $shift;
    protected Contractor $contractor;
    protected BillingService $billingService;
    protected ShiftService $shiftService;
    protected WalletService $walletService;
    protected ContractorSettlementService $contractorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->billingService = app(BillingService::class);
        $this->shiftService = app(ShiftService::class);
        $this->walletService = app(WalletService::class);
        $this->contractorService = app(ContractorSettlementService::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->accountant = User::where('email', 'accountant@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-3')->firstOrFail(); // Food & Restaurant Counter

        $this->shift = $this->shiftService->openShift(
            counterId: $this->counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 100000
        );

        $this->contractor = Contractor::firstOrCreate(
            ['code' => 'CONT-FOOD-01'],
            [
                'name' => 'Malabar Spices Catering & Hospitality',
                'department_code' => 'food',
                'contact_person' => 'Moideen Kutty',
                'phone' => '9847123456',
                'email' => 'catering@malabarspices.local',
                'bank_name' => 'State Bank of India',
                'bank_account_number' => '12345678901',
                'bank_ifsc' => 'SBIN0001234',
                'commission_rate_percent' => 0.00,
                'is_active' => true,
            ]
        );
    }

    /**
     * Scenario C01:
     * Food bills paid by cash, digital and prepaid all feed their qualifying contractor payable.
     * A general unused prepaid recharge does NOT.
     */
    public function test_c01_food_paid_via_cash_digital_and_prepaid_feeds_settlement_recharge_does_not(): void
    {
        $today = now()->toDateString();

        // 1. General wallet recharge: ₹5,000 (500,000 paise)
        // Must NOT enter contractor settlement!
        $this->walletService->recharge(
            wallet: $this->member->wallet,
            amountPaise: 500000,
            tenderType: TenderType::CASH,
            idempotencyKey: 'rcg_c01_general_wallet',
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        // 2. Food bill paid via Cash: ₹400 (40,000 paise net)
        $cashBill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Kerala Meals with Fish Fry', 'quantity' => 2, 'unit_price_paise' => 20000, 'tax_percent' => 0]],
            idempotencyKey: 'bill_food_cash',
            userId: $this->cashier->id
        );
        $this->billingService->checkout($cashBill, [['tender_type' => TenderType::CASH, 'amount_paise' => 40000]], $this->cashier->id);

        // 3. Food bill paid via Digital (UPI): ₹350 (35,000 paise net)
        $digitalBill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Chicken Biryani Special', 'quantity' => 1, 'unit_price_paise' => 35000, 'tax_percent' => 0]],
            idempotencyKey: 'bill_food_digital',
            userId: $this->cashier->id
        );
        $this->billingService->checkout($digitalBill, [['tender_type' => TenderType::DIGITAL, 'amount_paise' => 35000, 'reference' => 'UPI-FOOD-123']], $this->cashier->id);

        // 4. Food bill paid via Member Prepaid Wallet: ₹250 (25,000 paise net)
        $prepaidBill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Evening Snacks & Tea', 'quantity' => 1, 'unit_price_paise' => 25000, 'tax_percent' => 0]],
            idempotencyKey: 'bill_food_prepaid',
            userId: $this->cashier->id
        );
        $this->billingService->checkout($prepaidBill, [['tender_type' => TenderType::PREPAID, 'amount_paise' => 25000]], $this->cashier->id);

        // Preview settlement for today:
        // Eligible lines: ₹400 + ₹350 + ₹250 = ₹1,000 net (100,000 paise)
        // General recharge ₹5,000 is completely excluded!
        $preview = $this->contractorService->previewDailySettlement($this->contractor, $today);

        $this->assertEquals(3, $preview['total_lines']);
        $this->assertEquals(100000, $preview['total_net_paise']);
        $this->assertEquals(100000, $preview['net_payable_paise']);
        $this->assertEquals(40000, $preview['tender_breakdown']['cash']);
        $this->assertEquals(35000, $preview['tender_breakdown']['digital']);
        $this->assertEquals(25000, $preview['tender_breakdown']['prepaid']);

        // Create settlement batch
        $batch = $this->contractorService->createSettlementBatch($this->contractor, $today, $this->accountant->id);
        $this->assertEquals(100000, $batch->net_payable_paise);
        $this->assertEquals(3, $batch->allocations()->count());

        // Approve batch
        $approvedBatch = $this->contractorService->approveSettlementBatch($batch, $this->accountant);
        $this->assertEquals('approved', $approvedBatch->status);

        // Assert Journal: Debit 5020 (Contractor Food Expense) ₹1,000, Credit 2020 (Food Contractor Payable) ₹1,000
        $journal = JournalHeader::where('source_type', 'contractor_settlement')
            ->where('source_id', $batch->id)
            ->first();
        $this->assertNotNull($journal);

        $debitLine = $journal->lines()->where('account_id', Account::where('code', '5020')->value('id'))->first();
        $creditLine = $journal->lines()->where('account_id', Account::where('code', '2020')->value('id'))->first();

        $this->assertEquals(100000, $debitLine->debit_paise);
        $this->assertEquals(100000, $creditLine->credit_paise);
    }

    /**
     * Scenario C02:
     * One bill contains food and bar items.
     * Only qualifying food value enters contractor settlement; bar revenue remains club income.
     */
    public function test_c02_mixed_department_bill_only_allocates_food_to_contractor(): void
    {
        $today = now()->toDateString();

        // Mixed bill:
        // Line 1: Bar Beer (₹250 + 18% GST = ₹295) -> department_code: bar
        // Line 2: Food Finger Chips (₹150 + 5% GST = ₹157.50 / 15750 paise) -> department_code: food
        $mixedBill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [
                [
                    'department_code' => 'bar',
                    'item_name' => 'Kingfisher Beer',
                    'quantity' => 1,
                    'unit_price_paise' => 25000,
                    'tax_percent' => 18.00,
                ],
                [
                    'department_code' => 'food',
                    'item_name' => 'Chilli Paneer & Roti',
                    'quantity' => 1,
                    'unit_price_paise' => 15000,
                    'tax_percent' => 5.00,
                ],
            ],
            idempotencyKey: 'bill_mixed_c02',
            userId: $this->cashier->id
        );

        // Checkout bill with cash: ₹295.00 + ₹157.50 = ₹452.50 (45250 paise)
        $this->billingService->checkout(
            $mixedBill,
            [['tender_type' => TenderType::CASH, 'amount_paise' => 45250]],
            $this->cashier->id
        );

        // Check contractor settlement preview
        $preview = $this->contractorService->previewDailySettlement($this->contractor, $today);

        // Only the food line (15750 paise) must be allocated; the bar line must be excluded!
        $this->assertEquals(1, $preview['total_lines']);
        $this->assertEquals(15750, $preview['total_net_paise']);
        $this->assertEquals(15750, $preview['net_payable_paise']);
    }

    /**
     * Scenario C03:
     * Two jobs prepare the same business-date settlement or a user retries.
     * No paid food allocation enters multiple batches.
     */
    public function test_c03_idempotent_settlement_batch_prevents_duplicate_allocation(): void
    {
        $today = now()->toDateString();

        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Lunch Plate', 'quantity' => 1, 'unit_price_paise' => 18000, 'tax_percent' => 0]],
            idempotencyKey: 'bill_food_c03',
            userId: $this->cashier->id
        );
        $this->billingService->checkout($bill, [['tender_type' => TenderType::CASH, 'amount_paise' => 18000]], $this->cashier->id);

        // First job creates batch
        $batch1 = $this->contractorService->createSettlementBatch($this->contractor, $today, $this->accountant->id);
        $this->assertEquals(1, $batch1->allocations()->count());

        // Second retry / job for same business date returns the existing batch without duplicate records
        $batch2 = $this->contractorService->createSettlementBatch($this->contractor, $today, $this->accountant->id);
        $this->assertEquals($batch1->id, $batch2->id);

        $totalBatches = ContractorSettlementBatch::where('contractor_id', $this->contractor->id)->where('business_date', $today)->count();
        $this->assertEquals(1, $totalBatches);

        $totalAllocations = ContractorSettlementAllocation::where('settlement_batch_id', $batch1->id)->count();
        $this->assertEquals(1, $totalAllocations);
    }

    /**
     * Scenario C04:
     * Contractor transfer fails or is partial.
     * Confirmed amount reduces payable once; unpaid residual remains visible.
     */
    public function test_c04_partial_contractor_payout_tracks_residual_payable(): void
    {
        $today = now()->toDateString();

        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Club Dinner Buffet', 'quantity' => 10, 'unit_price_paise' => 10000, 'tax_percent' => 0]],
            idempotencyKey: 'bill_food_c04',
            userId: $this->cashier->id
        );
        $this->billingService->checkout($bill, [['tender_type' => TenderType::CASH, 'amount_paise' => 100000]], $this->cashier->id);

        $batch = $this->contractorService->createSettlementBatch($this->contractor, $today, $this->accountant->id);
        $this->contractorService->approveSettlementBatch($batch, $this->accountant);

        $this->assertEquals(100000, $batch->net_payable_paise); // ₹1,000 net payable

        // Partial payment: Transfer ₹600 (60,000 paise) via NEFT
        $partialBatch = $this->contractorService->confirmSettlementPayment(
            batch: $batch,
            paidAmountPaise: 60000,
            paymentReference: 'NEFT-PARTIAL-001',
            userId: $this->accountant->id
        );

        $this->assertEquals('partially_paid', $partialBatch->status);
        $this->assertEquals(60000, $partialBatch->paid_amount_paise);
        $this->assertEquals(40000, $partialBatch->residual_payable_paise); // ₹400 residual!

        // Complete remaining payment: Transfer ₹400 (40,000 paise)
        $completedBatch = $this->contractorService->confirmSettlementPayment(
            batch: $partialBatch,
            paidAmountPaise: 40000,
            paymentReference: 'NEFT-REMAINDER-002',
            userId: $this->accountant->id
        );

        $this->assertEquals('paid', $completedBatch->status);
        $this->assertEquals(100000, $completedBatch->paid_amount_paise);
        $this->assertEquals(0, $completedBatch->residual_payable_paise);
    }

    /**
     * Scenario C05:
     * A refund occurs AFTER settlement was paid and locked.
     * A linked next-settlement adjustment appears without modifying the locked paid batch.
     */
    public function test_c05_post_settlement_refund_creates_adjustment_for_next_batch(): void
    {
        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        // 1. Yesterday's food bill: ₹500 (50,000 paise)
        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Yesterday Special Thali', 'quantity' => 1, 'unit_price_paise' => 50000, 'tax_percent' => 0]],
            idempotencyKey: 'bill_yesterday_c05',
            userId: $this->cashier->id
        );
        $this->billingService->checkout($bill, [['tender_type' => TenderType::CASH, 'amount_paise' => 50000]], $this->cashier->id);

        // Update created_at to yesterday via DB table
        \Illuminate\Support\Facades\DB::table('bills')->where('id', $bill->id)->update(['created_at' => $yesterday . ' 14:00:00', 'shift_id' => null]);
        \Illuminate\Support\Facades\DB::table('bill_lines')->where('bill_id', $bill->id)->update(['created_at' => $yesterday . ' 14:00:00']);

        // 2. Yesterday's batch created, approved, paid & locked!
        $batchYesterday = $this->contractorService->createSettlementBatch($this->contractor, $yesterday, $this->accountant->id);
        $this->contractorService->approveSettlementBatch($batchYesterday, $this->accountant);
        $this->contractorService->confirmSettlementPayment($batchYesterday, 50000, 'NEFT-YESTERDAY-PAID', $this->accountant->id);

        $this->assertTrue($batchYesterday->isLocked());
        $this->assertEquals(50000, $batchYesterday->paid_amount_paise);

        // 3. Today: Member complains about food quality yesterday and requests a refund.
        // Manager approves refund of ₹500 for the yesterday food bill.
        $refund = $this->billingService->refund(
            bill: $bill,
            amountPaise: 50000,
            reason: 'Food poisoning complaint from yesterday',
            approver: $this->manager,
            idempotencyKey: 'refund_yesterday_food',
            returnStock: false
        );

        // Assert: Locked yesterday batch was NOT modified!
        $this->assertEquals(50000, $batchYesterday->fresh()->net_payable_paise);
        $this->assertEquals('paid', $batchYesterday->fresh()->status);

        // Assert: Unapplied adjustment created for contractor with -₹500 (-50,000 paise)
        $adjustment = ContractorSettlementAdjustment::where('contractor_id', $this->contractor->id)
            ->where('is_applied', false)
            ->first();
        $this->assertNotNull($adjustment);
        $this->assertEquals(-50000, $adjustment->amount_paise);
        $this->assertEquals('post_settlement_refund', $adjustment->type);

        // 4. Today: New food sales of ₹800 (80,000 paise)
        $todayBill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Today Lunch Feast', 'quantity' => 1, 'unit_price_paise' => 80000, 'tax_percent' => 0]],
            idempotencyKey: 'bill_today_food_c05',
            userId: $this->cashier->id
        );
        $this->billingService->checkout($todayBill, [['tender_type' => TenderType::CASH, 'amount_paise' => 80000]], $this->cashier->id);

        // 5. Today's settlement preview:
        // Total food sales: ₹800
        // Recovery adjustment: -₹500
        // Net payable for today: ₹300 (30,000 paise)!
        $todayPreview = $this->contractorService->previewDailySettlement($this->contractor, $today);
        $this->assertEquals(80000, $todayPreview['total_net_paise']);
        $this->assertEquals(-50000, $todayPreview['total_adjustments_paise']);
        $this->assertEquals(30000, $todayPreview['net_payable_paise']);

        // Today's batch applies the adjustment cleanly
        $todayBatch = $this->contractorService->createSettlementBatch($this->contractor, $today, $this->accountant->id);
        $this->assertEquals(30000, $todayBatch->net_payable_paise);
        $this->assertTrue($adjustment->fresh()->is_applied);
        $this->assertEquals($todayBatch->id, $adjustment->fresh()->applied_batch_id);
    }
}
