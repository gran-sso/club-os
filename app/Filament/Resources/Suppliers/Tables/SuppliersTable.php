<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Supplier Code')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('name')
                    ->label('Wholesale Supplier / Agency')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->contact_person ? "Contact: {$record->contact_person}" : null),

                TextColumn::make('gstin')
                    ->label('GSTIN')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->placeholder('Unregistered / NA'),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('purchase_receipts_count')
                    ->label('Receipts')
                    ->counts('purchaseReceipts')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active Suppliers Only')
                    ->falseLabel('Inactive / Blocked Suppliers'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading('Delete Supplier Record')
                    ->modalDescription('Are you sure? Suppliers with historical purchase receipts cannot be deleted to preserve the audit trail.')
                    ->before(function ($record, DeleteAction $action) {
                        if ($record->purchaseReceipts()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Deletion Prohibited')
                                ->body("Supplier #{$record->code} cannot be deleted because historical inward purchase receipts are linked to it. Mark as Inactive instead.")
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }
}

