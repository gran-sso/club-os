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
