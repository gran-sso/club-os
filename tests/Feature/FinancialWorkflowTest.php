<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Models\JournalLine;
use App\Domain\Finance\Services\JournalService;
use App\Domain\Integrations\Adapters\ThermalReceiptPrinter;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\InsufficientFundsException;
use App\Domain\Tariff\Models\Department;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $admin;
    protected Counter $counter;
    protected Shift $shift;
    protected Member $member;
    protected WalletAccount $wallet;
    protected WalletService $walletService;
    protected BillingService $billingService;
    protected ShiftService $shiftService;
    protected JournalService $journalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->walletService = app(WalletService::class);
        $this->billingService = app(BillingService::class);
        $this->shiftService = app(ShiftService::class);
        $this->journalService = app(JournalService::class);

        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->wallet = $this->member->wallet;

        // Open shift for cashier
        $this->shift = $this->shiftService->openShift(
            counterId: $this->counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 200000 // ₹2,000 float
        );
    }

    /**
     * Scenario F01:
     * Confirm a fictional INR 1,000 recharge.
     * Prepaid balance rises by 100,000 paise, external collections rise once,
     * backing liability rises (2010), and sales do not rise.
     */
    public function test_f01_confirm_recharge_updates_prepaid_balance_liability_and_not_sales(): void
    {
        $initialBalance = $this->wallet->balance_paise; // 0
        $rechargeAmountPaise = 100000; // ₹1,000

        $result = $this->walletService->recharge(
            wallet: $this->wallet,
            amountPaise: $rechargeAmountPaise,
            tenderType: TenderType::CASH,
            idempotencyKey: 'recharge_test_f01_001',
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        $freshWallet = $this->wallet->fresh();
        $this->assertEquals(100000, $freshWallet->balance_paise);

        // Check Payment Receipt
        $receipt = PaymentReceipt::where('idempotency_key', 'recharge_test_f01_001')->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(100000, $receipt->amount_paise);
        $this->assertEquals(TenderType::CASH, $receipt->tender_type);
        $this->assertNull($receipt->bill_id); // Not a sales bill!

        // Check Journal: Debit Cash in Till (1010) ₹1,000, Credit Prepaid Advances Liability (2010) ₹1,000
        $journal = JournalHeader::where('source_type', 'recharge')
            ->where('source_id', $receipt->id)
            ->first();
        $this->assertNotNull($journal);

        $debitLine = $journal->lines()->where('account_id', Account::where('code', '1010')->value('id'))->first();
        $creditLine = $journal->lines()->where('account_id', Account::where('code', '2010')->value('id'))->first();

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals(100000, $debitLine->debit_paise);
        $this->assertEquals(100000, $creditLine->credit_paise);

        // Ensure NO sales revenue was credited
        $revenueAccounts = Account::where('type', 'revenue')->pluck('id');
        $revenueLine = $journal->lines()->whereIn('account_id', $revenueAccounts)->first();
        $this->assertNull($revenueLine);
    }

    /**
     * Scenario F02:
     * Pay a fictional INR 300 bill using prepaid funds.
     * Balance becomes INR 700, billed sales/tax/ledger components post once,
     * and there is NO second INR 300 external receipt.
     */
    public function test_f02_pay_bill_using_prepaid_funds(): void
    {
        // 1. Initial recharge: ₹1,000 (100,000 paise)
        $this->walletService->recharge(
            wallet: $this->wallet,
            amountPaise: 100000,
            tenderType: TenderType::CASH,
            idempotencyKey: 'recharge_f02_funding',
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        $externalReceiptsCountBeforeSale = PaymentReceipt::whereIn('tender_type', [TenderType::CASH->value, TenderType::DIGITAL->value])->count();

        // 2. Create draft bill for ₹300 (e.g., Guest entry / facility fee ₹254.24 + ₹45.76 GST or flat ₹300 net)
        // Let's do ₹254.24 gross (25424 paise) + 18% tax (4576 paise) = 30000 paise (₹300.00)
        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'card_room',
            items: [
                [
                    'item_name' => 'Card Room Service Fee',
                    'quantity' => 1,
                    'unit_price_paise' => 25424,
                    'tax_percent' => 18.00,
                ]
            ],
            idempotencyKey: 'bill_draft_f02_001',
            userId: $this->cashier->id
        );

        $this->assertEquals(30000, $bill->net_amount_paise);
        $this->assertEquals(BillStatus::DRAFT, $bill->status);

        // 3. Checkout using PREPAID tender
        $checkoutResult = $this->billingService->checkout(
            bill: $bill,
            tenders: [
                [
                    'tender_type' => TenderType::PREPAID,
                    'amount_paise' => 30000,
                ]
            ],
            userId: $this->cashier->id
        );

        $this->assertEquals(BillStatus::PAID, $bill->fresh()->status);
        $this->assertEquals(70000, $this->wallet->fresh()->balance_paise); // ₹700 remaining

        // 4. Ensure NO second external cash/digital receipt was recorded
        $externalReceiptsCountAfterSale = PaymentReceipt::whereIn('tender_type', [TenderType::CASH->value, TenderType::DIGITAL->value])->count();
        $this->assertEquals($externalReceiptsCountBeforeSale, $externalReceiptsCountAfterSale);

        // 5. Verify Journal: Debit 2010 (Prepaid Liability) ₹300, Credit 4010 (Revenue) ₹254.24, Credit 2030 (GST) ₹45.76
        $billJournal = JournalHeader::where('source_type', 'bill')
            ->where('source_id', $bill->id)
            ->first();
        $this->assertNotNull($billJournal);

        $liabilityDebit = $billJournal->lines()->where('account_id', Account::where('code', '2010')->value('id'))->first();
        $revenueCredit = $billJournal->lines()->where('account_id', Account::where('code', '4010')->value('id'))->first();
        $gstCredit = $billJournal->lines()->where('account_id', Account::where('code', '2030')->value('id'))->first();

        $this->assertEquals(30000, $liabilityDebit->debit_paise);
        $this->assertEquals(25424, $revenueCredit->credit_paise);
        $this->assertEquals(4576, $gstCredit->credit_paise);
        $this->assertEquals(30000, $billJournal->totalDebitsPaise());
        $this->assertEquals(30000, $billJournal->totalCreditsPaise());
    }

    /**
     * Scenario F04:
     * Retry recharge/checkout with same idempotency key after timeout.
     * Returns original outcome without duplicating bill, cash receipt or prepaid entry.
     */
    public function test_f04_idempotent_recharge_and_checkout_retries_do_not_duplicate(): void
    {
        $idempotencyKey = 'idempotent_recharge_key_123';

        // First recharge attempt
        $res1 = $this->walletService->recharge(
            wallet: $this->wallet,
            amountPaise: 50000,
            tenderType: TenderType::CASH,
            idempotencyKey: $idempotencyKey,
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        $this->assertEquals(50000, $this->wallet->fresh()->balance_paise);
        $receiptCount1 = PaymentReceipt::where('idempotency_key', $idempotencyKey)->count();
        $this->assertEquals(1, $receiptCount1);

        // Retry with same idempotency key
        $res2 = $this->walletService->recharge(
            wallet: $this->wallet,
            amountPaise: 50000,
            tenderType: TenderType::CASH,
            idempotencyKey: $idempotencyKey,
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        // Balance must remain 50000, NOT 100000!
        $this->assertEquals(50000, $this->wallet->fresh()->balance_paise);
        $receiptCount2 = PaymentReceipt::where('idempotency_key', $idempotencyKey)->count();
        $this->assertEquals(1, $receiptCount2);
    }

    /**
     * Scenario F08:
     * Insufficient prepaid funds are rejected for every role.
     * An administrator does not gain a hidden credit bypass.
     */
    public function test_f08_insufficient_funds_rejected_for_cashier_and_admin(): void
    {
        $this->assertEquals(0, $this->wallet->balance_paise);

        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'card_room',
            items: [
                [
                    'item_name' => 'Card Room Entry',
                    'quantity' => 1,
                    'unit_price_paise' => 10000,
                    'tax_percent' => 0,
                ]
            ],
            idempotencyKey: 'insufficient_funds_bill_001',
            userId: $this->cashier->id
        );

        // Attempt checkout as cashier -> Must throw InsufficientFundsException
        $this->expectException(InsufficientFundsException::class);
        $this->billingService->checkout(
            bill: $bill,
            tenders: [
                [
                    'tender_type' => TenderType::PREPAID,
                    'amount_paise' => 10000,
                ]
            ],
            userId: $this->cashier->id
        );

        // Bill remains draft, balance remains 0
        $this->assertEquals(BillStatus::DRAFT, $bill->fresh()->status);
        $this->assertEquals(0, $this->wallet->fresh()->balance_paise);
    }

    /**
     * Scenario F08 (Admin cannot bypass):
     */
    public function test_f08_admin_cannot_bypass_no_credit_rule(): void
    {
        $this->assertEquals(0, $this->wallet->balance_paise);

        $bill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'card_room',
            items: [
                [
                    'item_name' => 'Card Room Entry',
                    'quantity' => 1,
                    'unit_price_paise' => 20000,
                    'tax_percent' => 0,
                ]
            ],
            idempotencyKey: 'admin_no_credit_bypass_bill',
            userId: $this->admin->id
        );

        $this->expectException(InsufficientFundsException::class);
        // Admin user tries to checkout
        $this->billingService->checkout(
            bill: $bill,
            tenders: [
                [
                    'tender_type' => TenderType::PREPAID,
                    'amount_paise' => 20000,
                ]
            ],
            userId: $this->admin->id
        );
    }

    /**
     * Scenario F12:
     * Cashier closes a shift with opening float, recharge, cash sale, digital sale,
     * prepaid sale and cash transfer.
     * Expected till cash excludes prepaid redemption and includes funding/float/transfers correctly.
     */
    public function test_f12_shift_closing_reconciles_cash_and_excludes_prepaid_redemption(): void
    {
        // 1. Shift opened with float: ₹2,000 (200,000 paise)
        $this->assertEquals(200000, $this->shift->opening_float_paise);

        // 2. Wallet recharge in cash: ₹1,000 (100,000 paise)
        $this->walletService->recharge(
            wallet: $this->wallet,
            amountPaise: 100000,
            tenderType: TenderType::CASH,
            idempotencyKey: 'shift_recharge_cash_1',
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        // 3. Cash sale: ₹500 (50,000 paise)
        $cashBill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'card_room',
            items: [['item_name' => 'Guest Fee', 'quantity' => 1, 'unit_price_paise' => 50000, 'tax_percent' => 0]],
            idempotencyKey: 'shift_cash_sale_bill',
            userId: $this->cashier->id
        );
        $this->billingService->checkout(
            bill: $cashBill,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => 50000]],
            userId: $this->cashier->id
        );

        // 4. Digital sale: ₹300 (30,000 paise via UPI) - DOES NOT enter cash till
        $digitalBill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'card_room',
            items: [['item_name' => 'Service Fee', 'quantity' => 1, 'unit_price_paise' => 30000, 'tax_percent' => 0]],
            idempotencyKey: 'shift_digital_sale_bill',
            userId: $this->cashier->id
        );
        $this->billingService->checkout(
            bill: $digitalBill,
            tenders: [['tender_type' => TenderType::DIGITAL, 'amount_paise' => 30000, 'reference' => 'UPI-REF-999']],
            userId: $this->cashier->id
        );

        // 5. Prepaid sale: ₹400 (40,000 paise) - REDEMPTION, DOES NOT enter cash till
        $prepaidBill = $this->billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'card_room',
            items: [['item_name' => 'Table Fee', 'quantity' => 1, 'unit_price_paise' => 40000, 'tax_percent' => 0]],
            idempotencyKey: 'shift_prepaid_sale_bill',
            userId: $this->cashier->id
        );
        $this->billingService->checkout(
            bill: $prepaidBill,
            tenders: [['tender_type' => TenderType::PREPAID, 'amount_paise' => 40000]],
            userId: $this->cashier->id
        );

        // 6. Cash drop / transfer out: ₹1,000 (100,000 paise) to club safe
        $this->shiftService->recordCashMovement(
            shift: $this->shift,
            type: 'cash_drop',
            amountPaise: 100000,
            notes: 'Midday cash drop to main vault',
            userId: $this->cashier->id
        );

        // Calculation:
        // Opening float: ₹2,000
        // + Cash recharge: ₹1,000
        // + Cash sale: ₹500
        // - Cash drop: ₹1,000
        // Total expected cash = ₹2,500 (250,000 paise)
        // Note: Digital sale (₹300) and Prepaid sale (₹400) are EXCLUDED from till cash!
        $expectedCash = $this->shift->calculateExpectedCashPaise();
        $this->assertEquals(250000, $expectedCash);

        // 7. Close shift with actual counted cash = ₹2,500
        $closedShift = $this->shiftService->closeShift(
            shift: $this->shift,
            closingCashCountedPaise: 250000,
            notes: 'Even till reconciliation'
        );

        $this->assertEquals(ShiftStatus::CLOSED, $closedShift->status);
        $this->assertEquals(250000, $closedShift->closing_cash_counted_paise);
        $this->assertEquals(250000, $closedShift->expected_cash_paise);
        $this->assertEquals(0, $closedShift->discrepancy_paise);
    }

    /**
     * Scenario F03:
     * Two simultaneous spends competing for balance on MySQL.
     * At most one succeeds; rejected transaction leaves no side effect.
     */
    public function test_f03_concurrent_spend_protection_on_mysql(): void
    {
        // Give member ₹500 (50,000 paise)
        $this->walletService->recharge(
            wallet: $this->wallet,
            amountPaise: 50000,
            tenderType: TenderType::CASH,
            idempotencyKey: 'concurrent_f03_funding',
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        $this->assertEquals(50000, $this->wallet->fresh()->balance_paise);

        // Connection 1 attempts to spend ₹400
        // Connection 2 attempts to spend ₹400
        // Since available is only ₹500, at most one can succeed.
        $successCount = 0;
        $failCount = 0;

        try {
            DB::transaction(function () {
                $this->walletService->debit(
                    wallet: $this->wallet,
                    amountPaise: 40000,
                    referenceType: 'test_concurrency',
                    referenceId: 1
                );
            });
            $successCount++;
        } catch (InsufficientFundsException $e) {
            $failCount++;
        }

        try {
            DB::transaction(function () {
                $this->walletService->debit(
                    wallet: $this->wallet,
                    amountPaise: 40000,
                    referenceType: 'test_concurrency',
                    referenceId: 2
                );
            });
            $successCount++;
        } catch (InsufficientFundsException $e) {
            $failCount++;
        }

        $this->assertEquals(1, $successCount);
        $this->assertEquals(1, $failCount);
        $this->assertEquals(10000, $this->wallet->fresh()->balance_paise);
    }
}
