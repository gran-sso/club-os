<?php

namespace App\Filament\Resources\MembershipCategories\Tables;

use App\Domain\Membership\Models\MembershipCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembershipCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Category Code')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('admission_fee_paise')
                    ->label('Admission Fee')
                    ->state(fn (MembershipCategory $record) => $record->admission_fee_paise > 0 ? '₹' . number_format($record->admission_fee_paise / 100, 2) : 'Nil / ₹0.00')
                    ->sortable(),

                TextColumn::make('annual_fee_paise')
                    ->label('Annual Fee')
                    ->state(fn (MembershipCategory $record) => $record->annual_fee_paise > 0 ? '₹' . number_format($record->annual_fee_paise / 100, 2) . '/yr' : 'Exempt / ₹0.00')
                    ->sortable(),

                IconColumn::make('has_voting_rights')
                    ->label('Voting Rights')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Enrolled Members')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'info' : 'gray'),

                IconColumn::make('is_active')
                    ->label('Admissions Active')
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (MembershipCategory $record, DeleteAction $action) {
                        if ($record->members()->exists()) {
                            Notification::make()
                                ->title('Deletion Prohibited: Category Has Active Members')
                                ->body("Cannot delete '{$record->name}' because {$record->members()->count()} member(s) are currently classified under it.")
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ]);
    }
}

