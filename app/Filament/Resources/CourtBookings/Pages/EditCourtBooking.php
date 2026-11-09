<?php

namespace App\Filament\Resources\CourtBookings\Pages;

use App\Filament\Resources\CourtBookings\CourtBookingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourtBooking extends EditRecord
{
    protected static string $resource = CourtBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => empty($record->bill_line_id) && in_array($record->status, ['confirmed', 'cancelled'])),
        ];
    }
}

