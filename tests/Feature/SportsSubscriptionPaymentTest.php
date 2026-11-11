<?php

namespace Tests\Feature;

use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentAllocation;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Facility\Exceptions\MembershipRequiredException;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Services\CourtBookingService;
use App\Domain\Facility\Services\SubscriptionService;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Models\RevenueRecognitionSchedule;
use App\Domain\Finance\Services\FinancialReportService;
use App\Domain\Finance\Services\RevenueRecognitionService;
use App\Domain\Integrations\Models\OutboxMessage;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Models\WalletTransaction;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Domain\Tariff\Models\Facility;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Subscriptions\Pages\CreateFacilitySubscription;
use App\Filament\Widgets\DepartmentRevenueChart;
use App\Filament\Widgets\ExecutiveStatsOverviewWidget;
use App\Livewire\CounterPos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SportsSubscriptionPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Member $member;
    protected Counter $counter;
    protected Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $this->shift = app(ShiftService::class)->openShift(
            counterId: $this->counter->id,
            userId: $this->admin->id,
            openingFloatPaise: 200000
        );
    }

    public function test_admin_cash_sports_subscription_collects_payment_posts_gl_updates_shift_and_reports(): void
    {
        $tariff = Tariff::where('code', 'BADMINTON-MONTHLY')->firstOrFail(); // ₹2,000 + 18% GST = ₹2,360
        $startsAt = now()->toDateString();
        $endsAt = now()->addMonth()->toDateString();

        Livewire::actingAs($this->admin)
            ->test(CreateFacilitySubscription::class)
            ->fillForm([
                'member_id' => $this->member->id,
                'facility_code' => 'badminton',
                'plan_type' => 'monthly',
                'tariff_id' => $tariff->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'total_fee_paise' => 2360.00,
                'status' => 'active',
                'tender_type' => 'cash',
                'shift_id' => $this->shift->id,
                'payment_reference' => 'SEC-CASH-001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var FacilitySubscription $sub */
        $sub = FacilitySubscription::where('member_id', $this->member->id)
            ->where('facility_code', 'badminton')
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals(236000, $sub->total_fee_paise);
        $this->assertNotNull($sub->payment_receipt_id);

        // Relationship fix verified
        $receipt = $sub->paymentReceipt;
        $this->assertNotNull($receipt);
        $this->assertEquals(TenderType::CASH, $receipt->tender_type);
        $this->assertEquals(236000, $receipt->amount_paise);
        $this->assertEquals($this->shift->id, $receipt->shift_id);
        $this->assertEquals('SEC-CASH-001', $receipt->provider_reference);

        // Authoritative Paid Bill + BillLine
        $bill = $sub->bill;
        $this->assertNotNull($bill);
        $this->assertEquals('sports', $bill->department_code);
        $this->assertEquals(BillStatus::PAID, $bill->status);
        $this->assertEquals(200000, $bill->gross_amount_paise);
        $this->assertEquals(36000, $bill->tax_amount_paise);
        $this->assertEquals(236000, $bill->net_amount_paise);
        $this->assertEquals(236000, $bill->paid_amount_paise);
        $this->assertStringNotContainsString('Guest', $bill->lines()->firstOrFail()->item_name);

        // Payment allocation & Shift expected cash
        $this->assertTrue(PaymentAllocation::where('payment_receipt_id', $receipt->id)->where('bill_id', $bill->id)->exists());
        $this->assertEquals(436000, $this->shift->fresh()->calculateExpectedCashPaise()); // ₹2,000 float + ₹2,360 cash

        // Balanced GL Journal: DR 1010 (236,000) -> CR 4040 (200,000) + CR 2030 (36,000)
        $journal = JournalHeader::where('source_type', 'bill')->where('source_id', $bill->id)->firstOrFail();
        $this->assertTrue($journal->isBalanced());
        $this->assertEquals(236000, $journal->totalDebitsPaise());

        $acct1010 = Account::where('code', '1010')->value('id');
        $acct4040 = Account::where('code', '4040')->value('id');
        $acct2030 = Account::where('code', '2030')->value('id');

        $this->assertEquals(236000, (int) $journal->lines()->where('account_id', $acct1010)->value('debit_paise'));
        $this->assertEquals(200000, (int) $journal->lines()->where('account_id', $acct4040)->value('credit_paise'));
        $this->assertEquals(36000, (int) $journal->lines()->where('account_id', $acct2030)->value('credit_paise'));

        // Outbox & Recognition Schedule
        $this->assertTrue(OutboxMessage::where('aggregate_type', 'Bill')->where('aggregate_id', $bill->id)->exists());
        $schedule = RevenueRecognitionSchedule::where('subscription_id', $sub->id)->firstOrFail();
        $this->assertEquals(236000, $schedule->total_amount_paise);

        // Running recognition must NOT double-post 4040 or debit 2010 for upfront bill-settled subscription
        app(RevenueRecognitionService::class)->recogniseForDate(now());
        $this->assertEquals('completed', $schedule->fresh()->status);

        // Financial reports & Subledger Reconciliation (no double counting!)
        $reportService = app(FinancialReportService::class);
        $recon = $reportService->getSubledgerReconciliation();
        $this->assertTrue($recon['prepaid_wallet']['reconciled']);

        $sportsReport = $reportService->getSportsFacilityIncomeReport(now()->toDateString(), now()->toDateString());
        $this->assertEquals(236000, $sportsReport['badminton']['subscription_revenue_paise']);
        $this->assertEquals(236000, $sportsReport['summary']['total_sports_revenue_paise']);

        $pnl = $reportService->getDepartmentProfitAndLoss(now()->toDateString(), now()->toDateString());
        $this->assertEquals(200000, $pnl['departments']['sports']['revenue_paise']);

        // Dashboard widgets show ₹2,360.00
        Livewire::actingAs($this->admin)
            ->test(ExecutiveStatsOverviewWidget::class)
            ->assertSee('₹2,360.00');

        Livewire::actingAs($this->admin)
            ->test(DepartmentRevenueChart::class)
            ->assertSee('Badminton & Sports')
            ->assertSee('₹2,360.00');

        // Member profile renders Receipt # & Tender
        Livewire::actingAs($this->admin)
            ->test(ViewMember::class, ['record' => $this->member->id])
            ->set('activeTab', 'facilities')
            ->assertSee($receipt->receipt_number)
            ->assertSee('Cash');
    }

    public function test_admin_inclusive_2000_bank_transfer_splits_tax_and_credits_bank_clearing_1020(): void
    {
        $tariff = Tariff::where('code', 'BADMINTON-MONTHLY')->firstOrFail(); // 18% GST configured

        Livewire::actingAs($this->admin)
            ->test(CreateFacilitySubscription::class)
            ->fillForm([
                'member_id' => $this->member->id,
                'facility_code' => 'badminton',
                'plan_type' => 'monthly',
                'tariff_id' => $tariff->id,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'total_fee_paise' => 2000.00, // Flat ₹2,000 inclusive entered by Admin
                'status' => 'active',
                'tender_type' => 'bank_transfer',
                'payment_reference' => 'NEFT-UTR-889900',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sub = FacilitySubscription::where('member_id', $this->member->id)->latest('id')->firstOrFail();
        $receipt = $sub->paymentReceipt;
        $bill = $sub->bill;

        $this->assertEquals(200000, $sub->total_fee_paise);
        $this->assertEquals(TenderType::BANK_TRANSFER, $receipt->tender_type);
        $this->assertNull($receipt->shift_id);
        $this->assertEquals('NEFT-UTR-889900', $receipt->provider_reference);
        $this->assertEquals(200000, $bill->net_amount_paise);
        $this->assertEquals(200000, $bill->gross_amount_paise + $bill->tax_amount_paise);

        $journal = JournalHeader::where('source_type', 'bill')->where('source_id', $bill->id)->firstOrFail();
        $this->assertTrue($journal->isBalanced());
        $acct1020 = Account::where('code', '1020')->value('id');
        $this->assertEquals(200000, (int) $journal->lines()->where('account_id', $acct1020)->value('debit_paise'));
    }

    public function test_prepaid_wallet_sports_subscription_debits_wallet_and_keeps_account_2010_reconciled(): void
    {
        $walletService = app(WalletService::class);
        $walletService->recharge(
            wallet: $this->member->wallet,
            amountPaise: 500000, // ₹5,000 top-up
            tenderType: TenderType::CASH,
            idempotencyKey: 'rcg_sub_prepaid_01',
            shiftId: $this->shift->id,
            userId: $this->admin->id
        );

        $gymTariff = Tariff::where('code', 'GYM-MONTHLY')->firstOrFail(); // ₹1,500 + 18% GST = ₹1,770

        Livewire::actingAs($this->admin)
            ->test(CreateFacilitySubscription::class)
            ->fillForm([
                'member_id' => $this->member->id,
                'facility_code' => 'gym',
                'plan_type' => 'monthly',
                'tariff_id' => $gymTariff->id,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'total_fee_paise' => 1770.00,
                'status' => 'active',
                'tender_type' => 'prepaid',
                'shift_id' => $this->shift->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals(323000, $this->member->wallet->fresh()->balance_paise);

        $sub = FacilitySubscription::where('member_id', $this->member->id)->where('facility_code', 'gym')->latest('id')->firstOrFail();
        $receipt = $sub->paymentReceipt;
        $this->assertEquals(TenderType::PREPAID, $receipt->tender_type);
        $this->assertNotNull($receipt->wallet_transaction_id);

        $walletTx = WalletTransaction::findOrFail($receipt->wallet_transaction_id);
        $this->assertEquals('spend', $walletTx->type);
        $this->assertEquals(-177000, $walletTx->amount_paise);

        // Critical: Wallet Sum === A/C 2010 Balance (no drift!)
        $recon = app(FinancialReportService::class)->getSubledgerReconciliation();
        $this->assertTrue($recon['prepaid_wallet']['reconciled']);
        $this->assertEquals(323000, $recon['prepaid_wallet']['subledger_total_paise']);
        $this->assertEquals(323000, $recon['prepaid_wallet']['general_ledger_paise']);

        // Collections Today stays ₹5,000 (cash recharge only — no double-count on wallet redemption)
        $cashDigitalCollections = (int) PaymentReceipt::whereDate('created_at', today())
            ->whereIn('tender_type', ['cash', 'digital'])
            ->sum('amount_paise');
        $this->assertEquals(500000, $cashDigitalCollections);
    }

    public function test_zero_credit_enforced_when_wallet_insufficient_or_base_membership_expired(): void
    {
        $gymTariff = Tariff::where('code', 'GYM-MONTHLY')->firstOrFail();

        // 1. Insufficient prepaid funds (wallet = ₹0, plan = ₹1,770)
        Livewire::actingAs($this->admin)
            ->test(CreateFacilitySubscription::class)
            ->fillForm([
                'member_id' => $this->member->id,
                'facility_code' => 'gym',
                'plan_type' => 'monthly',
                'tariff_id' => $gymTariff->id,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'total_fee_paise' => 1770.00,
                'status' => 'active',
                'tender_type' => 'prepaid',
            ])
            ->call('create');

        $this->assertEquals(0, FacilitySubscription::where('member_id', $this->member->id)->count());
        $this->assertEquals(0, Bill::where('customer_id', $this->member->id)->count());

        // 2. Expired base club membership cannot be enrolled
        $this->member->update(['valid_until' => now()->subDay()->toDateString()]);

        Livewire::actingAs($this->admin)
            ->test(CreateFacilitySubscription::class)
            ->fillForm([
                'member_id' => $this->member->id,
                'facility_code' => 'gym',
                'plan_type' => 'monthly',
                'tariff_id' => $gymTariff->id,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'total_fee_paise' => 1770.00,
                'status' => 'active',
                'tender_type' => 'cash',
            ])
            ->call('create');

        $this->assertEquals(0, FacilitySubscription::where('member_id', $this->member->id)->count());
        $this->assertEquals(0, PaymentReceipt::where('customer_id', $this->member->id)->count());

        // 3. Domain method rejects ₹0 fee on active paid tariff
        $this->member->update(['valid_until' => now()->addYear()->toDateString()]);
        $this->expectException(UnauthorizedActionException::class);
        app(SubscriptionService::class)->enrollWithPayment(
            member: $this->member,
            facilityCode: 'gym',
            planType: 'monthly',
            tariff: $gymTariff,
            totalFeePaise: 0,
            tenderType: TenderType::CASH,
            user: $this->admin
        );
    }

    public function test_counter_pos_sale_of_badminton_monthly_tariff_activates_member_subscription(): void
    {
        $tariff = Tariff::where('code', 'BADMINTON-MONTHLY')->firstOrFail();

        // Cashier/Admin selects member MC-001 at Counter POS, adds BADMINTON-MONTHLY, settles via Cash
        Livewire::actingAs($this->admin)
            ->test(CounterPos::class)
            ->set('searchQuery', 'MC-001')
            ->call('searchMember')
            ->call('addToCart', $tariff->id)
            ->call('checkout', 'cash')
            ->assertSet('errorMessage', null);

        $sub = FacilitySubscription::where('member_id', $this->member->id)
            ->where('facility_code', 'badminton')
            ->where('plan_type', 'monthly')
            ->first();

        $this->assertNotNull($sub);
        $this->assertEquals('active', $sub->status);
        $this->assertEquals(236000, $sub->total_fee_paise);
        $this->assertNotNull($sub->payment_receipt_id);

        // Plan immediately covers Indoor Badminton Court booking at ₹0
        $court = Facility::where('code', 'COURT-1')->firstOrFail();
        $booking = app(CourtBookingService::class)->bookCourt(
            $court,
            $this->member,
            now()->addDay()->toDateString(),
            '08:00:00',
            '09:00:00'
        );
        $this->assertTrue($booking->is_plan_covered);
    }

    public function test_guest_cannot_purchase_member_sports_subscription_tariff_at_pos(): void
    {
        $tariff = Tariff::where('code', 'BADMINTON-MONTHLY')->firstOrFail();
        $guest = app(GuestService::class)->registerGuest(
            personData: ['first_name' => 'WalkIn', 'last_name' => 'Visitor', 'phone' => '9847000999'],
            sponsorType: 'member',
            sponsorId: $this->member->id
        );

        $billingService = app(BillingService::class);
        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $guest,
            departmentCode: 'sports',
            items: [[
                'item_type' => 'tariff',
                'item_id' => $tariff->id,
                'item_name' => $tariff->name,
                'quantity' => 1,
                'unit_price_paise' => $tariff->rate_paise,
                'tax_percent' => $tariff->tax_percent,
                'department_code' => 'sports',
            ]],
            idempotencyKey: 'bill_guest_sports_sub_block',
            userId: $this->admin->id
        );

        try {
            $billingService->checkout(
                bill: $bill,
                tenders: [['tender_type' => TenderType::CASH, 'amount_paise' => $bill->net_amount_paise]],
                userId: $this->admin->id
            );
            $this->fail('Expected UnauthorizedActionException when issuing sports plan to a guest');
        } catch (UnauthorizedActionException $e) {
            $this->assertStringContainsString('Club Members', $e->getMessage());
        }

        // Rollback preserved: bill still draft, zero subscription/receipt created
        $this->assertEquals(BillStatus::DRAFT, $bill->fresh()->status);
        $this->assertEquals(0, FacilitySubscription::count());
        $this->assertEquals(0, PaymentReceipt::where('bill_id', $bill->id)->count());
    }

    public function test_member_query_param_prefills_create_subscription_form(): void
    {
        $url = route('filament.admin.resources.subscriptions.facility-subscriptions.create', ['member_id' => $this->member->id]);

        $response = $this->actingAs($this->admin)->get($url);
        $response->assertStatus(200);

        Livewire::actingAs($this->admin)
            ->withQueryParams(['member_id' => $this->member->id])
            ->test(CreateFacilitySubscription::class)
            ->assertFormFieldExists('tender_type')
            ->assertFormFieldExists('shift_id')
            ->assertFormSet([
                'member_id' => $this->member->id,
                'tender_type' => 'cash',
            ]);
    }
}
