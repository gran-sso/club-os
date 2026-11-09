<?php

namespace App\Filament\Resources\Purchases;

use App\Domain\Inventory\Models\PurchaseReceipt;
use App\Filament\Resources\Purchases\Pages\CreatePurchaseReceipt;
use App\Filament\Resources\Purchases\Pages\ListPurchaseReceipts;
use App\Filament\Resources\Purchases\Schemas\PurchaseReceiptForm;
use App\Filament\Resources\Purchases\Tables\PurchaseReceiptsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement & Food Contractor';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Supplier Receipts';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 11;

    public static function getGloballySearchableAttributes(): array
    {
        return ['receipt_number', 'invoice_number', 'supplier.name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Purchase Receipt #{$record->receipt_number}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Supplier' => $record->supplier?->name ?? 'Direct Vendor',
            'Invoice #' => $record->invoice_number ?? 'N/A',
            'Total' => '₹' . number_format(($record->total_net_paise ?? 0) / 100, 2),
            'Status' => ucfirst($record->status ?? 'posted'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['supplier']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->hasPermissionTo('stock.manage')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->hasPermissionTo('stock.manage')));
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseReceipts::route('/'),
            'create' => CreatePurchaseReceipt::route('/create'),
        ];
    }
}
