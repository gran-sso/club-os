<?php

namespace App\Filament\Resources\Counters\Pages;

use App\Filament\Resources\Counters\CounterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCounters extends ListRecords
{
    protected static string $resource = CounterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Billing Counter')
                ->modalHeading('Configure New Billing Counter Station')
                ->modalDescription('Provision a new physical or logical counter till for cashier shift operations.'),
        ];
    }
}

