<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Services\BankingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedStar)
                    ->trueColor('amber')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('account_name')
                    ->label('Account Title & Bank')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (BankAccount $record): string => "{$record->bank_name} • {$record->branch_name}"),

                TextColumn::make('account_number')
                    ->label('Account #')
                    ->fontFamily('mono')
                    ->weight('semibold')
                    ->copyable()
                    ->copyMessage('Account number copied to clipboard')
                    ->searchable(),

                TextColumn::make('account_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'current' => 'info',
                        'savings' => 'success',
                        'fixed_deposit' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper(str_replace('_', ' ', $state))),

                TextColumn::make('ifsc_code')
                    ->label('IFSC Code')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('current_balance')
                    ->label('Bank Balance (₹)')
                    ->fontFamily('mono')
                    ->weight('black')
                    ->color('success')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '₹' . number_format((float) $state, 2))
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('current_balance_paise', $direction);
                    }),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->headerActions([
                Action::make('depositCashGlobal')
                    ->label('Deposit Counter Cash')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->modalHeading('Deposit Counter Cash to Bank Account')
                    ->modalDescription('Record a physical cash remittance from counter tills into a club bank account. Automatically posts balanced Contra Voucher (Debit: Bank, Credit: Cash in Hand 1010).')
                    ->modalWidth(Width::Large)
                    ->form([
                        Select::make('bank_account_id')
                            ->label('Destination Bank Account')
                            ->required()
                            ->options(fn () => BankAccount::where('is_active', true)->get()->mapWithKeys(
                                fn ($b) => [$b->id => "{$b->account_name} — {$b->bank_name} ({$b->account_number}) • Bal: ₹" . number_format($b->current_balance, 2)]
                            ))
                            ->default(fn () => BankAccount::where('is_primary', true)->first()?->id ?? BankAccount::first()?->id)
                            ->searchable()
                            ->preload(),

                        TextInput::make('amount_rupees')
                            ->label('Deposit Amount (₹)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->prefix('₹')
                            ->placeholder('e.g. 50000.00')
                            ->helperText('Exact amount of physical cash deposited at the bank counter or CDM.'),

                        DatePicker::make('business_date')
                            ->label('Deposit / Challan Date')
                            ->required()
                            ->default(today())
                            ->helperText('Date printed on the bank pay-in slip.'),

                        TextInput::make('reference_number')
                            ->label('Bank Pay-in Slip # / Challan # / CDM Txn ID')
                            ->required()
                            ->placeholder('e.g. SBI-CHAL-20261109-0041')
                            ->helperText('Auditable external banking slip reference number.'),

                        TextInput::make('deposited_by')
                            ->label('Deposited By (Staff / Authorized Person)')
                            ->default(fn () => auth()->user()?->name ?? 'Accounts Staff')
                            ->required(),

                        Textarea::make('description')
                            ->label('Narration / Deposit Remarks')
                            ->rows(2)
                            ->placeholder('e.g. Weekend counter sales and wallet recharge collections deposit.'),
                    ])
                    ->action(function (array $data) {
                        $bankAccount = BankAccount::findOrFail($data['bank_account_id']);
                        $amountPaise = (int) round(((float) $data['amount_rupees']) * 100);

                        $bankingService = app(BankingService::class);
                        $txn = $bankingService->depositCash(
                            bankAccount: $bankAccount,
                            amountPaise: $amountPaise,
                            businessDate: $data['business_date'],
                            referenceNumber: $data['reference_number'] ?? null,
                            depositedBy: $data['deposited_by'] ?? null,
                            description: $data['description'] ?? null,
                            userId: auth()->id()
                        );

                        Notification::make()
                            ->title('Cash Remittance Deposited')
                            ->body("Successfully deposited ₹" . number_format($data['amount_rupees'], 2) . " into {$bankAccount->account_name}. Contra voucher {$txn->transaction_number} posted.")
                            ->success()
                            ->send();
                    }),

                Action::make('interBankTransfer')
                    ->label('Inter-Bank Transfer')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->modalHeading('Transfer Funds Between Club Bank Accounts')
                    ->modalDescription('Record a transfer from one club bank account to another (Contra entry).')
                    ->modalWidth(Width::Large)
                    ->form([
                        Select::make('from_bank_account_id')
                            ->label('Source Bank Account (Debited)')
                            ->required()
                            ->options(fn () => BankAccount::where('is_active', true)->get()->mapWithKeys(
                                fn ($b) => [$b->id => "{$b->account_name} — {$b->bank_name} (Bal: ₹" . number_format($b->current_balance, 2) . ")"]
                            ))
                            ->searchable()
                            ->preload(),

                        Select::make('to_bank_account_id')
                            ->label('Destination Bank Account (Credited)')
                            ->required()
                            ->options(fn () => BankAccount::where('is_active', true)->get()->mapWithKeys(
                                fn ($b) => [$b->id => "{$b->account_name} — {$b->bank_name} (Bal: ₹" . number_format($b->current_balance, 2) . ")"]
                            ))
                            ->different('from_bank_account_id')
                            ->searchable()
                            ->preload(),

                        TextInput::make('amount_rupees')
                            ->label('Transfer Amount (₹)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->prefix('₹')
                            ->placeholder('e.g. 25000.00'),

                        DatePicker::make('business_date')
                            ->label('Transfer Date')
                            ->required()
                            ->default(today()),

                        TextInput::make('reference_number')
                            ->label('Bank UTR / Cheque # / Ref #')
                            ->required()
                            ->placeholder('e.g. UTR-AXIS-20261109-0982'),

                        Textarea::make('description')
                            ->label('Transfer Purpose / Narration')
                            ->rows(2)
                            ->placeholder('e.g. Fund transfer to operational current account for vendor payouts.'),
                    ])
                    ->action(function (array $data) {
                        $from = BankAccount::findOrFail($data['from_bank_account_id']);
                        $to = BankAccount::findOrFail($data['to_bank_account_id']);
                        $amountPaise = (int) round(((float) $data['amount_rupees']) * 100);

                        $bankingService = app(BankingService::class);
                        $txn = $bankingService->transferFunds(
                            fromAccount: $from,
                            toAccount: $to,
                            amountPaise: $amountPaise,
                            businessDate: $data['business_date'],
                            referenceNumber: $data['reference_number'] ?? null,
                            description: $data['description'] ?? null,
                            userId: auth()->id()
                        );

                        Notification::make()
                            ->title('Inter-Bank Transfer Executed')
                            ->body("Transferred ₹" . number_format($data['amount_rupees'], 2) . " from {$from->account_name} to {$to->account_name}. Voucher {$txn->transaction_number} posted.")
                            ->success()
                            ->send();
                    }),

                CreateAction::make()
                    ->label('Add Bank Account')
                    ->icon(Heroicon::OutlinedPlus),
            ])
            ->recordActions([
                Action::make('depositCash')
                    ->label('Deposit Cash')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->modalHeading(fn (BankAccount $record) => "Deposit Counter Cash to {$record->account_name}")
                    ->modalDescription(fn (BankAccount $record) => "Record cash remittance into {$record->bank_name} (A/c: {$record->account_number}). Posts balanced Contra Voucher.")
                    ->modalWidth(Width::Large)
                    ->form(fn (BankAccount $record) => [
                        Placeholder::make('target_info')
                            ->content(new HtmlString("
                                <div class=\"p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs text-slate-800\">
                                    <div>
                                        <span class=\"font-extrabold text-slate-900 block\">{$record->account_name}</span>
                                        <span class=\"text-slate-500\">{$record->bank_name} • {$record->branch_name} • IFSC: {$record->ifsc_code}</span>
                                    </div>
                                    <div class=\"text-right\">
                                        <span class=\"text-[10px] uppercase font-bold text-slate-400 block\">Current Balance</span>
                                        <span class=\"font-mono font-extrabold text-emerald-800 text-sm\">₹" . number_format($record->current_balance, 2) . "</span>
                                    </div>
                                </div>
                            ")),

                        TextInput::make('amount_rupees')
                            ->label('Cash Remittance Amount (₹)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->prefix('₹')
                            ->placeholder('e.g. 25000.00')
                            ->helperText('Amount deposited into bank account.'),

                        DatePicker::make('business_date')
                            ->label('Deposit / Challan Date')
                            ->required()
                            ->default(today()),

                        TextInput::make('reference_number')
                            ->label('Pay-in Slip # / Challan # / CDM Txn ID')
                            ->required()
                            ->placeholder('e.g. SBI-CHAL-10492'),

                        TextInput::make('deposited_by')
                            ->label('Deposited By (Staff Name)')
                            ->default(fn () => auth()->user()?->name ?? 'Cashier')
                            ->required(),

                        Textarea::make('description')
                            ->label('Deposit Remarks / Narration')
                            ->rows(2)
                            ->placeholder('e.g. Cash collected from front desk shift #14'),
                    ])
                    ->action(function (BankAccount $record, array $data) {
                        $amountPaise = (int) round(((float) $data['amount_rupees']) * 100);

                        $bankingService = app(BankingService::class);
                        $txn = $bankingService->depositCash(
                            bankAccount: $record,
                            amountPaise: $amountPaise,
                            businessDate: $data['business_date'],
                            referenceNumber: $data['reference_number'] ?? null,
                            depositedBy: $data['deposited_by'] ?? null,
                            description: $data['description'] ?? null,
                            userId: auth()->id()
                        );

                        Notification::make()
                            ->title('Cash Deposited to Bank')
                            ->body("Deposited ₹" . number_format($data['amount_rupees'], 2) . " to {$record->account_name}. Voucher {$txn->transaction_number} posted.")
                            ->success()
                            ->send();
                    }),

                Action::make('passbook')
                    ->label('Passbook')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->modalHeading(fn (BankAccount $record) => "Passbook & Ledger: {$record->account_name}")
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalContent(fn (BankAccount $record) => view('filament.components.bank-passbook-modal', ['account' => $record])),

                EditAction::make(),
            ]);
    }
}
