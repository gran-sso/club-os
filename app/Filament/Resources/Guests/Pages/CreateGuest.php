<?php

namespace App\Filament\Resources\Guests\Pages;

use App\Domain\Access\Services\GuestService;
use App\Filament\Resources\Guests\GuestResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGuest extends CreateRecord
{
    protected static string $resource = GuestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $personData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'id_proof_type' => $data['id_proof_type'] ?? null,
            'id_proof_number' => $data['id_proof_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        return app(GuestService::class)->registerGuest(
            $personData,
            $data['sponsor_type'],
            (int) $data['sponsor_id'],
            $data['visit_date'] ?? null
        );
    }
}
