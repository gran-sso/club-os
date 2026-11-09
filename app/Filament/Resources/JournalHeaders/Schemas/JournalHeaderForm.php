<?php

namespace App\Filament\Resources\JournalHeaders\Schemas;

use App\Domain\Finance\Models\Account;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JournalHeaderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('General Journal Voucher Details')
                    ->description('Double-entry accounting header information and business posting date.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('business_date')
                                ->label('Posting Business Date')
                                ->required()
                                ->default(now()->toDateString())
                                ->helperText('Date posted to General Ledger (cannot be in a closed financial period).'),

                            Select::make('source_type')
                                ->label('Source Classification')
                                ->required()
                                ->options([
                                    'manual_journal' => 'Manual Journal Adjustment',
                                    'recharge' => 'Wallet Recharge',
                                    'bill' => 'Counter Sales Bill',
                                    'refund' => 'Bill Refund',
                                    'contractor_settlement' => 'Contractor Settlement',
                                    'inventory_receipt' => 'Stock Inward Purchase',
                                    'expense' => 'Operating Expense',
                                ])
                                ->default('manual_journal'),

                            TextInput::make('description')
                                ->label('Voucher Narration / Purpose')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Month-end depreciation adjustment / Bank charges correction')
                                ->columnSpan(2),
                        ]),
                    ]),

                Section::make('Double-Entry Ledger Lines (Debits & Credits)')
                    ->description('Rule: Total Debits must exactly equal Total Credits. Every entry must balance.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->label('Journal Line Items')
                            ->minItems(2)
                            ->schema([
                                Grid::make(5)->schema([
                                    Select::make('account_id')
                                        ->label('GL Account Head')
                                        ->required()
                                        ->options(fn () => Account::get()->mapWithKeys(
                                            fn ($a) => [$a->id => "{$a->code} — {$a->name} (" . ucfirst($a->type->value ?? $a->type) . ")"]
                                        ))
                                        ->searchable()
                                        ->preload()
                                        ->columnSpan(2),

                                    TextInput::make('debit_paise')
                                        ->label('Debit (₹)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->prefix('₹')
                                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : 0)
                                        ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),

                                    TextInput::make('credit_paise')
                                        ->label('Credit (₹)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->prefix('₹')
                                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : 0)
                                        ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),

                                    Select::make('department_code')
                                        ->label('Department')
                                        ->options([
                                            'bar' => 'Bar & Lounge',
                                            'food' => 'Food & Restaurant',
                                            'accommodation' => 'Accommodation',
                                            'sports' => 'Sports & Badminton',
                                            'admin' => 'Administration',
                                        ])
                                        ->nullable(),
                                ]),

                                TextInput::make('narration')
                                    ->label('Line Explanation')
                                    ->placeholder('Specific explanation for this debit/credit entry')
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
