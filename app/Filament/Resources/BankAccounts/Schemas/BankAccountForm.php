<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use App\Domain\Finance\Models\Account;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Bank & Branch Identity')
                    ->description('Specify the banking institution, branch location, and internal ledger title for this account.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextInput::make('bank_name')
                                ->label('Bank Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. State Bank of India, Federal Bank, Canara Bank')
                                ->helperText('Scheduled commercial or cooperative bank name.'),

                            TextInput::make('branch_name')
                                ->label('Branch Location')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Vadakkencherry Branch (001234)')
                                ->helperText('Physical branch maintaining the account.'),

                            TextInput::make('account_name')
                                ->label('Internal Account Title')
                                ->required()
                                ->maxLength(150)
                                ->placeholder('e.g. SBI Main Operational Current A/c')
                                ->helperText('Display label used across cash deposit vouchers and reports.'),
                        ]),
                    ]),

                Section::make('Account Number & Statutory Codes')
                    ->description('Official account identification and Indian banking clearance codes.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                            TextInput::make('account_number')
                                ->label('Bank Account Number')
                                ->required()
                                ->maxLength(50)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. 40912345678')
                                ->helperText('Official bank account number.'),

                            Select::make('account_type')
                                ->label('Account Classification')
                                ->required()
                                ->options([
                                    'current' => 'Current Account (Operational)',
                                    'savings' => 'Savings Account (Reserve Fund)',
                                    'fixed_deposit' => 'Fixed Deposit / Corpus Escrow',
                                ])
                                ->default('current'),

                            TextInput::make('ifsc_code')
                                ->label('Branch IFSC Code')
                                ->required()
                                ->maxLength(20)
                                ->placeholder('e.g. SBIN0001234')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                                ->helperText('11-character Indian Financial System Code.'),

                            TextInput::make('upi_id')
                                ->label('Club UPI ID / VPA (Optional)')
                                ->maxLength(100)
                                ->placeholder('e.g. malabarclub@sbi')
                                ->helperText('Virtual payment address for direct counter QR collections.'),
                        ]),
                    ]),

                Section::make('General Ledger Mapping & Initial Balance')
                    ->description('Chart of Accounts linkage and opening ledger balance.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Select::make('gl_account_id')
                                ->label('Linked General Ledger Asset Head')
                                ->options(fn () => Account::where('type', 'asset')
                                    ->get()
                                    ->mapWithKeys(fn ($a) => [
                                        $a->id => "{$a->code} — {$a->name}"
                                    ]))
                                ->default(fn () => Account::where('code', '1020')->first()?->id)
                                ->searchable()
                                ->preload()
                                ->helperText('Accounting asset account debited when counter cash is deposited.'),

                            TextInput::make('opening_balance_paise')
                                ->label('Opening Balance (₹)')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->default(0.00)
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : 0)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->disabled(fn ($record) => $record !== null)
                                ->helperText('Initial cleared bank balance. Converted securely to integer paise.'),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            Toggle::make('is_primary')
                                ->label('Primary Club Operating Account')
                                ->default(false)
                                ->helperText('Primary destination recommended for daily counter cash deposits and vendor disbursements.'),

                            Toggle::make('is_active')
                                ->label('Active for Transactions & Payouts')
                                ->default(true)
                                ->helperText('When inactive, account cannot receive cash deposits or pay expenses.'),
                        ]),

                        Textarea::make('notes')
                            ->label('Account Remarks / Authorised Signatories')
                            ->rows(2)
                            ->placeholder('e.g. Joint operation by President and Honorary Treasurer under club bylaws.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

