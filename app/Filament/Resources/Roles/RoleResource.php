<?php

namespace App\Filament\Resources\Roles;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'System & Access Control';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Roles & Form Access';

    protected static ?string $modelLabel = 'Club Role';

    protected static ?string $pluralModelLabel = 'Roles & Form Access';

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
        if (! $user || ! $user->hasClubPermission('users.manage')) {
            return false;
        }

        if (! $record instanceof Role) {
            return false;
        }

        if (ClubPermissionRegistry::isSystemRole($record->name)) {
            return false;
        }

        return $record->users()->count() === 0;
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}

