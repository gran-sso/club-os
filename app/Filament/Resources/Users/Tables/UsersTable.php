<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use App\Domain\Shared\Services\AuditLogService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
                    ->description(fn (User $record): string => ($record->designation ? "{$record->designation} • " : '') . ($record->email ?: 'No contact email')),

                TextColumn::make('username')
                    ->label('Login Username')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Username copied'),

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
                    ->label('Extra Permissions')
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

                IconColumn::make('must_change_password')
                    ->label('Temp Password')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)
                    ->falseIcon(Heroicon::OutlinedCheck)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (User $record): string => $record->must_change_password ? 'Temporary password active — rotation required upon login' : 'Password confirmed')
                    ->toggleable(isToggledHiddenByDefault: true),

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

                Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->visible(fn (): bool => (bool) auth()->user()?->hasClubPermission('users.manage'))
                    ->modalHeading(fn (User $record): string => "Reset Password for {$record->name}")
                    ->modalDescription(fn (User $record): string => "Provision a new temporary password for @{$record->username}. The staff member will be required to create their own private password upon next sign-in.")
                    ->form([
                        Radio::make('mode')
                            ->label('Password Generation Mode')
                            ->options([
                                'generate' => 'Generate secure random temporary password (Recommended)',
                                'manual' => 'Specify custom temporary password',
                            ])
                            ->default('generate')
                            ->live(),

                        TextInput::make('manual_password')
                            ->label('Temporary Password')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => $get('mode') === 'manual')
                            ->required(fn (Get $get): bool => $get('mode') === 'manual')
                            ->minLength(8)
                            ->helperText('Must be at least 8 characters long.'),

                        Toggle::make('must_change_password')
                            ->label('Require password change at next login')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Staff must set a private, confidential password upon initial sign-in.'),
                    ])
                    ->action(function (User $record, array $data) {
                        $mustChange = (bool) ($data['must_change_password'] ?? true);

                        if (($data['mode'] ?? 'generate') === 'manual') {
                            $plainPassword = (string) $data['manual_password'];
                        } else {
                            $plainPassword = 'MC!' . rand(1000, 9999) . '#' . Str::random(3);
                        }

                        $record->forceFill([
                            'password' => Hash::make($plainPassword),
                            'must_change_password' => $mustChange,
                            'password_changed_at' => null,
                        ])->save();

                        try {
                            DB::table('sessions')
                                ->where('user_id', $record->id)
                                ->delete();
                        } catch (\Throwable $e) {
                            // Non-fatal if session table is unavailable or using array/cookie driver
                        }

                        AuditLogService::record(
                            category: 'security',
                            action: 'user.password_reset_by_admin',
                            description: "Admin reset password for staff member {$record->name} (@{$record->username}). Must change password: " . ($mustChange ? 'Yes' : 'No'),
                            auditable: $record,
                            newValues: [
                                'username' => $record->username,
                                'must_change_password' => $mustChange,
                            ],
                            user: auth()->user(),
                        );

                        Notification::make()
                            ->title("Password Reset: {$record->name}")
                            ->body("Temporary password generated:\n\n**{$plainPassword}**\n\nPlease securely share this credential with the staff member. They will be required to change it on their next sign-in.")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

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

