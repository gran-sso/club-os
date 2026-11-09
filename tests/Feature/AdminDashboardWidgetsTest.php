<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Tariff\Models\Facility;
use App\Filament\Widgets\ContractorAndShiftStatusWidget;
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
        $response->assertSee('Guest Rooms (101–105)');
        $response->assertSee('Indoor Badminton Court');
        $response->assertSee('Departmental Revenue');
        $response->assertSee('Graph View');
        $response->assertSee('Tabular View');
        $response->assertSee('Contractor &amp; Counter Shifts', false);
        $response->assertSee('Qualified Food Sales');
        $response->assertSee('System Integrity &amp; Tally Bridge', false);
        $response->assertSee('Recent Club Billing &amp; Operational Activity', false);
    }

    public function test_manager_sees_executive_dashboard(): void
    {
        $managerResponse = $this->actingAs($this->manager)->get('/admin');
        $managerResponse->assertStatus(200);
        $managerResponse->assertSee('Dashboard');
        $managerResponse->assertSee('Billed Sales');
    }

    public function test_frontline_reception_sees_facility_pulse_while_financial_widgets_respect_role(): void
    {
        $response = $this->actingAs($this->reception)->get('/admin');
        $response->assertStatus(200);

        // Reception should see the Front-Desk & Facilities Pulse
        $response->assertSee('Front-Desk &amp; Facilities Pulse', false);
        $response->assertSee('Guest Rooms (101–105)');
        $response->assertSee('Indoor Badminton Court');

        // Sensitive back-office financial summaries must NOT be visible to reception
        $this->assertFalse(ExecutiveStatsOverviewWidget::canView());
        $this->assertFalse(SystemIntegrityWidget::canView());
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
    }

    public function test_contractor_and_shift_widget_tracks_qualified_food_and_open_registers(): void
    {
        $this->actingAs($this->admin);

        $widget = new ContractorAndShiftStatusWidget();
        $data = $widget->getViewData();

        $this->assertArrayHasKey('todayFoodRupees', $data);
        $this->assertArrayHasKey('openShifts', $data);
        $this->assertArrayHasKey('staleShiftsCount', $data);
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
