<?php

namespace App\Filament\Widgets;

use App\Domain\Access\Models\GuestPass;
use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\PaymentReceipt;
use App\Domain\Membership\Models\Member;
use App\Domain\Prepaid\Models\WalletAccount;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExecutiveStatsOverviewWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('reports.view'));
    }

    protected function getStats(): array
    {
        // 1. Today's Total Billed Sales (₹)
        $todaySalesPaise = (int) Bill::whereDate('created_at', today())
            ->where('status', 'paid')
            ->sum('net_amount_paise');
        $todaySalesRupees = number_format($todaySalesPaise / 100, 2);

        $salesHistory = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $salesHistory[] = (int) (Bill::whereDate('created_at', $date)->where('status', 'paid')->sum('net_amount_paise') / 100);
        }

        // 2. Fresh Collections Today (Direct Cash & UPI/Cards)
        $todayCollectionsPaise = (int) PaymentReceipt::whereDate('created_at', today())
            ->whereIn('tender_type', ['cash', 'digital'])
            ->sum('amount_paise');
        $todayCollectionsRupees = number_format($todayCollectionsPaise / 100, 2);

        $collectionsHistory = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $collectionsHistory[] = (int) (PaymentReceipt::whereDate('created_at', $date)->whereIn('tender_type', ['cash', 'digital'])->sum('amount_paise') / 100);
        }

        // 3. Prepaid Member Deposits Held (Account 2010 Liability)
        $totalPrepaidPaise = (int) WalletAccount::where('status', 'active')->sum('balance_paise');
        $totalPrepaidRupees = number_format($totalPrepaidPaise / 100, 2);
        $activeWalletsCount = WalletAccount::where('status', 'active')->where('balance_paise', '>', 0)->count();

        // 4. Today's Club Footfall & Attendance
        $membersTransactingToday = Bill::whereDate('created_at', today())
            ->where('customer_type', Member::class)
            ->distinct('customer_id')
            ->count('customer_id');
        $scannedMembersToday = \App\Domain\Access\Models\AccessEvent::whereDate('created_at', today())
            ->where('event_type', 'granted')
            ->whereNotNull('person_id')
            ->distinct('person_id')
            ->count('person_id');
        $activeMembersCount = max($scannedMembersToday, $membersTransactingToday);
        $guestsAdmittedToday = GuestPass::whereDate('created_at', today())->count();
        $totalFootfall = $activeMembersCount + $guestsAdmittedToday;

        return [
            Stat::make("Today's Billed Sales", "₹{$todaySalesRupees}")
                ->description('Net revenue posted across departments')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->chart($salesHistory),

            Stat::make('Collections Today', "₹{$todayCollectionsRupees}")
                ->description('Fresh cash till & UPI/card receipts')
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray)
                ->color('warning')
                ->chart($collectionsHistory),

            Stat::make('Prepaid Member Deposits', "₹{$totalPrepaidRupees}")
                ->description("Liability A/C 2010 ({$activeWalletsCount} funded accounts)")
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('info'),

            Stat::make("Today's Club Footfall", "{$totalFootfall} Persons")
                ->description("{$activeMembersCount} active members • {$guestsAdmittedToday} paid guests")
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('primary'),
        ];
    }
}
