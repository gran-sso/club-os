<?php

namespace App\Filament\Resources\Tariffs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class TariffsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Tariff Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                \Filament\Tables\Columns\TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color('primary'),
                \Filament\Tables\Columns\TextColumn::make('rate_paise')
                    ->label('Rate')
                    ->state(fn ($record) => '₹' . number_format($record->rate_paise / 100, 2))
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('tax_percent')
                    ->label('GST %')
                    ->state(fn ($record) => $record->tax_percent . '%')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('effective_from')
                    ->label('Effective From')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('department_code')
                    ->searchable()
                    ->options([
                        'membership' => 'Membership',
                        'bar' => 'Bar & Lounge',
                        'card_room' => 'Card Room',
                        'food' => 'Food & Restaurant',
                        'accommodation' => 'Accommodation',
                        'sports' => 'Sports',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
