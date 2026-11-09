<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Domain\Finance\Models\Account;
use App\Domain\Shared\Enums\AccountType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('General Ledger & Expense Head Details')
                    ->description('Define statutory general ledger accounts, operating expense heads, and revenue categories.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('code')
                                ->label('Ledger Account Code')
                                ->required()
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. 5110 or EXP-UTIL')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                                ->disabled(fn (?Account $record): bool => $record !== null && $record->is_system)
                                ->helperText('Unique 4-digit or alphanumeric code (1xxx Assets, 2xxx Liabilities, 4xxx Revenues, 5xxx Expenses).'),

                            TextInput::make('name')
                                ->label('Account Name / Ledger Head')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Electricity & Power Charges (KSEB)')
                                ->helperText('Official name shown on expense vouchers, P&L reports, and Tally export.'),

                            Select::make('type')
                                ->label('Accounting Classification')
                                ->options([
                                    AccountType::EXPENSE->value => 'Expense (5000s) — Utilities, Repairs, Supplies, Operations',
                                    AccountType::REVENUE->value => 'Revenue (4000s) — Sales, Guest Passes, Room & Sports Income',
                                    AccountType::ASSET->value => 'Asset (1000s) — Cash, Bank Accounts, Stores, Receivables',
                                    AccountType::LIABILITY->value => 'Liability (2000s) — Prepaid Advances, Vendor Payables, Taxes',
                                    AccountType::EQUITY->value => 'Equity (3000s) — Capital Corpus, General Reserves & Surplus',
                                ])
                                ->default(AccountType::EXPENSE->value)
                                ->required()
                                ->disabled(fn (?Account $record): bool => $record !== null && $record->is_system)
                                ->helperText('Financial statement category governing normal debit/credit balance.'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('category')
                                ->label('Sub-Category / Statement Group')
                                ->maxLength(80)
                                ->placeholder('e.g. Utilities & Power, Repairs & Maintenance, Housekeeping')
                                ->datalist([
                                    'Utilities & Power',
                                    'Repairs & Maintenance',
                                    'Housekeeping & Hospitality',
                                    'Administration & Staff',
                                    'Statutory & Professional',
                                    'Finance & Bank Charges',
                                    'Current Assets',
                                    'Current Liabilities',
                                    'Operating Revenue',
                                    'Capital & Reserves',
                                ])
                                ->helperText('Grouping tag for organizing departmental P&L and Income & Expenditure statements.'),

                            Toggle::make('is_active')
                                ->label('Active for New Vouchers & Postings')
                                ->default(true)
                                ->inline(false)
                                ->disabled(fn (?Account $record): bool => $record !== null && $record->is_system)
                                ->helperText('Inactive accounts are hidden from new voucher dropdowns while preserving historical ledgers.'),
                        ]),

                        Textarea::make('description')
                            ->label('Description & Purpose')
                            ->rows(3)
                            ->placeholder('Describe the scope of costs, statutory rules, or departmental attribution for this ledger head.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

