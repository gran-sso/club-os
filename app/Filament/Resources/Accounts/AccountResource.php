<?php

namespace App\Filament\Resources\Accounts;

use App\Domain\Finance\Models\Account;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Resources\Accounts\Tables\AccountsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Audit Ledger';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Chart of Accounts';

    protected static ?string $modelLabel = 'GL Account / Expense Head';

    protected static ?string $pluralModelLabel = 'Chart of Accounts & Expense Heads';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 15;

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'category', 'description'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Account $record */
        return "GL Account: {$record->name} (#{$record->code})";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Account $record */
        return [
            'Code' => $record->code,
            'Type' => ucfirst($record->type instanceof \BackedEnum ? $record->type->value : (string) $record->type),
            'Group' => $record->category ?? 'General',
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

        return (bool) ($user && ($user->hasRole(['admin', 'accountant']) || $user->hasPermissionTo('reports.view')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasRole(['admin', 'accountant']));
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasRole(['admin', 'accountant']));
    }

    public static function canDelete(Model $record): bool
    {
        /** @var Account $record */
        $user = auth()->user();

        if ($record->is_system) {
            return false;
        }

        return (bool) ($user && $user->hasRole(['admin', 'accountant']));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}

