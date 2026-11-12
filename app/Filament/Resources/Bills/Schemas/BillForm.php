<?php

namespace App\Filament\Resources\Bills\Schemas;

use App\Domain\Billing\Models\Bill;
use App\Domain\Billing\Models\Shift;
use App\Domain\Access\Models\Guest;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\BillStatus;
use App\Domain\Tariff\Models\Tariff;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // 1. Immutable Notice Banner (for posted/settled bills)
                Placeholder::make('immutable_notice')
                    ->hidden(fn ($record) => !$record || ($record->status?->value ?? $record->status) === 'draft')
                    ->columnSpanFull()
                    ->content(function ($record) {
                        $status = $record->status?->value ?? $record->status;
                        $color = $status === 'paid' ? 'emerald' : ($status === 'refunded' ? 'rose' : 'slate');
                        $statusText = strtoupper($status);
                        return new HtmlString("
                            <div class=\"p-4 bg-{$color}-50/80 border border-{$color}-200 rounded-2xl flex items-start gap-3.5 shadow-2xs text-slate-800 text-xs\">
                                <div class=\"w-8 h-8 rounded-xl bg-{$color}-500 text-white font-black flex items-center justify-center shrink-0 shadow-2xs text-sm\">
                                    " . ($status === 'paid' ? '✓' : ($status === 'refunded' ? '↩' : 'ℹ')) . "
                                </div>
                                <div class=\"leading-relaxed\">
                                    <div class=\"flex items-center gap-2\">
                                        <span class=\"font-extrabold text-slate-900 tracking-tight\">Official Tax Invoice: {$record->bill_number}</span>
                                        <span class=\"px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-{$color}-100 text-{$color}-900 border border-{$color}-300\">{$statusText}</span>
                                    </div>
                                    <p class=\"text-slate-600 mt-1\">
                                        Posted bills and tax invoices are immutable under statutory accounting policies. Transactions cannot be edited or deleted directly; authorized corrections require linked reversals or refunds.
                                    </p>
                                </div>
                            </div>
                        ");
                    }),

                // 2. Invoice Metadata & Operating Department
                Section::make('Tax Invoice Metadata & Operating Department')
                    ->description('Fiscal business date, operational department, and assigned cashier shift.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                            TextInput::make('bill_number')
                                ->label('Invoice / Bill #')
                                ->default(fn () => '(Auto-generated on creation)')
                                ->disabled()
                                ->dehydrated(false),

                            DatePicker::make('business_date')
                                ->label('Fiscal Business Date')
                                ->required()
                                ->default(today())
                                ->disabled(fn ($record) => $record && ($record->status?->value ?? $record->status) !== 'draft')
                                ->helperText('Business date for accounting ledger recognition.'),

                            Select::make('department_code')
                                ->label('Operating Department')
                                ->required()
                                ->options([
                                    'food' => 'Food & Restaurant (Catering)',
                                    'bar' => 'Bar & Lounge (Liquor)',
                                    'card_room' => 'Card Room',
                                    'accommodation' => 'Accommodation (Rooms)',
                                    'sports' => 'Sports & Badminton',
                                    'general' => 'Club General Administration',
                                ])
                                ->default('food')
                                ->searchable()
                                ->disabled(fn ($record) => $record && ($record->status?->value ?? $record->status) !== 'draft'),

                            Select::make('shift_id')
                                ->label('Cashier Counter Shift')
                                ->required()
                                ->options(function () {
                                    return Shift::where('status', 'open')
                                        ->with('counter', 'user')
                                        ->get()
                                        ->mapWithKeys(fn ($s) => [
                                            $s->id => "Shift #{$s->shift_number} — " . ($s->counter?->name ?? 'Counter') . ' (' . ($s->user?->name ?? 'Staff') . ')'
                                        ]);
                                })
                                ->default(function () {
                                    return Shift::where('status', 'open')
                                        ->where('user_id', auth()->id())
                                        ->first()?->id
                                        ?? Shift::where('status', 'open')->latest()->first()?->id;
                                })
                                ->searchable()
                                ->disabled(fn ($record) => $record && ($record->status?->value ?? $record->status) !== 'draft')
                                ->helperText('Shift owning the transactional till drawer.'),
                        ]),
                    ]),

                // 3. Customer & Account Identification
                Section::make('Customer & Billing Account')
                    ->description('Select the club member or registered guest accountable for this invoice.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            Select::make('customer_type')
                                ->label('Customer Classification')
                                ->required()
                                ->options([
                                    Member::class => 'Primary Club Member',
                                    Guest::class => 'Registered Club Guest',
                                ])
                                ->default(Member::class)
                                ->live()
                                ->columnSpan(['default' => 1, 'md' => 1])
                                ->disabled(fn ($record) => $record && ($record->status?->value ?? $record->status) !== 'draft'),

                            Select::make('customer_id')
                                ->label('Customer / Member Selection')
                                ->required()
                                ->options(function ($get) {
                                    $type = $get('customer_type') ?? Member::class;
                                    if ($type === Member::class) {
                                        return Member::where('status', 'active')
                                            ->with('person')
                                            ->get()
                                            ->mapWithKeys(function ($m) {
                                                $bal = number_format(($m->wallet_balance_paise ?? 0) / 100, 2);
                                                return [$m->id => "{$m->membership_number} — {$m->name} (Prepaid Bal: ₹{$bal})"];
                                            });
                                    }

                                    return Guest::where('status', 'active')
                                        ->with('sponsor')
                                        ->get()
                                        ->mapWithKeys(function ($g) {
                                            $sponsor = $g->sponsor?->name ? " [Sponsor: {$g->sponsor->name}]" : '';
                                            return [$g->id => "{$g->guest_code} — {$g->name}{$sponsor}"];
                                        });
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->columnSpan(['default' => 1, 'md' => 2])
                                ->disabled(fn ($record) => $record && ($record->status?->value ?? $record->status) !== 'draft')
                                ->helperText('Search by member number, guest code, or full name.'),
                        ]),

                        // Dynamic Customer Status Preview Card
                        Placeholder::make('customer_preview')
                            ->columnSpanFull()
                            ->hidden(fn ($get) => !$get('customer_id'))
                            ->content(function ($get) {
                                $type = $get('customer_type') ?? Member::class;
                                $id = $get('customer_id');
                                if (!$id) {
                                    return null;
                                }

                                if ($type === Member::class) {
                                    $member = Member::with(['category', 'activeCard'])->find($id);
                                    if (!$member) return null;
                                    $bal = number_format(($member->wallet_balance_paise ?? 0) / 100, 2);
                                    $card = $member->activeCard?->card_number ?? 'No Active RFID Card';
                                    $cat = $member->category?->name ?? 'Standard Membership';

                                    return new HtmlString("
                                        <div class=\"p-3.5 bg-slate-50 border border-slate-200 rounded-xl grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-slate-700\">
                                            <div><span class=\"font-bold text-slate-500 block text-[10px] uppercase\">Member Name</span><span class=\"font-extrabold text-slate-900\">{$member->name}</span></div>
                                            <div><span class=\"font-bold text-slate-500 block text-[10px] uppercase\">Category</span><span class=\"font-semibold text-slate-800\">{$cat}</span></div>
                                            <div><span class=\"font-bold text-slate-500 block text-[10px] uppercase\">Active Card UID</span><span class=\"font-mono font-bold text-slate-700\">{$card}</span></div>
                                            <div><span class=\"font-bold text-slate-500 block text-[10px] uppercase\">Prepaid Balance</span><span class=\"font-mono font-extrabold text-emerald-800 text-sm\">₹{$bal}</span></div>
                                        </div>
                                    ");
                                } else {
                                    $guest = Guest::with('sponsor')->find($id);
                                    if (!$guest) return null;
                                    $sponsor = $guest->sponsor?->name ?? 'Unassigned';

                                    return new HtmlString("
                                        <div class=\"p-3.5 bg-slate-50 border border-slate-200 rounded-xl grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs text-slate-700\">
                                            <div><span class=\"font-bold text-slate-500 block text-[10px] uppercase\">Guest Name</span><span class=\"font-extrabold text-slate-900\">{$guest->name}</span></div>
                                            <div><span class=\"font-bold text-slate-500 block text-[10px] uppercase\">Phone</span><span class=\"font-semibold text-slate-800\">{$guest->phone}</span></div>
                                            <div><span class=\"font-bold text-slate-500 block text-[10px] uppercase\">Sponsoring Member</span><span class=\"font-bold text-amber-800\">{$sponsor}</span></div>
                                        </div>
                                    ");
                                }
                            }),
                    ]),

                // 4. Line Items & Services Repeater
                Section::make('Invoice Line Items & Dispensed Services')
                    ->description('Select items from the club master catalogue or enter custom items with exact pricing and GST.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->label('Billed Line Items')
                            ->columnSpanFull()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->disabled(fn ($record) => $record && ($record->status?->value ?? $record->status) !== 'draft')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 6])->schema([
                                    Select::make('tariff_id')
                                        ->label('Select Menu Item')
                                        ->placeholder('— Select from Menu & Price List —')
                                        ->options(fn () => Tariff::where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn ($t) => [
                                                $t->id => "{$t->name} (₹" . number_format($t->rate_paise / 100, 2) . " + {$t->tax_percent}% GST)"
                                            ]))
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, $get) {
                                            if ($state) {
                                                $tariff = Tariff::find($state);
                                                if ($tariff) {
                                                    $set('item_name', $tariff->name);
                                                    $set('department_code', $tariff->department_code);
                                                    $set('unit_price', round($tariff->rate_paise / 100, 2));
                                                    $set('tax_percent', (float) $tariff->tax_percent);
                                                    $set('item_type', 'tariff');
                                                    $set('item_id', $tariff->id);
                                                }
                                            }
                                        })
                                        ->columnSpan(['default' => 1, 'lg' => 2]),

                                    TextInput::make('item_name')
                                        ->label('Item / Service Description')
                                        ->required()
                                        ->placeholder('e.g. Thalassery Biryani / Beer / Guest Pass')
                                        ->columnSpan(['default' => 1, 'lg' => 2]),

                                    Select::make('department_code')
                                        ->label('Department')
                                        ->required()
                                        ->options([
                                            'food' => 'Food & Restaurant',
                                            'bar' => 'Bar & Lounge',
                                            'card_room' => 'Card Room',
                                            'accommodation' => 'Accommodation',
                                            'sports' => 'Sports',
                                            'general' => 'General',
                                        ])
                                        ->default(fn ($get) => $get('../../department_code') ?? 'food')
                                        ->columnSpan(['default' => 1, 'lg' => 2]),
                                ]),

                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                                    TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->minValue(0.01)
                                        ->default(1)
                                        ->required()
                                        ->live(),

                                    TextInput::make('unit_price')
                                        ->label('Unit Rate (₹)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix('₹')
                                        ->required()
                                        ->live()
                                        ->helperText('Excluding GST'),

                                    Select::make('tax_percent')
                                        ->label('GST Tax Rate')
                                        ->options([
                                            '0' => '0% (Exempt)',
                                            '5' => '5% (Food & Restaurant)',
                                            '12' => '12% (Standard Accommodation)',
                                            '18' => '18% (Bar & Sports Standard)',
                                            '28' => '28% (Luxury Tier)',
                                        ])
                                        ->default('18')
                                        ->required()
                                        ->live(),

                                    Placeholder::make('line_total_preview')
                                        ->label('Calculated Line Total (₹)')
                                        ->content(function ($get) {
                                            $qty = (float) ($get('quantity') ?? 1);
                                            $rate = (float) ($get('unit_price') ?? 0);
                                            $taxPercent = (float) ($get('tax_percent') ?? 0);
                                            $subtotal = $qty * $rate;
                                            $tax = ($subtotal * $taxPercent) / 100;
                                            $total = $subtotal + $tax;
                                            return '₹' . number_format($total, 2) . ' (GST: ₹' . number_format($tax, 2) . ')';
                                        }),
                                ]),
                            ]),
                    ]),

                // 5. Totals & Financial Summary
                Section::make('Invoice Totals & Financial Summary')
                    ->description('Automatic calculation of taxable subtotal, statutory GST, and net payable amount.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            Placeholder::make('summary_taxable')
                                ->label('Taxable Subtotal')
                                ->content(function ($get, $record) {
                                    if ($record) {
                                        return '₹' . number_format($record->gross_amount, 2);
                                    }
                                    $lines = $get('lines') ?? [];
                                    $subtotal = 0;
                                    foreach ($lines as $line) {
                                        $subtotal += ((float) ($line['quantity'] ?? 1)) * ((float) ($line['unit_price'] ?? 0));
                                    }
                                    return '₹' . number_format($subtotal, 2);
                                }),

                            Placeholder::make('summary_tax')
                                ->label('GST Tax (CGST + SGST)')
                                ->content(function ($get, $record) {
                                    if ($record) {
                                        return '₹' . number_format($record->tax_amount, 2);
                                    }
                                    $lines = $get('lines') ?? [];
                                    $totalTax = 0;
                                    foreach ($lines as $line) {
                                        $sub = ((float) ($line['quantity'] ?? 1)) * ((float) ($line['unit_price'] ?? 0));
                                        $taxRate = (float) ($line['tax_percent'] ?? 0);
                                        $totalTax += ($sub * $taxRate) / 100;
                                    }
                                    return '₹' . number_format($totalTax, 2);
                                }),

                            Placeholder::make('summary_net')
                                ->label('Net Invoice Total')
                                ->content(function ($get, $record) {
                                    if ($record) {
                                        return '₹' . number_format($record->net_amount, 2);
                                    }
                                    $lines = $get('lines') ?? [];
                                    $net = 0;
                                    foreach ($lines as $line) {
                                        $sub = ((float) ($line['quantity'] ?? 1)) * ((float) ($line['unit_price'] ?? 0));
                                        $taxRate = (float) ($line['tax_percent'] ?? 0);
                                        $net += $sub + (($sub * $taxRate) / 100);
                                    }
                                    return '₹' . number_format($net, 2);
                                }),
                        ]),

                        Textarea::make('notes')
                            ->label('Invoice Notes / Bill Remarks')
                            ->placeholder('e.g. Dining Table #4 / Special event / Front-desk room billing')
                            ->rows(2)
                            ->disabled(fn ($record) => $record && ($record->status?->value ?? $record->status) !== 'draft'),
                    ]),

                // 6. Immediate Settlement Section (Creation only)
                Section::make('Payment Method & Settlement')
                    ->description('Strict Club Policy: No credit is permitted for any member or guest. Payment must be confirmed immediately.')
                    ->hidden(fn ($record) => $record !== null)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Toggle::make('settle_immediately')
                                ->label('Settle & Post Official Bill Immediately')
                                ->default(true)
                                ->live()
                                ->helperText('If enabled, records payment, posts accounting entries, and generates confirmed receipt.'),

                            Select::make('tender_type')
                                ->label('Payment Method')
                                ->required(fn ($get) => (bool) $get('settle_immediately'))
                                ->visible(fn ($get) => (bool) $get('settle_immediately'))
                                ->options([
                                    'prepaid' => '💳 Member Prepaid Wallet (Server Ledger Balance)',
                                    'cash' => '💵 Counter Cash (Till Drawer)',
                                    'digital' => '📱 Digital Payment (UPI / QR / Card POS)',
                                ])
                                ->default('prepaid')
                                ->helperText('Wallet tender verifies member ledger balance before posting.'),
                        ]),
                    ]),

                // 7. Confirmed Payment Receipts (Visible on view/edit)
                Section::make('Confirmed Payment Receipts & Tenders')
                    ->description('Immutable receipts posted against this invoice.')
                    ->visible(fn ($record) => $record && $record->receipts->isNotEmpty())
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('receipts_table')
                            ->content(function ($record) {
                                if (!$record || $record->receipts->isEmpty()) {
                                    return 'No confirmed receipts posted.';
                                }

                                $rows = '';
                                foreach ($record->receipts as $rcp) {
                                    $amount = number_format($rcp->amount_paise / 100, 2);
                                    $tender = strtoupper(str_replace('_', ' ', $rcp->tender_type?->value ?? $rcp->tender_type));
                                    $date = $rcp->created_at ? $rcp->created_at->format('d M Y, h:i A') : '—';
                                    $rows .= "
                                        <tr class=\"hover:bg-slate-50 transition\">
                                            <td class=\"p-2.5 font-mono font-bold text-slate-900\">{$rcp->receipt_number}</td>
                                            <td class=\"p-2.5 font-bold text-slate-700\">{$tender}</td>
                                            <td class=\"p-2.5 font-mono font-extrabold text-emerald-800 text-right\">₹{$amount}</td>
                                            <td class=\"p-2.5 text-slate-500 text-right\">{$date}</td>
                                        </tr>
                                    ";
                                }

                                return new HtmlString("
                                    <div class=\"border border-slate-200 rounded-xl overflow-hidden text-xs\">
                                        <table class=\"w-full text-left\">
                                            <thead class=\"bg-slate-100/80 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200\">
                                                <tr>
                                                    <th class=\"p-2.5\">Receipt #</th>
                                                    <th class=\"p-2.5\">Tender Method</th>
                                                    <th class=\"p-2.5 text-right\">Amount Settled</th>
                                                    <th class=\"p-2.5 text-right\">Date / Timestamp</th>
                                                </tr>
                                            </thead>
                                            <tbody class=\"divide-y divide-slate-100 bg-white\">
                                                {$rows}
                                            </tbody>
                                        </table>
                                    </div>
                                ");
                            }),
                    ]),
            ]);
    }
}
