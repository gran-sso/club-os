<?php

namespace App\Filament\Resources\JournalHeaders\Pages;

use App\Filament\Resources\JournalHeaders\JournalHeaderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJournalHeaders extends ListRecords
{
    protected static string $resource = JournalHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
