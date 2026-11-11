<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Domain\Shared\Security\ClubPermissionRegistry;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Staff Profile & Identity')
                    ->description('Personal identification, contact information, and active account status for club software users.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Suresh Pillai')
                                ->helperText('Legal or official display name of the staff member.'),

                            TextInput::make('employee_id')
                                ->label('Staff / Employee ID')
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. EMP-104')
                                ->helperText('Unique internal employee or payroll identifier.'),

                            TextInput::make('designation')
                                ->label('Designation / Role Title')
                                ->maxLength(80)
                                ->placeholder('e.g. Senior Counter Cashier')
                                ->helperText('Official organizational title within Malabar Club.'),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('email')
                                ->label('Email Address (Login Username)')
                                ->email()
                                ->required()
                                ->maxLength(150)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. cashier1@malabarclub.local')
                                ->helperText('Primary email address used for authenticating into the application.'),

                            TextInput::make('phone')
                                ->label('Phone / Mobile Number')
                                ->tel()
                                ->maxLength(20)
                                ->placeholder('e.g. +91 98470 12345')
                                ->helperText('Official or contact mobile number for verification and communication.'),

                            Toggle::make('is_active')
                                ->label('Account Active Status')
                                ->default(true)
                                ->inline(false)
                                ->helperText('Deactivating immediately blocks back-office and POS terminal access without destroying historical audit trails.')
                                ->disabled(fn (?User $record): bool => $record !== null && auth()->id() === $record->id)
                                ->rules([
                                    fn (?User $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                        if ($record && auth()->id() === $record->id && ! $value) {
                                            $fail('You cannot deactivate your own currently active administrator account.');
                                        }
                                    },
                                ]),
                        ]),
                    ]),

                Section::make('Security & Authentication Credentials')
                    ->description('Set or reset software access credentials. Passwords are securely hashed with bcrypt.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->required(fn (string $context): bool => $context === 'create')
                                ->rule(Password::min(8))
                                ->same('password_confirmation')
                                ->helperText(fn (string $context): string => $context === 'edit'
                                    ? 'Leave blank if you do not wish to change the existing password.'
                                    : 'Minimum 8 characters required for secure clubhouse operations.'
                                ),

                            TextInput::make('password_confirmation')
                                ->label('Confirm Password')
                                ->password()
                                ->revealable()
                                ->dehydrated(false)
                                ->required(fn (string $context): bool => $context === 'create')
                                ->helperText('Re-enter the password to verify accuracy.'),
                        ]),
                    ]),

                Section::make('Role-Based Access Control (RBAC)')
                    ->description('Select the staff role(s) assigned to this user. Configure which forms and approvals each role can access under System & Access Control → Roles & Form Access.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Assigned Club Roles')
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(function ($record): string {
                                $normalized = ClubPermissionRegistry::normalizeRoleName($record->name);
                                $meta = ClubPermissionRegistry::roleMeta()[$normalized] ?? null;

                                if ($meta) {
                                    return "{$meta['label']} ({$record->name}) — {$meta['description']}";
                                }

                                $count = $record->permissions()->count();

                                return ClubPermissionRegistry::roleDisplayName($record->name) . " ({$record->name}) — Custom role ({$count} permissions)";
                            })
                            ->rules([
                                fn (?User $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                    if (! $record) {
                                        return;
                                    }

                                    $selectedRoleNames = Role::query()
                                        ->whereIn('id', (array) $value)
                                        ->pluck('name')
                                        ->all();

                                    $keepsAdmin = collect($selectedRoleNames)
                                        ->contains(fn (string $name): bool => in_array($name, ClubPermissionRegistry::SUPER_ROLES, true));

                                    if (auth()->id() === $record->id && $record->hasAnyRole(ClubPermissionRegistry::SUPER_ROLES) && ! $keepsAdmin) {
                                        $fail('You cannot remove the Administrator role from your own account.');
                                    }

                                    if ($record->hasRole('admin') && ! $keepsAdmin) {
                                        $otherActiveAdmins = User::role('admin')
                                            ->where('is_active', true)
                                            ->where('id', '!=', $record->id)
                                            ->count();

                                        if ($otherActiveAdmins === 0) {
                                            $fail('Cannot remove Administrator role from the sole remaining active administrator.');
                                        }
                                    }
                                },
                            ])
                            ->columns(1)
                            ->helperText('Assign one or more built-in or custom roles. To change which forms a role sees across the club, edit that role under Roles & Form Access.'),
                    ]),

                Section::make('Additive Direct Permission Overrides')
                    ->description('Optionally grant extra form or approval capabilities directly to this single user in addition to what their assigned role(s) provide. To remove a capability, uncheck it from their role in Roles & Form Access (or assign a narrower custom role).')
                    ->collapsed()
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('Direct Individual Permissions (Additive)')
                            ->relationship('permissions', 'name')
                            ->getOptionLabelFromRecordUsing(function ($record): string {
                                $def = ClubPermissionRegistry::definitions()[$record->name] ?? null;
                                if (! $def) {
                                    return $record->name;
                                }

                                $groupTitle = ClubPermissionRegistry::groups()[$def['group']]['title'] ?? ucfirst($def['group']);

                                return "{$groupTitle}: {$def['label']} ({$record->name})";
                            })
                            ->bulkToggleable()
                            ->columns(2)
                            ->gridDirection('row')
                            ->helperText('Direct permissions stack additively on top of permissions inherited from assigned roles.'),
                    ]),
            ]);
    }
}
