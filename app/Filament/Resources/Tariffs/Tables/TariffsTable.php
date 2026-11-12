<?php

namespace App\Filament\Resources\Tariffs\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TariffsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('servingMapping.inventoryItem'))
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Item / Service Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                \Filament\Tables\Columns\TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color('primary'),
                \Filament\Tables\Columns\TextColumn::make('stock_link')
                    ->label('Stock Deduction Link')
                    ->badge()
                    ->state(function ($record): string {
                        $mapping = $record->servingMapping;
                        $item = $mapping?->inventoryItem;

                        if ($mapping && $item) {
                            $qty = (float) $mapping->serving_qty;
                            $decimals = fmod($qty, 1.0) === 0.0 ? 0 : 3;
                            $qtyFmt = number_format($qty, $decimals);
                            $prefix = $item->is_active ? '' : 'Inactive Stock: ';

                            return "{$prefix}{$item->name} (-{$qtyFmt} {$item->base_unit})";
                        }

                        if ($record->department_code === 'bar') {
                            return 'Needs Stock Link';
                        }

                        return 'Service / Non-Stock';
                    })
                    ->color(function ($record): string {
                        $item = $record->servingMapping?->inventoryItem;
                        if ($item) {
                            return $item->is_active ? 'success' : 'danger';
                        }

                        return $record->department_code === 'bar' ? 'warning' : 'gray';
                    })
                    ->description(fn ($record): ?string => $record->servingMapping?->serving_name),
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
                SelectFilter::make('department_code')
                    ->searchable()
                    ->options([
                        'membership' => 'Membership',
                        'bar' => 'Bar & Lounge',
                        'card_room' => 'Card Room',
                        'food' => 'Food & Restaurant',
                        'accommodation' => 'Accommodation',
                        'sports' => 'Sports',
                    ]),
                Filter::make('unlinked_bar')
                    ->label('Bar Items Missing Stock Link')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('department_code', 'bar')
                        ->doesntHave('servingMapping')),
                Filter::make('stock_linked')
                    ->label('Stock-Linked Items Only')
                    ->query(fn (Builder $query): Builder => $query->has('servingMapping')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
