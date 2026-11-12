<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Domain\Billing\Services\BillingService;
use App\Domain\Shared\Enums\BillStatus;
use App\Filament\Resources\Bills\BillResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditBill extends EditRecord
{
    protected static string $resource = BillResource::class;

    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $bill = $this->getRecord();
        $data['lines'] = $bill->lines->map(fn ($line) => [
            'item_name' => $line->item_name,
            'department_code' => $line->department_code,
            'quantity' => (float) $line->quantity,
            'unit_price' => round($line->unit_price_paise / 100, 2),
            'tax_percent' => (string) (int) $line->tax_percent,
        ])->toArray();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        $bill = $this->getRecord();
        $status = $bill->status?->value ?? $bill->status;

        $actions = [
            Action::make('viewReceipt')
                ->label('View Tax Invoice')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedEye)
                ->color('gray')
                ->modalHeading(fn (): string => 'Tax Invoice: ' . ($this->getRecord()?->bill_number ?? $bill->bill_number))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function () {
                    $record = $this->getRecord();
                    $freshBill = $record->fresh(['lines', 'receipts', 'customer'])
                        ?? $record->load(['lines', 'receipts', 'customer']);

                    return view('filament.components.bill-detail-modal', [
                        'bill' => $freshBill,
                    ]);
                }),
        ];

        // Settle Draft Bill action
        if ($status === 'draft') {
            $actions[] = Action::make('settleBill')
                ->label('Settle & Post Draft Bill')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Select::make('tender_type')
                        ->label('Payment Method')
                        ->required()
                        ->options([
                            'prepaid' => '💳 Member Prepaid Wallet (Server Ledger)',
                            'cash' => '💵 Counter Cash (Till Drawer)',
                            'digital' => '📱 Digital Payment (UPI / QR / Card POS)',
                        ])
                        ->default('prepaid'),
                ])
                ->action(function (array $data) use ($bill) {
                    $service = app(BillingService::class);
                    $tenders = [
                        [
                            'tender_type' => $data['tender_type'],
                            'amount_paise' => $bill->net_amount_paise,
                            'reference' => 'DRAFT-SETTLEMENT',
                        ],
                    ];

                    $service->checkout($bill, $tenders, auth()->id());
                    $bill->refresh();

                    Notification::make()
                        ->title("Invoice Settled: {$bill->bill_number}")
                        ->body("Payment confirmed and tax invoice posted to general ledger.")
                        ->success()
                        ->send();

                    $this->fillForm();
                });
        }

        // Refund action for paid bills
        if ($status === 'paid' && auth()->user()?->hasClubPermission('refunds.approve')) {
            $actions[] = Action::make('refund')
                ->label('Authorize Refund')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\TextInput::make('reason')
                        ->label('Manager Authorization Reason')
                        ->required()
                        ->placeholder('e.g. Customer returned unopened beverage / disputed service'),
                    \Filament\Forms\Components\Toggle::make('refund_to_wallet')
                        ->label('Credit Customer Prepaid Wallet')
                        ->default(true)
                        ->helperText('If enabled, restores customer prepaid balance. If disabled, settles via physical cash payout.'),
                ])
                ->action(function (array $data) use ($bill) {
                    $service = app(BillingService::class);
                    $refund = $service->refund(
                        bill: $bill,
                        amountPaise: $bill->net_amount_paise,
                        reason: $data['reason'],
                        approver: auth()->user(),
                        refundToWallet: $data['refund_to_wallet']
                    );

                    Notification::make()
                        ->title("Refund Authorized: {$refund->refund_number}")
                        ->body("Full refund of ₹" . number_format($bill->net_amount_paise / 100, 2) . " processed successfully.")
                        ->success()
                        ->send();

                    $this->fillForm();
                });
        }

        return $actions;
    }

    protected function getFormActions(): array
    {
        $status = $this->getRecord()->status?->value ?? $this->getRecord()->status;

        // Posted bills are immutable under Rule F14; no form save button
        if ($status !== 'draft') {
            return [];
        }

        return parent::getFormActions();
    }
}
