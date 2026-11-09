<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class GlobalQuickSearchWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.global-quick-search-widget';

    public static function canView(): bool
    {
        return (bool) auth()->user();
    }
}

