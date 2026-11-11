<?php

namespace App\Filament\Resources\JournalHeaders;

use App\Domain\Finance\Models\JournalHeader;
use App\Filament\Resources\JournalHeaders\Pages\CreateJournalHeader;
use App\Filament\Resources\JournalHeaders\Pages\EditJournalHeader;
use App\Filament\Resources\JournalHeaders\Pages\ListJournalHeaders;
use App\Filament\Resources\JournalHeaders\Schemas\JournalHeaderForm;
use App\Filament\Resources\JournalHeaders\Tables\JournalHeadersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JournalHeaderResource extends Resource
{
    protected static ?string $model = JournalHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Audit Ledger';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'General Ledger Journals';

    protected static ?string $recordTitleAttribute = 'journal_number';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 16;

    public static function getGloballySearchableAttributes(): array
    {
        return ['journal_number', 'description'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Journal Voucher #{$record->journal_number}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $date = $record->business_date ? $record->business_date->format('d M Y') : 'N/A';
        $source = $record->source_type ? class_basename($record->source_type) . " #{$record->source_id}" : 'Manual Entry';

        return [
            'Source' => $source,
            'Business Date' => $date,
            'Description' => \Illuminate\Support\Str::limit($record->description ?? 'Journal Entry', 35),
            'Total Debits' => '₹' . number_format(($record->totalDebits()) / 100, 2),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['lines']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['journals.view', 'journals.manage']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('journals.manage'));
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
        return JournalHeaderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JournalHeadersTable::configure($table);
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
            'index' => ListJournalHeaders::route('/'),
            'create' => CreateJournalHeader::route('/create'),
        ];
    }
}
