<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Staff Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): string => $record->email . ($record->designation ? " • {$record->designation}" : '')),

                TextColumn::make('employee_id')
                    ->label('Staff ID')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('roles.name')
                    ->label('Assigned Roles')
                    ->badge()
                    ->colors([
                        'danger' => 'admin',
                        'warning' => 'manager',
                        'info' => 'accountant',
                        'gray' => 'auditor',
                        'primary' => 'reception',
                        'success' => 'cashier',
                    ])
                    ->formatStateUsing(fn (string $state): string => ClubPermissionRegistry::roleShortBadgeLabel($state)),

                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Direct Perms')
                    ->badge()
                    ->color('secondary')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Mobile Phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created On')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Filter by Role')
                    ->relationship('roles', 'name')
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->boolean()
                    ->trueLabel('Active Staff Only')
                    ->falseLabel('Deactivated Staff Only'),
            ])
            ->actions([
                EditAction::make(),

                Action::make('toggle_status')
                    ->label(fn (User $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (User $record) => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->is_active ? "Deactivate {$record->name}?" : "Activate {$record->name}?")
                    ->modalDescription(fn (User $record): string => $record->is_active
                        ? 'Deactivating immediately blocks back-office and POS terminal login for this user. Historical audit records remain intact.'
                        : 'Re-activating this account immediately restores software access and operational permissions.'
                    )
                    ->action(function (User $record) {
                        if (auth()->id() === $record->id) {
                            Notification::make()
                                ->title('Action Blocked')
                                ->body('You cannot deactivate your own currently active administrator account.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->is_active && $record->hasRole('admin') && User::role('admin')->where('is_active', true)->count() <= 1) {
                            Notification::make()
                                ->title('Action Blocked')
                                ->body('You cannot deactivate the sole remaining active administrator.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'Staff Account Activated' : 'Staff Account Deactivated')
                            ->body("{$record->name} is now " . ($record->is_active ? 'active.' : 'deactivated.'))
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->modalHeading('Delete Staff Account')
                    ->modalDescription('Are you sure? Users with historical financial or operational transactions cannot be deleted to preserve the audit trail.')
                    ->before(function (User $record, DeleteAction $action) {
                        if (auth()->id() === $record->id) {
                            Notification::make()
                                ->title('Deletion Blocked')
                                ->body('You cannot delete your own currently logged-in account.')
                                ->danger()
                                ->send();

                            $action->cancel();

                            return;
                        }

                        if ($record->hasRole('admin') && User::role('admin')->count() <= 1) {
                            Notification::make()
                                ->title('Deletion Blocked')
                                ->body('You cannot delete the sole remaining administrator.')
                                ->danger()
                                ->send();

                            $action->cancel();

                            return;
                        }

                        if ($record->hasHistoricalRecords()) {
                            Notification::make()
                                ->title('Deletion Blocked (Audit Integrity)')
                                ->body("{$record->name} has linked operational records (shifts, bills, purchase receipts, or journal vouchers). To maintain legal audit integrity, deactivate this account instead of deleting it.")
                                ->warning()
                                ->send();

                            $action->cancel();

                            return;
                        }
                    }),
            ]);
    }
}

