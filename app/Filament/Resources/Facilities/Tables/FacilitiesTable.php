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
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
