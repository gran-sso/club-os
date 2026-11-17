<?php

namespace App\Filament\Resources\Waiters;

use App\Domain\Billing\Models\Waiter;
use App\Filament\Resources\Waiters\Pages\ListWaiters;
use App\Filament\Resources\Waiters\Schemas\WaiterForm;
use App\Filament\Resources\Waiters\Tables\WaitersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WaiterResource extends Resource
{
    protected static ?string $model = Waiter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Club Master Setup';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Bearers & Waiters';

    protected static ?string $modelLabel = 'Bearer / Waiter';

    protected static ?string $pluralModelLabel = 'Bearers & Waiters';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 5;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'phone'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "Bearer: {$record->name} ({$record->code})";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Department' => $record->department_code ? ucfirst($record->department_code) : 'General Floor',
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
            'counter.operate',
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
        return WaiterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WaitersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaiters::route('/'),
        ];
    }
}

