<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Membership\Services\CardService;
use App\Domain\Membership\Services\MemberService;
use App\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

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

        $member = app(MemberService::class)->registerMember(
            $personData,
            (int) $data['membership_category_id'],
            $data['membership_number'],
            $data['joined_at'] ?? null,
            $data['valid_until'] ?? null
        );

        if (!empty($data['status']) && $data['status'] !== 'active') {
            $member->update(['status' => $data['status']]);
        }

        if (!empty($data['rfid_card_number'])) {
            app(CardService::class)->issueCard(
                $member,
                $data['rfid_card_number'],
                'Initial card issuance on member registration'
            );
        }

        return $member;
    }
}
