<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class HelpPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Help';

    protected static ?string $slug = 'help';

    protected string $view = 'filament.pages.help';

    public string $activeTab = 'cashier'; // 'cashier', 'reception', 'finance', 'inventory', 'executive', 'bylaws'

    public string $searchQuery = '';

    public function mount(): void
    {
        $tab = request()->query('tab');
        if (in_array($tab, ['cashier', 'reception', 'finance', 'inventory', 'executive', 'bylaws'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->searchQuery = '';
    }

    public function jumpTo(string $tab, string $query = ''): void
    {
        $this->activeTab = $tab;
        $this->searchQuery = $query;
    }

    public function clearSearch(): void
    {
        $this->searchQuery = '';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<int, array{id: string, tab: string, tab_label: string, icon: string, title: string, summary: string, tags: array<int, string>}>
     */
    public function getSearchableArticles(): array
    {
        return [
            [
                'id' => 'cashier-shift',
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => 'banknotes',
                'title' => 'Cashier Shift Lifecycle & Float Reconciliation',
                'summary' => 'Opening float entry, real-time drawer expected cash tracking (Float + Cash Sales + Cash Recharges), and closing shift reconciliation with discrepancy auditing.',
                'tags' => ['shift', 'float', 'opening cash', 'closing cash', 'drawer', 'reconciliation', 'cashier', 'discrepancy'],
            ],
            [
                'id' => 'cashier-shortcuts',
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => 'command-line',
                'title' => 'High-Speed Keyboard Shortcuts (F2–F10)',
                'summary' => 'F2 Member/RFID tap, F3 Barcode/Item search, F4 Cart edit, F8 Prepaid tender, F9 Cash tender, F10 Digital UPI/EDC, 1/2/3 Peg size selection, Esc dismiss.',
                'tags' => ['shortcuts', 'f2', 'f3', 'f4', 'f8', 'f9', 'f10', 'keyboard', 'hotkeys', 'peg', 'barcode', 'rfid', 'tender'],
            ],
            [
                'id' => 'cashier-recharge',
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => 'credit-card',
                'title' => 'Member Prepaid Wallet Quick Recharge',
                'summary' => 'Quick top-up workflow via Cash or UPI at counter POS. Updates server ledger balance instantly. Prepaid funds do not count as restaurant revenue until spent.',
                'tags' => ['recharge', 'top-up', 'wallet', 'prepaid', 'card', 'rfid', 'balance', 'f8'],
            ],
            [
                'id' => 'cashier-guest-pass',
                'tab' => 'cashier',
                'tab_label' => 'Counter & POS',
                'icon' => 'ticket',
                'title' => 'Visiting Guest Day Passes & Gate Slips',
                'summary' => 'Issuing paid Bar, Card Room, or Sports guest passes for sponsored visitors. Generates thermal barcode receipt for turnstile verification. Zero-credit strictly prohibited.',
                'tags' => ['guest', 'day pass', 'turnstile', 'gate', 'visitor', 'slip', 'bar pass', 'sponsor'],
            ],
            [
                'id' => 'reception-deck',
                'tab' => 'reception',
                'tab_label' => 'Front Desk & Facilities',
                'icon' => 'building-office-2',
                'title' => 'Accommodation Deck (Rooms 101–105)',
                'summary' => 'Front-desk graphical room board. Visual statuses: Vacant Ready (Green), Occupied (Teal), Reserved (Blue), Needs Cleaning (Amber), Maintenance (Rose). Check-in and checkout workflows.',
                'tags' => ['accommodation deck', 'room rack', 'rooms', '101', '102', '103', '104', '105', 'check-in', 'checkout', 'housekeeping', 'vacant ready', 'occupied'],
            ],
            [
                'id' => 'reception-courts',
                'tab' => 'reception',
                'tab_label' => 'Front Desk & Facilities',
                'icon' => 'calendar-days',
                'title' => 'Badminton Court Daily Schedule (6:00 AM – 10:00 PM)',
                'summary' => 'Hourly slot booking with pessimistic concurrency locking. Automatic fee waiver (₹0.00) for active Sports subscription plan holders. Pay-per-play for casual members.',
                'tags' => ['badminton', 'court', 'schedule', 'slots', 'sports', 'subscription', 'hourly', 'waiver', 'reservation'],
            ],
            [
                'id' => 'finance-coa',
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => 'scale',
                'title' => 'General Ledger & 5-Digit Numbering Standard (COA)',
                'summary' => '1000s Assets, 2000s Liabilities, 3000s Equity, 4000s Revenue, 5000s Expenses. System-protected accounts vs custom operational expense heads (5110 KSEB, 5120 Diesel, 5210 Maintenance).',
                'tags' => ['chart of accounts', 'coa', '5-digit', 'gl', 'expense heads', '1010', '1020', '2010', '2020', '5110', '5120', '5210', 'kseb', 'diesel', 'system accounts'],
            ],
            [
                'id' => 'finance-contractor',
                'tab' => 'finance',
                'tab_label' => 'Finance & Accounts',
                'icon' => 'clipboard-document-check',
                'title' => 'External Food Contractor Daily Settlement Cycle',
                'summary' => 'Dining is operated by external contractor. Daily compilation of paid food lines (Cash, UPI, Wallet), management sign-off, bank disbursement (NEFT/Cheque/UTR), and Tally sync.',
                'tags' => ['food contractor', 'contractor', 'settlement', 'kitchen', 'dining', 'batch', 'payable', 'utr', 'tally', 'payout'],
            ],
            [
                'id' => 'inventory-purchases',
                'tab' => 'inventory',
                'tab_label' => 'Procurement & Stores',
                'icon' => 'archive-box',
                'title' => 'Inward Stock Purchases & Supplier Management',
                'summary' => 'Receipts from KSBC wholesale and distributors. Vendor registration with 15-digit GSTIN validation. Automatic Moving Weighted-Average Cost calculation and GL posting.',
                'tags' => ['purchase receipt', 'ksbc', 'supplier', 'gstin', 'consignment', 'stock inward', 'weighted average', 'mac', 'inventory'],
            ],
            [
                'id' => 'inventory-dispensing',
                'tab' => 'inventory',
                'tab_label' => 'Procurement & Stores',
                'icon' => 'beaker',
                'title' => 'Bar Liquor Dispensing & Milliliter (ml) Accuracy',
                'summary' => 'Tracking liquor stock in ml units: 30ml Small Peg, 60ml Large Peg, 750ml Bottle. Automatic deduction from inventory and COGS posting. Eliminates dispensing variance.',
                'tags' => ['bar', 'liquor', 'ml', 'peg', '30ml', '60ml', '750ml', 'bottle', 'dispensing', 'cogs', 'stock'],
            ],
            [
                'id' => 'executive-staff',
                'tab' => 'executive',
                'tab_label' => 'Executive & Admin',
                'icon' => 'user-group',
                'title' => 'Staff User Management & Role-Based Access Control',
                'summary' => 'Registering staff with Employee ID, designation, and phone. Role assignments (Admin, Manager, Accountant, Auditor, Reception, Cashier). Deactivation safeguards and deletion lock for historical records.',
                'tags' => ['staff', 'users', 'rbac', 'roles', 'admin', 'manager', 'cashier', 'reception', 'accountant', 'deactivate', 'access control'],
            ],
            [
                'id' => 'executive-reports',
                'tab' => 'executive',
                'tab_label' => 'Executive & Admin',
                'icon' => 'chart-bar-square',
                'title' => 'Executive Reporting Hub (8 Statutory Reports)',
                'summary' => 'Sales & Collections, Income & Expenditure, Till Recon, Food Contractor Audit, Bar Stock, Sports Income, Zero-Credit Audit, and Departmental P&L.',
                'tags' => ['reports', 'executive reports', 'p&l', 'income expenditure', 'till recon', 'statutory', 'audit'],
            ],
            [
                'id' => 'bylaws-rules',
                'tab' => 'bylaws',
                'tab_label' => 'Club Bylaws',
                'icon' => 'document-check',
                'title' => 'Club Operational Bylaws & System Rules',
                'summary' => 'Rule 1: Strict Zero-Credit Policy. Rule 2: Centralized Server Ledger. Rule 3: Immutable Financial Records & Linked Reversals. Rule 4: Guest Entry & Sponsorship. Rule 5: Period Closing Locks.',
                'tags' => ['bylaws', 'zero-credit', 'zero credit', 'overdraft', 'immutable', 'reversals', 'rules', 'policy', 'sponsorship'],
            ],
        ];
    }

    /**
     * @return array<int, array{id: string, tab: string, tab_label: string, icon: string, title: string, summary: string, tags: array<int, string>}>
     */
    public function getFilteredArticlesProperty(): array
    {
        $query = trim(strtolower($this->searchQuery));
        if ($query === '') {
            return [];
        }

        return array_values(array_filter($this->getSearchableArticles(), function ($article) use ($query) {
            if (str_contains(strtolower($article['title']), $query)) {
                return true;
            }
            if (str_contains(strtolower($article['summary']), $query)) {
                return true;
            }
            if (str_contains(strtolower($article['tab_label']), $query)) {
                return true;
            }
            foreach ($article['tags'] as $tag) {
                if (str_contains(strtolower($tag), $query)) {
                    return true;
                }
            }
            return false;
        }));
    }
}
