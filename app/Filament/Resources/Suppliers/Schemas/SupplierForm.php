<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Wholesale Supplier & Distributor Identity')
                    ->description('Register authorized beverage corporations, FMCG distributors, brewery depots, and clubhouse vendors.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Supplier Reference Code')
                                ->required()
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. KSBC-PKD or SUP-BEV-01')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                                ->helperText('Unique identification code for invoices and purchase audit.'),

                            TextInput::make('name')
                                ->label('Supplier / Commercial Entity Name')
                                ->required()
                                ->maxLength(150)
                                ->placeholder('e.g. Kerala State Beverages Corporation (KSBC)')
                                ->helperText('Official registered business, state corporation, or commercial agency name.'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('gstin')
                                ->label('Statutory GSTIN (15 Digits)')
                                ->maxLength(15)
                                ->regex('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/')
                                ->placeholder('e.g. 32AABCK1234F1Z5')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                                ->helperText('15-digit statutory GST identification number for input tax credit (ITC).'),

                            TextInput::make('contact_person')
                                ->label('Contact Person / Depot Manager')
                                ->maxLength(100)
                                ->placeholder('e.g. Sales Officer / Area Rep')
                                ->helperText('Primary liaison for consignment deliveries and accounts reconciliation.'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('phone')
                                ->label('Phone / Mobile Number')
                                ->tel()
                                ->maxLength(30)
                                ->placeholder('e.g. +91 98470 12345'),

                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->maxLength(100)
                                ->placeholder('e.g. orders@distributor.com'),
                        ]),

                        Textarea::make('address')
                            ->label('Depot / Billing Address')
                            ->rows(2)
                            ->placeholder('e.g. Warehouse No. 4, KINFRA Park, Palakkad - 678001')
                            ->helperText('Physical warehouse or registered billing address.'),

                        Toggle::make('is_active')
                            ->label('Active Supplier Status')
                            ->default(true)
                            ->helperText('Active suppliers appear in the Goods Receipt inward register.'),
                    ]),
            ]);
    }
}

