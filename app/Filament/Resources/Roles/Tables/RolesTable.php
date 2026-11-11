<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        $totalPermissions = count(ClubPermissionRegistry::permissionNames());

        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('guard_name', 'web')->withCount(['permissions', 'users']))
            ->columns([
                TextColumn::make('name')
                    ->label('Club Role')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => ClubPermissionRegistry::roleBadgeColor($state))
                    ->formatStateUsing(fn (string $state): string => ClubPermissionRegistry::roleDisplayName($state))
                    ->description(fn (Role $record): string => "Key: {$record->name} • " . (
                        ClubPermissionRegistry::roleMeta()[ClubPermissionRegistry::normalizeRoleName($record->name)]['description']
                        ?? 'Custom club role configured by Administrator'
                    ))
                    ->wrap(),

                TextColumn::make('role_type')
                    ->label('Classification')
                    ->state(fn (Role $record): string => ClubPermissionRegistry::isSystemRole($record->name) ? 'System Default' : 'Custom Role')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'System Default' ? 'gray' : 'purple'),

                TextColumn::make('permissions_count')
                    ->label('Enabled Forms & Actions')
                    ->state(fn (Role $record): string => ClubPermissionRegistry::normalizeRoleName($record->name) === 'admin'
                        ? "All ({$totalPermissions} / {$totalPermissions})"
                        : "{$record->permissions_count} / {$totalPermissions}")
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Assigned Staff')
                    ->state(fn (Role $record): string => "{$record->users_count} " . ($record->users_count === 1 ? 'user' : 'users'))
                    ->badge()
                    ->color(fn (Role $record): string => $record->users_count > 0 ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->label('Configure Forms'),
                DeleteAction::make()
                    ->visible(fn (Role $record): bool => ! ClubPermissionRegistry::isSystemRole($record->name) && (int) $record->users_count === 0),
            ]);
    }
}

