<?php

namespace App\Filament\Resources\Contractors;

use App\Domain\Contractor\Models\Contractor;
use App\Filament\Resources\Contractors\Pages\CreateContractor;
use App\Filament\Resources\Contractors\Pages\EditContractor;
use App\Filament\Resources\Contractors\Pages\ListContractors;
use App\Filament\Resources\Contractors\Schemas\ContractorForm;
use App\Filament\Resources\Contractors\Tables\ContractorsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContractorResource extends Resource
{
    protected static ?string $model = Contractor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement & Food Contractor';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Contractors & Caterers';

    protected static ?string $modelLabel = 'Contractor';

    protected static ?string $pluralModelLabel = 'Contractors';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 12;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'contact_person', 'phone', 'email'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Contractor: {$record->name}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Contact Person' => $record->contact_person ?? 'N/A',
            'Phone' => $record->phone ?? 'N/A',
            'Status' => ucfirst($record->status ?? 'active'),
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->checkPermissionTo('contractor.settle')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->checkPermissionTo('contractor.settle')));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->checkPermissionTo('contractor.settle')));
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->checkPermissionTo('contractor.settle')));
    }

    public static function form(Schema $schema): Schema
    {
        return ContractorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractors::route('/'),
            'create' => CreateContractor::route('/create'),
            'edit' => EditContractor::route('/{record}/edit'),
        ];
    }
}
