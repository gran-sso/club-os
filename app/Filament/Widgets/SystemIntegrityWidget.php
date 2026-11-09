<?php

namespace App\Filament\Widgets;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\CapturedPayment;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Domain\Finance\Models\JournalLine;
use App\Domain\Integrations\Contracts\AccountingConnectorInterface;
use App\Domain\Integrations\Models\OutboxMessage;
use Filament\Widgets\Widget;

class SystemIntegrityWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 1;

    protected string $view = 'filament.widgets.system-integrity';

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager', 'accountant', 'auditor']));
    }

    public function getViewData(): array
    {
        // 1. Tally Prime Outbox Status
        $tallyConnector = app(AccountingConnectorInterface::class);
        $tallyStatus = $tallyConnector->getStatus();

        $outboxPending = OutboxMessage::where('status', 'pending')->count();
        $outboxAcknowledged = OutboxMessage::where('status', 'acknowledged')
            ->whereDate('updated_at', today())
            ->count();
        $outboxFailed = OutboxMessage::where('status', 'failed')->count();

        // 2. Double-Entry General Ledger Balance Check
        $totalDebits = (int) JournalLine::sum('debit_paise');
        $totalCredits = (int) JournalLine::sum('credit_paise');
        $isGlBalanced = ($totalDebits === $totalCredits);

        // 3. Active Financial Period
        $activePeriod = FinancialPeriod::where('status', 'open')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->first() ?? FinancialPeriod::where('status', 'open')->latest()->first();

        // 4. Captured Unposted Payments Needing Resolution (Scenario F07)
        $unpostedCapturesCount = CapturedPayment::where('status', 'pending')->count();

        // 5. Month-to-Date Accrued GST (CGST + SGST)
        $monthGstPaise = (int) Bill::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'paid')
            ->sum('tax_amount_paise');

        return [
            'tallyStatus' => $tallyStatus,
            'outboxPending' => $outboxPending,
            'outboxAcknowledged' => $outboxAcknowledged,
            'outboxFailed' => $outboxFailed,
            'isGlBalanced' => $isGlBalanced,
            'activePeriod' => $activePeriod,
            'unpostedCapturesCount' => $unpostedCapturesCount,
            'monthGstRupees' => number_format($monthGstPaise / 100, 2),
        ];
    }
}
