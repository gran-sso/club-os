<?php

namespace App\Filament\Resources\BankAccounts;

use App\Domain\Finance\Models\BankAccount;
use App\Filament\Resources\BankAccounts\Pages\CreateBankAccount;
use App\Filament\Resources\BankAccounts\Pages\EditBankAccount;
use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Resources\BankAccounts\Schemas\BankAccountForm;
use App\Filament\Resources\BankAccounts\Tables\BankAccountsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Audit Ledger';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Bank Accounts & Deposits';

    protected static ?string $recordTitleAttribute = 'account_name';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 15;

    public static function getGloballySearchableAttributes(): array
    {
        return ['account_name', 'account_number', 'bank_name', 'ifsc_code'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Bank: {$record->account_name} ({$record->bank_name})";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $maskedAccount = '••••' . substr($record->account_number ?? '', -4);

        return [
            'Bank' => $record->bank_name ?? 'Bank',
            'A/C #' => $maskedAccount,
            'IFSC' => $record->ifsc_code ?? 'N/A',
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->hasPermissionTo('reports.view')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->hasPermissionTo('reports.view')));
    }

    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankAccountsTable::configure($table);
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
            'index' => ListBankAccounts::route('/'),
            'create' => CreateBankAccount::route('/create'),
            'edit' => EditBankAccount::route('/{record}/edit'),
        ];
    }
}

