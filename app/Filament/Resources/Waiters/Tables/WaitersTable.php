<?php

namespace App\Filament\Resources\Waiters\Tables;

use App\Domain\Billing\Models\Waiter;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WaitersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Badge #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Bearer Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bar' => 'Bar & Lounge',
                        'food' => 'Food & Dining',
                        'card_room' => 'Card Room',
                        default => 'General Floor',
                    })
                    ->color(fn ($state) => match ($state) {
                        'bar' => 'warning',
                        'food' => 'success',
                        'card_room' => 'primary',
                        default => 'secondary',
                    }),

                TextColumn::make('tips_total')
                    ->label('Total Tips Earned')
                    ->state(fn (Waiter $record) => '₹' . number_format($record->tipsTotalPaise() / 100, 2))
                    ->badge()
                    ->color('success'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_code')
                    ->label('Department')
                    ->options([
                        'bar' => 'Bar & Lounge',
                        'food' => 'Food & Dining',
                        'card_room' => 'Card Room',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Edit Bearer Details'),
            ]);
    }
}

