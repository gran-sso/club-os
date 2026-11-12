<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Services\PeriodClosingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhase5FinancialOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_shift_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/shifts/create');
        $response->assertStatus(200);
        $response->assertSee('Counter Station &amp; Cashier Assignment', false);
        $response->assertSee('Drawer Cash &amp; Shift Float (₹)', false);
    }

    public function test_admin_can_open_counter_shift(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $counter = Counter::firstOrCreate(['code' => 'COUNTER-TEST-99'], ['name' => 'Bar Counter 99', 'is_active' => true]);
        $cashier = User::where('email', 'manager@malabarclub.local')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Shifts\Pages\CreateShift::class)
            ->fillForm([
                'counter_id' => $counter->id,
                'user_id' => $cashier->id,
                'business_date' => '2026-11-08',
                'opening_float_paise' => 2000.00, // ₹2,000.00
                'status' => 'open',
                'notes' => 'Evening counter shift opening',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $shift = Shift::where('counter_id', $counter->id)
            ->where('user_id', $cashier->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($shift);
        $this->assertEquals(200000, $shift->opening_float_paise, '₹2,000.00 float must be stored as 200000 paise');
        $this->assertEquals('open', $shift->status->value);
    }

    public function test_admin_can_close_shift_via_table_action(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $counter = Counter::firstOrCreate(['code' => 'COUNTER-CLOSE-01'], ['name' => 'Test Close Counter', 'is_active' => true]);
        $cashier = User::where('email', 'accountant@malabarclub.local')->firstOrFail();

        $shift = app(\App\Domain\Billing\Services\ShiftService::class)->openShift(
            counterId: $counter->id,
            userId: $cashier->id,
            openingFloatPaise: 100000
        );

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Shifts\Pages\ListShifts::class)
            ->callTableAction('closeShift', $shift, [
                'counted_cash' => 1250.00, // ₹1,250.00
                'close_notes' => 'Drawer reconciled with ₹250 overage',
            ])
            ->assertHasNoTableActionErrors();

        $shift->refresh();
        $this->assertEquals('closed', $shift->status->value);
        $this->assertEquals(125000, $shift->closing_cash_counted_paise);
        $this->assertNotNull($shift->closed_at);
    }

    public function test_admin_cannot_create_duplicate_open_shift_for_same_counter_and_sees_validation_error(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $firstCashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $secondCashier = User::where('email', 'accountant@malabarclub.local')->firstOrFail();

        $shiftService = app(\App\Domain\Billing\Services\ShiftService::class);
        $existingShift = $shiftService->openShift(
            counterId: $counter->id,
            userId: $firstCashier->id,
            openingFloatPaise: 100000
        );

        $beforeCount = Shift::count();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Shifts\Pages\CreateShift::class)
            ->fillForm([
                'counter_id' => $counter->id,
                'user_id' => $secondCashier->id,
                'business_date' => now()->toDateString(),
                'opening_float_paise' => 1500.00,
                'status' => 'open',
                'notes' => 'Duplicate shift test',
            ])
            ->call('create')
            ->assertHasFormErrors(['counter_id'])
            ->assertSee($existingShift->shift_number);

        $this->assertSame($beforeCount, Shift::count(), 'Duplicate open shift must not be created');
        $this->assertSame(1, Shift::where('counter_id', $counter->id)->where('status', 'open')->count());

        // Editing the open shift (notes only) must still work cleanly without triggering create-only uniqueness rules.
        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Shifts\Pages\EditShift::class, ['record' => $existingShift->getRouteKey()])
            ->fillForm([
                'notes' => 'Updated handover note while shift open',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated handover note while shift open', $existingShift->fresh()->notes);

        // Once the open shift is closed, opening a new shift on the same counter succeeds.
        $shiftService->closeShift($existingShift, 100000, 'Reconciled');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Shifts\Pages\CreateShift::class)
            ->fillForm([
                'counter_id' => $counter->id,
                'user_id' => $secondCashier->id,
                'business_date' => now()->toDateString(),
                'opening_float_paise' => 1500.00,
                'status' => 'open',
                'notes' => 'Next shift after closing',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($beforeCount + 1, Shift::count());
    }

    public function test_admin_cannot_create_second_open_shift_for_same_staff_user(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $counter1 = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $counter2 = Counter::where('code', 'COUNTER-2')->firstOrFail();
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();

        $existingShift = app(\App\Domain\Billing\Services\ShiftService::class)->openShift(
            counterId: $counter1->id,
            userId: $cashier->id,
            openingFloatPaise: 100000
        );

        $beforeCount = Shift::count();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Shifts\Pages\CreateShift::class)
            ->fillForm([
                'counter_id' => $counter2->id,
                'user_id' => $cashier->id,
                'business_date' => now()->toDateString(),
                'opening_float_paise' => 1000.00,
                'status' => 'open',
            ])
            ->call('create')
            ->assertHasFormErrors(['user_id'])
            ->assertSee($existingShift->shift_number);

        $this->assertSame($beforeCount, Shift::count());
    }

    public function test_create_shift_catches_service_level_duplicate_exception_without_server_error(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $counter = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();

        $this->mock(\App\Domain\Billing\Services\ShiftService::class, function ($mock): void {
            $mock->shouldReceive('openShift')
                ->once()
                ->andThrow(new \InvalidArgumentException('Counter Main Reception Counter already has an open shift (#SFT-202611-00002).'));
        });

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Shifts\Pages\CreateShift::class)
            ->fillForm([
                'counter_id' => $counter->id,
                'user_id' => $cashier->id,
                'business_date' => now()->toDateString(),
                'opening_float_paise' => 1000.00,
                'status' => 'open',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Cannot Open Shift');

        $this->assertSame(0, Shift::where('counter_id', $counter->id)->count());
    }

    public function test_shift_service_rejects_duplicate_open_shifts_and_negative_float(): void
    {
        $counter1 = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $counter2 = Counter::where('code', 'COUNTER-2')->firstOrFail();
        $cashier1 = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $cashier2 = User::where('email', 'cashier2@malabarclub.local')->firstOrFail();

        $shiftService = app(\App\Domain\Billing\Services\ShiftService::class);
        $shift = $shiftService->openShift($counter1->id, $cashier1->id, 100000);

        try {
            $shiftService->openShift($counter1->id, $cashier2->id, 50000);
            $this->fail('Expected InvalidArgumentException when opening second shift on busy counter.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($shift->shift_number, $e->getMessage());
        }

        try {
            $shiftService->openShift($counter2->id, $cashier1->id, 50000);
            $this->fail('Expected InvalidArgumentException when opening second shift for busy cashier.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($shift->shift_number, $e->getMessage());
        }

        try {
            $shiftService->openShift($counter2->id, $cashier2->id, -100);
            $this->fail('Expected InvalidArgumentException for negative opening cash float.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('negative', strtolower($e->getMessage()));
        }

        $this->assertSame(1, Shift::where('status', 'open')->count());
    }

    public function test_admin_can_access_financial_period_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/financial-periods/create');
        $response->assertStatus(200);
        $response->assertSee('Financial Period Definition');
        $response->assertSee('Status &amp; Closing Lock Policy', false);
    }

    public function test_admin_can_create_and_close_financial_period(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\FinancialPeriods\Pages\CreateFinancialPeriod::class)
            ->fillForm([
                'period_code' => 'FY2026-M11-TEST',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-30',
                'status' => 'open',
                'notes' => 'November 2026 operating period',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $period = FinancialPeriod::where('period_code', 'FY2026-M11-TEST')->first();
        $this->assertNotNull($period);
        $this->assertEquals('open', $period->status);

        // Test closing via table action
        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\FinancialPeriods\Pages\ListFinancialPeriods::class)
            ->callTableAction('closePeriod', $period)
            ->assertHasNoTableActionErrors();

        $period->refresh();
        $this->assertEquals('closed', $period->status);
        $this->assertNotNull($period->closed_at);

        // Period lock must be active (Scenario R04)
        $closingService = app(PeriodClosingService::class);
        $this->assertTrue($closingService->isDateClosed('2026-11-15'));
    }

    public function test_admin_can_access_journal_create_page(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/journal-headers/create');
        $response->assertStatus(200);
        $response->assertSee('General Journal Voucher Details');
        $response->assertSee('Double-Entry Ledger Lines (Debits &amp; Credits)', false);
    }

    public function test_admin_can_post_balanced_manual_journal(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $expenseAcc = Account::where('code', '5030')->firstOrFail();
        $bankAcc = Account::where('code', '1020')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\JournalHeaders\Pages\CreateJournalHeader::class)
            ->fillForm([
                'business_date' => '2026-11-08',
                'source_type' => 'manual_journal',
                'description' => 'Annual bank processing fee adjustment',
                'lines' => [
                    [
                        'account_id' => $expenseAcc->id,
                        'debit_paise' => 350.00, // ₹350.00 debit
                        'credit_paise' => 0.00,
                        'department_code' => 'admin',
                        'narration' => 'Bank service charge',
                    ],
                    [
                        'account_id' => $bankAcc->id,
                        'debit_paise' => 0.00,
                        'credit_paise' => 350.00, // ₹350.00 credit
                        'department_code' => null,
                        'narration' => 'Clearing charge',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $journal = JournalHeader::where('description', 'Annual bank processing fee adjustment')->first();
        $this->assertNotNull($journal);
        $this->assertEquals(35000, $journal->totalDebits());
        $this->assertEquals(35000, $journal->totalCredits());
        $this->assertTrue($journal->isBalanced());
    }

    public function test_unbalanced_journal_submission_is_rejected(): void
    {
        $admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $expenseAcc = Account::where('code', '5030')->firstOrFail();
        $bankAcc = Account::where('code', '1020')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\JournalHeaders\Pages\CreateJournalHeader::class)
            ->fillForm([
                'business_date' => '2026-11-08',
                'source_type' => 'manual_journal',
                'description' => 'Unbalanced test entry',
                'lines' => [
                    [
                        'account_id' => $expenseAcc->id,
                        'debit_paise' => 500.00, // ₹500.00 debit
                        'credit_paise' => 0.00,
                    ],
                    [
                        'account_id' => $bankAcc->id,
                        'debit_paise' => 0.00,
                        'credit_paise' => 300.00, // ₹300.00 credit (Unbalanced by ₹200!)
                    ],
                ],
            ])
            ->call('create')
            ->assertHasErrors(['data.lines']);
    }
}

