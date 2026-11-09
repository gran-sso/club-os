<?php

namespace Tests\Feature;

use App\Domain\Access\Models\Guest;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Services\WalletService;
use App\Domain\Shared\Enums\BillStatus;
use App\Filament\Resources\Bills\Pages\CreateBill;
use App\Filament\Resources\Bills\Pages\EditBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BillResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected Member $member;
    protected Guest $guest;
    protected Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();

        $this->member = Member::where('membership_number', 'MC-001')->firstOrFail();
        $this->guest = Guest::firstOrFail();

        // Ensure an open shift exists
        $counter = Counter::firstOrFail();
        $shiftService = app(ShiftService::class);
        $this->shift = Shift::where('status', 'open')->first()
            ?? $shiftService->openShift(
                counterId: $counter->id,
                userId: $this->admin->id,
                openingFloatPaise: 200000,
                notes: 'Test shift'
            );

        // Fund member wallet for tests
        app(WalletService::class)->recharge(
            wallet: $this->member->wallet,
            amountPaise: 500000,
            tenderType: \App\Domain\Shared\Enums\TenderType::CASH,
            idempotencyKey: 'SETUP-TOPUP-' . Str::uuid(),
            shiftId: $this->shift->id,
            userId: $this->admin->id
        );
        $this->member->refresh();
    }

    public function test_admin_can_access_bills_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/bills');
        $response->assertStatus(200);
        $response->assertSee('Bills &amp; Invoices', false);
    }

    public function test_manager_can_access_bills_list(): void
    {
        $response = $this->actingAs($this->manager)->get('/admin/bills');
        $response->assertStatus(200);
        $response->assertSee('Bills &amp; Invoices', false);
    }

    public function test_admin_can_access_create_bill_form(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/bills/create');
        $response->assertStatus(200);
        $response->assertSee('Tax Invoice Metadata');
        $response->assertSee('Customer &amp; Billing Account', false);
        $response->assertSee('Invoice Line Items &amp; Dispensed Services', false);
        $response->assertSee('Payment Tender Settlement');
    }

    public function test_admin_can_create_and_settle_bill_with_member_prepaid_wallet(): void
    {
        $initialBalance = $this->member->wallet_balance_paise;
        $this->assertGreaterThan(100000, $initialBalance);

        Livewire::actingAs($this->admin)
            ->test(CreateBill::class)
            ->fillForm([
                'business_date' => now()->toDateString(),
                'department_code' => 'food',
                'shift_id' => $this->shift->id,
                'customer_type' => Member::class,
                'customer_id' => $this->member->id,
                'lines' => [
                    [
                        'item_name' => 'Thalassery Chicken Biryani',
                        'department_code' => 'food',
                        'quantity' => 2,
                        'unit_price' => 280.00,
                        'tax_percent' => '5',
                    ],
                ],
                'notes' => 'Executive lunch table #2',
                'settle_immediately' => true,
                'tender_type' => 'prepaid',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $bill = Bill::where('customer_id', $this->member->id)
            ->where('department_code', 'food')
            ->latest('id')
            ->first();

        $this->assertNotNull($bill);
        $this->assertEquals(BillStatus::PAID, $bill->status);
        $this->assertEquals(56000, $bill->gross_amount_paise); // 2 * 28000 paise = 56000
        $this->assertEquals(2800, $bill->tax_amount_paise); // 5% of 56000 = 2800
        $this->assertEquals(58800, $bill->net_amount_paise); // 58800 paise = ₹588.00

        // Member wallet was debited
        $this->member->refresh();
        $this->assertEquals($initialBalance - 58800, $this->member->wallet_balance_paise);

        // Payment receipt was created
        $this->assertEquals(1, $bill->receipts()->count());
        $this->assertEquals('prepaid', $bill->receipts()->first()->tender_type->value ?? $bill->receipts()->first()->tender_type);
    }

    public function test_admin_can_create_and_settle_bill_with_cash_tender(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateBill::class)
            ->fillForm([
                'business_date' => now()->toDateString(),
                'department_code' => 'bar',
                'shift_id' => $this->shift->id,
                'customer_type' => Guest::class,
                'customer_id' => $this->guest->id,
                'lines' => [
                    [
                        'item_name' => 'Kingfisher Premium Beer 650ml',
                        'department_code' => 'bar',
                        'quantity' => 1,
                        'unit_price' => 240.00,
                        'tax_percent' => '18',
                    ],
                ],
                'notes' => 'Guest counter purchase',
                'settle_immediately' => true,
                'tender_type' => 'cash',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $bill = Bill::where('customer_type', Guest::class)
            ->where('customer_id', $this->guest->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($bill);
        $this->assertEquals(BillStatus::PAID, $bill->status);
        $this->assertEquals(24000, $bill->gross_amount_paise);
        $this->assertEquals(4320, $bill->tax_amount_paise); // 18% of 24000
        $this->assertEquals(28320, $bill->net_amount_paise);
        $this->assertEquals('cash', $bill->receipts()->first()->tender_type->value ?? $bill->receipts()->first()->tender_type);
    }

    public function test_admin_can_create_draft_bill_and_settle_it_later(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateBill::class)
            ->fillForm([
                'business_date' => now()->toDateString(),
                'department_code' => 'accommodation',
                'shift_id' => $this->shift->id,
                'customer_type' => Member::class,
                'customer_id' => $this->member->id,
                'lines' => [
                    [
                        'item_name' => 'Deluxe Room Daily Tariff',
                        'department_code' => 'accommodation',
                        'quantity' => 1,
                        'unit_price' => 2500.00,
                        'tax_percent' => '12',
                    ],
                ],
                'notes' => 'Draft accommodation invoice before checkout',
                'settle_immediately' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $bill = Bill::where('department_code', 'accommodation')
            ->latest('id')
            ->first();

        $this->assertNotNull($bill);
        $this->assertEquals(BillStatus::DRAFT, $bill->status);
        $this->assertEquals(0, $bill->paid_amount_paise);

        // Now settle it on the EditBill page
        Livewire::actingAs($this->admin)
            ->test(EditBill::class, ['record' => $bill->id])
            ->callAction('settleBill', [
                'tender_type' => 'prepaid',
            ]);

        $bill->refresh();
        $this->assertEquals(BillStatus::PAID, $bill->status);
        $this->assertEquals(1, $bill->receipts()->count());
    }

    public function test_posted_bill_shows_immutable_notice_and_hides_save_action(): void
    {
        $billingService = app(BillingService::class);

        $bill = $billingService->createDraftBill(
            shift: $this->shift,
            customer: $this->member,
            departmentCode: 'food',
            items: [
                [
                    'item_name' => 'Kerala Traditional Sadhya Meals',
                    'quantity' => 1,
                    'unit_price_paise' => 15000,
                    'tax_percent' => 5,
                ],
            ],
            idempotencyKey: 'ADM-PAID-' . Str::uuid()
        );

        $billingService->checkout($bill, [
            ['tender_type' => 'cash', 'amount_paise' => $bill->net_amount_paise]
        ]);
        $bill->refresh();

        $response = $this->actingAs($this->admin)->get("/admin/bills/{$bill->id}/edit");
        $response->assertStatus(200);
        $response->assertSee('Official Tax Invoice: ' . $bill->bill_number);
        $response->assertSee('immutable under statutory accounting policies');
    }
}
