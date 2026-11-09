<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FacilitySubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subscription_number')
                    ->label('Plan #')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('member.membership_number')
                    ->label('Member #')
                    ->searchable()
                    ->badge(),
                TextColumn::make('facility_code')
                    ->label('Facility')
                    ->badge()
                    ->color('info'),
                TextColumn::make('plan_type')
                    ->label('Plan Type')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends (Exclusive)')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('total_fee_paise')
                    ->label('Fee')
                    ->state(fn ($record) => '₹' . number_format($record->total_fee_paise / 100, 2))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'gray',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    }),
            ]);
    }
}
