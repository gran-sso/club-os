<?php

namespace App\Filament\Resources\RoomReservations\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reservation_number')
                    ->label('Reservation #')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('facility.name')
                    ->label('Room')
                    ->searchable(),
                TextColumn::make('check_in_date')
                    ->label('Check-In')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('check_out_date')
                    ->label('Check-Out')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('total_charge_paise')
                    ->label('Total Charge')
                    ->state(fn ($record) => '₹' . number_format($record->total_charge_paise / 100, 2))
                    ->sortable(),
                TextColumn::make('paid_amount_paise')
                    ->label('Paid')
                    ->state(fn ($record) => '₹' . number_format($record->paid_amount_paise / 100, 2)),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->state(fn ($record) => $record->isFullyPaid() ? 'Fully Paid' : 'Deposit / Unpaid')
                    ->color(fn ($record) => $record->isFullyPaid() ? 'success' : 'danger'),
                TextColumn::make('housekeeping_status')
                    ->label('Housekeeping')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'clean' => 'success',
                        'dirty' => 'warning',
                        'maintenance' => 'danger',
                        default => 'secondary',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'checked_in' => 'success',
                        'reserved' => 'info',
                        'checked_out' => 'gray',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
