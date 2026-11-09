<?php

namespace App\Filament\Resources\RoomReservations\Pages;

use App\Filament\Resources\RoomReservations\RoomReservationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoomReservation extends EditRecord
{
    protected static string $resource = RoomReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => ($record->paid_amount_paise ?? 0) === 0 && in_array($record->status, ['reserved', 'cancelled'])),
        ];
    }
}

