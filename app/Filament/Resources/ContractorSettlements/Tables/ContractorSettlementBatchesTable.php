<?php

namespace App\Filament\Resources\ContractorSettlements\Tables;

use App\Domain\Contractor\Services\ContractorSettlementService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractorSettlementBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Batch #')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('contractor.name')
                    ->label('Contractor')
                    ->searchable(),
                TextColumn::make('business_date')
                    ->label('Business Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('total_food_sales_net_paise')
                    ->label('Net Food Sales')
                    ->state(fn ($record) => '₹' . number_format($record->total_food_sales_net_paise / 100, 2))
                    ->sortable(),
                TextColumn::make('total_adjustments_paise')
                    ->label('Adjustments')
                    ->state(fn ($record) => '₹' . number_format($record->total_adjustments_paise / 100, 2))
                    ->color(fn ($record) => $record->total_adjustments_paise < 0 ? 'danger' : 'gray'),
                TextColumn::make('net_payable_paise')
                    ->label('Net Payable')
                    ->state(fn ($record) => '₹' . number_format($record->net_payable_paise / 100, 2))
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('paid_amount_paise')
                    ->label('Paid')
                    ->state(fn ($record) => '₹' . number_format($record->paid_amount_paise / 100, 2)),
                TextColumn::make('residual_payable_paise')
                    ->label('Residual')
                    ->state(fn ($record) => '₹' . number_format($record->residual_payable_paise / 100, 2))
                    ->color(fn ($record) => $record->residual_payable_paise > 0 ? 'warning' : 'success'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'paid' => 'success',
                        'approved' => 'info',
                        'draft' => 'warning',
                        'partially_paid' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Contractor')
                    ->relationship('contractor', 'name')
                    ->searchable()
                    ->preload(),
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'partially_paid' => 'Partially Paid',
                        'paid' => 'Paid',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve Batch')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Contractor Settlement Batch')
                    ->modalDescription('Approve this food settlement batch? This will post the contractor food expense and liability to the general ledger.')
                    ->action(function ($record) {
                        $service = app(ContractorSettlementService::class);
                        $service->approveSettlementBatch($record, auth()->user());
                        Notification::make()
                            ->title("Batch {$record->batch_number} Approved")
                            ->body('Financial journal posted to Contractor Food Expense & Payable accounts.')
                            ->success()
                            ->send();
                    }),

                Action::make('recordPayout')
                    ->label('Record Payout')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('primary')
                    ->visible(fn ($record) => in_array($record->status, ['approved', 'partially_paid']) && $record->residual_payable_paise > 0)
                    ->form([
                        Select::make('bank_account_id')
                            ->label('Disbursing Club Bank Account')
                            ->options(fn () => \App\Domain\Finance\Models\BankAccount::where('is_active', true)->get()->mapWithKeys(
                                fn ($b) => [$b->id => "{$b->account_name} — {$b->bank_name} ({$b->account_number}) • Bal: ₹" . number_format($b->current_balance, 2)]
                            ))
                            ->default(fn () => \App\Domain\Finance\Models\BankAccount::where('is_primary', true)->first()?->id)
                            ->searchable()
                            ->preload()
                            ->helperText('Club account from which the contractor settlement is paid.'),
                        Select::make('payment_mode')
                            ->label('Payment Instrument')
                            ->options([
                                'NEFT' => 'NEFT Bank Transfer',
                                'RTGS' => 'RTGS Bank Transfer',
                                'CHEQUE' => 'Account Payee Cheque',
                                'IMPS' => 'IMPS / Online Transfer',
                            ])
                            ->default('NEFT')
                            ->required(),
                        TextInput::make('payment_reference')
                            ->label('Bank UTR / Cheque Number')
                            ->required()
                            ->placeholder('e.g. UTR123456789 or CHQ-004291'),
                        TextInput::make('amount_rupees')
                            ->label('Payout Amount (₹)')
                            ->numeric()
                            ->required()
                            ->default(fn ($record) => $record->residual_payable_paise / 100)
                            ->helperText(fn ($record) => 'Residual payable: ₹' . number_format($record->residual_payable_paise / 100, 2)),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(ContractorSettlementService::class);
                        $amountPaise = (int) ($data['amount_rupees'] * 100);
                        $reference = $data['payment_mode'] . ': ' . $data['payment_reference'];
                        $service->confirmSettlementPayment($record, $amountPaise, $reference, auth()->id());

                        if (!empty($data['bank_account_id'])) {
                            $bankAccount = \App\Domain\Finance\Models\BankAccount::find($data['bank_account_id']);
                            if ($bankAccount) {
                                app(\App\Domain\Finance\Services\BankingService::class)->recordDisbursement(
                                    bankAccount: $bankAccount,
                                    amountPaise: $amountPaise,
                                    type: 'contractor_payout',
                                    description: "Contractor Payout: " . ($record->contractor?->name ?? 'Contractor') . " [Batch {$record->batch_number}]",
                                    businessDate: today()->toDateString(),
                                    referenceNumber: $reference,
                                    userId: auth()->id()
                                );
                            }
                        }

                        Notification::make()
                            ->title("Payout Recorded")
                            ->body("Settlement payment of ₹" . number_format($data['amount_rupees'], 2) . " logged against batch {$record->batch_number}.")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}

