<?php

namespace App\Filament\Resources\Inventory\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable(),
                TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('current_stock_qty')
                    ->label('Stock Qty')
                    ->state(fn ($record) => number_format($record->current_stock_qty, $record->base_unit === 'ml' ? 0 : 2) . ' ' . $record->base_unit)
                    ->sortable()
                    ->weight('bold')
                    ->color(fn ($record) => $record->current_stock_qty <= $record->reorder_level ? 'danger' : 'success'),
                TextColumn::make('current_avg_cost_paise')
                    ->label('Moving Avg Cost')
                    ->state(fn ($record) => '₹' . number_format($record->current_avg_cost_paise / 100, 2) . '/' . $record->base_unit)
                    ->sortable(),
                TextColumn::make('reorder_level')
                    ->label('Reorder Level')
                    ->state(fn ($record) => number_format($record->reorder_level, 0) . ' ' . $record->base_unit)
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'liquor' => 'Spirits & Liquor',
                        'beer_wine' => 'Beer & Wine',
                        'beverages' => 'Beverages & Soft Drinks',
                        'tobacco' => 'Tobacco',
                        'consumables' => 'Consumables',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
