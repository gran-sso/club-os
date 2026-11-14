<?php

namespace App\Filament\Resources\Counters;

use App\Domain\Billing\Models\Counter;
use App\Filament\Resources\Counters\Pages\ListCounters;
use App\Filament\Resources\Counters\Schemas\CounterForm;
use App\Filament\Resources\Counters\Tables\CountersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CounterResource extends Resource
{
    protected static ?string $model = Counter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|\UnitEnum|null $navigationGroup = 'Club Master Setup';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Billing Counters';

    protected static ?string $modelLabel = 'Billing Counter';

    protected static ?string $pluralModelLabel = 'Billing Counters';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 17;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "Counter: {$record->name} ({$record->code})";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Department' => $record->department?->name ?? 'General',
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission([
            'counters.view',
            'counters.manage',
            'facilities.view',
            'facilities.manage',
            'settings.manage',
        ]));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission([
            'counters.manage',
            'facilities.manage',
            'settings.manage',
        ]));
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission([
            'counters.manage',
            'facilities.manage',
            'settings.manage',
        ]));
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CounterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CountersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCounters::route('/'),
        ];
    }
}

