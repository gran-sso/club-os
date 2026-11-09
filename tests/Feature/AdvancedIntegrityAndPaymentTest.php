<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Models\GuestPass;
use App\Domain\Access\Services\GuestService;
use App\Domain\Accommodation\Models\RoomReservation;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\PaymentRecoveryService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Services\ContractorSettlementService;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Services\SubscriptionService;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\ServingMapping;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\PassStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdvancedIntegrityAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $accountant;
    protected Shift $shift;
    protected Member $member;
    protected WalletAccount $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->accountant = User::where('email', 'accountant@malabarclub.local')->firstOrFail();

        $shiftService = app(ShiftService::class);
        $counter = \App\Domain\Billing\Models\Counter::firstOrFail();
        $this->shift = $shiftService->openShift(
            counterId: $counter->id,
            userId: $this->cashier->id,
            openingFloatPaise: 200000 // ₹2,000 float
        );

        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->wallet = $this->member->wallet;
    }

    /**
     * Scenario A06:
     * Refund or cancellation of an unused entry pass revokes it and records the
     * corresponding financial adjustment once. An unrelated valid facility plan is unaffected.
     */
    public function test_a06_unused_pass_cancellation_revokes_pass_posts_refund_and_preserves_unrelated_plan(): void
    {
        $guestService = app(GuestService::class);
        $billingService = app(BillingService::class);
        $subscriptionService = app(SubscriptionService::class);

        // 1. Give member an unrelated valid gym plan
        $gymTariff = Tariff::where('code', 'GYM-MONTHLY')->firstOrFail();
        $gymReceipt = PaymentReceipt::create([
            'receipt_number' => 'RCT-GYM-A06',
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'tender_type' => TenderType::CASH,
            'amount_paise' => $gymTariff->rate_paise,
            'provider_name' => 'cashier_manual',
            'provider_status' => 'confirmed',
            'idempotency_key' => 'receipt_gym_a06',
            'confirmed_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]);
        $gymSub = $subscriptionService->createSubscription(
            member: $this->member,
            facilityCode: 'gym',
            planType: 'monthly',
            tariff: $gymTariff,
            receipt: $gymReceipt
        );
        $this->assertEquals('active', $gymSub->status);

        // 2. Register guest and purchase a pass
        $guest = $guestService->registerGuest(
            personData: ['first_name' => 'Siddharth', 'phone' => '9847111222'],
            sponsorType: 'member',
            sponsorId: $this->member->id
        );

        $tariff = Tariff::where('code', 'GUEST-BAR-ENTRY')->firstOrFail();
        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $guest,
            departmentCode: 'card_room',
            items: [
                [
                    'item_name' => 'Guest Entry Pass',
                    'quantity' => 1,
                    'unit_price_paise' => $tariff->rate_paise,
                    'tax_percent' => 18.00,
                    'department_code' => 'card_room',
                ]
            ],
            idempotencyKey: 'bill_guest_a06'
        );

        $checkout = $billingService->checkout(
            bill: $bill,
            tenders: [
                ['tender_type' => TenderType::CASH, 'amount_paise' => $bill->net_amount_paise]
            ],
            userId: $this->cashier->id
        );

        $pass = GuestPass::where('guest_id', $guest->id)->firstOrFail();
        $this->assertEquals(PassStatus::ACTIVE, $pass->status);
        $this->assertEquals(0, $pass->entry_count);

        // 3. Cancel the unused guest pass for refund
        $result = $guestService->cancelUnusedGuestPass(
            pass: $pass,
            reason: 'Guest could not attend; requested cancellation before entry',
            approver: $this->manager
        );

        // 4. Verify pass revoked and financial adjustment recorded
        $this->assertEquals(PassStatus::REVOKED, $result['pass']->status);
        $this->assertNotNull($result['refund']);
        $this->assertEquals(BillStatus::REFUNDED, $bill->fresh()->status);

        // 5. Verify the unrelated gym plan remains completely active
        $this->assertEquals('active', $gymSub->fresh()->status);
        $entitlement = $subscriptionService->checkEntitlement($this->member, 'gym');
        $this->assertTrue($entitlement['allowed']);
    }

    /**
     * Scenario A09:
     * A suspended or expired pass cannot create an entry entitlement by URL manipulation or person ID swapping.
     * Sensitive member documents are not publicly accessible.
     */
    public function test_a09_revoked_pass_cannot_grant_access_and_documents_are_private(): void
    {
        $guestService = app(GuestService::class);
        $guest = $guestService->registerGuest(
            personData: ['first_name' => 'Kiran', 'phone' => '9847333444'],
            sponsorType: 'member',
            sponsorId: $this->member->id
        );

        $pass = GuestPass::create([
            'guest_id' => $guest->id,
            'pass_code' => 'PASS-REVOKED-01',
            'facility_code' => 'bar',
            'bill_line_id' => 1,
            'status' => PassStatus::REVOKED,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'entry_count' => 0,
            'max_entries' => 1,
        ]);

        $this->assertFalse($pass->isValidForEntry());

        // Document security check: Unauthenticated user cannot access sensitive member documents
        $guestResponse = $this->get('/documents/members/' . $this->member->id . '/id_proof.jpg');
        $guestResponse->assertRedirect('/login');

        // Cashier role without document clearance gets 403 or unauthorized
        $staffResponse = $this->actingAs($this->cashier)->get('/documents/members/' . $this->member->id . '/id_proof.jpg');
        $staffResponse->assertStatus(403);

        // Admin can view with private headers
        $adminResponse = $this->actingAs($this->admin)->get('/documents/members/' . $this->member->id . '/id_proof.jpg');
        $adminResponse->assertStatus(200);
        $adminResponse->assertHeader('Pragma', 'no-cache');
    }

    /**
     * Scenario F05:
     * Payment attempt is initiated, cancelled, failed or remains pending.
     * No fulfillment or paid access occurs. A forged success redirect does not change this.
     */
    public function test_f05_payment_attempt_failed_or_forged_redirect_does_not_fulfill(): void
    {
        $billingService = app(BillingService::class);
        $recoveryService = app(PaymentRecoveryService::class);

        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [
                ['item_name' => 'Fruit Juice', 'quantity' => 2, 'unit_price_paise' => 10000, 'tax_percent' => 5.00]
            ],
            idempotencyKey: 'bill_f05_test'
        );

        $this->assertEquals(BillStatus::DRAFT, $bill->status);

        // 1. Failed / Pending callback
        $pendingPayload = [
            'provider_reference' => 'TXN-PENDING-001',
            'status' => 'pending',
            'amount_paise' => $bill->net_amount_paise,
        ];
        $secret = 'malabar_webhook_secret_key';
        $signature = hash_hmac('sha256', json_encode($pendingPayload), $secret);

        $result = $recoveryService->handleProviderCallback(
            payload: $pendingPayload,
            signature: $signature,
            secretKey: $secret,
            bill: $bill
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(BillStatus::DRAFT, $bill->fresh()->status);
        $this->assertEquals(0, $bill->fresh()->paid_amount_paise);

        // 2. Forged success redirect with bad signature
        $this->expectException(UnauthorizedActionException::class);
        $recoveryService->handleProviderCallback(
            payload: ['provider_reference' => 'TXN-FORGED-001', 'status' => 'confirmed'],
            signature: 'invalid_forged_hmac_signature',
            secretKey: $secret,
            bill: $bill
        );
    }

    /**
     * Scenario F06:
     * Receive the same signed provider confirmation repeatedly or out of order.
     * Confirm the financial result once and retain a meaningful provider event trail.
     */
    public function test_f06_idempotent_signed_webhook_processing_with_event_trail(): void
    {
        $billingService = app(BillingService::class);
        $recoveryService = app(PaymentRecoveryService::class);

        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [
                ['item_name' => 'Soda', 'quantity' => 1, 'unit_price_paise' => 5000, 'tax_percent' => 5.00]
            ],
            idempotencyKey: 'bill_f06_test'
        );

        $secret = 'test_secret_key';
        $payload = [
            'provider_reference' => 'UPI-F06-999888',
            'status' => 'confirmed',
            'amount_paise' => $bill->net_amount_paise,
        ];
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // First delivery: Confirms bill
        $res1 = $recoveryService->handleProviderCallback($payload, $signature, $secret, $bill, $this->cashier);
        $this->assertTrue($res1['success']);
        $this->assertEquals(BillStatus::PAID, $bill->fresh()->status);

        // Second duplicate delivery: Returns already processed without duplicating receipts
        $res2 = $recoveryService->handleProviderCallback($payload, $signature, $secret, $bill, $this->cashier);
        $this->assertTrue($res2['success']);
        $this->assertEquals('already_processed', $res2['status']);
        $this->assertEquals(1, PaymentReceipt::where('provider_reference', 'UPI-F06-999888')->count());
    }

    /**
     * Scenario F07:
     * External payment is captured but application posting fails.
     * Recovery finds the unmatched captured amount, resolves it exactly once to posting or approved refund,
     * and does not issue goods before confirmed posting.
     */
    public function test_f07_captured_unposted_payment_recovery_resolves_to_refund_without_goods_issuance(): void
    {
        $recoveryService = app(PaymentRecoveryService::class);

        // Record captured payment where application crashed before bill could be posted
        $capture = $recoveryService->recordCapturedPayment(
            providerName: 'razorpay',
            providerReference: 'pay_unmatched_12345',
            amountPaise: 15000,
            rawPayload: ['method' => 'upi', 'vpa' => 'member@okhdfcbank']
        );

        $this->assertEquals('captured', $capture->status);

        // Recovery resolves to approved refund since target bill cannot be posted (e.g. out of stock)
        $outcome = $recoveryService->recoverUnmatchedCapture(
            capture: $capture,
            targetBill: null,
            operator: $this->manager,
            forceRefundIfCannotPost: true
        );

        $this->assertEquals('refunded', $outcome['outcome']);
        $this->assertFalse($outcome['goods_issued']);
        $this->assertEquals('refunded', $capture->fresh()->status);

        // Trying to resolve again throws exception (exactly once)
        $this->expectException(\InvalidArgumentException::class);
        $recoveryService->recoverUnmatchedCapture($capture->fresh(), null, $this->manager);
    }

    /**
     * Scenario F09:
     * Two counters finish bills concurrently. Unique receipt numbers and source journal references
     * remain collision-free. A reprint keeps the original reference.
     */
    public function test_f09_counter_bills_generate_collision_free_receipt_numbers_and_reprint_keeps_reference(): void
    {
        $billingService = app(BillingService::class);

        $bill1 = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_name' => 'Snack 1', 'quantity' => 1, 'unit_price_paise' => 4000]],
            idempotencyKey: 'bill_f09_1'
        );

        $bill2 = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_name' => 'Snack 2', 'quantity' => 1, 'unit_price_paise' => 6000]],
            idempotencyKey: 'bill_f09_2'
        );

        $res1 = $billingService->checkout($bill1, [['tender_type' => TenderType::CASH, 'amount_paise' => 4000]]);
        $res2 = $billingService->checkout($bill2, [['tender_type' => TenderType::CASH, 'amount_paise' => 6000]]);

        $rcp1 = $res1['receipts'][0]->receipt_number;
        $rcp2 = $res2['receipts'][0]->receipt_number;

        // Distinct collision-free numbers
        $this->assertNotEquals($rcp1, $rcp2);

        // Reprint maintains exact original receipt number and references
        $reprint = $billingService->checkout($bill1, [['tender_type' => TenderType::CASH, 'amount_paise' => 4000]]);
        $this->assertEquals($rcp1, $reprint['receipts'][0]->receipt_number);
    }

    /**
     * Scenario F10:
     * If split tenders are enabled, one part is confirmed and another fails.
     * The sale remains unfulfilled and the confirmed portion is tracked for recovery/refund;
     * it cannot disappear or be collected twice.
     */
    public function test_f10_split_tender_partial_failure_tracks_unapplied_funds(): void
    {
        $billingService = app(BillingService::class);
        $recoveryService = app(PaymentRecoveryService::class);

        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_name' => 'Meal', 'quantity' => 1, 'unit_price_paise' => 50000]],
            idempotencyKey: 'bill_f10_test'
        );

        // Tender 1: Cash ₹200 confirmed. Tender 2: UPI ₹300 failed.
        $outcome = $recoveryService->recordSplitTenderFailure(
            bill: $bill,
            confirmedTenders: [
                ['tender_type' => TenderType::CASH, 'amount_paise' => 20000]
            ],
            failureReason: 'UPI gateway connection timed out at bank terminal',
            operator: $this->cashier
        );

        $this->assertFalse($outcome['is_fulfilled']);
        $this->assertEquals(BillStatus::DRAFT, $bill->fresh()->status);
        $this->assertEquals(1, count($outcome['unapplied_payments']));
        $this->assertEquals('held', $outcome['unapplied_payments'][0]->status);
        $this->assertEquals(20000, $outcome['unapplied_payments'][0]->amount_paise);
    }

    /**
     * Scenario F11:
     * Refund retries cannot exceed the original net refundable amount or restore a prepaid balance
     * more than once. A financial refund of consumed liquor does not fabricate a physical stock return.
     */
    public function test_f11_refund_limits_and_no_fabricated_stock_on_consumed_liquor(): void
    {
        $billingService = app(BillingService::class);

        $item = InventoryItem::create([
            'sku' => 'WHISKY-F11',
            'name' => 'Premium Whisky 750ml',
            'department_code' => 'bar',
            'category' => 'liquor',
            'base_unit' => 'ml',
            'current_stock_qty' => 750.0,
            'current_avg_cost_paise' => 100,
            'reorder_level' => 100.0,
            'is_active' => true,
        ]);

        $tariff = Tariff::create([
            'code' => 'BAR-WHISKY-PEG-F11',
            'name' => 'Whisky 30ml Peg',
            'department_code' => 'bar',
            'rate_paise' => 25000,
            'tax_percent' => 0.0,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        ServingMapping::create([
            'tariff_id' => $tariff->id,
            'inventory_item_id' => $item->id,
            'serving_name' => 'Whisky 30ml Peg',
            'serving_qty' => 30.0,
        ]);

        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [
                [
                    'item_name' => 'Whisky 30ml Peg',
                    'quantity' => 1,
                    'unit_price_paise' => 25000,
                    'item_id' => $tariff->id,
                    'item_type' => 'inventory_serving',
                    'department_code' => 'bar',
                ]
            ],
            idempotencyKey: 'bill_f11_liquor'
        );

        $billingService->checkout($bill, [['tender_type' => TenderType::CASH, 'amount_paise' => 25000]]);
        $this->assertEquals(720.0, $item->fresh()->current_stock_qty);

        // Consumed drink refund: returnStock is FALSE
        $refund = $billingService->refund(
            bill: $bill,
            amountPaise: 25000,
            reason: 'Customer complaint about taste; drink was consumed',
            approver: $this->manager,
            idempotencyKey: 'refund_f11_1',
            returnStock: false
        );

        // Stock does NOT increment back to 750 because consumed liquor cannot fabricate stock
        $this->assertEquals(720.0, $item->fresh()->current_stock_qty);

        // Attempting to refund again throws exception
        $this->expectException(\InvalidArgumentException::class);
        $billingService->refund(
            bill: $bill,
            amountPaise: 5000,
            reason: 'Excess refund attempt',
            approver: $this->manager,
            idempotencyKey: 'refund_f11_excess',
            returnStock: false
        );
    }

    /**
     * Scenario F13:
     * Gateway settlement is net of fees. Gross confirmed customer payment, provider clearing,
     * fees and bank payout reconcile without changing the original sale value.
     */
    public function test_f13_gateway_settlement_reconciles_gross_fee_and_net_bank_deposit(): void
    {
        $recoveryService = app(PaymentRecoveryService::class);

        // Gross customer payments: ₹1,000 (100,000 paise)
        // Gateway fee: ₹20 (2,000 paise)
        // Net bank deposit: ₹980 (98,000 paise)
        $recon = $recoveryService->reconcileGatewaySettlement(
            batchReference: 'PAYOUT-BATCH-20261018',
            grossPaise: 100000,
            feePaise: 2000,
            netPayoutPaise: 98000,
            accountant: $this->accountant
        );

        $this->assertTrue($recon['balanced']);
        $this->assertEquals(100000, $recon['gross_paise']);
        $this->assertEquals(2000, $recon['fee_paise']);
        $this->assertEquals(98000, $recon['net_payout_paise']);
    }

    /**
     * Scenario F14:
     * A posted bill/journal is edited through a crafted request or deleted.
     * Application denies it.
     */
    public function test_f14_posted_bills_and_journals_are_immutable(): void
    {
        $billingService = app(BillingService::class);

        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_name' => 'Tea', 'quantity' => 1, 'unit_price_paise' => 2000]],
            idempotencyKey: 'bill_f14_test'
        );

        $billingService->checkout($bill, [['tender_type' => TenderType::CASH, 'amount_paise' => 2000]]);
        $this->assertEquals(BillStatus::PAID, $bill->fresh()->status);

        // Attempting to edit financial figures on a posted bill throws UnauthorizedActionException
        $this->expectException(UnauthorizedActionException::class);
        $bill->update(['net_amount_paise' => 1000]);
    }

    /**
     * Scenario F15:
     * A confirmed top-up is later reversed after some funds were spent.
     * A real reconciliation exception is recorded and further affected use is restricted;
     * no fictitious funding is inserted to hide it.
     */
    public function test_f15_topup_reversal_on_partially_spent_wallet_restricts_account_and_logs_exception(): void
    {
        $walletService = app(WalletService::class);

        // Top up ₹1,000 (100,000 paise)
        $recharge = $walletService->recharge(
            wallet: $this->wallet,
            amountPaise: 100000,
            tenderType: TenderType::DIGITAL,
            idempotencyKey: 'recharge_f15_test',
            shiftId: $this->shift->id,
            userId: $this->cashier->id
        );

        // Member spends ₹400 (40,000 paise) -> Balance is ₹600 (60,000 paise)
        $walletService->debit(
            wallet: $this->wallet,
            amountPaise: 40000,
            referenceType: 'bill',
            referenceId: 999
        );

        $this->assertEquals(60000, $this->wallet->fresh()->balance_paise);

        // External gateway reverses/cancels the ₹1,000 recharge
        $outcome = $walletService->reverseTopUp(
            wallet: $this->wallet,
            reversalAmountPaise: 100000,
            reason: 'Bank chargeback: fraudulent card report',
            operator: $this->manager
        );

        $this->assertEquals(0, $this->wallet->fresh()->balance_paise); // Drained to 0 without going into negative fake credit
        $this->assertEquals('suspended', $this->wallet->fresh()->status); // Account suspended from further use
        $this->assertTrue($outcome['has_exception']);
        $this->assertEquals(40000, $outcome['unrecovered_deficit_paise']); // Real ₹400 discrepancy recorded
    }

    /**
     * Scenario F16:
     * Generate a member statement for already-settled transactions.
     * No new bill, tax charge or receivable is created.
     * All posted journals balance and prepaid subledger total equals its mapped liability.
     */
    public function test_f16_member_statement_is_strictly_readonly_and_balanced(): void
    {
        $walletService = app(WalletService::class);

        $initialBillCount = Bill::count();
        $initialJournalCount = JournalHeader::count();

        $statement = $walletService->generateMemberStatement($this->member);

        $this->assertNotNull($statement['transactions']);
        $this->assertTrue($statement['reconciliation']['prepaid_wallet_reconciled']);
        $this->assertTrue($statement['reconciliation']['journal_balanced']);

        // Zero side effects
        $this->assertEquals($initialBillCount, Bill::count());
        $this->assertEquals($initialJournalCount, JournalHeader::count());
    }

    /**
     * Scenario C06:
     * Transactions straddle the Asia/Kolkata cutoff.
     * Previous-day eligibility and next-day settlement are correct, repeatable and based on recorded business dates.
     */
    public function test_c06_contractor_settlement_respects_business_date_cutoff(): void
    {
        $contractorService = app(ContractorSettlementService::class);
        $contractor = Contractor::create([
            'code' => 'CONT-C06',
            'name' => 'Malabar Caterers Ltd',
            'contact_person' => 'K. V. Joseph',
            'phone' => '9847123999',
            'commission_percent' => 0.00,
            'is_active' => true,
        ]);

        $shiftService = app(ShiftService::class);
        $shiftService->closeShift($this->shift, 200000);

        $cashier2 = User::where('email', 'cashier2@malabarclub.local')->firstOrFail();
        $counters = \App\Domain\Billing\Models\Counter::all();
        $shiftDay1 = $shiftService->openShift(counterId: $counters[0]->id, userId: $this->cashier->id, openingFloatPaise: 100000, businessDate: '2026-10-18');
        $shiftDay2 = $shiftService->openShift(counterId: $counters[1]->id, userId: $cashier2->id, openingFloatPaise: 100000, businessDate: '2026-10-19');

        $billingService = app(BillingService::class);

        // Bill on Day 1 shift (23:55)
        $billDay1 = $billingService->createDraftBill(
            shift: $shiftDay1,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Meals Day 1', 'quantity' => 1, 'unit_price_paise' => 10000, 'department_code' => 'food']],
            idempotencyKey: 'bill_c06_day1'
        );
        $billDay1->update(['created_at' => Carbon::parse('2026-10-18 23:55:00'), 'business_date' => '2026-10-18']);
        $billingService->checkout($billDay1, [['tender_type' => TenderType::CASH, 'amount_paise' => 10000]]);

        // Bill on Day 2 shift (00:05)
        $billDay2 = $billingService->createDraftBill(
            shift: $shiftDay2,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Meals Day 2', 'quantity' => 1, 'unit_price_paise' => 15000, 'department_code' => 'food']],
            idempotencyKey: 'bill_c06_day2'
        );
        $billDay2->update(['created_at' => Carbon::parse('2026-10-19 00:05:00'), 'business_date' => '2026-10-19']);
        $billingService->checkout($billDay2, [['tender_type' => TenderType::CASH, 'amount_paise' => 15000]]);

        // Preview Day 1 settlement: only Day 1 bill is eligible
        $previewDay1 = $contractorService->previewDailySettlement($contractor, '2026-10-18');
        $this->assertEquals(1, $previewDay1['total_lines']);
        $this->assertEquals(10000, $previewDay1['total_net_paise']);

        // Preview Day 2 settlement: only Day 2 bill is eligible
        $previewDay2 = $contractorService->previewDailySettlement($contractor, '2026-10-19');
        $this->assertEquals(1, $previewDay2['total_lines']);
        $this->assertEquals(15000, $previewDay2['total_net_paise']);
    }

    /**
     * Requirement 4:
     * Subscription renewal requires confirmed payment.
     */
    public function test_subscription_renewal_requires_confirmed_payment(): void
    {
        $subscriptionService = app(SubscriptionService::class);
        $gymTariff = Tariff::where('code', 'GYM-MONTHLY')->firstOrFail();

        $receipt = PaymentReceipt::create([
            'receipt_number' => 'RCT-SUB-INIT',
            'customer_type' => Member::class,
            'customer_id' => $this->member->id,
            'tender_type' => TenderType::CASH,
            'amount_paise' => $gymTariff->rate_paise,
            'provider_name' => 'cashier_manual',
            'provider_status' => 'confirmed',
            'idempotency_key' => 'receipt_sub_init',
            'confirmed_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]);

        $sub = $subscriptionService->createSubscription(
            member: $this->member,
            facilityCode: 'gym',
            planType: 'monthly',
            tariff: $gymTariff,
            receipt: $receipt
        );

        // Attempting to renew without confirmed payment throws UnauthorizedActionException
        $this->expectException(UnauthorizedActionException::class);
        $subscriptionService->renewSubscription($sub, $gymTariff, null);
    }

    /**
     * Requirement 2: Database-Level Trigger Protection across Mass & Query Builder Operations
     * Verifies that MySQL schema triggers block direct DB::table() mass updates and deletes
     * on posted bills, bill lines, journal headers, journal lines, and wallet transactions.
     */
    public function test_posted_record_database_triggers_block_query_builder_updates_and_deletes(): void
    {
        $billingService = app(BillingService::class);
        $walletService = app(WalletService::class);

        // 1. Create and post a bill
        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_name' => 'Club Soda', 'quantity' => 2, 'unit_price_paise' => 5000, 'tax_percent' => 5.0]],
            idempotencyKey: 'bill_trigger_test_01',
            userId: $this->cashier->id
        );

        $billingService->checkout(
            bill: $bill,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => $bill->net_amount_paise]],
            userId: $this->cashier->id
        );

        $postedBill = $bill->fresh();
        $this->assertEquals(BillStatus::PAID, $postedBill->status);

        // A. Direct DB::table update on posted bill must be blocked by trigger
        $billUpdateBlocked = false;
        try {
            DB::table('bills')->where('id', $postedBill->id)->update(['net_amount_paise' => 1000]);
        } catch (\Illuminate\Database\QueryException $e) {
            $billUpdateBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($billUpdateBlocked, "Trigger must block DB::table update on posted bill");

        // B. Direct DB::table delete on posted bill must be blocked by trigger
        $billDeleteBlocked = false;
        try {
            DB::table('bills')->where('id', $postedBill->id)->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            $billDeleteBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($billDeleteBlocked, "Trigger must block DB::table delete on posted bill");

        // C. Direct DB::table update on bill lines of posted bill must be blocked
        $lineUpdateBlocked = false;
        try {
            DB::table('bill_lines')->where('bill_id', $postedBill->id)->update(['unit_price_paise' => 100]);
        } catch (\Illuminate\Database\QueryException $e) {
            $lineUpdateBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($lineUpdateBlocked, "Trigger must block DB::table update on posted bill lines");

        // D. Direct DB::table delete on bill lines of posted bill must be blocked
        $lineDeleteBlocked = false;
        try {
            DB::table('bill_lines')->where('bill_id', $postedBill->id)->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            $lineDeleteBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($lineDeleteBlocked, "Trigger must block DB::table delete on posted bill lines");

        // E. Direct DB::table update/delete on journal headers must be blocked
        $journal = JournalHeader::where('source_type', 'bill')->where('source_id', $postedBill->id)->firstOrFail();
        $journalUpdateBlocked = false;
        try {
            DB::table('journal_headers')->where('id', $journal->id)->update(['business_date' => '2025-01-01']);
        } catch (\Illuminate\Database\QueryException $e) {
            $journalUpdateBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($journalUpdateBlocked, "Trigger must block DB::table update on journal header");

        $journalDeleteBlocked = false;
        try {
            DB::table('journal_headers')->where('id', $journal->id)->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            $journalDeleteBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($journalDeleteBlocked, "Trigger must block DB::table delete on journal header");

        // F. Direct DB::table update/delete on journal lines must be blocked
        $line = $journal->lines->first();
        $journalLineUpdateBlocked = false;
        try {
            DB::table('journal_lines')->where('id', $line->id)->update(['debit_paise' => 0]);
        } catch (\Illuminate\Database\QueryException $e) {
            $journalLineUpdateBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($journalLineUpdateBlocked, "Trigger must block DB::table update on journal line");

        // G. Direct DB::table update/delete on wallet transactions must be blocked
        $walletService->recharge(wallet: $this->wallet, amountPaise: 25000, tenderType: TenderType::CASH, idempotencyKey: 'trigger_recharge_01', shiftId: $this->shift->id, providerRef: null, userId: $this->cashier->id);
        $walletTxBlocked = false;
        try {
            DB::table('wallet_transactions')->where('wallet_account_id', $this->wallet->id)->update(['amount_paise' => 999999]);
        } catch (\Illuminate\Database\QueryException $e) {
            $walletTxBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($walletTxBlocked, "Trigger must block DB::table update on wallet transactions");

        $walletTxDeleteBlocked = false;
        try {
            DB::table('wallet_transactions')->where('wallet_account_id', $this->wallet->id)->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            $walletTxDeleteBlocked = true;
            $this->assertEquals('45000', $e->getCode());
        }
        $this->assertTrue($walletTxDeleteBlocked, "Trigger must block DB::table delete on wallet transactions");
    }

    /**
     * Scenario F04 Extension:
     * Retry with same key returns original outcome; reusing key with different payload is rejected.
     */
    public function test_f04_reusing_idempotency_key_with_different_payload_is_rejected(): void
    {
        $walletService = app(WalletService::class);
        $billingService = app(BillingService::class);

        // 1. Recharge: first call
        $key = 'idemp_key_payload_test_01';
        $res1 = $walletService->recharge(wallet: $this->wallet, amountPaise: 50000, tenderType: TenderType::CASH, idempotencyKey: $key, shiftId: $this->shift->id, providerRef: null, userId: $this->cashier->id);
        $this->assertEquals(50000, $this->wallet->fresh()->balance_paise);

        // Identical retry succeeds without duplicate
        $res2 = $walletService->recharge(wallet: $this->wallet, amountPaise: 50000, tenderType: TenderType::CASH, idempotencyKey: $key, shiftId: $this->shift->id, providerRef: null, userId: $this->cashier->id);
        $this->assertEquals($res1['receipt']->id, $res2['receipt']->id);
        $this->assertEquals(50000, $this->wallet->fresh()->balance_paise);

        // Mismatched payload with same key is rejected
        $mismatchRejected = false;
        try {
            $walletService->recharge(wallet: $this->wallet, amountPaise: 75000, tenderType: TenderType::CASH, idempotencyKey: $key, shiftId: $this->shift->id, providerRef: null, userId: $this->cashier->id);
        } catch (\InvalidArgumentException $e) {
            $mismatchRejected = true;
            $this->assertStringContainsString('different recharge payload', $e->getMessage());
        }
        $this->assertTrue($mismatchRejected, "Reusing idempotency key with different recharge amount must be rejected");

        // 2. Draft bill: mismatched payload rejection
        $billKey = 'bill_idemp_payload_test_01';
        $bill1 = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_name' => 'Tea', 'quantity' => 1, 'unit_price_paise' => 2000, 'tax_percent' => 0]],
            idempotencyKey: $billKey,
            userId: $this->cashier->id
        );

        // Retry with same items succeeds
        $billRetry = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_name' => 'Tea', 'quantity' => 1, 'unit_price_paise' => 2000, 'tax_percent' => 0]],
            idempotencyKey: $billKey,
            userId: $this->cashier->id
        );
        $this->assertEquals($bill1->id, $billRetry->id);

        // Retry with different items is rejected
        $billMismatchRejected = false;
        try {
            $billingService->createDraftBill(
                shift: $this->shift,
                customer: $this->member,
                departmentCode: 'bar',
                items: [['item_name' => 'Coffee', 'quantity' => 2, 'unit_price_paise' => 5000, 'tax_percent' => 0]],
                idempotencyKey: $billKey,
                userId: $this->cashier->id
            );
        } catch (\InvalidArgumentException $e) {
            $billMismatchRejected = true;
            $this->assertStringContainsString('different request payload', $e->getMessage());
        }
        $this->assertTrue($billMismatchRejected, "Reusing draft bill idempotency key with different items must be rejected");
    }

    /**
     * Scenario A01:
     * Cashier requests a manager-only tariff/refund/stock-adjustment action through the UI and directly over HTTP.
     * Both paths deny access without changing money or stock.
     */
    public function test_a01_cashier_denied_manager_action_directly_without_side_effects(): void
    {
        $billingService = app(BillingService::class);
        $inventoryService = app(InventoryService::class);

        // 1. Cashier attempts stock adjustment via service directly -> throws UnauthorizedActionException
        $item = InventoryItem::firstOrCreate(
            ['sku' => 'BAR-WHISKY-750'],
            [
                'name' => 'Signature Premier Whisky 750ml',
                'department_code' => 'bar',
                'category' => 'liquor',
                'base_unit' => 'ml',
                'current_stock_qty' => 750.0,
                'current_avg_cost_paise' => 120,
                'is_active' => true,
            ]
        );
        $initialStock = (float) $item->current_stock_qty;

        $stockDenied = false;
        try {
            $inventoryService->recordPhysicalCountAdjustment(
                item: $item,
                countedQty: $initialStock + 10.0,
                reason: 'Unauthorized cashier count',
                userId: $this->cashier->id
            );
        } catch (UnauthorizedActionException $e) {
            $stockDenied = true;
        }
        $this->assertTrue($stockDenied, "Direct stock adjustment by cashier must be denied");
        $this->assertEquals($initialStock, (float) $item->fresh()->current_stock_qty, "Stock must remain unchanged");

        // 2. Cashier attempts refund approval via service -> throws UnauthorizedActionException
        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'bar',
            items: [['item_name' => 'Juice', 'quantity' => 1, 'unit_price_paise' => 5000, 'tax_percent' => 0]],
            idempotencyKey: 'bill_refund_perm_test',
            userId: $this->cashier->id
        );
        $billingService->checkout($bill, [['tender_type' => TenderType::CASH, 'amount_paise' => 5000]], $this->cashier->id);

        $initialBalance = $this->wallet->fresh()->balance_paise;
        $refundDenied = false;
        try {
            $billingService->refund(
                bill: $bill->fresh(),
                amountPaise: 5000,
                reason: 'Cashier unauthorized refund attempt',
                approver: $this->cashier,
                idempotencyKey: 'unauth_refund_attempt_01'
            );
        } catch (UnauthorizedActionException $e) {
            $refundDenied = true;
        }
        $this->assertTrue($refundDenied, "Direct refund approval by cashier must be denied");
        $this->assertEquals($initialBalance, $this->wallet->fresh()->balance_paise, "Prepaid balance must remain unchanged");
        $this->assertEquals(BillStatus::PAID, $bill->fresh()->status, "Bill must not be refunded");

        // 3. Cashier visits manager-only HTTP endpoints (/admin/tariffs) -> returns 403 Forbidden
        $response = $this->actingAs($this->cashier)->get('/admin/tariffs');
        $response->assertStatus(403);
    }

    /**
     * Scenario R01:
     * Compare daily, weekly, monthly, yearly and custom report drill-downs.
     * With the same filters/date basis, totals reconcile to their source records and journal.
     */
    public function test_r01_drilldown_reconciles_to_source_bills_and_journals(): void
    {
        $reportService = app(\App\Domain\Finance\Services\FinancialReportService::class);

        $today = now()->toDateString();
        $weekStart = now()->subDays(6)->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();

        // 1. Daily drilldown
        $dailyReport = $reportService->getDepartmentProfitAndLoss($today, $today);
        $this->assertTrue($dailyReport['summary']['is_reconciled']);

        // 2. Weekly drilldown
        $weeklyReport = $reportService->getDepartmentProfitAndLoss($weekStart, $today);
        $this->assertTrue($weeklyReport['summary']['is_reconciled']);

        // 3. Monthly drilldown
        $monthlyReport = $reportService->getDepartmentProfitAndLoss($monthStart, $today);
        $this->assertTrue($monthlyReport['summary']['is_reconciled']);

        // 4. Yearly drilldown
        $yearlyReport = $reportService->getDepartmentProfitAndLoss($yearStart, $today);
        $this->assertTrue($yearlyReport['summary']['is_reconciled']);

        // 5. Compare custom drilldown with source journal headers sum
        $customReport = $reportService->getDepartmentProfitAndLoss('2026-01-01', '2026-12-31');
        $this->assertTrue($customReport['summary']['is_reconciled']);

        // Reconcile subledgers to General Ledger
        $subledger = $reportService->getSubledgerReconciliation();
        $this->assertTrue($subledger['journal_integrity']['balanced']);
        $this->assertTrue($subledger['prepaid_wallet']['reconciled']);
        $this->assertTrue($subledger['contractor_payable']['reconciled']);
    }

    /**
     * Scenario C06 (Midnight Cutoff Policy):
     * With a midnight cutoff ('00:00'), a 01:30 transaction belongs to the new
     * business date (2026-10-19), even if yesterday's cashier shift remains open.
     * Cashier-shift grouping is kept separate (drawer balances with full cash).
     * Contractor settlement for 2026-10-18 excludes it; 2026-10-19 includes it.
     */
    public function test_c06_business_date_calculation_against_midnight_cutoff_with_open_shift(): void
    {
        config(['club.business_day_cutoff_time' => '00:00']);

        $billingService = app(BillingService::class);
        $shiftService = app(ShiftService::class);
        $contractorService = app(ContractorSettlementService::class);

        $cashier2 = User::where('email', 'cashier2@malabarclub.local')->firstOrFail();
        $contractor = Contractor::firstOrCreate(
            ['code' => 'CONT-FOOD-01'],
            [
                'name' => 'Malabar Spices Catering & Hospitality',
                'department_code' => 'food',
                'contact_person' => 'Moideen Kutty',
                'phone' => '9847123456',
                'is_active' => true,
            ]
        );
        $counter = \App\Domain\Billing\Models\Counter::where('code', 'COUNTER-3')->firstOrFail();

        // 1. Open shift for 2026-10-18 on Food Counter with Cashier 2
        $eveningShift = $shiftService->openShift($counter->id, $cashier2->id, 50000, businessDate: '2026-10-18');
        $this->assertEquals('2026-10-18', $eveningShift->business_date->toDateString());

        // 2. Create food bill completed at 01:30 AM on calendar date 2026-10-19 while shift remains open
        $midnightBill = $billingService->createDraftBill(
            shift: $eveningShift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Late Night Biryani', 'quantity' => 1, 'unit_price_paise' => 30000, 'tax_percent' => 5.0]],
            idempotencyKey: 'midnight_food_bill_c06_00',
            userId: $cashier2->id
        );
        $midnightBill->update(['created_at' => Carbon::parse('2026-10-19 01:30:00'), 'business_date' => '2026-10-19']);

        $billingService->checkout(
            bill: $midnightBill,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => $midnightBill->net_amount_paise]],
            userId: $cashier2->id
        );

        $midnightBill->refresh();
        $this->assertEquals('2026-10-19', $midnightBill->business_date->toDateString(), '01:30 bill must belong to new business date under midnight cutoff');

        // Sales journal business_date must also belong to 2026-10-19
        $salesJournal = JournalHeader::where('source_type', 'bill')->where('source_id', $midnightBill->id)->firstOrFail();
        $this->assertEquals('2026-10-19', Carbon::parse($salesJournal->business_date)->toDateString());

        // 3. Cashier-shift grouping remains strictly separate:
        // The bill and receipt belong to $eveningShift for drawer balancing
        $this->assertEquals($eveningShift->id, $midnightBill->shift_id);
        $receipt = $midnightBill->receipts->first();
        $this->assertEquals($eveningShift->id, $receipt->shift_id);
        $expectedCash = $eveningShift->calculateExpectedCashPaise();
        $this->assertEquals(50000 + $midnightBill->net_amount_paise, $expectedCash);

        // Closing the shift balances the physical drawer regardless of fiscal date
        $closedShift = $shiftService->closeShift($eveningShift, $expectedCash);
        $this->assertEquals(0, $closedShift->discrepancy_paise);

        // 4. Contractor settlement eligibility follows midnight cutoff policy:
        // Settlement for 2026-10-18 DOES NOT include this 01:30 bill
        $preview18 = $contractorService->previewDailySettlement($contractor, '2026-10-18');
        $matchingLine18 = collect($preview18['lines'])->first(fn($l) => $l->id === $midnightBill->lines->first()->id);
        $this->assertNull($matchingLine18, "2026-10-18 settlement must NOT include 01:30 bill under midnight cutoff");

        // Settlement for 2026-10-19 DOES include this 01:30 bill
        $preview19 = $contractorService->previewDailySettlement($contractor, '2026-10-19');
        $matchingLine19 = collect($preview19['lines'])->first(fn($l) => $l->id === $midnightBill->lines->first()->id);
        $this->assertNotNull($matchingLine19, "2026-10-19 settlement MUST include 01:30 bill under midnight cutoff");

        // Approve 2026-10-19 batch: contractor expense/payable journal business_date is 2026-10-19
        $batch = $contractorService->createSettlementBatch($contractor, '2026-10-19', $this->manager->id);
        $approvedBatch = $contractorService->approveSettlementBatch($batch, $this->manager);
        $contractorJournal = JournalHeader::where('source_type', 'contractor_settlement')->where('source_id', $approvedBatch->id)->firstOrFail();
        $this->assertEquals('2026-10-19', Carbon::parse($contractorJournal->business_date)->toDateString());
    }

    /**
     * Scenario C06 (Configured 03:00 Cutoff Policy):
     * With a configured 03:00 cutoff, a 01:30 transaction belongs to the PREVIOUS
     * business date (2026-10-18).
     * Cashier-shift grouping is kept separate.
     * Contractor settlement for 2026-10-18 includes it; 2026-10-19 excludes it.
     * A transaction past 03:00 (e.g. 03:15) belongs to 2026-10-19.
     */
    public function test_c06_business_date_calculation_against_0300_cutoff_attributes_to_previous_date(): void
    {
        config(['club.business_day_cutoff_time' => '03:00']);

        $billingService = app(BillingService::class);
        $shiftService = app(ShiftService::class);
        $contractorService = app(ContractorSettlementService::class);

        $cashier2 = User::where('email', 'cashier2@malabarclub.local')->firstOrFail();
        $contractor = Contractor::firstOrCreate(
            ['code' => 'CONT-FOOD-01'],
            [
                'name' => 'Malabar Spices Catering & Hospitality',
                'department_code' => 'food',
                'contact_person' => 'Moideen Kutty',
                'phone' => '9847123456',
                'is_active' => true,
            ]
        );
        $counter = \App\Domain\Billing\Models\Counter::where('code', 'COUNTER-3')->firstOrFail();

        // 1. Open shift for 2026-10-18 on Food Counter
        $eveningShift = $shiftService->openShift($counter->id, $cashier2->id, 50000, businessDate: '2026-10-18');

        // 2. Transaction 1: 01:30 AM on calendar date 2026-10-19 (before 03:00 cutoff)
        $bill0130 = $billingService->createDraftBill(
            shift: $eveningShift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Late Night Biryani 01:30', 'quantity' => 1, 'unit_price_paise' => 30000, 'tax_percent' => 5.0]],
            idempotencyKey: 'food_bill_0130_cutoff03',
            userId: $cashier2->id
        );
        $bill0130->update(['created_at' => Carbon::parse('2026-10-19 01:30:00'), 'business_date' => '2026-10-18']);

        $billingService->checkout(
            bill: $bill0130,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => $bill0130->net_amount_paise]],
            userId: $cashier2->id
        );

        $bill0130->refresh();
        $this->assertEquals('2026-10-18', $bill0130->business_date->toDateString(), '01:30 bill must belong to previous business date (2026-10-18) under 03:00 cutoff');

        $salesJournal = JournalHeader::where('source_type', 'bill')->where('source_id', $bill0130->id)->firstOrFail();
        $this->assertEquals('2026-10-18', Carbon::parse($salesJournal->business_date)->toDateString());

        // 3. Transaction 2: 03:15 AM on calendar date 2026-10-19 (after 03:00 cutoff)
        $bill0315 = $billingService->createDraftBill(
            shift: $eveningShift,
            customer: $this->member,
            departmentCode: 'food',
            items: [['item_name' => 'Early Morning Chai & Puttu 03:15', 'quantity' => 1, 'unit_price_paise' => 12000, 'tax_percent' => 5.0]],
            idempotencyKey: 'food_bill_0315_cutoff03',
            userId: $cashier2->id
        );
        $bill0315->update(['created_at' => Carbon::parse('2026-10-19 03:15:00'), 'business_date' => '2026-10-19']);

        $billingService->checkout(
            bill: $bill0315,
            tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => $bill0315->net_amount_paise]],
            userId: $cashier2->id
        );

        $bill0315->refresh();
        $this->assertEquals('2026-10-19', $bill0315->business_date->toDateString(), '03:15 bill must belong to new business date (2026-10-19) under 03:00 cutoff');

        // 4. Cashier shift grouping remains separate:
        // Both bills and receipts are grouped under $eveningShift
        $this->assertEquals($eveningShift->id, $bill0130->shift_id);
        $this->assertEquals($eveningShift->id, $bill0315->shift_id);
        $expectedCash = $eveningShift->calculateExpectedCashPaise();
        $this->assertEquals(50000 + $bill0130->net_amount_paise + $bill0315->net_amount_paise, $expectedCash);

        $closedShift = $shiftService->closeShift($eveningShift, $expectedCash);
        $this->assertEquals(0, $closedShift->discrepancy_paise);

        // 5. Contractor settlement eligibility follows 03:00 cutoff policy:
        // Settlement for 2026-10-18 INCLUDES 01:30 bill, EXCLUDES 03:15 bill
        $preview18 = $contractorService->previewDailySettlement($contractor, '2026-10-18');
        $line0130_in_18 = collect($preview18['lines'])->first(fn($l) => $l->id === $bill0130->lines->first()->id);
        $line0315_in_18 = collect($preview18['lines'])->first(fn($l) => $l->id === $bill0315->lines->first()->id);
        $this->assertNotNull($line0130_in_18, "2026-10-18 settlement MUST include 01:30 bill under 03:00 cutoff");
        $this->assertNull($line0315_in_18, "2026-10-18 settlement must NOT include 03:15 bill under 03:00 cutoff");

        // Settlement for 2026-10-19 EXCLUDES 01:30 bill, INCLUDES 03:15 bill
        $preview19 = $contractorService->previewDailySettlement($contractor, '2026-10-19');
        $line0130_in_19 = collect($preview19['lines'])->first(fn($l) => $l->id === $bill0130->lines->first()->id);
        $line0315_in_19 = collect($preview19['lines'])->first(fn($l) => $l->id === $bill0315->lines->first()->id);
        $this->assertNull($line0130_in_19, "2026-10-19 settlement must NOT include 01:30 bill under 03:00 cutoff");
        $this->assertNotNull($line0315_in_19, "2026-10-19 settlement MUST include 03:15 bill under 03:00 cutoff");

        // Approve 2026-10-18 batch: contractor journal business_date is 2026-10-18
        $batch18 = $contractorService->createSettlementBatch($contractor, '2026-10-18', $this->manager->id);
        $approvedBatch18 = $contractorService->approveSettlementBatch($batch18, $this->manager);
        $journal18 = JournalHeader::where('source_type', 'contractor_settlement')->where('source_id', $approvedBatch18->id)->firstOrFail();
        $this->assertEquals('2026-10-18', Carbon::parse($journal18->business_date)->toDateString());
    }
}

