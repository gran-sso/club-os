<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Membership\Services\CardService;
use App\Domain\Membership\Services\MemberService;
use App\Filament\Resources\Members\MemberResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewProfile')
                ->label('View 360° Profile')
                ->icon(Heroicon::OutlinedUser)
                ->color('primary')
                ->url(fn ($record) => MemberResource::getUrl('view', ['record' => $record])),

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
            $data['title'] = $record->person->title;
            $data['first_name'] = $record->person->first_name;
            $data['last_name'] = $record->person->last_name;
            $data['phone'] = $record->person->phone;
            $data['email'] = $record->person->email;
            $data['date_of_birth'] = $record->person->date_of_birth?->toDateString();
            $data['gender'] = $record->person->gender;
            $data['blood_group'] = $record->person->blood_group;
            $data['profession'] = $record->person->profession;
            $data['designation'] = $record->person->designation;
            $data['organization'] = $record->person->organization;
            $data['address_line1'] = $record->person->address_line1;
            $data['address_line2'] = $record->person->address_line2;
            $data['city'] = $record->person->city;
            $data['state'] = $record->person->state ?? 'Kerala';
            $data['postal_code'] = $record->person->postal_code;
            $data['emergency_contact_name'] = $record->person->emergency_contact_name;
            $data['emergency_contact_phone'] = $record->person->emergency_contact_phone;
            $data['id_proof_type'] = $record->person->id_proof_type;
            $data['id_proof_number'] = $record->person->id_proof_number;
            $data['id_proof_path'] = $record->person->id_proof_path;
            $data['application_form_path'] = $record->person->application_form_path;
            $data['photo_path'] = $record->person->photo_path;
            $data['notes'] = $record->person->notes;
        }

        $data['proposer_member_id'] = $record->proposer_member_id;
        $data['seconder_member_id'] = $record->seconder_member_id;
        $data['wedding_anniversary'] = $record->wedding_anniversary?->toDateString();
        $data['vehicle_numbers'] = $record->vehicle_numbers;
        $data['committee_role'] = $record->committee_role;

        if ($record->status instanceof BackedEnum) {
            $data['status'] = $record->status->value;
        }

        $data['fee_status'] = $record->fee_status ?? 'pending';

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Domain\Membership\Models\Member $record */
        $oldStatus = (string) ($record->status?->value ?? $record->status);
        $oldData = [
            'status' => $oldStatus,
            'phone' => $record->person?->phone,
            'email' => $record->person?->email,
        ];

        if ($record->person) {
            $oldPhotoPath = $record->person->photo_path;
            $newPhotoPath = $data['photo_path'] ?? null;

            $record->person->update([
                'title' => $data['title'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'profession' => $data['profession'] ?? null,
                'designation' => $data['designation'] ?? null,
                'organization' => $data['organization'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? 'Kerala',
                'postal_code' => $data['postal_code'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'id_proof_type' => $data['id_proof_type'] ?? null,
                'id_proof_number' => $data['id_proof_number'] ?? null,
                'id_proof_path' => $data['id_proof_path'] ?? null,
                'application_form_path' => $data['application_form_path'] ?? null,
                'photo_path' => $newPhotoPath,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($oldPhotoPath && $oldPhotoPath !== $newPhotoPath) {
                app(MemberService::class)->cleanupUnusedPhoto($oldPhotoPath);
            }
        }

        $record->update([
            'membership_number' => $data['membership_number'],
            'membership_category_id' => $data['membership_category_id'],
            'status' => $data['status'],
            'fee_status' => $data['fee_status'] ?? $record->fee_status,
            'joined_at' => $data['joined_at'],
            'valid_until' => $data['valid_until'] ?? null,
            'proposer_member_id' => ! empty($data['proposer_member_id']) ? (int) $data['proposer_member_id'] : null,
            'seconder_member_id' => ! empty($data['seconder_member_id']) ? (int) $data['seconder_member_id'] : null,
            'wedding_anniversary' => $data['wedding_anniversary'] ?? null,
            'vehicle_numbers' => $data['vehicle_numbers'] ?? null,
            'committee_role' => $data['committee_role'] ?? null,
        ]);

        if (! empty($data['new_rfid_card_number'])) {
            $newCardNumber = trim((string) $data['new_rfid_card_number']);
            $reason = ! empty($data['card_change_reason']) ? trim((string) $data['card_change_reason']) : 'Admin profile update';
            $cardService = app(CardService::class);

            if ($record->activeCard) {
                if ($record->activeCard->card_number !== $newCardNumber) {
                    $cardService->replaceCard($record->activeCard, $newCardNumber, $reason);
                }
            } else {
                $cardService->issueCard($record, $newCardNumber, $reason);
            }
        }

        $action = ($oldStatus !== (string) $data['status']) ? 'status_changed' : 'updated';
        app(\App\Domain\Shared\Services\AuditLogService::class)->logMemberChange(
            member: $record,
            action: $action,
            old: $oldData,
            new: [
                'status' => (string) $data['status'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
            ]
        );

        return $record;
    }
}
