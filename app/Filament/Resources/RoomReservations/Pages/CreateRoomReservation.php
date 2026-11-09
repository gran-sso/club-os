<?php

namespace App\Filament\Resources\RoomReservations\Pages;

use App\Domain\Facility\Services\RoomReservationService;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRoomReservation extends CreateRecord
{
    protected static string $resource = RoomReservationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(RoomReservationService::class)->createReservation(
            $data,
            auth()->user()
        );
    }
}

