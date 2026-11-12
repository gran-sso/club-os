<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\PosHeldTab;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Models\ShiftCashMovement;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Tariff\Models\Facility;
use App\Filament\Widgets\ContractorAndShiftStatusWidget;
use App\Filament\Widgets\CounterStationsPulseWidget;
use App\Filament\Widgets\DepartmentRevenueChart;
use App\Filament\Widgets\ExecutiveStatsOverviewWidget;
use App\Filament\Widgets\FrontDeskPulseWidget;
use App\Filament\Widgets\RecentActivityTableWidget;
use App\Filament\Widgets\SystemIntegrityWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@malabarclub.local')->firstOrFail();
        $this->manager = User::where('email', 'manager@malabarclub.local')->firstOrFail();
        $this->cashier = User::where('email', 'cashier1@malabarclub.local')->firstOrFail();
        $this->reception = User::where('email', 'reception@malabarclub.local')->firstOrFail();
    }

    public function test_admin_sees_complete_executive_dashboard_suite(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');
        $response->assertStatus(200);

        // Core Sections & Widgets
        $response->assertSee('Dashboard');
        $response->assertSee('Billed Sales');
        $response->assertSee('Collections Today');
        $response->assertSee('Prepaid Member Deposits');
        $response->assertSee('Front-Desk &amp; Facilities Pulse', false);
        $response->assertSee('Billing Counters &amp; Live Shifts', false);
        $response->assertSee('Guest Rooms (101–105)');
        $response->assertSee('Indoor Badminton Court');
        $response->assertSee('Departmental Revenue');
        $response->assertSee('Graph View');
        $response->assertSee('Tabular View');
        $response->assertSee('x-ref="canvas"', false);
        $response->assertSee('x-ref="backgroundColorElement"', false);
        $response->assertSee('x-ref="borderColorElement"', false);
        $response->assertSee('x-ref="gridColorElement"', false);
        $response->assertSee('x-ref="textColorElement"', false);
        $response->assertSee('x-ref="tooltipBackgroundColorElement"', false);
        $response->assertSee('x-ref="tooltipTextColorElement"', false);
        $response->assertSee('x-ref="tooltipBorderColorElement"', false);
        $response->assertSee('fi-wi-chart-bg-color', false);
        $response->assertSee('fi-wi-chart-frame-no-aspect-ratio', false);
        $response->assertSee('Food Contractor &amp; Settlements', false);
        $response->assertDontSee('Active POS Stations');
        $response->assertSee('Accounts Health &amp; Tally Sync', false);
        $response->assertSee('Recent Club Billing &amp; Operational Activity', false);
    }

    public function test_manager_sees_executive_dashboard(): void
    {
        $managerResponse = $this->actingAs($this->manager)->get('/admin');
        $managerResponse->assertStatus(200);
        $managerResponse->assertSee('Dashboard');
        $managerResponse->assertSee('Billed Sales');
        $managerResponse->assertSee('Billing Counters &amp; Live Shifts', false);
        $managerResponse->assertSee('Main Reception Counter');
    }

    public function test_frontline_reception_sees_facility_pulse_while_financial_widgets_respect_role(): void
    {
        $response = $this->actingAs($this->reception)->get('/admin');
        $response->assertStatus(200);

        // Reception should see the Front-Desk & Facilities Pulse
        $response->assertSee('Front-Desk &amp; Facilities Pulse', false);
        $response->assertSee('Guest Rooms (101–105)');
        $response->assertSee('Indoor Badminton Court');

        // Sensitive back-office financial & shift oversight widgets must NOT be visible to reception
        $this->assertFalse(ExecutiveStatsOverviewWidget::canView());
        $this->assertFalse(CounterStationsPulseWidget::canView());
        $this->assertFalse(SystemIntegrityWidget::canView());
        $response->assertDontSee('Billing Counters &amp; Live Shifts', false);
    }

    public function test_executive_stats_widget_computes_sales_collections_and_liability(): void
    {
        $this->actingAs($this->admin);

        $widget = new ExecutiveStatsOverviewWidget();
        $stats = invade($widget)->getStats();

        $this->assertCount(4, $stats);
        $this->assertStringContainsString('Billed Sales', $stats[0]->getLabel());
        $this->assertStringContainsString('Collections Today', $stats[1]->getLabel());
        $this->assertStringContainsString('Prepaid Member Deposits', $stats[2]->getLabel());
        $this->assertStringContainsString('Footfall', $stats[3]->getLabel());
    }

    public function test_front_desk_pulse_widget_loads_room_and_court_data(): void
    {
        $this->actingAs($this->admin);

        $widget = new FrontDeskPulseWidget();
        $this->assertEquals('full', invade($widget)->columnSpan);

        $data = $widget->getViewData();

        $this->assertArrayHasKey('roomStatuses', $data);
        $this->assertCount(5, $data['roomStatuses']); // Rooms 101 to 105
        $this->assertArrayHasKey('court', $data);
        $this->assertArrayHasKey('totalDailySlots', $data);
        $this->assertEquals(16, $data['totalDailySlots']);
    }

    public function test_department_revenue_widget_provides_graph_and_tabular_data(): void
    {
        $this->actingAs($this->admin);

        $widget = new DepartmentRevenueChart();
        $this->assertEquals('full', invade($widget)->columnSpan);

        $tableData = $widget->getTableData();
        $this->assertArrayHasKey('rows', $tableData);
        $this->assertCount(5, $tableData['rows']);
        $this->assertArrayHasKey('total_formatted', $tableData);
        $this->assertArrayHasKey('total_bills', $tableData);

        $chartData = invade($widget)->getData();
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(5, $chartData['datasets'][0]['data']);
        $this->assertArrayNotHasKey('borderColor', $chartData['datasets'][0]);
    }

    public function test_department_revenue_widget_renders_chart_sentinels_and_live_sales_correctly(): void
    {
        $this->actingAs($this->admin);

        // When no sales exist in today or past 7 days, widget shows the empty doughnut placeholder
        Bill::where('status', 'paid')->delete();
        $emptyResponse = $this->actingAs($this->admin)->get('/admin');
        $emptyResponse->assertStatus(200);
        $emptyResponse->assertSee('No Sales');
        $emptyResponse->assertSee('0 Paid Bills');

        // Record a ₹118.50 Bar bill today
        $counter = Counter::firstOrCreate(
            ['code' => 'BAR-01'],
            ['name' => 'Main Bar Counter', 'department_code' => 'bar']
        );
        $shift = Shift::create([
            'shift_number' => 'SH-WIDGET-TEST-01',
            'counter_id' => $counter->id,
            'user_id' => $this->admin->id,
            'business_date' => now()->toDateString(),
            'opened_at' => now(),
            'opening_float_paise' => 0,
            'expected_cash_paise' => 0,
            'status' => ShiftStatus::OPEN,
        ]);
        $member = Member::firstOrFail();

        Bill::create([
            'bill_number' => 'BILL-WIDGET-BAR-01',
            'shift_id' => $shift->id,
            'customer_type' => Member::class,
            'customer_id' => $member->id,
            'department_code' => 'bar',
            'status' => BillStatus::PAID,
            'business_date' => now()->toDateString(),
            'gross_amount_paise' => 11850,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 0,
            'net_amount_paise' => 11850,
            'paid_amount_paise' => 11850,
            'created_by_user_id' => $this->admin->id,
            'idempotency_key' => 'IDEM-WIDGET-BAR-01',
            'idempotency_hash' => hash('sha256', 'IDEM-WIDGET-BAR-01'),
        ]);

        $widget = new DepartmentRevenueChart();
        $chartData = invade($widget)->getData();
        $this->assertEquals(118.5, $chartData['datasets'][0]['data'][0]);

        $liveResponse = $this->actingAs($this->admin)->get('/admin');
        $liveResponse->assertStatus(200);
        $liveResponse->assertSee('1 Paid Bill');
        $liveResponse->assertDontSee('1 Paid Bills');
        $liveResponse->assertDontSee('>No Sales<', false);
    }

    public function test_contractor_settlement_widget_tracks_qualified_food_without_duplicate_shift_list(): void
    {
        $this->actingAs($this->admin);

        $widget = new ContractorAndShiftStatusWidget();
        $data = $widget->getViewData();

        $this->assertArrayHasKey('todayFoodRupees', $data);
        $this->assertArrayHasKey('latestBatch', $data);
        $this->assertArrayNotHasKey('openShifts', $data);
        $this->assertArrayNotHasKey('staleShiftsCount', $data);
    }

    public function test_counter_stations_pulse_widget_displays_open_stale_and_idle_stations_and_enforces_close_guards(): void
    {
        $counter1 = Counter::where('code', 'COUNTER-1')->firstOrFail();
        $counter2 = Counter::where('code', 'COUNTER-2')->firstOrFail();
        $cashier2 = User::where('email', 'cashier2@malabarclub.local')->firstOrFail();
        $member = Member::firstOrFail();

        $shiftService = app(ShiftService::class);
        // Open stale shift on Counter 1 (previous business date) with cash sales and float_in
        $staleShift = $shiftService->openShift(
            counterId: $counter1->id,
            userId: $this->cashier->id,
            openingFloatPaise: 200000,
            businessDate: today()->subDay()->toDateString()
        );
        $bill = Bill::create([
            'bill_number' => 'BILL-PULSE-01',
            'shift_id' => $staleShift->id,
            'customer_type' => Member::class,
            'customer_id' => $member->id,
            'department_code' => 'membership',
            'status' => BillStatus::PAID,
            'business_date' => today()->toDateString(),
            'gross_amount_paise' => 50000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 0,
            'net_amount_paise' => 50000,
            'paid_amount_paise' => 50000,
            'created_by_user_id' => $this->cashier->id,
            'idempotency_key' => 'IDEM-PULSE-01',
            'idempotency_hash' => hash('sha256', 'IDEM-PULSE-01'),
        ]);
        PaymentReceipt::create([
            'receipt_number' => 'RCP-PULSE-01',
            'shift_id' => $staleShift->id,
            'bill_id' => $bill->id,
            'customer_type' => Member::class,
            'customer_id' => $member->id,
            'tender_type' => 'cash',
            'amount_paise' => 50000,
            'provider_status' => 'confirmed',
            'idempotency_key' => 'IDEM-RCP-PULSE-01',
            'confirmed_at' => now(),
            'created_by_user_id' => $this->cashier->id,
        ]);
        ShiftCashMovement::create([
            'shift_id' => $staleShift->id,
            'type' => 'float_in',
            'amount_paise' => 30000,
            'notes' => 'Extra change',
            'created_by_user_id' => $this->admin->id,
        ]);

        // Open current shift on Counter 2 with 1 open running bar tab
        $barShift = $shiftService->openShift(
            counterId: $counter2->id,
            userId: $cashier2->id,
            openingFloatPaise: 100000
        );
        PosHeldTab::create([
            'shift_id' => $barShift->id,
            'counter_id' => $counter2->id,
            'tab_number' => 'TAB-PULSE-01',
            'customer_type' => 'member',
            'member_id' => $member->id,
            'customer_name' => $member->name,
            'customer_identifier' => $member->membership_number,
            'cart_items' => [['name' => 'Craft Beer', 'quantity' => 1, 'unit_price_paise' => 35000]],
            'gross_amount_paise' => 35000,
            'discount_amount_paise' => 0,
            'tax_amount_paise' => 0,
            'net_amount_paise' => 35000,
            'status' => 'open',
            'created_by' => $cashier2->id,
        ]);

        // Verify batched snapshot metrics
        $snapshots = $shiftService->getCounterStationSnapshots()->keyBy(fn (array $row) => $row['counter']->code);
        $this->assertTrue($snapshots['COUNTER-1']['is_open']);
        $this->assertTrue($snapshots['COUNTER-1']['is_stale']);
        $this->assertSame(280000, $snapshots['COUNTER-1']['expected_cash_paise']); // 2000 + 500 + 300
        $this->assertSame(50000, $snapshots['COUNTER-1']['billed_total_paise']);
        $this->assertSame(1, $snapshots['COUNTER-1']['paid_bills_count']);
        $this->assertSame(1, $snapshots['COUNTER-2']['open_tabs_count']);
        $this->assertFalse($snapshots['COUNTER-3']['is_open']);

        // Manager sees cards & action buttons
        $managerDashboard = $this->actingAs($this->manager)->get('/admin');
        $managerDashboard->assertStatus(200)
            ->assertSee($staleShift->shift_number)
            ->assertSee($barShift->shift_number)
            ->assertSee('Overdue')
            ->assertSee('/admin/shifts/create?counter_id=' . Counter::where('code', 'COUNTER-3')->value('id'));

        // Accountant (shifts.view only) sees cards read-only without Open/Close action controls
        $accountant = User::where('email', 'accountant@malabarclub.local')->firstOrFail();
        $this->actingAs($accountant);
        \Livewire\Livewire::test(CounterStationsPulseWidget::class)
            ->assertSee($staleShift->shift_number)
            ->assertDontSee('Close &amp; Reconcile', false)
            ->assertDontSee('+ Open Shift');

        // Closing Counter 2 shift with unsettled bar tab is blocked cleanly (modal & ShiftsTable)
        \Livewire\Livewire::actingAs($this->manager)
            ->test(CounterStationsPulseWidget::class)
            ->call('openCloseShiftModal', $barShift->id)
            ->assertSet('showCloseModal', false)
            ->assertNotified('Cannot Close Shift With Open Bar Tabs');

        $this->assertTrue($barShift->fresh()->isOpen());

        // Manager closes Counter 1 shift cleanly via dashboard card modal
        \Livewire\Livewire::actingAs($this->manager)
            ->test(CounterStationsPulseWidget::class)
            ->call('openCloseShiftModal', $staleShift->id)
            ->assertSet('showCloseModal', true)
            ->assertSet('closingExpectedCashPaise', 280000)
            ->set('countedCash', '2800.00')
            ->set('closeNotes', 'Reconciled by manager')
            ->call('confirmCloseShift')
            ->assertSet('showCloseModal', false);

        $staleShift->refresh();
        $this->assertSame('closed', $staleShift->status->value);
        $this->assertSame(0, $staleShift->discrepancy_paise);

        // Prefilled CreateShift route (?counter_id=...) selects the target idle counter cleanly
        $counter3 = Counter::where('code', 'COUNTER-3')->firstOrFail();
        \Livewire\Livewire::actingAs($this->manager)
            ->withQueryParams(['counter_id' => $counter3->id])
            ->test(\App\Filament\Resources\Shifts\Pages\CreateShift::class)
            ->assertFormSet(['counter_id' => $counter3->id]);
    }

    public function test_system_integrity_widget_verifies_gl_balancing_and_outbox(): void
    {
        $this->actingAs($this->admin);

        $widget = new SystemIntegrityWidget();
        $data = $widget->getViewData();

        $this->assertArrayHasKey('tallyStatus', $data);
        $this->assertArrayHasKey('outboxPending', $data);
        $this->assertArrayHasKey('isGlBalanced', $data);
        $this->assertTrue($data['isGlBalanced']); // Double-entry ledger must always balance
    }
}
