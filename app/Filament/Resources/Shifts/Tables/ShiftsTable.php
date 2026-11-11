<?php

namespace App\Filament\Resources\Shifts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('shift_number')
                    ->label('Shift #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                \Filament\Tables\Columns\TextColumn::make('counter.name')
                    ->label('Counter')
                    ->badge()
                    ->color('primary'),
                \Filament\Tables\Columns\TextColumn::make('user.name')
                    ->label('Cashier')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('business_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'open' => 'success',
                        'closed' => 'gray',
                        default => 'warning',
                    }),
                \Filament\Tables\Columns\TextColumn::make('opening_float_paise')
                    ->label('Opening Float')
                    ->state(fn ($record) => '₹' . number_format($record->opening_float_paise / 100, 2)),
                \Filament\Tables\Columns\TextColumn::make('expected_cash_paise')
                    ->label('Expected Cash')
                    ->state(fn ($record) => $record->expected_cash_paise !== null ? ('₹' . number_format($record->expected_cash_paise / 100, 2)) : '-'),
                \Filament\Tables\Columns\TextColumn::make('closing_cash_counted_paise')
                    ->label('Counted Cash')
                    ->state(fn ($record) => $record->closing_cash_counted_paise !== null ? ('₹' . number_format($record->closing_cash_counted_paise / 100, 2)) : '-'),
                \Filament\Tables\Columns\TextColumn::make('discrepancy_paise')
                    ->label('Discrepancy')
                    ->state(fn ($record) => $record->discrepancy_paise !== null ? ('₹' . number_format($record->discrepancy_paise / 100, 2)) : '-')
                    ->badge()
                    ->color(fn ($state, $record) => $record->discrepancy_paise === 0 ? 'success' : ($record->discrepancy_paise === null ? 'gray' : 'danger')),
                \Filament\Tables\Columns\TextColumn::make('opened_at')
                    ->label('Opened At')
                    ->dateTime('d M H:i'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('closeShift')
                    ->label('Close Shift')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedLockClosed)
                    ->color('danger')
                    ->visible(fn ($record) => ($record->status?->value ?? $record->status) === 'open' && (bool) auth()->user()?->hasClubPermission('shifts.manage'))
                    ->form([
                        \Filament\Forms\Components\Placeholder::make('expected_display')
                            ->label('Current System Expected Cash')
                            ->content(fn ($record) => '₹' . number_format($record->calculateExpectedCashPaise() / 100, 2)),
                        \Filament\Forms\Components\TextInput::make('counted_cash')
                            ->label('Actual Counted Cash in Till (₹)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('₹')
                            ->placeholder('e.g. 2500.00'),
                        \Filament\Forms\Components\Textarea::make('close_notes')
                            ->label('Closing & Handover Notes')
                            ->placeholder('Cash drop reference or reasons for discrepancy if any.'),
                    ])
                    ->action(function ($record, array $data) {
                        abort_unless((bool) auth()->user()?->hasClubPermission('shifts.manage'), 403);
                        $service = app(\App\Domain\Billing\Services\ShiftService::class);
                        $countedPaise = (int) round(((float) $data['counted_cash']) * 100);
                        $service->closeShift($record, $countedPaise, $data['close_notes'] ?? null);

                        \Filament\Notifications\Notification::make()
                            ->title("Shift #{$record->shift_number} Closed")
                            ->body("Shift reconciled. Counted: ₹" . number_format($countedPaise / 100, 2))
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
