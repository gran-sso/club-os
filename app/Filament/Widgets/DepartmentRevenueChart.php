<?php

namespace App\Filament\Widgets;

use App\Domain\Billing\Models\Bill;
use Filament\Widgets\DoughnutChartWidget;

class DepartmentRevenueChart extends DoughnutChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Departmental Revenue';

    protected ?string $description = "Real-time sales distribution across club departments";

    protected ?string $pollingInterval = '30s';

    protected ?string $maxHeight = '260px';

    protected string $view = 'filament.widgets.department-revenue-widget';

    public static function canView(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager', 'accountant', 'auditor']));
    }

    protected function getData(): array
    {
        $tableData = $this->getTableData();
        $rows = $tableData['rows'];

        $amounts = array_map(fn ($r) => (int) round($r['amount_paise'] / 100), $rows);
        $labels = array_map(fn ($r) => $r['name'], $rows);
        $colors = array_map(fn ($r) => $r['color'], $rows);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $amounts,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                    'borderColor' => '#0f172a',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'padding' => 14,
                        'font' => [
                            'size' => 11,
                        ],
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
            'cutout' => '65%',
        ];
    }

    public function getTableData(): array
    {
        $departments = [
            [
                'name' => 'Bar & Lounge',
                'code' => 'bar',
                'codes' => ['bar'],
                'icon' => '🍸',
                'color' => '#f59e0b', // Amber
                'account' => 'Bar Liquor & Beverage Revenue',
            ],
            [
                'name' => 'Kitchen Food (Contractor)',
                'code' => 'food',
                'codes' => ['food'],
                'icon' => '🍽️',
                'color' => '#10b981', // Emerald
                'account' => 'Catering Food Sales (Settlement Batch)',
            ],
            [
                'name' => 'Guest Rooms & Suites',
                'code' => 'room',
                'codes' => ['room', 'accommodation'],
                'icon' => '🏨',
                'color' => '#0ea5e9', // Sky
                'account' => 'Room Tariff & Accommodation Revenue',
            ],
            [
                'name' => 'Badminton & Sports',
                'code' => 'sports',
                'codes' => ['court', 'sports'],
                'icon' => '🏸',
                'color' => '#8b5cf6', // Violet
                'account' => 'Sports & Court Booking Revenue',
            ],
            [
                'name' => 'Guest Passes & Cards',
                'code' => 'guests',
                'codes' => ['guest_entry', 'card_room'],
                'icon' => '🎟️',
                'color' => '#ec4899', // Pink
                'account' => 'Guest Affiliation & Facility Entry Fees',
            ],
        ];

        // 1. Check today's paid sales
        $totalTodayPaise = (int) Bill::whereDate('created_at', today())
            ->where('status', 'paid')
            ->sum('net_amount_paise');

        $isPeriodFallback = false;
        $periodLabel = "Today's Live Sales (" . today()->format('d M Y') . ')';

        if ($totalTodayPaise === 0) {
            $isPeriodFallback = true;
            $since = now()->subDays(7)->toDateString();
            $periodLabel = 'Past 7 Days (Preview)';
            $totalTodayPaise = (int) Bill::whereDate('created_at', '>=', $since)
                ->where('status', 'paid')
                ->sum('net_amount_paise');
        }

        $rows = [];
        $totalBills = 0;

        foreach ($departments as $dept) {
            $query = Bill::where('status', 'paid')->whereIn('department_code', $dept['codes']);
            if ($isPeriodFallback) {
                $query->whereDate('created_at', '>=', now()->subDays(7)->toDateString());
            } else {
                $query->whereDate('created_at', today());
            }

            $amountPaise = (int) $query->sum('net_amount_paise');
            $billCount = (int) $query->count();
            $totalBills += $billCount;

            $sharePct = $totalTodayPaise > 0
                ? round(($amountPaise / $totalTodayPaise) * 100, 1)
                : 0.0;

            $rows[] = [
                'name' => $dept['name'],
                'code' => $dept['code'],
                'icon' => $dept['icon'],
                'color' => $dept['color'],
                'account' => $dept['account'],
                'amount_paise' => $amountPaise,
                'amount_formatted' => '₹' . number_format($amountPaise / 100, 2),
                'bill_count' => $billCount,
                'share_pct' => $sharePct,
            ];
        }

        return [
            'rows' => $rows,
            'total_paise' => $totalTodayPaise,
            'total_formatted' => '₹' . number_format($totalTodayPaise / 100, 2),
            'total_bills' => $totalBills,
            'is_period_fallback' => $isPeriodFallback,
            'period_label' => $periodLabel,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
