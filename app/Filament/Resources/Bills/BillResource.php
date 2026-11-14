<?php

namespace App\Filament\Resources\Bills;

use App\Domain\Billing\Models\Bill;
use App\Filament\Resources\Bills\Pages\CreateBill;
use App\Filament\Resources\Bills\Pages\EditBill;
use App\Filament\Resources\Bills\Pages\ListBills;
use App\Filament\Resources\Bills\Schemas\BillForm;
use App\Filament\Resources\Bills\Tables\BillsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial & Billing';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Bills & Invoices';

    protected static ?string $recordTitleAttribute = 'bill_number';

    protected static int $globalSearchResultsLimit = 6;

    protected static ?int $globalSearchSort = 8;

    public static function getGloballySearchableAttributes(): array
    {
        return ['bill_number', 'department_code'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Bill #{$record->bill_number}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $customerName = $record->customer?->name ?? 'Walk-in Guest';
        $amount = '₹' . number_format(($record->net_amount_paise ?? 0) / 100, 2);

        return [
            'Customer' => $customerName,
            'Department' => strtoupper($record->department_code ?? 'POS'),
            'Amount' => $amount,
            'Status' => ucfirst($record->status->value ?? $record->status ?? 'paid'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer', 'shift']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['bills.view', 'bills.manage']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('bills.manage'));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('bills.manage'));
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return BillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillsTable::configure($table);
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
            'index' => ListBills::route('/'),
            'create' => CreateBill::route('/create'),
            'edit' => EditBill::route('/{record}/edit'),
        ];
    }
}
