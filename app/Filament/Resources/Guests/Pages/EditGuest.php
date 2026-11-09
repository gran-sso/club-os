<?php

namespace App\Filament\Resources\Guests\Pages;

use App\Filament\Resources\Guests\GuestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditGuest extends EditRecord
{
    protected static string $resource = GuestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Domain\Access\Models\Guest $record */
        $record = $this->getRecord();

        if ($record->person) {
            $data['first_name'] = $record->person->first_name;
            $data['last_name'] = $record->person->last_name;
            $data['phone'] = $record->person->phone;
            $data['email'] = $record->person->email;
            $data['id_proof_type'] = $record->person->id_proof_type;
            $data['id_proof_number'] = $record->person->id_proof_number;
            $data['notes'] = $record->person->notes;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Domain\Access\Models\Guest $record */
        if ($record->person) {
            $record->person->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'id_proof_type' => $data['id_proof_type'] ?? null,
                'id_proof_number' => $data['id_proof_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        $record->update([
            'sponsor_type' => $data['sponsor_type'],
            'sponsor_id' => $data['sponsor_id'],
            'visit_date' => $data['visit_date'],
            'status' => $data['status'],
        ]);

        return $record;
    }
}

