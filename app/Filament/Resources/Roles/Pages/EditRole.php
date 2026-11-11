<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @var list<string> */
    protected array $selectedPermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_defaults')
                ->label('Restore Role Defaults')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Restore Recommended Default Permissions?')
                ->modalDescription('This replaces this role\'s customized form permissions with Malabar Club\'s standard operational blueprint for this role.')
                ->visible(fn (): bool => ClubPermissionRegistry::isSystemRole($this->record->name)
                    && ClubPermissionRegistry::normalizeRoleName($this->record->name) !== 'admin')
                ->action(function (): void {
                    ClubPermissionRegistry::ensurePermissionsExist();
                    $defaults = ClubPermissionRegistry::defaultPermissionsForRole($this->record->name);
                    $permissions = Permission::query()
                        ->where('guard_name', 'web')
                        ->whereIn('name', $defaults)
                        ->get();

                    $this->record->syncPermissions($permissions);
                    app()[PermissionRegistrar::class]->forgetCachedPermissions();

                    $this->fillForm();

                    Notification::make()
                        ->title('Role permissions restored to club defaults.')
                        ->success()
                        ->send();
                }),

            DeleteAction::make()
                ->visible(fn (): bool => ! ClubPermissionRegistry::isSystemRole($this->record->name)
                    && $this->record->users()->count() === 0),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        ClubPermissionRegistry::ensurePermissionsExist();

        $isAdmin = ClubPermissionRegistry::normalizeRoleName($this->record->name) === 'admin';
        $currentPermissions = $isAdmin
            ? ClubPermissionRegistry::permissionNames()
            : $this->record->permissions()->pluck('name')->all();

        $grouped = [];
        $definitions = ClubPermissionRegistry::definitions();

        foreach (array_keys(ClubPermissionRegistry::groups()) as $groupKey) {
            $grouped[$groupKey] = [];
        }

        foreach ($currentPermissions as $permissionName) {
            $groupKey = $definitions[$permissionName]['group'] ?? null;
            if ($groupKey !== null) {
                $grouped[$groupKey][] = $permissionName;
            }
        }

        $data['permission_groups'] = $grouped;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $permissionGroups = $data['permission_groups'] ?? [];
        unset($data['permission_groups']);

        $selected = [];
        foreach ($permissionGroups as $groupItems) {
            if (is_array($groupItems)) {
                foreach ($groupItems as $perm) {
                    if (is_string($perm) && $perm !== '') {
                        $selected[] = $perm;
                    }
                }
            }
        }

        $this->selectedPermissions = array_values(array_unique($selected));

        if (ClubPermissionRegistry::isSystemRole($this->record->name)) {
            $data['name'] = $this->record->name;
        } else {
            $data['name'] = strtolower(trim((string) ($data['name'] ?? $this->record->name)));
        }

        return $data;
    }

    protected function afterSave(): void
    {
        ClubPermissionRegistry::ensurePermissionsExist();

        if (ClubPermissionRegistry::normalizeRoleName($this->record->name) === 'admin') {
            $this->record->syncPermissions(Permission::where('guard_name', 'web')->get());
        } else {
            $permissions = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $this->selectedPermissions)
                ->get();

            $this->record->syncPermissions($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

