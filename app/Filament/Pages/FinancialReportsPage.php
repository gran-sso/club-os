<?php

namespace App\Filament\Pages;

use App\Domain\Billing\Models\Counter;
use App\Domain\Finance\Services\FinancialReportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Audit Ledger';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Executive Reports Hub';

    protected static ?string $title = 'Club Management Reporting Hub';

    protected static ?string $slug = 'financial-reports';

    protected string $view = 'filament.pages.financial-reports';

    public string $activeTab = 'sales';
    public string $startDate = '';
    public string $endDate = '';
    public ?string $departmentFilter = null;
    public ?int $counterFilter = null;

    // Report Datasets
    public array $salesReport = [];
    public array $incomeExpenditureReport = [];
    public array $cashReconReport = [];
    public array $contractorReport = [];
    public array $barStockReport = [];
    public array $sportsReport = [];
    public array $payablesReport = [];
    public array $pnl = [];
    public array $recon = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant', 'auditor']) || $user->hasPermissionTo('reports.view')));
    }

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
        $this->refreshReports();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->refreshReports();
    }

    public function setPreset(string $preset): void
    {
        $today = now();

        match ($preset) {
            'today' => [
                $this->startDate = $today->toDateString(),
                $this->endDate = $today->toDateString(),
            ],
            'yesterday' => [
                $this->startDate = $today->copy()->subDay()->toDateString(),
                $this->endDate = $today->copy()->subDay()->toDateString(),
            ],
            'this_week' => [
                $this->startDate = $today->copy()->startOfWeek()->toDateString(),
                $this->endDate = $today->copy()->endOfWeek()->toDateString(),
            ],
            'this_month' => [
                $this->startDate = $today->copy()->startOfMonth()->toDateString(),
                $this->endDate = $today->copy()->endOfMonth()->toDateString(),
            ],
            'last_month' => [
                $this->startDate = $today->copy()->subMonth()->startOfMonth()->toDateString(),
                $this->endDate = $today->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
            'fiscal_year' => [
                $this->startDate = ($today->month >= 4
                    ? Carbon::create($today->year, 4, 1)->toDateString()
                    : Carbon::create($today->year - 1, 4, 1)->toDateString()),
                $this->endDate = ($today->month >= 4
                    ? Carbon::create($today->year + 1, 3, 31)->toDateString()
                    : Carbon::create($today->year, 3, 31)->toDateString()),
            ],
            default => null,
        };

        $this->refreshReports();
    }

    public function refreshReports(): void
    {
        $service = app(FinancialReportService::class);

        // Fetch reports according to active tab or load essentials
        switch ($this->activeTab) {
            case 'sales':
                $this->salesReport = $service->getDepartmentAndCounterSalesReport(
                    $this->startDate,
                    $this->endDate,
                    $this->departmentFilter,
                    $this->counterFilter
                );
                break;

            case 'income_expenditure':
                $this->incomeExpenditureReport = $service->getIncomeAndExpenditureReport($this->startDate, $this->endDate);
                break;

            case 'cash_recon':
                $this->cashReconReport = $service->getDailyCashAndDigitalReconciliation($this->startDate, $this->endDate);
                break;

            case 'contractor':
                $this->contractorReport = $service->getFoodContractorSettlementReport($this->startDate, $this->endDate);
                break;

            case 'bar_stock':
                $this->barStockReport = $service->getBarAndStockReport($this->startDate, $this->endDate);
                break;

            case 'sports':
                $this->sportsReport = $service->getSportsFacilityIncomeReport($this->startDate, $this->endDate);
                break;

            case 'payables':
                $this->payablesReport = $service->getOutstandingPayablesAndExceptionsReport();
                break;

            case 'pnl':
            default:
                $this->pnl = $service->getDepartmentProfitAndLoss($this->startDate, $this->endDate);
                $this->recon = $service->getSubledgerReconciliation();
                break;
        }
    }

    public function exportCsv(): StreamedResponse
    {
        $service = app(FinancialReportService::class);
        $tab = $this->activeTab;
        $start = $this->startDate;
        $end = $this->endDate;
        $filename = "malabar_club_report_{$tab}_{$start}_to_{$end}.csv";

        return response()->streamDownload(function () use ($service, $tab, $start, $end) {
            $handle = fopen('php://output', 'w');

            // BOM for UTF-8 compatibility in Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['MALABAR CLUB — EXECUTIVE REPORTING HUB']);
            fputcsv($handle, ['Report Type', strtoupper(str_replace('_', ' ', $tab))]);
            fputcsv($handle, ['Period', "{$start} to {$end}"]);
            fputcsv($handle, ['Generated At', now()->toDateTimeString() . ' IST']);
            fputcsv($handle, []);

            switch ($tab) {
                case 'sales':
                    $data = $service->getDepartmentAndCounterSalesReport($start, $end);
                    fputcsv($handle, ['DEPARTMENT-WISE SALES BREAKDOWN']);
                    fputcsv($handle, ['Department Code', 'Department Name', 'Bills Count', 'Gross Sales (₹)', 'Discounts (₹)', 'GST (₹)', 'Net Sales (₹)']);
                    foreach ($data['departments'] as $d) {
                        fputcsv($handle, [
                            $d['code'],
                            $d['name'],
                            $d['bills_count'],
                            number_format($d['gross_paise'] / 100, 2),
                            number_format($d['discount_paise'] / 100, 2),
                            number_format($d['tax_paise'] / 100, 2),
                            number_format($d['net_paise'] / 100, 2),
                        ]);
                    }
                    fputcsv($handle, []);
                    fputcsv($handle, ['BILLING COUNTER BREAKDOWN']);
                    fputcsv($handle, ['Counter ID', 'Counter Code', 'Counter Name', 'Bills Count', 'Net Sales (₹)']);
                    foreach ($data['counters'] as $c) {
                        fputcsv($handle, [
                            $c['id'],
                            $c['code'],
                            $c['name'],
                            $c['bills_count'],
                            number_format($c['net_paise'] / 100, 2),
                        ]);
                    }
                    break;

                case 'income_expenditure':
                    $data = $service->getIncomeAndExpenditureReport($start, $end);
                    fputcsv($handle, ['OPERATING INCOMES']);
                    fputcsv($handle, ['Revenue Stream', 'Amount (₹)']);
                    fputcsv($handle, ['Food Catering Turnover', number_format($data['incomes']['food_sales_paise'] / 100, 2)]);
                    fputcsv($handle, ['Bar & Beverages Turnover', number_format($data['incomes']['bar_sales_paise'] / 100, 2)]);
                    fputcsv($handle, ['Sports Facilities Income', number_format($data['incomes']['sports_facility_paise'] / 100, 2)]);
                    fputcsv($handle, ['Guest Rooms Accommodation', number_format($data['incomes']['accommodation_paise'] / 100, 2)]);
                    fputcsv($handle, ['Card Room Table Income', number_format($data['incomes']['card_room_paise'] / 100, 2)]);
                    fputcsv($handle, ['Membership Subscriptions & Dues', number_format($data['incomes']['membership_dues_paise'] / 100, 2)]);
                    fputcsv($handle, ['Total Operating Income', number_format($data['incomes']['total_income_paise'] / 100, 2)]);
                    fputcsv($handle, []);
                    fputcsv($handle, ['OPERATING EXPENDITURES']);
                    fputcsv($handle, ['Expenditure Stream', 'Amount (₹)']);
                    fputcsv($handle, ['Food Contractor Disbursements', number_format($data['expenditures']['contractor_payouts_paise'] / 100, 2)]);
                    fputcsv($handle, ['Bar Consumed Stock COGS', number_format($data['expenditures']['bar_cogs_paise'] / 100, 2)]);
                    fputcsv($handle, ['Operating Expenses Total', number_format($data['expenditures']['operating_expenses_paise'] / 100, 2)]);
                    fputcsv($handle, ['Total Operating Expenditure', number_format($data['expenditures']['total_expenditure_paise'] / 100, 2)]);
                    fputcsv($handle, []);
                    fputcsv($handle, ['NET OPERATING SURPLUS / (DEFICIT)', number_format($data['net_operating_surplus_paise'] / 100, 2)]);
                    break;

                case 'cash_recon':
                    $data = $service->getDailyCashAndDigitalReconciliation($start, $end);
                    fputcsv($handle, ['DAILY SHIFT & DRAWER RECONCILIATION']);
                    fputcsv($handle, ['Shift #', 'Date', 'Counter', 'Cashier', 'Status', 'Opening Float (₹)', 'Cash Sales (₹)', 'Digital Sales (₹)', 'Expected Cash (₹)', 'Closing Cash Counted (₹)', 'Discrepancy (₹)']);
                    foreach ($data['shifts'] as $s) {
                        fputcsv($handle, [
                            $s['shift_number'],
                            $s['business_date'],
                            $s['counter_name'],
                            $s['cashier_name'],
                            ucfirst($s['status']),
                            number_format($s['opening_float_paise'] / 100, 2),
                            number_format($s['cash_sales_paise'] / 100, 2),
                            number_format($s['digital_sales_paise'] / 100, 2),
                            number_format($s['expected_cash_paise'] / 100, 2),
                            number_format($s['closing_cash_counted_paise'] / 100, 2),
                            number_format($s['discrepancy_paise'] / 100, 2),
                        ]);
                    }
                    break;

                case 'contractor':
                    $data = $service->getFoodContractorSettlementReport($start, $end);
                    fputcsv($handle, ['FOOD CONTRACTOR SETTLEMENT BATCHES']);
                    fputcsv($handle, ['Batch #', 'Date', 'Contractor', 'Status', 'Food Gross (₹)', 'GST (₹)', 'Net Sales (₹)', 'Refund Adjustments (₹)', 'Net Payable (₹)', 'Paid (₹)', 'Residual Payable (₹)', 'Payment Ref / UTR', 'Approver']);
                    foreach ($data['batches'] as $b) {
                        fputcsv($handle, [
                            $b['batch_number'],
                            $b['business_date'],
                            $b['contractor_name'],
                            ucfirst($b['status']),
                            number_format($b['gross_paise'] / 100, 2),
                            number_format($b['tax_paise'] / 100, 2),
                            number_format($b['net_paise'] / 100, 2),
                            number_format($b['adjustments_paise'] / 100, 2),
                            number_format($b['net_payable_paise'] / 100, 2),
                            number_format($b['paid_amount_paise'] / 100, 2),
                            number_format($b['residual_payable_paise'] / 100, 2),
                            $b['payment_reference'],
                            $b['approved_by'],
                        ]);
                    }
                    break;

                case 'bar_stock':
                    $data = $service->getBarAndStockReport($start, $end);
                    fputcsv($handle, ['BAR & INVENTORY STOCK CONSUMPTION']);
                    fputcsv($handle, ['SKU', 'Item Name', 'Department', 'Category', 'Unit', 'Stock Qty', 'Avg Cost (₹)', 'Stock Asset Value (₹)', 'Period Sales Qty', 'Sales COGS (₹)', 'Wastage Qty', 'Wastage Loss (₹)', 'Status']);
                    foreach ($data['items'] as $item) {
                        fputcsv($handle, [
                            $item['sku'],
                            $item['name'],
                            strtoupper($item['department_code']),
                            $item['category'],
                            $item['base_unit'],
                            $item['current_stock_qty'],
                            number_format($item['avg_cost_paise'] / 100, 2),
                            number_format($item['stock_value_paise'] / 100, 2),
                            $item['period_sales_qty'],
                            number_format($item['period_sales_cost_paise'] / 100, 2),
                            $item['period_wastage_qty'],
                            number_format($item['period_wastage_cost_paise'] / 100, 2),
                            $item['is_low_stock'] ? 'LOW STOCK WARNING' : 'HEALTHY',
                        ]);
                    }
                    break;

                case 'sports':
                    $data = $service->getSportsFacilityIncomeReport($start, $end);
                    fputcsv($handle, ['SPORTS FACILITY INCOME & UTILIZATION']);
                    fputcsv($handle, ['Facility', 'Subscriptions Count', 'Monthly Plans', 'Annual Plans', 'Total Revenue (₹)']);
                    fputcsv($handle, ['Gymnasium & Health Club', $data['gym']['subscriptions_count'], $data['gym']['monthly_count'], $data['gym']['annual_count'], number_format($data['gym']['revenue_paise'] / 100, 2)]);
                    fputcsv($handle, ['Badminton Court Subscriptions', $data['badminton']['subscriptions_count'], $data['badminton']['monthly_count'], $data['badminton']['annual_count'], number_format($data['badminton']['subscription_revenue_paise'] / 100, 2)]);
                    fputcsv($handle, []);
                    fputcsv($handle, ['Badminton Court Booking Utilization']);
                    fputcsv($handle, ['Metric', 'Count / Value']);
                    fputcsv($handle, ['Total Court Slots Booked', $data['badminton']['total_court_slots_booked']]);
                    fputcsv($handle, ['Plan Covered Slots (Free)', $data['badminton']['plan_covered_slots_count']]);
                    fputcsv($handle, ['Paid Pay-Per-Play Slots', $data['badminton']['paid_court_slots_count']]);
                    fputcsv($handle, ['Court Slot Fees Collected', '₹' . number_format($data['badminton']['court_fees_collected_paise'] / 100, 2)]);
                    fputcsv($handle, ['Guest Sports Passes Collected', '₹' . number_format($data['guest_passes_paise'] / 100, 2)]);
                    fputcsv($handle, ['Total Sports Revenue', '₹' . number_format($data['summary']['total_sports_revenue_paise'] / 100, 2)]);
                    break;

                case 'payables':
                    $data = $service->getOutstandingPayablesAndExceptionsReport();
                    fputcsv($handle, ['OUTSTANDING PAYABLES & AUDIT RECONCILIATIONS']);
                    fputcsv($handle, ['Category', 'Status', 'Count / Amount']);
                    fputcsv($handle, ['Member Overdrawn Balances (Zero-Credit Rule)', $data['member_credit']['is_zero_credit_compliant'] ? 'COMPLIANT (0 Bad Debts)' : 'NON-COMPLIANT', $data['member_credit']['overdrawn_accounts_count']]);
                    fputcsv($handle, ['Active Prepaid Member Wallets Backing Liability', 'Active Liability', '₹' . number_format($data['member_credit']['total_wallet_liability_paise'] / 100, 2)]);
                    fputcsv($handle, ['Food Contractor Unsettled Payables', "{$data['contractor_payables']['unsettled_batches_count']} Batches", '₹' . number_format($data['contractor_payables']['total_residual_payable_paise'] / 100, 2)]);
                    fputcsv($handle, ['Digital Gateway Unposted Captures', "{$data['gateway_exceptions']['unposted_count']} Unposted", '₹' . number_format($data['gateway_exceptions']['total_unposted_paise'] / 100, 2)]);
                    fputcsv($handle, ['Upcoming Accommodation Pending Deposits', "{$data['accommodation_pending']['pending_rooms_count']} Rooms", '₹' . number_format($data['accommodation_pending']['pending_amount_paise'] / 100, 2)]);
                    fputcsv($handle, ['General Ledger Balance (Debits === Credits)', $data['general_ledger']['is_balanced'] ? 'PERFECTLY BALANCED' : 'VARIANCE', '₹' . number_format($data['general_ledger']['difference_paise'] / 100, 2)]);
                    break;

                case 'pnl':
                default:
                    $data = $service->getDepartmentProfitAndLoss($start, $end);
                    fputcsv($handle, ['DEPARTMENTAL PROFIT & LOSS STATEMENT']);
                    fputcsv($handle, ['Department Code', 'Department Name', 'Revenue (₹)', 'Cost / Expenses (₹)', 'Contribution Margin (₹)']);
                    foreach ($data['departments'] as $d) {
                        fputcsv($handle, [
                            $d['department_code'],
                            strtoupper($d['department_code']),
                            number_format($d['revenue_paise'] / 100, 2),
                            number_format($d['cost_paise'] / 100, 2),
                            number_format($d['net_margin_paise'] / 100, 2),
                        ]);
                    }
                    fputcsv($handle, []);
                    fputcsv($handle, ['OVERALL CLUB RESULT']);
                    fputcsv($handle, ['Total Departmental Margin', number_format($data['summary']['total_department_margin_paise'] / 100, 2)]);
                    fputcsv($handle, ['Unallocated Overhead Expenses', number_format($data['summary']['unallocated_margin_paise'] / 100, 2)]);
                    fputcsv($handle, ['Net Club Profit / (Loss)', number_format($data['summary']['overall_club_net_paise'] / 100, 2)]);
                    break;
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
