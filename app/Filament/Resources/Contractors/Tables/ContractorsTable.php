<?php

namespace App\Filament\Resources\Contractors\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('name')
                    ->label('Contractor / Vendor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->contact_person ? "Contact: {$record->contact_person}" : null),

                TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'food' => 'success',
                        'bar' => 'warning',
                        'maintenance' => 'info',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'food' => 'Food & Restaurant',
                        'bar' => 'Bar & Beverage',
                        'maintenance' => 'Maintenance',
                        'security' => 'Security',
                        default => ucfirst((string) $state),
                    }),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('bank_details')
                    ->label('Bank Payout Account')
                    ->state(function ($record) {
                        if (!$record->bank_name && !$record->bank_account_number) {
                            return '—';
                        }
                        return "{$record->bank_name} • {$record->bank_account_number}" . ($record->bank_ifsc ? " ({$record->bank_ifsc})" : '');
                    })
                    ->fontFamily('mono')
                    ->size('xs'),

                TextColumn::make('commission_rate_percent')
                    ->label('Commission')
                    ->state(fn ($record) => number_format((float) $record->commission_rate_percent, 2) . '%')
                    ->sortable(),

                TextColumn::make('settlement_batches_count')
                    ->counts('settlementBatches')
                    ->label('Batches Settled')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active Only',
                        '0' => 'Inactive Only',
                    ]),

                SelectFilter::make('department_code')
                    ->label('Department')
                    ->options([
                        'food' => 'Food & Restaurant Catering',
                        'bar' => 'Bar & Beverage Partner',
                        'maintenance' => 'Maintenance',
                        'security' => 'Security',
                        'general' => 'General Services',
                    ]),
            ])
            ->recordActions([
                Action::make('viewSettlements')
                    ->label('Settlements')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.contractor-settlements.contractor-settlement-batches.index', [
                        'tableFilters' => ['contractor_id' => ['value' => $record->id]],
                    ])),

                EditAction::make(),

                DeleteAction::make()
                    ->label('Delete Unused')
                    ->color('danger')
                    ->visible(fn ($record) => $record->settlementBatches()->doesntExist())
                    ->requiresConfirmation()
                    ->modalHeading('Delete Unused Contractor Profile')
                    ->modalDescription('Only contractor profiles with zero settlement batches or past payout history can be removed. Active contractors with financial transactions must be deactivated instead to preserve accounting integrity.')
                    ->successNotificationTitle('Contractor record removed successfully.'),
            ]);
    }
}

