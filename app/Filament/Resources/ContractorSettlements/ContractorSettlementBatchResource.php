<?php

namespace App\Filament\Resources\ContractorSettlements;

use App\Domain\Contractor\Models\ContractorSettlementBatch;
use App\Filament\Resources\ContractorSettlements\Pages\ListContractorSettlementBatches;
use App\Filament\Resources\ContractorSettlements\Tables\ContractorSettlementBatchesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContractorSettlementBatchResource extends Resource
{
    protected static ?string $model = ContractorSettlementBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement & Food Contractor';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Contractor Settlements';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->hasPermissionTo('contractor.settle')));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ContractorSettlementBatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractorSettlementBatches::route('/'),
        ];
    }
}
