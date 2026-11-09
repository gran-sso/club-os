<?php

namespace App\Filament\Resources\MembershipCategories\Pages;

use App\Domain\Membership\Models\MembershipCategory;
use App\Filament\Resources\MembershipCategories\MembershipCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMembershipCategory extends EditRecord
{
    protected static string $resource = MembershipCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (MembershipCategory $record, DeleteAction $action) {
                    if ($record->members()->exists()) {
                        Notification::make()
                            ->title('Deletion Prohibited: Category Has Active Members')
                            ->body("Cannot delete '{$record->name}' because {$record->members()->count()} member(s) are currently classified under it.")
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}

