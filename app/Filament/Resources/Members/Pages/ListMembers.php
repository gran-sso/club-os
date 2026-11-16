<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use App\Domain\Membership\Services\MemberService;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.members.pages.list-members';

    #[Url(as: 'view')]
    public string $viewMode = 'table';

    #[Url(as: 'q')]
    public string $cardSearch = '';

    #[Url(as: 'cat')]
    public string $cardCategory = '';

    #[Url(as: 'status')]
    public string $cardStatus = '';

    #[Url(as: 'comm')]
    public bool $cardCommitteeOnly = false;

    public int $cardPage = 1;

    public int $cardPerPage = 12;

    public function mount(): void
    {
        parent::mount();

        $sessionMode = session()->get('members_view_mode');
        if (request()->has('view')) {
            $this->viewMode = request()->query('view') === 'cards' ? 'cards' : 'table';
        } elseif ($sessionMode) {
            $this->viewMode = $sessionMode === 'cards' ? 'cards' : 'table';
        }
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['table', 'cards']) ? $mode : 'table';
        session()->put('members_view_mode', $this->viewMode);

        if ($this->viewMode === 'cards' && empty($this->cardSearch) && ! empty($this->tableSearch)) {
            $this->cardSearch = (string) $this->tableSearch;
        } elseif ($this->viewMode === 'table' && empty($this->tableSearch) && ! empty($this->cardSearch)) {
            $this->tableSearch = $this->cardSearch;
        }
    }

    public function updatedCardSearch(): void
    {
        $this->cardPage = 1;
    }

    public function updatedCardCategory(): void
    {
        $this->cardPage = 1;
    }

    public function updatedCardStatus(): void
    {
        $this->cardPage = 1;
    }

    public function updatedCardCommitteeOnly(): void
    {
        $this->cardPage = 1;
    }

    public function resetCardFilters(): void
    {
        $this->cardSearch = '';
        $this->cardCategory = '';
        $this->cardStatus = '';
        $this->cardCommitteeOnly = false;
        $this->cardPage = 1;
    }

    public function previousCardPage(): void
    {
        if ($this->cardPage > 1) {
            $this->cardPage--;
        }
    }

    public function nextCardPage(): void
    {
        if ($this->getCardMembersProperty()->hasMorePages()) {
            $this->cardPage++;
        }
    }

    public function gotoCardPage(int $page): void
    {
        $this->cardPage = max(1, $page);
    }

    public function getCardMembersProperty(): LengthAwarePaginator
    {
        $query = Member::query()
            ->with(['person', 'category', 'wallet', 'activeCard']);

        if (! empty(trim($this->cardSearch))) {
            $term = '%' . trim($this->cardSearch) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('membership_number', 'like', $term)
                    ->orWhere('vehicle_numbers', 'like', $term)
                    ->orWhere('committee_role', 'like', $term)
                    ->orWhereHas('person', function ($pq) use ($term) {
                        $pq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('profession', 'like', $term)
                            ->orWhere('designation', 'like', $term)
                            ->orWhere('organization', 'like', $term)
                            ->orWhere('city', 'like', $term);
                    })
                    ->orWhereHas('activeCard', function ($cq) use ($term) {
                        $cq->where('card_number', 'like', $term);
                    });
            });
        }

        if (! empty($this->cardCategory)) {
            $query->where('membership_category_id', (int) $this->cardCategory);
        }

        if (! empty($this->cardStatus)) {
            $query->where('status', $this->cardStatus);
        }

        if ($this->cardCommitteeOnly) {
            $query->whereNotNull('committee_role')->where('committee_role', '!=', '');
        }

        return $query->orderBy('membership_number')->paginate($this->cardPerPage, ['*'], 'cardPage', $this->cardPage);
    }

    protected function getViewData(): array
    {
        return [
            'totalMembersCount' => Member::count(),
            'activeMembersCount' => Member::where('status', 'active')->count(),
            'committeeMembersCount' => Member::whereNotNull('committee_role')->where('committee_role', '!=', '')->count(),
            'categories' => MembershipCategory::where('is_active', true)->orderBy('name')->get(),
            'cardMembers' => $this->getCardMembersProperty(),
        ];
    }

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
