<?php

namespace App\Filament\Resources\Contractors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Contractor & Catering Enterprise Identity')
                    ->description('Establish the contractor legal business name, unique code, and assigned club operating department.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Contractor Reference Code')
                                ->required()
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. CONT-FOOD-01 or CONT-BANQUET')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                                ->helperText('Unique reference identifier for settlement vouchers and Tally ledger mapping.'),

                            TextInput::make('name')
                                ->label('Contractor / Commercial Name')
                                ->required()
                                ->maxLength(150)
                                ->placeholder('e.g. Malabar Spices Catering & Hospitality')
                                ->helperText('Official registered business or commercial entity name.'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('department_code')
                                ->label('Club Section Managed')
                                ->required()
                                ->options([
                                    'food' => 'Food & Restaurant Catering',
                                    'bar' => 'Bar & Beverage Partner',
                                    'maintenance' => 'Club Facilities & Maintenance',
                                    'security' => 'Security & Access Operations',
                                    'general' => 'General Clubhouse Services',
                                ])
                                ->default('food')
                                ->searchable()
                                ->helperText('Operating department whose sales and settlement cutoffs feed this contractor.'),

                            TextInput::make('contact_person')
                                ->label('Managing Proprietor / Contact Person')
                                ->maxLength(100)
                                ->placeholder('e.g. Moideen Kutty')
                                ->helperText('Primary authorized representative for communication and payouts.'),
                        ]),
                    ]),

                Section::make('Communication & Contact Details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('phone')
                                ->label('Contact Phone / Mobile')
                                ->tel()
                                ->maxLength(50)
                                ->placeholder('e.g. +91 98471 23456'),

                            TextInput::make('email')
                                ->label('Official Email Address')
                                ->email()
                                ->maxLength(100)
                                ->placeholder('e.g. catering@malabarspices.local'),
                        ]),
                    ]),

                Section::make('Banking & Settlement Payout Details')
                    ->description('Bank account information used when generating NEFT / RTGS settlement vouchers and Tally payment journals.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('bank_name')
                                ->label('Bank Name')
                                ->maxLength(100)
                                ->placeholder('e.g. State Bank of India'),

                            TextInput::make('bank_account_number')
                                ->label('Beneficiary Account Number')
                                ->maxLength(50)
                                ->placeholder('e.g. 123456789012')
                                ->helperText('Bank account credited during settlement payouts.'),

                            TextInput::make('bank_ifsc')
                                ->label('Bank IFSC Code')
                                ->maxLength(20)
                                ->placeholder('e.g. SBIN0001234')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state))),
                        ]),
                    ]),

                Section::make('Commercial Terms & Operational Status')
                    ->description('Define revenue sharing commission rates and operational authorization.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('commission_rate_percent')
                                ->label('Club Commission Share (%)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0.00)
                                ->suffix('%')
                                ->helperText('Club commission share. Standard catering contract is 0.00% (100% of net food sales passed through to contractor).'),

                            Toggle::make('is_active')
                                ->label('Active for Operations & Settlements')
                                ->default(true)
                                ->helperText('Inactive contractors cannot be selected for new billing cutoffs.'),
                        ]),
                    ]),
            ]);
    }
}

