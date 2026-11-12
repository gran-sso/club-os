<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Services\MemberService;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkImport')
                ->label('Import Members (CSV)')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('info')
                ->visible(fn (): bool => (bool) auth()->user()?->hasClubPermission('members.manage'))
                ->url(fn (): string => MemberResource::getUrl('import')),

            Action::make('exportRoster')
                ->label('Download Member List (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->hasAnyClubPermission(['members.view', 'members.manage']))
                ->action(function () {
                    $user = auth()->user();
                    abort_unless((bool) ($user && $user->hasAnyClubPermission(['members.view', 'members.manage'])), 403);

                    $query = ($this->getFilteredTableQuery() ?? Member::query())
                        ->with(['person', 'category', 'wallet', 'activeCard'])
                        ->orderBy('membership_number')
                        ->orderBy('id');

                    return app(MemberService::class)->streamMembersCsvExport(
                        $query->lazy(500),
                        (bool) $user->hasClubPermission('members.kyc_view'),
                        'malabar_club_members_' . now()->format('Ymd_His') . '.csv'
                    );
                }),

            CreateAction::make(),
        ];
    }
}
