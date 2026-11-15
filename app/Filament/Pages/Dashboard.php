<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    public function getColumns(): int | array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        $canOperateCounter = (bool) ($user && ($user->isSuperAdmin() || $user->hasClubPermission('counter.operate')));
        $canRegisterGuest = (bool) ($user && ($user->isSuperAdmin() || $user->hasAnyClubPermission(['guests.register', 'guests.manage'])));
        $canBookRoom = (bool) ($user && ($user->isSuperAdmin() || $user->hasAnyClubPermission(['rooms.view_rack', 'rooms.manage_reservations'])));
        $canBookCourt = (bool) ($user && ($user->isSuperAdmin() || $user->hasAnyClubPermission(['courts.view_schedule', 'courts.manage_bookings'])));
        $canManageMembers = (bool) ($user && ($user->isSuperAdmin() || $user->hasAnyClubPermission(['members.view', 'members.manage', 'wallet.recharge'])));
        $canManageShifts = (bool) ($user && ($user->isSuperAdmin() || $user->hasAnyClubPermission(['shifts.view', 'shifts.manage'])));
        $canViewReports = (bool) ($user && ($user->isSuperAdmin() || $user->hasClubPermission('reports.view')));

        $actions = [];

        // 1. Primary Operational Shortcut: Counter POS
        if ($canOperateCounter) {
            $actions[] = Action::make('counterPos')
                ->label('Counter POS')
                ->icon(Heroicon::OutlinedBolt)
                ->color('warning')
                ->url(route('pos'));
        }

        // 2. High-Frequency: Register Visiting Guest
        if ($canRegisterGuest) {
            $actions[] = Action::make('registerGuest')
                ->label('Register Guest')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->url(url('/admin/guests/create'));
        }

        // 3. High-Frequency: Accommodation Room Rack
        if ($canBookRoom) {
            $actions[] = Action::make('roomRack')
                ->label('Room Rack')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color('gray')
                ->url(url('/admin/room-rack'));
        }

        // 4. High-Frequency: Badminton Court Booking
        if ($canBookCourt) {
            $actions[] = Action::make('bookCourt')
                ->label('Book Court')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(url('/admin/court-bookings/create'));
        }

        // 5. Secondary Administrative Shortcuts in "More" Dropdown
        $moreActions = [];

        if ($canManageMembers) {
            $moreActions[] = Action::make('memberTopUp')
                ->label('Member Top-Up')
                ->icon(Heroicon::OutlinedCreditCard)
                ->url(url('/admin/members'));
        }

        if ($canManageShifts) {
            $moreActions[] = Action::make('shiftCash')
                ->label('Shift Cash')
                ->icon(Heroicon::OutlinedBanknotes)
                ->url(url('/admin/shifts'));
        }

        if ($canViewReports) {
            $moreActions[] = Action::make('reportingHub')
                ->label('Reporting Hub')
                ->icon(Heroicon::OutlinedChartBar)
                ->url(url('/admin/financial-reports'));
        }

        if (! empty($moreActions)) {
            $actions[] = ActionGroup::make($moreActions)
                ->label('More')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray');
        }

        return $actions;
    }
}
