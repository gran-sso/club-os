<?php

namespace App\Filament\Resources\Users;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'System & Access Control';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Staff & User Access';

    protected static ?string $modelLabel = 'Staff User';

    protected static ?string $pluralModelLabel = 'Staff & System Users';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 1;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'employee_id', 'phone', 'designation'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "Staff: {$record->name} ({$record->email})";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var User $record */
        return [
            'Role' => $record->roles->pluck('name')->map(fn ($r) => ClubPermissionRegistry::roleShortBadgeLabel($r))->join(', ') ?: 'No Role Assigned',
            'Staff ID' => $record->employee_id ?? 'N/A',
            'Status' => $record->is_active ? 'Active' : 'Deactivated',
            'Phone' => $record->phone ?? 'N/A',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('users.manage'));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('users.manage'));
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('users.manage'));
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        if ($user && $user->id === $record->id) {
            return false;
        }

        return (bool) ($user && $user->hasClubPermission('users.manage'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}

