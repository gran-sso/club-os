<?php

namespace App\Filament\Resources\Guests\Pages;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Tariff;
use App\Filament\Resources\Guests\GuestResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();
        if (empty($data['issue_pass_now'])) {
            return;
        }

        /** @var Guest $record */
        $record = $this->record;

        $facilityCode = $data['pass_facility_code'] ?? 'bar_card_room';
        $tariffCode = match ($facilityCode) {
            'badminton' => 'GUEST-BADMINTON-ENTRY',
            'gym' => 'GUEST-GYM-ENTRY',
            default => 'GUEST-BAR-ENTRY',
        };

        $tariff = Tariff::where('code', $tariffCode)->first();
        $ratePaise = $tariff ? $tariff->rate_paise : 20000;
        $taxPercent = $tariff ? $tariff->tax_percent : 18.0;

        $shift = Shift::where('status', 'open')->first();
        if (! $shift) {
            Notification::make()
                ->title('Pass Not Issued: No Open Counter Shift')
                ->body('Guest registered, but entry pass could not be billed because no counter shift is currently open.')
                ->warning()
                ->send();

            return;
        }

        $billingService = app(BillingService::class);
        $guestService = app(GuestService::class);

        $bill = $billingService->createDraftBill(
            shift: $shift,
            customer: $record,
            departmentCode: 'card_room',
            items: [
                [
                    'item_name' => $tariff?->name ?? 'Guest Admission Pass',
                    'quantity' => 1,
                    'unit_price_paise' => $ratePaise,
                    'tax_percent' => $taxPercent,
                ]
            ],
            idempotencyKey: 'pass_bill_' . Str::uuid(),
            userId: auth()->id()
        );

        $tender = match ($data['pass_tender_type'] ?? 'cash') {
            'digital' => TenderType::DIGITAL,
            default => TenderType::CASH,
        };

        $billingService->checkout(
            bill: $bill,
            tenders: [
                ['tender_type' => $tender, 'amount_paise' => $bill->net_amount_paise]
            ],
            userId: auth()->id()
        );

        $billLine = $bill->lines()->firstOrFail();
        $pass = $guestService->activateGuestPass(
            guest: $record,
            facilityCode: $facilityCode,
            billLineId: $billLine->id,
            paidAmountPaise: $bill->net_amount_paise,
            validFrom: now(),
            validUntil: now()->endOfDay()
        );

        $record->update(['status' => 'admitted']);

        Notification::make()
            ->title("Entry Pass Issued: {$pass->pass_code}")
            ->body("Billed ₹" . number_format($bill->net_amount_paise / 100, 2) . " via " . ucfirst($data['pass_tender_type'] ?? 'cash') . ". Pass active for today.")
            ->success()
            ->send();
    }
}
