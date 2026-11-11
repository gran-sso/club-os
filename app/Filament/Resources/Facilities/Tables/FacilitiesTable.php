<?php

namespace App\Filament\Resources\Facilities\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FacilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('Facility Name')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'room' => 'info',
                        'court' => 'success',
                        default => 'primary',
                    }),
                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('housekeeping_status')
                    ->label('Room Status')
                    ->badge()
                    ->state(fn ($record): string => $record->type === 'room' ? ucfirst($record->housekeeping_status ?? 'clean') : '—')
                    ->color(fn ($record, string $state): string => match (true) {
                        $record->type !== 'room' => 'gray',
                        strtolower($state) === 'dirty' => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'room' => 'Accommodation Rooms',
                        'court' => 'Sports Courts',
                        'gym' => 'Gymnasium',
                        'bar' => 'Bar & Lounge',
                        'card_room' => 'Card Room',
                        'general' => 'General Infrastructure',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make()
                    ->after(function ($record): void {
                        if ($record->type === 'room' && $record->wasChanged('housekeeping_status')) {
                            $status = in_array($record->housekeeping_status, ['clean', 'dirty'], true)
                                ? $record->housekeeping_status
                                : 'clean';

                            app(\App\Domain\Facility\Services\RoomReservationService::class)
                                ->updateRoomOperationalStatus(
                                    facilityId: $record->id,
                                    targetStatus: $status,
                                    dateStr: now()->toDateString(),
                                    user: auth()->user(),
                                );
                        }
                    }),
            ]);
    }
}
