<?php

namespace App\Filament\Resources\JournalHeaders\Tables;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JournalHeadersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('journal_number')
                    ->label('Journal #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('business_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'recharge' => 'success',
                        'bill' => 'info',
                        'refund' => 'warning',
                        'contractor_settlement' => 'primary',
                        'inventory_receipt' => 'info',
                        'expense' => 'danger',
                        'opening_balance' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(35),

                TextColumn::make('total_debits')
                    ->label('Voucher Amount')
                    ->state(fn ($record) => '₹' . number_format($record->totalDebits() / 100, 2))
                    ->weight('bold')
                    ->color('primary'),

                IconColumn::make('is_balanced')
                    ->label('Balanced')
                    ->state(fn ($record) => $record->isBalanced())
                    ->boolean(),

                TextColumn::make('creator.name')
                    ->label('Posted By')
                    ->searchable()
                    ->default('System Ledger'),

                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M H:i'),
            ])
            ->filters([
                SelectFilter::make('source_type')
                    ->searchable()
                    ->options([
                        'recharge' => 'Recharge',
                        'bill' => 'Sales Bill',
                        'refund' => 'Refund',
                        'contractor_settlement' => 'Contractor Settlement',
                        'inventory_receipt' => 'Stock Inward Purchase',
                        'expense' => 'Operating Expense',
                        'opening_balance' => 'Opening Balance Migration',
                        'manual_journal' => 'Manual Journal',
                    ]),
            ])
            ->recordActions([
                Action::make('viewVoucher')
                    ->label('View Voucher')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->modalHeading(fn ($record) => "Journal Voucher — {$record->journal_number}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function ($record) {
                        $lines = $record->lines()->with('account')->get();
                        return view('filament.components.journal-voucher-modal', [
                            'journal' => $record,
                            'lines' => $lines,
                        ]);
                    }),
            ]);
    }
}
