<?php

namespace App\Filament\Resources\Guests\Tables;

use App\Domain\Access\Models\Guest;
use App\Domain\Access\Services\GuestService;
use App\Domain\Billing\Enums\TenderType;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Tariff\Models\Tariff;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GuestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('person.first_name')
                    ->label('Guest Name')
                    ->state(fn ($record) => ($record->person?->first_name . ' ' . $record->person?->last_name))
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('person.phone')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('sponsor_type')
                    ->label('Sponsor Type')
                    ->badge()
                    ->state(fn ($record) => class_basename($record->sponsor_type))
                    ->color('info'),
                TextColumn::make('sponsor.name')
                    ->label('Sponsor Name')
                    ->state(fn ($record) => $record->sponsor?->name ?? $record->sponsor?->home_member_name ?? '—'),
                TextColumn::make('active_pass')
                    ->label('Active Pass')
                    ->badge()
                    ->state(function ($record) {
                        $active = $record->passes()->where('status', 'active')->latest()->first();
                        return $active ? $active->pass_code : 'No Pass';
                    })
                    ->color(fn ($state) => $state !== 'No Pass' ? 'success' : 'gray'),
                TextColumn::make('visit_date')
                    ->label('Visit Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'registered' => 'warning',
                        'admitted' => 'success',
                        'departed' => 'gray',
                        default => 'secondary',
                    }),
            ])
            ->recordActions([
                Action::make('issuePass')
                    ->label('Issue Entry Pass')
                    ->icon(Heroicon::OutlinedTicket)
                    ->color('primary')
                    ->form([
                        Select::make('facility_code')
                            ->label('Facility Access')
                            ->options([
                                'bar_card_room' => 'Bar & Card Room (₹200 + 18% GST = ₹236)',
                                'badminton' => 'Badminton Court Daily Access (₹100 + 18% GST = ₹118)',
                                'gym' => 'Gym Daily Access (₹150 + 18% GST = ₹177)',
                            ])
                            ->default('bar_card_room')
                            ->required(),
                        Select::make('tender_type')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash in Drawer (₹236.00)',
                                'digital' => 'UPI / QR / EDC Card (₹236.00)',
                            ])
                            ->default('cash')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $facilityCode = $data['facility_code'];
                        $tariffCode = match ($facilityCode) {
                            'badminton' => 'GUEST-BADMINTON-ENTRY',
                            'gym' => 'GUEST-GYM-ENTRY',
                            default => 'GUEST-BAR-ENTRY',
                        };

                        $tariff = Tariff::where('code', $tariffCode)->first();
                        $ratePaise = $tariff ? $tariff->rate_paise : 20000;
                        $taxPercent = $tariff ? $tariff->tax_percent : 18.0;

                        // Ensure active shift exists
                        $shift = Shift::where('status', 'open')->first();
                        if (!$shift) {
                            Notification::make()
                                ->title('No Open Counter Shift')
                                ->body('Please open a counter shift before issuing and billing guest entry passes.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $billingService = app(BillingService::class);
                        $guestService = app(GuestService::class);

                        // 1. Create draft bill
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

                        // 2. Checkout
                        $tender = match ($data['tender_type']) {
                            'digital' => TenderType::DIGITAL,
                            default => TenderType::CASH,
                        };

                        $billingService->checkout(
                            bill: $bill,
                            allocations: [
                                ['tender_type' => $tender, 'amount_paise' => $bill->net_amount_paise]
                            ],
                            userId: auth()->id()
                        );

                        // 3. Activate guest pass
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
                            ->title("Guest Pass Issued: {$pass->pass_code}")
                            ->body("Paid ₹" . number_format($bill->net_amount_paise / 100, 2) . ". Guest admitted.")
                            ->success()
                            ->send();
                    }),

                Action::make('printPass')
                    ->label('Print Slip')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->visible(fn ($record) => $record->passes()->where('status', 'active')->exists())
                    ->modalHeading(fn ($record) => "Guest Entry Badge — {$record->person?->first_name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function ($record) {
                        $pass = $record->passes()->where('status', 'active')->latest()->first();
                        return view('filament.components.guest-pass-modal', [
                            'guest' => $record,
                            'pass' => $pass,
                        ]);
                    }),

                \Filament\Actions\EditAction::make(),
            ]);
    }
}

