<?php

namespace App\Filament\Resources\FinancialPeriods;

use App\Domain\Finance\Models\FinancialPeriod;
use App\Filament\Resources\FinancialPeriods\Pages\CreateFinancialPeriod;
use App\Filament\Resources\FinancialPeriods\Pages\EditFinancialPeriod;
use App\Filament\Resources\FinancialPeriods\Pages\ListFinancialPeriods;
use App\Filament\Resources\FinancialPeriods\Schemas\FinancialPeriodForm;
use App\Filament\Resources\FinancialPeriods\Tables\FinancialPeriodsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FinancialPeriodResource extends Resource
{
    protected static ?string $model = FinancialPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Audit Ledger';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Fiscal Periods & Locks';

    protected static ?string $recordTitleAttribute = 'period_code';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 17;

    public static function getGloballySearchableAttributes(): array
    {
        return ['period_code', 'notes'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Fiscal Period: {$record->period_code}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $dates = $record->start_date && $record->end_date
            ? $record->start_date->format('d M Y') . ' – ' . $record->end_date->format('d M Y')
            : 'N/A';

        return [
            'Dates' => $dates,
            'Status' => ucfirst($record->status ?? 'open'),
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager', 'accountant', 'auditor']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager', 'accountant']));
    }

    public static function form(Schema $schema): Schema
    {
        return FinancialPeriodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancialPeriodsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFinancialPeriods::route('/'),
            'create' => CreateFinancialPeriod::route('/create'),
            'edit' => EditFinancialPeriod::route('/{record}/edit'),
        ];
    }
}
