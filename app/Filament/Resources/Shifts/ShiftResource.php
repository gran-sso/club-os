<?php

namespace App\Filament\Resources\Shifts;

use App\Domain\Billing\Models\Shift;
use App\Filament\Resources\Shifts\Pages\CreateShift;
use App\Filament\Resources\Shifts\Pages\EditShift;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Filament\Resources\Shifts\Schemas\ShiftForm;
use App\Filament\Resources\Shifts\Tables\ShiftsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations & Front Desk';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'shift_number';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 7;

    public static function getGloballySearchableAttributes(): array
    {
        return ['shift_number', 'cashier.name', 'counter.name', 'business_date'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Shift #{$record->shift_number}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $date = $record->business_date ? $record->business_date->format('d M Y') : 'N/A';

        return [
            'Counter' => $record->counter?->name ?? 'POS',
            'Cashier' => $record->cashier?->name ?? 'Staff',
            'Business Date' => $date,
            'Status' => ucfirst($record->status ?? 'open'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['counter', 'cashier']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function form(Schema $schema): Schema
    {
        return ShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'create' => CreateShift::route('/create'),
            'edit' => EditShift::route('/{record}/edit'),
        ];
    }
}
