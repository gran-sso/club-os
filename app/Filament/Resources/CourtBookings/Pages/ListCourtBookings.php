<?php

namespace App\Filament\Resources\CourtBookings\Pages;

use App\Filament\Resources\CourtBookings\CourtBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourtBookings extends ListRecords
{
    protected static string $resource = CourtBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Book Badminton Slot'),
        ];
    }
}
