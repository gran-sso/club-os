<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Domain\Billing\Services\ShiftService;
use App\Filament\Resources\Shifts\ShiftResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ShiftService::class)->openShift(
            counterId: (int) $data['counter_id'],
            userId: (int) $data['user_id'],
            openingFloatPaise: (int) $data['opening_float_paise'],
            notes: $data['notes'] ?? null,
            businessDate: $data['business_date'] ?? null
        );
    }
}
