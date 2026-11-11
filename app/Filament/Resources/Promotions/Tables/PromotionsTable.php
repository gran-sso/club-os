<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Domain\Billing\Models\Promotion;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label('Offer Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('rule_badge')
                    ->label('Deal Rule')
                    ->state(fn (Promotion $record) => $record->badgeLabel())
                    ->badge()
                    ->color('warning'),

                TextColumn::make('customer_scope')
                    ->label('Eligibility')
                    ->state(fn (Promotion $record) => $record->customer_scope === Promotion::SCOPE_ALL
                        ? 'All Customers'
                        : 'Members & Rooms')
                    ->badge()
                    ->color('info'),

                TextColumn::make('tariffs_count')
                    ->label('Items')
                    ->counts('tariffs')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('quota')
                    ->label('Free Quota')
                    ->state(fn (Promotion $record) => $record->max_free_units !== null
                        ? "{$record->redeemed_free_units} / {$record->max_free_units}"
                        : "{$record->redeemed_free_units} (No cap)")
                    ->fontFamily('mono'),

                TextColumn::make('effective_from')
                    ->label('Valid From')
                    ->date('d M Y')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),

                SelectFilter::make('customer_scope')
                    ->label('Eligibility Scope')
                    ->options([
                        Promotion::SCOPE_MEMBERS_ONLY => 'Members & Room Guests Only',
                        Promotion::SCOPE_ALL => 'All Customers',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}

