<?php

namespace App\Filament\Resources\JournalHeaders\Pages;

use App\Filament\Resources\JournalHeaders\JournalHeaderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJournalHeader extends EditRecord
{
    protected static string $resource = JournalHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
