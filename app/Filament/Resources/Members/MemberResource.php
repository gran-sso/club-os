<?php

namespace App\Filament\Resources\Members;

use App\Domain\Membership\Models\Member;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ImportMembers;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Members\Tables\MembersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Membership & Guest Desk';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 6;

    protected static ?int $globalSearchSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['membership_number', 'person.first_name', 'person.last_name', 'person.phone', 'person.email'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "{$record->name} ({$record->membership_number})";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Phone' => $record->phone ?? 'N/A',
            'Category' => $record->category?->name ?? 'Standard',
            'Status' => ucfirst($record->status->value ?? $record->status ?? 'active'),
            'Wallet' => '₹' . number_format(($record->wallet_balance_paise ?? 0) / 100, 2),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['person', 'category', 'wallet']);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['members.view', 'members.manage']));
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('members.manage'));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('members.manage'));
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('members.manage'));
    }

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
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
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'import' => ImportMembers::route('/import'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }
}
