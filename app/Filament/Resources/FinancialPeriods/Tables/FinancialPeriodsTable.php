<?php

namespace App\Filament\Resources\FinancialPeriods\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FinancialPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_code')
                    ->label('Period Code')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'closed' => 'danger',
                        'locked' => 'warning',
                        'open' => 'success',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'closed' => 'Closed (Locked)',
                        'locked' => 'Locked (Auditing)',
                        'open' => 'Open',
                        default => ucfirst($state),
                    }),

                TextColumn::make('closed_at')
                    ->label('Closed At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—'),

                TextColumn::make('closedBy.name')
                    ->label('Closed By')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'locked' => 'Locked',
                        'closed' => 'Closed',
                    ]),
            ])
            ->recordActions([
                Action::make('closePeriod')
                    ->label('Close Period')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => "Close Financial Period {$record->period_code}")
                    ->modalDescription('Closing this period will permanently lock all accounting transactions within this date range. No backdated bills or expenses can be posted.')
                    ->visible(fn ($record) => $record->status !== 'closed')
                    ->action(function ($record) {
                        $service = app(\App\Domain\Finance\Services\PeriodClosingService::class);
                        $service->closePeriod(
                            periodCode: $record->period_code,
                            startDate: $record->start_date->toDateString(),
                            endDate: $record->end_date->toDateString(),
                            notes: "Period closed by " . auth()->user()?->name,
                            user: auth()->user()
                        );

                        Notification::make()
                            ->title("Period {$record->period_code} Closed")
                            ->body("Strict lock activated. Backdating into this period is strictly forbidden.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ]);
    }
}
