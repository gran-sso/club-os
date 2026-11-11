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

    protected static ?string $recordTitleAttribute = 'batch_number';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 13;

    public static function getGloballySearchableAttributes(): array
    {
        return ['batch_number', 'business_date'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Settlement Batch #{$record->batch_number}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $date = $record->business_date ? $record->business_date->format('d M Y') : 'N/A';

        return [
            'Business Date' => $date,
            'Net Payable' => '₹' . number_format(($record->net_payable_paise ?? 0) / 100, 2),
            'Paid Amount' => '₹' . number_format(($record->paid_amount_paise ?? 0) / 100, 2),
            'Status' => ucfirst($record->status ?? 'pending'),
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['contractor.view', 'contractor.settle']));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
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
