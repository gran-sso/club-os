<?php

namespace App\Filament\Resources\MembershipCategories\Pages;

use App\Filament\Resources\MembershipCategories\MembershipCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembershipCategory extends CreateRecord
{
    protected static string $resource = MembershipCategoryResource::class;
}

