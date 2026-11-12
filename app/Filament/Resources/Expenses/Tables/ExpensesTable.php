<?php

namespace App\Filament\Resources\Expenses\Tables;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('expense_number')
                    ->label('Expense #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('account.name')
                    ->label('Expense Category')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->color('gray')
                    ->default('General Clubhouse'),

                TextColumn::make('payee')
                    ->label('Payee / Supplier')
                    ->searchable(),

                TextColumn::make('amount_paise')
                    ->label('Amount Paid')
                    ->state(fn ($record) => '₹' . number_format($record->amount_paise / 100, 2))
                    ->sortable()
                    ->weight('bold')
                    ->color('danger'),

                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'bank_transfer' => 'success',
                        'cheque' => 'info',
                        'cash' => 'warning',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bank_transfer' => 'Bank Transfer (NEFT/RTGS)',
                        'cheque' => 'Cheque',
                        'cash' => 'Counter Cash',
                        default => ucfirst($state),
                    })
                    ->description(fn ($record) => $record->bankAccount ? ($record->bankAccount->bank_name . ' • ' . $record->bankAccount->account_number) : null),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'paid' => 'success',
                        'approved' => 'info',
                        'void' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),

                SelectFilter::make('payment_method')
                    ->options([
                        'bank_transfer' => 'Bank Transfer (NEFT/RTGS)',
                        'cheque' => 'Cheque',
                        'cash' => 'Counter Cash',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'approved' => 'Approved',
                        'void' => 'Void',
                    ]),
            ])
            ->recordActions([
                Action::make('viewVoucher')
                    ->label('Voucher')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->modalHeading(fn ($record) => "Expense Payment Voucher — {$record->expense_number}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function ($record) {
                        return view('filament.components.expense-voucher-modal', [
                            'expense' => $record,
                        ]);
                    }),
            ]);
    }
}
