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
        if ($user->isSuperAdmin() || $user->hasAnyClubPermission([
            'users.manage',
            'reports.view',
            'journals.view',
            'expenses.view',
            'bills.view',
        ])) {
            return Filament::getPanel('admin')?->getUrl() ?? url('/admin');
        }

        if ($user->hasAnyRole(['reception', 'Reception'])) {
            if ($user->hasClubPermission('rooms.view_rack')) {
                return url('/admin/room-rack');
            }

            if ($user->hasClubPermission('courts.view_schedule')) {
                return url('/admin/court-schedule');
            }

            if ($user->hasClubPermission('counter.operate')) {
                return route('pos');
            }
        }

        if ($user->hasAnyRole(['cashier', 'Cashier']) || $user->hasClubPermission('counter.operate')) {
            if ($user->hasClubPermission('counter.operate')) {
                return route('pos');
            }

            if ($user->hasClubPermission('rooms.view_rack')) {
                return url('/admin/room-rack');
            }

            if ($user->hasClubPermission('courts.view_schedule')) {
                return url('/admin/court-schedule');
            }
        }

        $adminPanel = Filament::getPanel('admin');

        if ($adminPanel && $user->canAccessPanel($adminPanel)) {
            if ($user->hasClubPermission('rooms.view_rack')) {
                return url('/admin/room-rack');
            }

            if ($user->hasClubPermission('courts.view_schedule')) {
                return url('/admin/court-schedule');
            }

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

