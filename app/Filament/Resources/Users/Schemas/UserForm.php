<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
                    ->description('Select the staff role(s) to grant preset operational capabilities. Each role confers standard clubhouse permissions.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Assigned Club Roles')
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => match ($record->name) {
                                'admin' => 'Club Administrator — Full System, Settings & Access Control',
                                'manager' => 'General Manager — Operations, Members, Tariffs & Approvals',
                                'accountant' => 'Club Accountant — Financial Ledgers, Settlements & Vouchers',
                                'auditor' => 'Internal Auditor — Read-Only Financial Registers & Reports',
                                'reception' => 'Front Desk Reception — Room Rack, Court Schedule & Guest KYC',
                                'cashier' => 'Counter Cashier — POS Terminal Billing, Drawer Shifts & Prepaid Recharges',
                                default => ucfirst($record->name),
                            })
                            ->columns(2)
                            ->gridDirection('row')
                            ->helperText('Assigning one or more roles confers the bundled permissions configured for that operational capacity.'),
                    ]),

                Section::make('Granular Permission Overrides')
                    ->description('Optionally grant or restrict individual capabilities directly to this user account beyond their assigned roles.')
                    ->collapsed()
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('Direct Individual Permissions')
                            ->relationship('permissions', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => match ($record->name) {
                                'users.manage' => 'Manage Staff & User Access (users.manage)',
                                'members.manage' => 'Manage Members & KYC Records (members.manage)',
                                'guests.register' => 'Register Visiting Guests & Issue Passes (guests.register)',
                                'counter.operate' => 'Open, Operate & Reconcile Counter Shifts (counter.operate)',
                                'wallet.recharge' => 'Recharge Member Prepaid Balances (wallet.recharge)',
                                'sales.checkout' => 'Execute POS Checkouts & Commercial Sales (sales.checkout)',
                                'tariffs.manage' => 'Edit Selling Prices & GST Tariffs (tariffs.manage)',
                                'refunds.approve' => 'Approve Returns & Tender Refunds (refunds.approve)',
                                'rooms.view_rack' => 'View Accommodation Room Rack (rooms.view_rack)',
                                'rooms.manage_reservations' => 'Book & Check-In Room Guests (rooms.manage_reservations)',
                                'courts.view_schedule' => 'View Badminton Court Schedule (courts.view_schedule)',
                                'courts.manage_bookings' => 'Book Badminton Court Slots (courts.manage_bookings)',
                                'stock.manage' => 'Inward Consignments & Stock Control (stock.manage)',
                                'contractor.settle' => 'Settle Daily Food Contractor Batches (contractor.settle)',
                                'reports.view' => 'Access Executive & Financial Reports (reports.view)',
                                'integrations.manage' => 'Configure Tally & Hardware Connectors (integrations.manage)',
                                default => $record->name,
                            })
                            ->columns(2)
                            ->gridDirection('row')
                            ->helperText('Direct permissions apply in addition to any permissions inherited through assigned roles.'),
                    ]),
            ]);
    }
}
