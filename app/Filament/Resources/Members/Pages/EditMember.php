<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Delete Unused Profile')
                ->visible(fn ($record) => 
                    !$record->bills()->exists() && 
                    (!$record->wallet || ($record->wallet->balance_paise === 0 && !$record->wallet->entries()->exists())) && 
                    !$record->courtBookings()->exists() &&
                    !$record->subscriptions()->exists() &&
                    !$record->roomReservations()->exists()
                )
                ->modalHeading('Delete Unused Member Profile')
                ->modalDescription('Only newly created member profiles with zero financial history, unspent wallet deposits, or facility bookings can be deleted.'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Domain\Membership\Models\Member $record */
        $record = $this->getRecord();

        if ($record->person) {
            $data['first_name'] = $record->person->first_name;
            $data['last_name'] = $record->person->last_name;
            $data['phone'] = $record->person->phone;
            $data['email'] = $record->person->email;
            $data['id_proof_type'] = $record->person->id_proof_type;
            $data['id_proof_number'] = $record->person->id_proof_number;
            $data['id_proof_path'] = $record->person->id_proof_path;
            $data['application_form_path'] = $record->person->application_form_path;
            $data['notes'] = $record->person->notes;
        }

        if ($record->status instanceof BackedEnum) {
            $data['status'] = $record->status->value;
        }

        $data['fee_status'] = $record->fee_status ?? 'pending';

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Domain\Membership\Models\Member $record */
        if ($record->person) {
            $record->person->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'id_proof_type' => $data['id_proof_type'] ?? null,
                'id_proof_number' => $data['id_proof_number'] ?? null,
                'id_proof_path' => $data['id_proof_path'] ?? null,
                'application_form_path' => $data['application_form_path'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        $record->update([
            'membership_number' => $data['membership_number'],
            'membership_category_id' => $data['membership_category_id'],
            'status' => $data['status'],
            'fee_status' => $data['fee_status'] ?? $record->fee_status,
            'joined_at' => $data['joined_at'],
            'valid_until' => $data['valid_until'] ?? null,
        ]);

        return $record;
    }
}
