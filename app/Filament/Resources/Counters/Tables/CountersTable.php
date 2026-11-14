<?php

namespace App\Filament\Resources\Counters\Tables;

use App\Domain\Billing\Models\Counter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CountersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Station Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Counter Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('department.name')
                    ->label('Home Department')
                    ->badge()
                    ->placeholder('General / Multi-Department')
                    ->color(fn ($state) => match ($state) {
                        'Bar & Lounge' => 'warning',
                        'Food & Restaurant' => 'success',
                        'Membership & Front Desk' => 'primary',
                        default => 'secondary',
                    })
                    ->sortable(),

                TextColumn::make('active_shift')
                    ->label('Current Shift')
                    ->badge()
                    ->state(function (Counter $record): string {
                        $shift = $record->currentOpenShift();
                        if (! $shift) {
                            return 'Closed';
                        }
                        return "Open: Shift #{$shift->shift_number} (" . ($shift->user?->name ?? 'Staff') . ')';
                    })
                    ->color(fn (string $state): string => str_starts_with($state, 'Open') ? 'success' : 'gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),

                TernaryFilter::make('is_active')
                    ->label('Operational Status')
                    ->placeholder('All Counters')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make()
                    ->modalHeading(fn (Counter $record) => "Edit Billing Counter: {$record->name}")
                    ->modalDescription('Update station identifier, display name, department association, or operational status.'),
            ]);
    }
}

