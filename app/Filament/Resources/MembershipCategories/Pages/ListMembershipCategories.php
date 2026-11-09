<?php

namespace App\Filament\Resources\MembershipCategories\Pages;

use App\Filament\Resources\MembershipCategories\MembershipCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipCategories extends ListRecords
{
    protected static string $resource = MembershipCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Membership Category'),
        ];
    }
}

