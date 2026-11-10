<?php

namespace App\Filament\Resources\MemberNotices\Tables;

use App\Domain\Communication\Models\MemberNotice;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MemberNoticesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Notice Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (MemberNotice $record) => \Illuminate\Support\Str::limit($record->content, 60)),

                TextColumn::make('department')
                    ->label('Department')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'accounts' => 'Accounts & Dues',
                        'reception' => 'Reception',
                        'sports' => 'Sports',
                        'food' => 'Dining / F&B',
                        default => 'Administration',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'accounts' => 'warning',
                        'reception' => 'info',
                        'sports' => 'success',
                        'food' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'urgent' => 'danger',
                        'reminder' => 'warning',
                        'event' => 'success',
                        default => 'info',
                    })
                    ->sortable(),

                TextColumn::make('target_type')
                    ->label('Audience')
                    ->badge()
                    ->formatStateUsing(function (string $state, MemberNotice $record) {
                        return match ($state) {
                            'dues_pending' => '⚠️ Pending Dues',
                            'category' => '🏷️ ' . ($record->targetCategory?->name ?? 'Category'),
                            'individual' => '👤 ' . ($record->targetMember?->name ?? 'Member'),
                            default => '📢 All Members',
                        };
                    })
                    ->color(fn (string $state) => match ($state) {
                        'dues_pending' => 'danger',
                        'individual' => 'primary',
                        'category' => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_banner')
                    ->label('Banner')
                    ->boolean()
                    ->trueIcon('heroicon-o-megaphone')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('department')
                    ->options([
                        'admin' => 'Administration',
                        'accounts' => 'Accounts & Dues',
                        'reception' => 'Reception',
                        'sports' => 'Sports',
                        'food' => 'Dining / F&B',
                    ]),

                SelectFilter::make('priority')
                    ->options([
                        'info' => 'General Info',
                        'reminder' => 'Payment Reminder',
                        'urgent' => 'Urgent Alert',
                        'event' => 'Event / Celebration',
                    ]),

                SelectFilter::make('target_type')
                    ->options([
                        'all' => 'All Members',
                        'dues_pending' => 'Pending Dues Only',
                        'category' => 'By Category',
                        'individual' => 'Individual Member',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
