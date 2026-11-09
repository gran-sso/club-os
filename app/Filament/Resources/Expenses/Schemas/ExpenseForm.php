<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Domain\Finance\Models\Account;
use App\Domain\Tariff\Models\Department;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Expense Head & Departmental Allocation')
                    ->description('Categorize the disbursement under the club chart of accounts and assign operating department attribution.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('account_id')
                                ->label('General Ledger Expense Head')
                                ->required()
                                ->options(fn () => Account::where('type', 'expense')->pluck('name', 'id'))
                                ->default(fn () => Account::where('code', '5030')->first()?->id)
                                ->searchable()
                                ->preload()
                                ->helperText('Determines the financial accounting ledger debit entry.'),

                            Select::make('department_id')
                                ->label('Operating Department (P&L)')
                                ->options(fn () => Department::pluck('name', 'id'))
                                ->nullable()
                                ->searchable()
                                ->placeholder('General Clubhouse Infrastructure')
                                ->helperText('Attributed department for segmented profitability reports.'),

                            DatePicker::make('expense_date')
                                ->label('Payment / Voucher Date')
                                ->required()
                                ->default(now()->toDateString())
                                ->helperText('Cannot be posted to a locked/closed financial period.'),
                        ]),
                    ]),

                Section::make('Disbursement & Payment Details')
                    ->description('Specify the beneficiary payee, exact amount in Rupees, and external banking instrument reference.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('payee')
                                ->label('Payee / Beneficiary / Vendor')
                                ->required()
                                ->maxLength(150)
                                ->placeholder('e.g. Kerala State Electricity Board (KSEB) or Vadakkencherry Sanitation'),

                            TextInput::make('amount_paise')
                                ->label('Disbursed Amount (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->prefix('₹')
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Stored securely in exact integer paise. Enter payout in Rupees.'),
                        ]),

                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            Select::make('payment_method')
                                ->label('Payment Instrument')
                                ->required()
                                ->live()
                                ->options([
                                    'bank_transfer' => 'NEFT / RTGS / Bank Transfer',
                                    'cheque' => 'Account Payee Cheque',
                                    'cash' => 'Counter Petty Cash',
                                ])
                                ->default('bank_transfer')
                                ->helperText('Bank transfer/cheque credits Bank; cash credits Till Cash (1010).'),

                            Select::make('bank_account_id')
                                ->label('Disbursing Club Bank Account')
                                ->options(fn () => \App\Domain\Finance\Models\BankAccount::where('is_active', true)->get()->mapWithKeys(
                                    fn ($b) => [$b->id => "{$b->account_name} — {$b->bank_name} ({$b->account_number}) • Bal: ₹" . number_format($b->current_balance, 2)]
                                ))
                                ->default(fn () => \App\Domain\Finance\Models\BankAccount::where('is_primary', true)->first()?->id)
                                ->visible(fn ($get) => in_array($get('payment_method'), ['bank_transfer', 'cheque']))
                                ->searchable()
                                ->preload()
                                ->helperText('Club account from which disbursement is drawn.'),

                            TextInput::make('document_reference')
                                ->label('Bank UTR / Cheque # / Bill Reference')
                                ->maxLength(100)
                                ->placeholder('e.g. UTR-AXIS-20261108-9876 or Cheque # 104820')
                                ->helperText('Auditable reference for bank reconciliation.'),
                        ]),

                        Textarea::make('notes')
                            ->label('Payment Description & Narration')
                            ->rows(3)
                            ->placeholder('Describe the purpose of expense, monthly billing cycle, or approval authority.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

