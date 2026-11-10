<?php

namespace App\Filament\Resources\MemberNotices\Pages;

use App\Filament\Resources\MemberNotices\MemberNoticeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMemberNotice extends EditRecord
{
    protected static string $resource = MemberNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
