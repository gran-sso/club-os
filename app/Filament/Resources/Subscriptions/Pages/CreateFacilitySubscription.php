<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Domain\Facility\Exceptions\MembershipRequiredException;
use App\Domain\Facility\Models\FacilitySubscription;
use App\Domain\Facility\Services\SubscriptionService;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Exceptions\InsufficientFundsException;
use App\Domain\Shared\Exceptions\PeriodClosedException;
use App\Domain\Shared\Exceptions\ShiftNotOpenException;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Resources\Subscriptions\FacilitySubscriptionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateFacilitySubscription extends CreateRecord
{
    protected static string $resource = FacilitySubscriptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $member = Member::findOrFail((int) $data['member_id']);
        $tariff = Tariff::findOrFail((int) $data['tariff_id']);
        $tenderType = TenderType::tryFrom((string) ($data['tender_type'] ?? TenderType::CASH->value)) ?? TenderType::CASH;

        try {
            return app(SubscriptionService::class)->enrollWithPayment(
                member: $member,
                facilityCode: (string) $data['facility_code'],
                planType: (string) $data['plan_type'],
                tariff: $tariff,
                totalFeePaise: (int) $data['total_fee_paise'],
                tenderType: $tenderType,
                startDate: $data['starts_at'] ?? null,
                endDate: $data['ends_at'] ?? null,
                status: (string) ($data['status'] ?? 'active'),
                shiftId: !empty($data['shift_id']) ? (int) $data['shift_id'] : null,
                paymentReference: $data['payment_reference'] ?? null,
                user: auth()->user(),
            );
        } catch (
            InsufficientFundsException
            | MembershipRequiredException
            | UnauthorizedActionException
            | ShiftNotOpenException
            | PeriodClosedException
            | InvalidArgumentException $e
        ) {
            Notification::make()
                ->title('Subscription Payment / Activation Rejected')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var FacilitySubscription $subscription */
        $subscription = $this->getRecord();
        $receipt = $subscription->paymentReceipt;
        $feeFormatted = number_format(($subscription->total_fee_paise ?? 0) / 100, 2);

        if ($receipt) {
            $tenderLabel = $receipt->tender_type instanceof TenderType
                ? $receipt->tender_type->label()
                : ucfirst((string) $receipt->tender_type);

            return Notification::make()
                ->success()
                ->title('Sports Plan Activated & Payment Settled')
                ->body("Plan {$subscription->subscription_number} active. Receipt {$receipt->receipt_number} (₹{$feeFormatted} via {$tenderLabel}) posted to Sports Revenue (A/C 4040).");
        }

        return Notification::make()
            ->success()
            ->title('Sports Plan Created')
            ->body("Plan {$subscription->subscription_number} created.");
    }
}

