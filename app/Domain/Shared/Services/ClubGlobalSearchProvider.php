<?php

namespace App\Domain\Shared\Services;

use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Illuminate\Support\Collection;

class ClubGlobalSearchProvider implements GlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $builder = GlobalSearchResults::make();
        $trimmedQuery = trim($query);

        if (blank($trimmedQuery)) {
            return null;
        }

        // 1. Navigation & Operational Screens Quick-Jump
        $navigationResults = $this->getNavigationResults($trimmedQuery);
        if ($navigationResults->isNotEmpty()) {
            $builder->category('Operational Screens & Quick Links', $navigationResults);
        }

        // 2. Query all registered Filament Resources
        $resources = Filament::getResources();

        usort(
            $resources,
            fn (string $a, string $b): int => ($a::getGlobalSearchSort() ?? 50) <=> ($b::getGlobalSearchSort() ?? 50),
        );

        foreach ($resources as $resource) {
            if (! $resource::canGloballySearch()) {
                continue;
            }

            $resourceResults = $resource::getGlobalSearchResults($trimmedQuery);

            if (! $resourceResults->count()) {
                continue;
            }

            $groupName = $resource::getNavigationGroup();
            $categoryLabel = $groupName
                ? "{$groupName} — " . $resource::getPluralModelLabel()
                : $resource::getPluralModelLabel();

            $builder->category($categoryLabel, $resourceResults);
        }

        return $builder;
    }

    /**
     * Match query against club navigation pages and core operational screens.
     *
     * @return Collection<int, GlobalSearchResult>
     */
    protected function getNavigationResults(string $query): Collection
    {
        $normalized = strtolower($query);

        if (strlen($normalized) < 2) {
            return collect();
        }

        $screens = [
            [
                'title' => '⚡ Counter POS Terminal (Live Billing & Fast Orders)',
                'url' => route('pos'),
                'details' => ['Section' => 'Commercial & Counters', 'Action' => 'Open Counter POS'],
                'keywords' => ['pos', 'counter', 'billing', 'sale', 'fast sale', 'quick sale', 'till', 'cashier', 'order', 'token'],
                'visible' => fn (): bool => ! (auth()->user()?->hasRole('admin') ?? false),
            ],
            [
                'title' => '🏨 Front-Desk Accommodation Deck (Room Rack Grid & Housekeeping)',
                'url' => url('/admin/room-rack'),
                'details' => ['Section' => 'Operations & Front Desk', 'Action' => 'View Deluxe Rooms 101–105'],
                'keywords' => ['accommodation deck', 'deck', 'room rack', 'room', 'rack', 'accommodation', 'hotel', 'deluxe', 'housekeeping', 'cleaning', 'rooms'],
            ],
            [
                'title' => '🏸 Badminton Court Schedule (Hourly Slots & Matrix)',
                'url' => url('/admin/court-schedule'),
                'details' => ['Section' => 'Operations & Front Desk', 'Action' => 'View Court Schedule'],
                'keywords' => ['court', 'badminton', 'schedule', 'court schedule', 'slots', 'arena', 'sports'],
            ],
            [
                'title' => '📊 Executive Reporting Hub (Department P&L, Sales & Audits)',
                'url' => url('/admin/financial-reports'),
                'details' => ['Section' => 'Finance & Audit Ledger', 'Action' => 'Open 8 Core Reports'],
                'keywords' => ['report', 'reports', 'financial reports', 'p&l', 'profit', 'loss', 'statement', 'sales report', 'audit', 'income', 'expenditure', 'contractor settlement report', 'drawer reconciliation'],
            ],
            [
                'title' => '⚙️ Club System Settings (Bylaws, GSTIN & Identity)',
                'url' => url('/admin/system-settings'),
                'details' => ['Section' => 'Club Master Setup', 'Action' => 'Configure Club Profile & Policies'],
                'keywords' => ['settings', 'config', 'system settings', 'bylaws', 'club profile', 'gstin', 'address', 'policies', 'rules'],
            ],
            [
                'title' => '📡 Hardware & Integration Diagnostics (Tally & Outbox)',
                'url' => url('/admin/integration-status'),
                'details' => ['Section' => 'Audit & System', 'Action' => 'Inspect Outbox & Connectors'],
                'keywords' => ['integration', 'status', 'hardware', 'tally', 'tally prime', 'turnstile', 'biometric', 'rfid', 'thermal printer', 'diagnostics'],
            ],
            [
                'title' => '⚖️ Chart of Accounts & Expense Heads (GL Ledgers & 5000-Series)',
                'url' => url('/admin/accounts'),
                'details' => ['Section' => 'Finance & Audit Ledger', 'Action' => 'Manage GL Ledgers & Expense Heads'],
                'keywords' => ['account', 'accounts', 'chart of accounts', 'coa', 'gl', 'ledger', 'expense head', 'expense heads', '5000', '5100', 'kseb', 'electricity', 'diesel', 'generator', 'maintenance'],
                'visible' => fn (): bool => (bool) (auth()->user()?->hasRole(['admin', 'accountant']) || auth()->user()?->hasPermissionTo('reports.view')),
            ],
            [
                'title' => '🛡️ Staff & User Access Control (RBAC, Roles & Credentials)',
                'url' => url('/admin/users'),
                'details' => ['Section' => 'System & Access Control', 'Action' => 'Manage Staff & Roles'],
                'keywords' => ['user', 'users', 'staff', 'role', 'roles', 'permission', 'permissions', 'access control', 'rbac', 'cashier account', 'password', 'deactivate', 'employee'],
                'visible' => fn (): bool => (bool) (auth()->user()?->hasRole('admin') || auth()->user()?->hasPermissionTo('users.manage')),
            ],
        ];

        return collect($screens)
            ->filter(function (array $screen) use ($normalized): bool {
                if (isset($screen['visible']) && is_callable($screen['visible']) && ! call_user_func($screen['visible'])) {
                    return false;
                }

                if (str_contains(strtolower($screen['title']), $normalized)) {
                    return true;
                }

                foreach ($screen['keywords'] as $keyword) {
                    if (str_contains($keyword, $normalized) || str_contains($normalized, $keyword)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (array $screen): GlobalSearchResult => new GlobalSearchResult(
                title: $screen['title'],
                url: $screen['url'],
                details: $screen['details'],
            ))
            ->values();
    }
}

