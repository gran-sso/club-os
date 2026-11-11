<?php

namespace App\Domain\Shared\Services;

use App\Domain\Membership\Models\Member;
use App\Models\User;
use Filament\Facades\Filament;

class UserLandingService
{
    /**
     * Resolve the default post-login landing URL for a club staff user.
     */
    public function resolveHomeUrlForUser(User $user): string
    {
        if ($user->hasAnyRole([
            'admin',
            'Administrator',
            'manager',
            'Manager',
            'accountant',
            'Accountant',
            'auditor',
            'Auditor',
        ]) || $user->hasAnyPermission([
            'users.manage',
            'reports.view',
        ])) {
            return Filament::getPanel('admin')?->getUrl() ?? url('/admin');
        }

        if ($user->hasAnyRole(['reception', 'Reception'])) {
            return url('/admin/room-rack');
        }

        if ($user->hasAnyRole(['cashier', 'Cashier'])) {
            return route('pos');
        }

        $adminPanel = Filament::getPanel('admin');

        if ($adminPanel && $user->canAccessPanel($adminPanel)) {
            return $adminPanel->getUrl();
        }

        return route('pos');
    }

    /**
     * Resolve the default post-login landing URL for an authenticated club member.
     */
    public function resolveHomeUrlForMember(?Member $member = null): string
    {
        return route('member.dashboard');
    }
}

