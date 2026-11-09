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

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager', 'accountant']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager', 'accountant']));
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
