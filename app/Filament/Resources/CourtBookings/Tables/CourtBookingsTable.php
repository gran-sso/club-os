<?php

namespace App\Filament\Resources\CourtBookings\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourtBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking_number')
                    ->label('Booking #')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('facility.name')
                    ->label('Court')
                    ->searchable(),
                TextColumn::make('booking_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Start')
                    ->time('H:i'),
                TextColumn::make('end_time')
                    ->label('End')
                    ->time('H:i'),
                TextColumn::make('member.membership_number')
                    ->label('Member #')
                    ->searchable()
                    ->badge(),
                IconColumn::make('is_plan_covered')
                    ->label('Plan Covered')
                    ->boolean(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'confirmed' => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
