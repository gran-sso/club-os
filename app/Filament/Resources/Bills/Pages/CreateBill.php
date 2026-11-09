<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Access\Models\Guest;
use App\Domain\Membership\Models\Member;
use App\Filament\Resources\Bills\BillResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    protected function handleRecordCreation(array $data): Model
    {
        $shift = isset($data['shift_id']) ? Shift::find($data['shift_id']) : null;
        if (!$shift || !$shift->isOpen()) {
            $shift = Shift::where('status', 'open')->where('user_id', auth()->id())->first()
                ?? Shift::where('status', 'open')->latest()->first();
        }

        if (!$shift) {
            $shiftService = app(ShiftService::class);
            $shift = $shiftService->openShift(
                counterId: Counter::firstOrFail()->id,
                userId: auth()->id() ?? 1,
                openingFloatPaise: 200000,
                notes: 'Administrative invoice creation auto-shift'
            );
        }

        $customerClass = $data['customer_type'] ?? Member::class;
        $customer = $customerClass::findOrFail($data['customer_id']);

        $departmentCode = $data['department_code'] ?? 'food';

        // Prepare line items
        $items = [];
        foreach ($data['lines'] ?? [] as $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $unitPricePaise = (int) round(((float) ($line['unit_price'] ?? 0)) * 100);
            $taxPercent = (float) ($line['tax_percent'] ?? 0);

            $items[] = [
                'department_code' => $line['department_code'] ?? $departmentCode,
                'item_type' => $line['item_type'] ?? 'tariff',
                'item_id' => $line['item_id'] ?? null,
                'item_name' => $line['item_name'],
                'quantity' => $qty,
                'unit_price_paise' => $unitPricePaise,
                'tax_percent' => $taxPercent,
            ];
        }

        if (empty($items)) {
            throw new InvalidArgumentException("A bill must contain at least one line item.");
        }

        $billingService = app(BillingService::class);
        $idempotencyKey = 'ADM-BILL-' . (string) Str::uuid();

        $bill = $billingService->createDraftBill(
            shift: $shift,
            customer: $customer,
            departmentCode: $departmentCode,
            items: $items,
            idempotencyKey: $idempotencyKey,
            notes: $data['notes'] ?? null,
            userId: auth()->id()
        );

        if (!empty($data['settle_immediately'])) {
            $tenderType = $data['tender_type'] ?? 'cash';
            $tenders = [
                [
                    'tender_type' => $tenderType,
                    'amount_paise' => $bill->net_amount_paise,
                    'reference' => 'ADMIN-SETTLEMENT',
                ],
            ];

            $billingService->checkout($bill, $tenders, auth()->id());
            $bill->refresh();
        }

        return $bill;
    }
}
