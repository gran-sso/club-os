<?php

namespace App\Filament\Widgets;

use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\Shift;
use App\Domain\Contractor\Models\ContractorSettlementBatch;
use Filament\Widgets\Widget;

class ContractorAndShiftStatusWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 1;

    protected string $view = 'filament.widgets.contractor-and-shift-status';

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['contractor.view', 'contractor.settle', 'shifts.manage']));
    }

    public function getViewData(): array
    {
        // 1. Food Contractor Accumulated Sales Today
        $todayFoodPaise = (int) BillLine::where('department_code', 'food')
            ->whereHas('bill', function ($q) {
                $q->whereDate('created_at', today())->where('status', 'paid');
            })
            ->sum('line_total_paise');

        $latestBatch = ContractorSettlementBatch::with('contractor')
            ->orderByDesc('business_date')
            ->first();

        // 2. Active Counter Shifts
        $openShifts = Shift::with(['counter', 'cashier'])
            ->where('status', 'open')
            ->get();

        $staleShiftsCount = Shift::where('status', 'open')
            ->whereDate('business_date', '<', today())
            ->count();

        return [
            'todayFoodPaise' => $todayFoodPaise,
            'todayFoodRupees' => number_format($todayFoodPaise / 100, 2),
            'latestBatch' => $latestBatch,
            'openShifts' => $openShifts,
            'staleShiftsCount' => $staleShiftsCount,
        ];
    }
}
