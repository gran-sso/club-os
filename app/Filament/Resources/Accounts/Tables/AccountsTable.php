<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Domain\Finance\Models\Account;
use App\Domain\Shared\Enums\AccountType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Ledger Code')
                    ->badge()
                    ->color('primary')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Account / Head Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Account $record): ?string => $record->category ? "Group: {$record->category}" : $record->description),

                TextColumn::make('type')
                    ->label('Classification')
                    ->badge()
                    ->colors([
                        'warning' => AccountType::EXPENSE->value,
                        'info' => AccountType::REVENUE->value,
                        'success' => AccountType::ASSET->value,
                        'danger' => AccountType::LIABILITY->value,
                        'purple' => AccountType::EQUITY->value,
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state instanceof AccountType ? $state->value : (string) $state))
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Statement Group')
                    ->badge()
                    ->color('gray')
                    ->placeholder('General')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_system')
                    ->label('System Lock')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedLockClosed)
                    ->falseIcon(Heroicon::OutlinedSparkles)
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn (Account $record): string => $record->is_system
                        ? 'Automated System Account (Protected from deletion or code alteration)'
                        : 'Discretionary Club Ledger Head'
                    ),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created On')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('type')
                    ->label('Filter by Type')
                    ->options([
                        AccountType::EXPENSE->value => 'Expenses (5000s)',
                        AccountType::REVENUE->value => 'Revenue (4000s)',
                        AccountType::ASSET->value => 'Assets (1000s)',
                        AccountType::LIABILITY->value => 'Liabilities (2000s)',
                        AccountType::EQUITY->value => 'Equity (3000s)',
                    ]),

                TernaryFilter::make('is_system')
                    ->label('System vs Custom')
                    ->boolean()
                    ->trueLabel('Protected System Accounts Only')
                    ->falseLabel('Custom Club Heads Only'),

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active Heads Only')
                    ->falseLabel('Inactive / Archived Heads'),
            ])
            ->actions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Delete Ledger Account')
                    ->modalDescription('Are you sure? Core system accounts and accounts with linked historical transactions cannot be deleted to preserve financial audit integrity.')
                    ->before(function (Account $record, DeleteAction $action) {
                        if ($record->is_system) {
                            Notification::make()
                                ->title('Deletion Prohibited (System Account)')
                                ->body("Account #{$record->code} ({$record->name}) is a protected system account required for automated club double-entry transactions. It cannot be deleted.")
                                ->danger()
                                ->send();

                            $action->cancel();

                            return;
                        }

                        if ($record->hasHistoricalLines()) {
                            Notification::make()
                                ->title('Deletion Prohibited (Audit Trail)')
                                ->body("Account #{$record->code} cannot be deleted because historical journal entries or expense vouchers are linked to it. Deactivate the account instead to hide it from new vouchers.")
                                ->warning()
                                ->send();

                            $action->cancel();

                            return;
                        }
                    }),
            ]);
    }
}

