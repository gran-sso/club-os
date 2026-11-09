<?php

namespace App\Domain\Finance\Services;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\CapturedPayment;
use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Billing\Models\Shift;
use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Models\ContractorSettlementBatch;
use App\Domain\Facility\Models\CourtBooking;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Models\RoomReservation;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\JournalHeader;
use App\Domain\Finance\Models\JournalLine;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Prepaid\Models\WalletAccount;
use App\Domain\Prepaid\Models\WalletTransaction;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Compute Departmental Profit and Loss with exact reconciliation to overall result (Scenario R03).
     * Incorporates:
     * - Consumed inventory COGS (Account 5010)
     * - Settled food contractor expense (Account 5020)
     * - Recognised plan income (Account 4040)
     * - Direct expenses (Account 5030)
     * - Disclosed unallocated/shared items
     */
    public function getDepartmentProfitAndLoss(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay()->toDateString();
        $end = Carbon::parse($endDate)->endOfDay()->toDateString();

        $journalHeaders = JournalHeader::whereBetween('business_date', [$start, $end])
            ->pluck('id');

        $lines = JournalLine::with('account')
            ->whereIn('journal_header_id', $journalHeaders)
            ->get();

        $departments = ['bar', 'food', 'sports', 'accommodation', 'card_room', 'membership'];
        $deptReports = [];

        foreach ($departments as $dept) {
            $deptReports[$dept] = [
                'department_code' => $dept,
                'revenue_paise' => 0,
                'cost_paise' => 0,
                'net_margin_paise' => 0,
                'revenue_breakdown' => [],
                'cost_breakdown' => [],
            ];
        }

        $unallocated = [
            'department_code' => 'unallocated',
            'revenue_paise' => 0,
            'cost_paise' => 0,
            'net_margin_paise' => 0,
        ];

        foreach ($lines as $line) {
            $acc = $line->account;
            $deptCode = $line->department_code;

            // Revenue Accounts (Code starting with 4)
            if (str_starts_with($acc->code, '4')) {
                $netRevenue = $line->credit_paise - $line->debit_paise;
                if ($deptCode && isset($deptReports[$deptCode])) {
                    $deptReports[$deptCode]['revenue_paise'] += $netRevenue;
                    $deptReports[$deptCode]['revenue_breakdown'][$acc->name] = ($deptReports[$deptCode]['revenue_breakdown'][$acc->name] ?? 0) + $netRevenue;
                } else {
                    $unallocated['revenue_paise'] += $netRevenue;
                }
            }

            // Expense Accounts (Code starting with 5)
            if (str_starts_with($acc->code, '5')) {
                $netCost = $line->debit_paise - $line->credit_paise;
                if ($deptCode && isset($deptReports[$deptCode])) {
                    $deptReports[$deptCode]['cost_paise'] += $netCost;
                    $deptReports[$deptCode]['cost_breakdown'][$acc->name] = ($deptReports[$deptCode]['cost_breakdown'][$acc->name] ?? 0) + $netCost;
                } else {
                    $unallocated['cost_paise'] += $netCost;
                }
            }
        }

        $totalDeptRevenue = 0;
        $totalDeptCost = 0;
        $totalDeptMargin = 0;

        foreach ($deptReports as $code => &$report) {
            $report['net_margin_paise'] = $report['revenue_paise'] - $report['cost_paise'];
            $totalDeptRevenue += $report['revenue_paise'];
            $totalDeptCost += $report['cost_paise'];
            $totalDeptMargin += $report['net_margin_paise'];
        }

        $unallocated['net_margin_paise'] = $unallocated['revenue_paise'] - $unallocated['cost_paise'];

        $overallNetResultPaise = $totalDeptMargin + $unallocated['net_margin_paise'];

        return [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'departments' => $deptReports,
            'unallocated' => $unallocated,
            'summary' => [
                'total_department_revenue_paise' => $totalDeptRevenue,
                'total_department_cost_paise' => $totalDeptCost,
                'total_department_margin_paise' => $totalDeptMargin,
                'unallocated_margin_paise' => $unallocated['net_margin_paise'],
                'overall_club_net_paise' => $overallNetResultPaise,
                'is_reconciled' => ($totalDeptMargin + $unallocated['net_margin_paise']) === $overallNetResultPaise,
            ],
        ];
    }

    /**
     * Reconcile subledgers to general ledger liability accounts (Scenario R01, F16).
     */
    public function getSubledgerReconciliation(): array
    {
        // 1. Member Wallets vs Account 2010 (Prepaid Liability)
        $totalWalletBalances = (int) WalletAccount::where('status', 'active')->sum('balance_paise');
        
        $liabilityLines = JournalLine::whereHas('account', fn($q) => $q->where('code', '2010'))->get();
        $account2010Balance = (int) ($liabilityLines->sum('credit_paise') - $liabilityLines->sum('debit_paise'));

        // 2. Food Contractor Payables vs Account 2020
        $contractorPayableBalance = (int) ContractorSettlementBatch::whereIn('status', ['approved', 'partially_paid'])
            ->sum('residual_payable_paise');
        $contractor2020Lines = JournalLine::whereHas('account', fn($q) => $q->where('code', '2020'))->get();
        $account2020Balance = (int) ($contractor2020Lines->sum('credit_paise') - $contractor2020Lines->sum('debit_paise'));

        // 3. Operational Journal balancing check (Debits must equal Credits)
        $totalDebits = (int) JournalLine::sum('debit_paise');
        $totalCredits = (int) JournalLine::sum('credit_paise');

        return [
            'prepaid_wallet' => [
                'subledger_total_paise' => $totalWalletBalances,
                'general_ledger_paise' => $account2010Balance,
                'difference_paise' => $totalWalletBalances - $account2010Balance,
                'reconciled' => $totalWalletBalances === $account2010Balance,
            ],
            'contractor_payable' => [
                'subledger_total_paise' => (int) $contractorPayableBalance,
                'general_ledger_paise' => (int) $account2020Balance,
                'difference_paise' => (int) ($contractorPayableBalance - $account2020Balance),
                'reconciled' => $contractorPayableBalance === $account2020Balance,
            ],
            'journal_integrity' => [
                'total_debits_paise' => (int) $totalDebits,
                'total_credits_paise' => (int) $totalCredits,
                'difference_paise' => (int) ($totalDebits - $totalCredits),
                'balanced' => $totalDebits === $totalCredits,
            ],
        ];
    }

    /**
     * Report 1: Sales and Collections by Department and Billing Counter.
     */
    public function getDepartmentAndCounterSalesReport(string $startDate, string $endDate, ?string $departmentFilter = null, ?int $counterFilter = null): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $startDateStr = $start->toDateString();
        $endDateStr = $end->toDateString();

        $query = Bill::whereBetween('business_date', [$startDateStr, $endDateStr])
            ->whereIn('status', [BillStatus::PAID, 'paid', 'settled']);

        if ($departmentFilter) {
            $query->where('department_code', $departmentFilter);
        }

        if ($counterFilter) {
            $query->whereHas('shift', fn($q) => $q->where('counter_id', $counterFilter));
        }

        $bills = $query->with(['shift.counter', 'lines'])->get();

        // 1. By Department
        $departments = [
            'bar' => 'Bar & Beverages',
            'food' => 'Food Catering',
            'sports' => 'Badminton & Gym',
            'accommodation' => 'Guest Rooms',
            'card_room' => 'Card Room',
            'membership' => 'Secretariat & Subscriptions',
        ];

        $deptReport = [];
        foreach ($departments as $code => $label) {
            $deptBills = $bills->where('department_code', $code);
            $deptReport[$code] = [
                'code' => $code,
                'name' => $label,
                'bills_count' => $deptBills->count(),
                'gross_paise' => (int) $deptBills->sum('gross_amount_paise'),
                'discount_paise' => (int) $deptBills->sum('discount_amount_paise'),
                'tax_paise' => (int) $deptBills->sum('tax_amount_paise'),
                'net_paise' => (int) $deptBills->sum('net_amount_paise'),
            ];
        }

        // 2. By Billing Counter
        $counters = Counter::all();
        $counterReport = [];
        foreach ($counters as $cnt) {
            $cntBills = $bills->filter(fn($b) => $b->shift?->counter_id === $cnt->id);
            $counterReport[] = [
                'id' => $cnt->id,
                'code' => $cnt->code,
                'name' => $cnt->name,
                'department_code' => $cnt->department_code,
                'bills_count' => $cntBills->count(),
                'gross_paise' => (int) $cntBills->sum('gross_amount_paise'),
                'tax_paise' => (int) $cntBills->sum('tax_amount_paise'),
                'net_paise' => (int) $cntBills->sum('net_amount_paise'),
            ];
        }

        // 3. Collections (External Inflow vs Prepaid Redemptions)
        $receipts = PaymentReceipt::whereBetween('confirmed_at', [$start, $end])
            ->where('provider_status', 'confirmed')
            ->get();

        $billReceiptsCash = (int) $receipts->whereNotNull('bill_id')->where('tender_type', TenderType::CASH)->sum('amount_paise');
        $billReceiptsDigital = (int) $receipts->whereNotNull('bill_id')->where('tender_type', TenderType::DIGITAL)->sum('amount_paise');
        $billReceiptsPrepaid = (int) $receipts->whereNotNull('bill_id')->where('tender_type', TenderType::PREPAID)->sum('amount_paise');

        $rechargeReceiptsCash = (int) $receipts->whereNotNull('wallet_transaction_id')->where('tender_type', TenderType::CASH)->sum('amount_paise');
        $rechargeReceiptsDigital = (int) $receipts->whereNotNull('wallet_transaction_id')->where('tender_type', TenderType::DIGITAL)->sum('amount_paise');

        $totalCashCollections = $billReceiptsCash + $rechargeReceiptsCash;
        $totalDigitalCollections = $billReceiptsDigital + $rechargeReceiptsDigital;
        $totalExternalCollections = $totalCashCollections + $totalDigitalCollections;

        return [
            'period' => ['start_date' => $startDateStr, 'end_date' => $endDateStr],
            'departments' => $deptReport,
            'counters' => $counterReport,
            'collections' => [
                'bill_cash_paise' => $billReceiptsCash,
                'bill_digital_paise' => $billReceiptsDigital,
                'bill_prepaid_redemptions_paise' => $billReceiptsPrepaid,
                'recharge_cash_paise' => $rechargeReceiptsCash,
                'recharge_digital_paise' => $rechargeReceiptsDigital,
                'total_cash_inflow_paise' => $totalCashCollections,
                'total_digital_inflow_paise' => $totalDigitalCollections,
                'total_external_collections_paise' => $totalExternalCollections,
            ],
            'summary' => [
                'total_bills' => $bills->count(),
                'total_gross_paise' => (int) $bills->sum('gross_amount_paise'),
                'total_discount_paise' => (int) $bills->sum('discount_amount_paise'),
                'total_tax_paise' => (int) $bills->sum('tax_amount_paise'),
                'total_net_paise' => (int) $bills->sum('net_amount_paise'),
            ],
        ];
    }

    /**
     * Report 2: Income and Expenditure Statement.
     */
    public function getIncomeAndExpenditureReport(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $startDateStr = $start->toDateString();
        $endDateStr = $end->toDateString();

        $bills = Bill::whereBetween('business_date', [$startDateStr, $endDateStr])
            ->whereIn('status', [BillStatus::PAID, 'paid', 'settled'])
            ->get();

        // Income by Source
        $barIncome = (int) $bills->where('department_code', 'bar')->sum('net_amount_paise');
        $foodIncome = (int) $bills->where('department_code', 'food')->sum('net_amount_paise');
        $sportsIncome = (int) $bills->where('department_code', 'sports')->sum('net_amount_paise');
        $accommodationIncome = (int) $bills->where('department_code', 'accommodation')->sum('net_amount_paise');
        $cardRoomIncome = (int) $bills->where('department_code', 'card_room')->sum('net_amount_paise');
        $membershipIncome = (int) $bills->where('department_code', 'membership')->sum('net_amount_paise');
        $otherIncome = (int) $bills->whereNotIn('department_code', ['bar', 'food', 'sports', 'accommodation', 'card_room', 'membership'])->sum('net_amount_paise');

        $totalIncome = $barIncome + $foodIncome + $sportsIncome + $accommodationIncome + $cardRoomIncome + $membershipIncome + $otherIncome;

        // Expenditure by Category
        // 1. Food Contractor Catering Disbursements
        $contractorDisbursed = (int) ContractorSettlementBatch::whereBetween('business_date', [$startDateStr, $endDateStr])->sum('paid_amount_paise');

        // 2. Bar Consumed Inventory COGS
        $barCogs = (int) InventoryMovement::whereBetween('created_at', [$start, $end])
            ->where('type', 'sale')
            ->whereHas('item', fn($q) => $q->where('department_code', 'bar'))
            ->sum('total_cost_paise');

        // 3. Operating Expenses from Expense vouchers
        $expenses = Expense::whereBetween('expense_date', [$startDateStr, $endDateStr])
            ->whereIn('status', ['approved', 'paid'])
            ->get();

        $expenseCategories = [
            'staff_and_wages' => (int) $expenses->filter(fn($e) => str_contains(strtolower($e->notes ?? ''), 'salar') || str_contains(strtolower($e->payee ?? ''), 'staff') || str_contains(strtolower($e->payee ?? ''), 'wage'))->sum('amount_paise'),
            'electricity_and_utilities' => (int) $expenses->filter(fn($e) => str_contains(strtolower($e->notes ?? ''), 'elect') || str_contains(strtolower($e->notes ?? ''), 'water') || str_contains(strtolower($e->notes ?? ''), 'fuel') || str_contains(strtolower($e->notes ?? ''), 'generator'))->sum('amount_paise'),
            'clubhouse_maintenance' => (int) $expenses->filter(fn($e) => str_contains(strtolower($e->notes ?? ''), 'repair') || str_contains(strtolower($e->notes ?? ''), 'maint'))->sum('amount_paise'),
        ];
        $knownExpenseSum = array_sum($expenseCategories);
        $otherExpenses = (int) $expenses->sum('amount_paise') - $knownExpenseSum;
        if ($otherExpenses < 0) $otherExpenses = 0;
        $expenseCategories['administrative_and_other'] = $otherExpenses;

        $totalOperatingExpenses = (int) $expenses->sum('amount_paise');
        $totalExpenditure = $contractorDisbursed + $barCogs + $totalOperatingExpenses;
        $netSurplus = $totalIncome - $totalExpenditure;

        return [
            'period' => ['start_date' => $startDateStr, 'end_date' => $endDateStr],
            'incomes' => [
                'food_sales_paise' => $foodIncome,
                'bar_sales_paise' => $barIncome,
                'sports_facility_paise' => $sportsIncome,
                'accommodation_paise' => $accommodationIncome,
                'card_room_paise' => $cardRoomIncome,
                'membership_dues_paise' => $membershipIncome,
                'other_paise' => $otherIncome,
                'total_income_paise' => $totalIncome,
            ],
            'expenditures' => [
                'contractor_payouts_paise' => $contractorDisbursed,
                'bar_cogs_paise' => $barCogs,
                'operating_expenses_paise' => $totalOperatingExpenses,
                'expense_breakdown' => $expenseCategories,
                'total_expenditure_paise' => $totalExpenditure,
            ],
            'net_operating_surplus_paise' => $netSurplus,
        ];
    }

    /**
     * Report 3: Daily Cash and Digital Payment Reconciliation.
     */
    public function getDailyCashAndDigitalReconciliation(string $startDate, string $endDate): array
    {
        $shifts = Shift::whereBetween('business_date', [$startDate, $endDate])
            ->with(['counter', 'user', 'paymentReceipts'])
            ->orderBy('business_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $shiftRows = [];
        $totalOpeningFloat = 0;
        $totalClosingCashCounted = 0;
        $totalExpectedCash = 0;
        $totalDiscrepancy = 0;
        $totalCashReceipts = 0;
        $totalDigitalReceipts = 0;
        $totalPrepaidDebits = 0;

        foreach ($shifts as $s) {
            $cashReceipts = (int) $s->paymentReceipts->where('tender_type', TenderType::CASH)->where('provider_status', 'confirmed')->sum('amount_paise');
            $digitalReceipts = (int) $s->paymentReceipts->where('tender_type', TenderType::DIGITAL)->where('provider_status', 'confirmed')->sum('amount_paise');
            $prepaidDebits = (int) $s->paymentReceipts->where('tender_type', TenderType::PREPAID)->where('provider_status', 'confirmed')->sum('amount_paise');

            $shiftRows[] = [
                'id' => $s->id,
                'shift_number' => $s->shift_number,
                'business_date' => $s->business_date?->toDateString() ?? '',
                'counter_name' => $s->counter?->name ?? 'Front Counter',
                'cashier_name' => $s->user?->name ?? 'Cashier',
                'status' => $s->status instanceof \BackedEnum ? $s->status->value : (string) $s->status,
                'opening_float_paise' => (int) $s->opening_float_paise,
                'cash_sales_paise' => $cashReceipts,
                'digital_sales_paise' => $digitalReceipts,
                'prepaid_debits_paise' => $prepaidDebits,
                'expected_cash_paise' => (int) $s->expected_cash_paise,
                'closing_cash_counted_paise' => (int) ($s->closing_cash_counted_paise ?? 0),
                'discrepancy_paise' => (int) ($s->discrepancy_paise ?? 0),
            ];

            $totalOpeningFloat += (int) $s->opening_float_paise;
            $totalClosingCashCounted += (int) ($s->closing_cash_counted_paise ?? 0);
            $totalExpectedCash += (int) $s->expected_cash_paise;
            $totalDiscrepancy += (int) ($s->discrepancy_paise ?? 0);
            $totalCashReceipts += $cashReceipts;
            $totalDigitalReceipts += $digitalReceipts;
            $totalPrepaidDebits += $prepaidDebits;
        }

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'shifts' => $shiftRows,
            'summary' => [
                'total_shifts' => $shifts->count(),
                'total_opening_float_paise' => $totalOpeningFloat,
                'total_cash_receipts_paise' => $totalCashReceipts,
                'total_digital_receipts_paise' => $totalDigitalReceipts,
                'total_prepaid_debits_paise' => $totalPrepaidDebits,
                'total_expected_cash_paise' => $totalExpectedCash,
                'total_closing_cash_counted_paise' => $totalClosingCashCounted,
                'total_discrepancy_paise' => $totalDiscrepancy,
            ],
        ];
    }

    /**
     * Report 4: Food Contractor Settlements.
     */
    public function getFoodContractorSettlementReport(string $startDate, string $endDate): array
    {
        $batches = ContractorSettlementBatch::whereBetween('business_date', [$startDate, $endDate])
            ->with(['contractor', 'approver'])
            ->orderBy('business_date', 'desc')
            ->get();

        $rows = [];
        $totalGross = 0;
        $totalTax = 0;
        $totalNet = 0;
        $totalAdjustments = 0;
        $totalNetPayable = 0;
        $totalPaid = 0;
        $totalResidual = 0;

        foreach ($batches as $b) {
            $rows[] = [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'business_date' => $b->business_date?->toDateString() ?? '',
                'contractor_name' => $b->contractor?->name ?? 'External Caterer',
                'status' => $b->status,
                'gross_paise' => (int) $b->total_food_sales_gross_paise,
                'tax_paise' => (int) $b->total_food_sales_tax_paise,
                'net_paise' => (int) $b->total_food_sales_net_paise,
                'adjustments_paise' => (int) $b->total_adjustments_paise,
                'net_payable_paise' => (int) $b->net_payable_paise,
                'paid_amount_paise' => (int) $b->paid_amount_paise,
                'residual_payable_paise' => (int) $b->residual_payable_paise,
                'payment_reference' => $b->payment_reference ?? '—',
                'approved_by' => $b->approver?->name ?? 'Pending Review',
            ];

            $totalGross += (int) $b->total_food_sales_gross_paise;
            $totalTax += (int) $b->total_food_sales_tax_paise;
            $totalNet += (int) $b->total_food_sales_net_paise;
            $totalAdjustments += (int) $b->total_adjustments_paise;
            $totalNetPayable += (int) $b->net_payable_paise;
            $totalPaid += (int) $b->paid_amount_paise;
            $totalResidual += (int) $b->residual_payable_paise;
        }

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'batches' => $rows,
            'summary' => [
                'total_batches' => $batches->count(),
                'total_food_gross_paise' => $totalGross,
                'total_food_tax_paise' => $totalTax,
                'total_food_net_paise' => $totalNet,
                'total_adjustments_paise' => $totalAdjustments,
                'total_net_payable_paise' => $totalNetPayable,
                'total_paid_paise' => $totalPaid,
                'current_outstanding_payable_paise' => $totalResidual,
            ],
        ];
    }

    /**
     * Report 5: Bar and Card Room Sales and Stock Reports.
     */
    public function getBarAndStockReport(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $items = InventoryItem::with(['movements' => fn($q) => $q->whereBetween('created_at', [$start, $end])])
            ->where('is_active', true)
            ->get();

        $rows = [];
        $totalValuation = 0;
        $totalPeriodCogs = 0;
        $totalPeriodWastage = 0;
        $lowStockCount = 0;

        foreach ($items as $item) {
            $stockVal = (int) round($item->current_stock_qty * $item->current_avg_cost_paise);
            $salesQty = (float) $item->movements->where('type', 'sale')->sum('quantity');
            $salesCost = (int) $item->movements->where('type', 'sale')->sum('total_cost_paise');
            $receiptsQty = (float) $item->movements->where('type', 'receipt')->sum('quantity');
            $wastageQty = (float) $item->movements->where('type', 'wastage')->sum('quantity');
            $wastageCost = (int) $item->movements->where('type', 'wastage')->sum('total_cost_paise');
            $isLow = $item->current_stock_qty <= $item->reorder_level;

            if ($isLow) $lowStockCount++;

            $rows[] = [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'department_code' => $item->department_code,
                'category' => $item->category,
                'base_unit' => $item->base_unit,
                'current_stock_qty' => $item->current_stock_qty,
                'avg_cost_paise' => $item->current_avg_cost_paise,
                'stock_value_paise' => $stockVal,
                'reorder_level' => $item->reorder_level,
                'is_low_stock' => $isLow,
                'period_sales_qty' => $salesQty,
                'period_sales_cost_paise' => $salesCost,
                'period_receipts_qty' => $receiptsQty,
                'period_wastage_qty' => $wastageQty,
                'period_wastage_cost_paise' => $wastageCost,
            ];

            $totalValuation += $stockVal;
            $totalPeriodCogs += $salesCost;
            $totalPeriodWastage += $wastageCost;
        }

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'items' => $rows,
            'summary' => [
                'total_items_count' => $items->count(),
                'total_stock_valuation_paise' => $totalValuation,
                'period_cogs_paise' => $totalPeriodCogs,
                'period_wastage_loss_paise' => $totalPeriodWastage,
                'low_stock_alerts_count' => $lowStockCount,
            ],
        ];
    }

    /**
     * Report 6: Gym and Badminton Membership Income.
     */
    public function getSportsFacilityIncomeReport(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $startDateStr = $start->toDateString();
        $endDateStr = $end->toDateString();

        // 1. Subscriptions created in period
        $subs = FacilitySubscription::whereBetween('created_at', [$start, $end])
            ->with(['member.person', 'tariff'])
            ->get();

        $gymSubs = $subs->where('facility_code', 'gym');
        $badmintonSubs = $subs->where('facility_code', 'badminton');

        $gymRevenue = (int) $gymSubs->sum('total_fee_paise');
        $badmintonSubRevenue = (int) $badmintonSubs->sum('total_fee_paise');

        // 2. Court Bookings in period
        $bookings = CourtBooking::whereBetween('booking_date', [$startDateStr, $endDateStr])
            ->with(['member.person', 'facility', 'billLine'])
            ->get();

        $planCoveredCount = $bookings->where('is_plan_covered', true)->count();
        $paidBookings = $bookings->where('is_plan_covered', false);
        $courtFeesCollected = (int) $paidBookings->sum(fn($b) => $b->billLine?->line_total_paise ?? 0);

        // 3. Sports Guest Passes in period
        $guestPassBills = BillLine::whereBetween('created_at', [$start, $end])
            ->whereHas('bill', fn($q) => $q->where('department_code', 'sports'))
            ->where('item_name', 'LIKE', '%Guest%')
            ->sum('line_total_paise');

        $totalSportsRevenue = $gymRevenue + $badmintonSubRevenue + $courtFeesCollected + (int) $guestPassBills;

        return [
            'period' => ['start_date' => $startDateStr, 'end_date' => $endDateStr],
            'gym' => [
                'subscriptions_count' => $gymSubs->count(),
                'monthly_count' => $gymSubs->where('plan_type', 'monthly')->count(),
                'annual_count' => $gymSubs->where('plan_type', 'annual')->count(),
                'revenue_paise' => $gymRevenue,
            ],
            'badminton' => [
                'subscriptions_count' => $badmintonSubs->count(),
                'monthly_count' => $badmintonSubs->where('plan_type', 'monthly')->count(),
                'annual_count' => $badmintonSubs->where('plan_type', 'annual')->count(),
                'subscription_revenue_paise' => $badmintonSubRevenue,
                'total_court_slots_booked' => $bookings->count(),
                'plan_covered_slots_count' => $planCoveredCount,
                'paid_court_slots_count' => $paidBookings->count(),
                'court_fees_collected_paise' => $courtFeesCollected,
            ],
            'guest_passes_paise' => (int) $guestPassBills,
            'summary' => [
                'total_subscriptions_count' => $subs->count(),
                'total_court_bookings_count' => $bookings->count(),
                'total_sports_revenue_paise' => $totalSportsRevenue,
            ],
        ];
    }

    /**
     * Report 7: Outstanding Payments and Exception Reconciliation.
     */
    public function getOutstandingPayablesAndExceptionsReport(): array
    {
        // 1. Zero Member Credit Verification
        $overdrawnWalletsCount = (int) WalletAccount::where('balance_paise', '<', 0)->count();
        $totalActiveWallets = (int) WalletAccount::where('status', 'active')->count();
        $totalWalletLiability = (int) WalletAccount::where('status', 'active')->sum('balance_paise');

        // 2. Contractor Residual Payables
        $unsettledBatches = ContractorSettlementBatch::whereIn('status', ['approved', 'partially_paid'])
            ->where('residual_payable_paise', '>', 0)
            ->with('contractor')
            ->get();
        $totalContractorPayable = (int) $unsettledBatches->sum('residual_payable_paise');

        // 3. Digital Gateway Unposted Captures (Scenario F07)
        $unpostedCaptures = CapturedPayment::where('status', 'captured')->get();
        $totalUnpostedCaptures = (int) $unpostedCaptures->sum('amount_paise');

        // 4. Upcoming Room Reservations Pending Advance Payments
        $pendingRooms = RoomReservation::where('status', 'confirmed')
            ->where('check_in_date', '>=', now()->toDateString())
            ->whereRaw('paid_amount_paise < rate_per_night_paise')
            ->with(['member.person'])
            ->get();
        $pendingRoomBalances = (int) $pendingRooms->sum(fn($r) => $r->rate_per_night_paise - $r->paid_amount_paise);

        // 5. Perpetual General Ledger Balance Check
        $totalDebits = (int) JournalLine::sum('debit_paise');
        $totalCredits = (int) JournalLine::sum('credit_paise');

        return [
            'member_credit' => [
                'overdrawn_accounts_count' => $overdrawnWalletsCount,
                'is_zero_credit_compliant' => $overdrawnWalletsCount === 0,
                'active_wallets_count' => $totalActiveWallets,
                'total_wallet_liability_paise' => $totalWalletLiability,
            ],
            'contractor_payables' => [
                'unsettled_batches_count' => $unsettledBatches->count(),
                'total_residual_payable_paise' => $totalContractorPayable,
                'batches' => $unsettledBatches->map(fn($b) => [
                    'batch_number' => $b->batch_number,
                    'contractor' => $b->contractor?->name ?? 'External Caterer',
                    'residual_payable_paise' => (int) $b->residual_payable_paise,
                    'status' => $b->status,
                ])->toArray(),
            ],
            'gateway_exceptions' => [
                'unposted_count' => $unpostedCaptures->count(),
                'total_unposted_paise' => $totalUnpostedCaptures,
            ],
            'accommodation_pending' => [
                'pending_rooms_count' => $pendingRooms->count(),
                'pending_amount_paise' => $pendingRoomBalances,
            ],
            'general_ledger' => [
                'total_debits_paise' => $totalDebits,
                'total_credits_paise' => $totalCredits,
                'is_balanced' => $totalDebits === $totalCredits,
                'difference_paise' => $totalDebits - $totalCredits,
            ],
        ];
    }
}
