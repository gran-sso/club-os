<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** @var list<string> */
    protected array $selectedPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
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
        $data['name'] = strtolower(trim((string) ($data['name'] ?? '')));
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        ClubPermissionRegistry::ensurePermissionsExist();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->selectedPermissions)
            ->get();

        $this->record->syncPermissions($permissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

