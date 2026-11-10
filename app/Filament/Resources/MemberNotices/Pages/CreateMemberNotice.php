<?php

namespace App\Filament\Resources\MemberNotices\Pages;

use App\Filament\Resources\MemberNotices\MemberNoticeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMemberNotice extends CreateRecord
{
    protected static string $resource = MemberNoticeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}

