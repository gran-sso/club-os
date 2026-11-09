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
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}

