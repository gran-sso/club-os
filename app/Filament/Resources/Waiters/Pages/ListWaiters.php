<?php

namespace App\Filament\Resources\Waiters\Pages;

use App\Filament\Resources\Waiters\WaiterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWaiters extends ListRecords
{
    protected static string $resource = WaiterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Register New Bearer')
                ->modalHeading('Register Service Bearer / Waiter')
                ->modalDescription('Add floor staff for table order attribution and gratuity/tips distribution.'),
        ];
    }
}

