<?php

namespace App\Filament\Resources\Suppliers;

use App\Domain\Inventory\Models\Supplier;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Resources\Suppliers\Tables\SuppliersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement & Food Contractor';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Wholesale Suppliers';

    protected static ?string $modelLabel = 'Wholesale Supplier';

    protected static ?string $pluralModelLabel = 'Wholesale Suppliers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 11;

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'gstin', 'contact_person', 'phone'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "Supplier: {$record->name} ({$record->code})";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Code' => $record->code,
            'Contact' => $record->contact_person ?? 'Depot',
            'GSTIN' => $record->gstin ?? 'N/A',
            'Phone' => $record->phone ?? 'N/A',
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->hasPermissionTo('stock.manage')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->hasPermissionTo('stock.manage')));
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->hasPermissionTo('stock.manage')));
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager']));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}

