<?php

namespace App\Filament\Resources\Purchases\Tables;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('supplier.name')
                    ->label('Wholesale Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_number')
                    ->label('Supplier Invoice #')
                    ->searchable(),

                TextColumn::make('invoice_date')
                    ->label('Invoice Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('total_net_paise')
                    ->label('Net Amount')
                    ->state(fn ($record) => '₹' . number_format($record->total_net_paise / 100, 2))
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'received' => 'success',
                        'verified' => 'info',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options([
                        'received' => 'Received',
                        'verified' => 'Verified',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                Action::make('viewReceipt')
                    ->label('View Purchase Bill')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('primary')
                    ->modalHeading(fn ($record) => "Stock Purchase Receipt — {$record->receipt_number}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function ($record) {
                        $lines = $record->lines()->with('item')->get();
                        return view('filament.components.purchase-receipt-modal', [
                            'receipt' => $record,
                            'lines' => $lines,
                        ]);
                    }),
            ]);
    }
}
