<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $groupSections = [];

        foreach (ClubPermissionRegistry::groups() as $groupKey => $groupMeta) {
            $groupSections[] = Section::make($groupMeta['title'])
                ->description($groupMeta['description'])
                ->icon($groupMeta['icon'])
                ->collapsible()
                ->collapsed(false)
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make("permission_groups.{$groupKey}")
                        ->hiddenLabel()
                        ->options(ClubPermissionRegistry::groupPermissionOptions($groupKey))
                        ->descriptions(ClubPermissionRegistry::groupPermissionDescriptions($groupKey))
                        ->bulkToggleable()
                        ->columns(2)
                        ->disabled(fn (?Role $record): bool => $record !== null && ClubPermissionRegistry::normalizeRoleName($record->name) === 'admin')
                        ->helperText(fn (?Role $record): ?string => ($record !== null && ClubPermissionRegistry::normalizeRoleName($record->name) === 'admin')
                            ? 'Administrator always retains every club permission to prevent accidental lockout.'
                            : null),
                ]);
        }

        return $schema
            ->columns(1)
            ->components([
                Section::make('Role Identity & Blueprint')
                    ->description('Define the role name and toggle exactly which Club Admin forms, POS tools, and financial approvals members of this role can access.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Role Identifier (Slug)')
                                ->required()
                                ->maxLength(64)
                                ->regex('/^[a-z][a-z0-9_-]*$/')
                                ->unique(table: Role::class, column: 'name', ignoreRecord: true)
                                ->disabled(fn (?Role $record): bool => $record !== null && ClubPermissionRegistry::isSystemRole($record->name))
                                ->dehydrated(fn (?Role $record): bool => $record === null || ! ClubPermissionRegistry::isSystemRole($record->name))
                                ->placeholder('e.g. bar_storekeeper')
                                ->helperText(fn (?Role $record): string => ($record !== null && ClubPermissionRegistry::isSystemRole($record->name))
                                    ? 'Built-in Malabar Club system role names cannot be renamed or deleted.'
                                    : 'Use lowercase letters, numbers, underscores, or hyphens (e.g. bar_storekeeper, frontdesk_supervisor).'),

                            Placeholder::make('role_guidance')
                                ->label('Role Summary')
                                ->content(function (?Role $record): string {
                                    if (! $record) {
                                        return 'Custom role: assign any combination of forms and actions below, then assign staff members to this role from Staff & Users.';
                                    }

                                    $normalized = ClubPermissionRegistry::normalizeRoleName($record->name);

                                    return ClubPermissionRegistry::roleMeta()[$normalized]['description']
                                        ?? 'Custom club role. Changes here immediately apply to every staff user assigned to this role.';
                                }),
                        ]),
                    ]),

                ...$groupSections,
            ]);
    }
}

