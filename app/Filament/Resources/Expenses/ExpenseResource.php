<?php

namespace App\Filament\Resources\Expenses;

use App\Domain\Finance\Models\Expense;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Resources\Expenses\Tables\ExpensesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement & Food Contractor';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Operating Expenses';

    protected static ?string $recordTitleAttribute = 'expense_number';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 14;

    public static function getGloballySearchableAttributes(): array
    {
        return ['expense_number', 'payee', 'document_reference'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Expense #{$record->expense_number} — {$record->payee}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $date = $record->expense_date ? $record->expense_date->format('d M Y') : 'N/A';

        return [
            'Payee' => $record->payee ?? 'Vendor',
            'Amount' => '₹' . number_format(($record->amount_paise ?? 0) / 100, 2),
            'Date' => $date,
            'Status' => ucfirst($record->status ?? 'posted'),
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->checkPermissionTo('reports.view')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->checkPermissionTo('reports.view')));
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
        ];
    }
}
