<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Finance\Services\RevenueRecognitionService;
use App\Filament\Resources\Subscriptions\FacilitySubscriptionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateFacilitySubscription extends CreateRecord
{
    protected static string $resource = FacilitySubscriptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $subscription = FacilitySubscription::create([
            'subscription_number' => 'SUB-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'member_id' => (int) $data['member_id'],
            'facility_code' => $data['facility_code'],
            'tariff_id' => (int) $data['tariff_id'],
            'plan_type' => $data['plan_type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'total_fee_paise' => (int) $data['total_fee_paise'],
            'status' => $data['status'] ?? 'active',
            'created_by_user_id' => auth()->id(),
        ]);

        if ($subscription->total_fee_paise > 0) {
            app(RevenueRecognitionService::class)->createSchedule($subscription);
        }

        return $subscription;
    }
}

