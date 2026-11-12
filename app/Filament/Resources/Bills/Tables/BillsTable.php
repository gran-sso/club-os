<?php

namespace App\Filament\Resources\Bills\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class BillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('bill_number')
                    ->label('Bill #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                \Filament\Tables\Columns\TextColumn::make('customer_id')
                    ->label('Customer')
                    ->state(fn ($record) => class_basename($record->customer_type) . ' #' . $record->customer_id)
                    ->badge()
                    ->color('info'),
                \Filament\Tables\Columns\TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color('primary'),
                \Filament\Tables\Columns\TextColumn::make('gross_amount_paise')
                    ->label('Gross')
                    ->state(fn ($record) => '₹' . number_format($record->gross_amount_paise / 100, 2))
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('tax_amount_paise')
                    ->label('GST')
                    ->state(fn ($record) => '₹' . number_format($record->tax_amount_paise / 100, 2))
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('net_amount_paise')
                    ->label('Net Total')
                    ->state(fn ($record) => '₹' . number_format($record->net_amount_paise / 100, 2))
                    ->weight('bold')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'paid' => 'success',
                        'draft' => 'warning',
                        'refunded' => 'danger',
                        'voided' => 'gray',
                        default => 'secondary',
                    }),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'draft' => 'Draft',
                        'refunded' => 'Refunded',
                        'voided' => 'Voided',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('department_code')
                    ->searchable()
                    ->options([
                        'bar' => 'Bar & Lounge',
                        'card_room' => 'Card Room',
                        'food' => 'Food & Restaurant',
                        'accommodation' => 'Accommodation',
                        'sports' => 'Sports',
                    ]),
            ])
            ->recordUrl(fn ($record) => \App\Filament\Resources\Bills\BillResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                \Filament\Actions\EditAction::make()
                    ->label('Invoice Details')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedDocumentText),

                \Filament\Actions\Action::make('viewBill')
                    ->label('Print Preview')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn ($record) => "Tax Invoice / Bill: {$record->bill_number}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function ($record) {
                        return view('filament.components.bill-detail-modal', ['bill' => $record->load(['lines', 'receipts', 'customer'])]);
                    }),

                \Filament\Actions\Action::make('refund')
                    ->label('Authorize Refund')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn ($record) => ($record->status?->value ?? $record->status) === 'paid' && auth()->user()?->hasClubPermission('refunds.approve'))
                    ->form([
                        \Filament\Forms\Components\TextInput::make('reason')
                            ->label('Manager Authorization Reason')
                            ->required()
                            ->placeholder('e.g. Returned unopened bottle / Disputed service charge'),
                        \Filament\Forms\Components\Toggle::make('refund_to_wallet')
                            ->label('Credit to Customer Prepaid Wallet')
                            ->default(true)
                            ->helperText('If enabled, restores customer prepaid balance. If disabled, settles via physical cash payout.'),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(\App\Domain\Billing\Services\BillingService::class);
                        $refund = $service->refund(
                            bill: $record,
                            amountPaise: $record->net_amount_paise,
                            reason: $data['reason'],
                            approver: auth()->user(),
                            refundToWallet: $data['refund_to_wallet']
                        );

                        \Filament\Notifications\Notification::make()
                            ->title("Refund Authorized: {$refund->refund_number}")
                            ->body("Full refund of ₹" . number_format($record->net_amount_paise / 100, 2) . " processed.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
