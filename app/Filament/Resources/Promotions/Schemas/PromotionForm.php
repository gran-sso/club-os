<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Domain\Billing\Models\Promotion;
use App\Domain\Billing\Services\PromotionService;
use App\Domain\Tariff\Models\Tariff;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Offer Identity & Status')
                    ->description('Configure club-approved Buy X Get Y Free schemes (e.g. Buy 2 Pegs, Get 1 Free). Only manager-configured offers apply on the Counter POS.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('code')
                                ->label('Offer Code')
                                ->required()
                                ->maxLength(40)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. BAR-2PLUS1-WHISKY')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                                ->helperText('Unique reference code printed on reports and audit logs.'),

                            TextInput::make('name')
                                ->label('Offer Display Name')
                                ->required()
                                ->maxLength(150)
                                ->placeholder('e.g. Signature Whisky 30ml — Buy 2 Get 1 Free')
                                ->columnSpan(2),
                        ]),

                        Toggle::make('is_active')
                            ->label('Offer Active on Counter POS')
                            ->default(true)
                            ->helperText('Turn off anytime to pause the offer immediately without deleting history.'),
                    ]),

                Section::make('Buy X, Get Y Mechanics')
                    ->description('Each completed set automatically bills the paid units (with GST on net price) while deducting total poured units (paid + free) from inventory.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('buy_qty')
                                ->label('Paid Units Required (Buy X)')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->default(2)
                                ->live()
                                ->helperText('e.g. 2 pegs'),

                            TextInput::make('get_qty')
                                ->label('Bonus / Free Units (Get Y)')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->live()
                                ->helperText('e.g. 1 free peg'),

                            TextInput::make('discount_percent')
                                ->label('Discount on Bonus Units (%)')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(100)
                                ->default(100)
                                ->suffix('%')
                                ->live()
                                ->helperText('100% = Completely Free'),
                        ]),

                        Placeholder::make('rule_summary')
                            ->label('POS Rule Summary Preview')
                            ->content(function ($get) {
                                $buy = max(1, (int) ($get('buy_qty') ?? 2));
                                $getQty = max(1, (int) ($get('get_qty') ?? 1));
                                $disc = (float) ($get('discount_percent') ?? 100);
                                $total = $buy + $getQty;

                                return $disc >= 99.99
                                    ? "Member orders {$total} units -> Pays for {$buy} + GST, gets {$getQty} FREE, and stock deducts all {$total} units."
                                    : "Member orders {$total} units -> Pays full price on {$buy}, gets {$disc}% off on {$getQty}, and stock deducts all {$total} units.";
                            }),
                    ]),

                Section::make('Eligible Serving Tariffs')
                    ->description('Select specific serving tariffs (e.g. 30ml Peg). Keep 30ml and 60ml pegs in separate offers so different peg sizes do not mix accidentally.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('tariffs')
                            ->label('Eligible Bar / Club Tariffs')
                            ->relationship(
                                name: 'tariffs',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('is_active', true)
                                    ->where('rate_paise', '>', 0)
                                    ->where('code', 'NOT LIKE', 'GUEST-%')
                                    ->orderBy('department_code')
                                    ->orderBy('name')
                            )
                            ->getOptionLabelFromRecordUsing(fn (Tariff $record) => "{$record->name} [{$record->code}] — ₹" . number_format($record->rate_paise / 100, 2))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Guest entry passes and zero-priced fees cannot receive promotional discounts.'),
                    ]),

                Section::make('Customer Eligibility, Validity Dates & Supplier Quota Cap')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('customer_scope')
                                ->label('Who Can Claim This Offer?')
                                ->required()
                                ->options([
                                    Promotion::SCOPE_MEMBERS_ONLY => 'Members & Resident Room Guests Only (Recommended)',
                                    Promotion::SCOPE_ALL => 'All Customers (Members, Room Guests & Day Guests)',
                                ])
                                ->default(Promotion::SCOPE_MEMBERS_ONLY)
                                ->helperText('Sponsored day guests only qualify when set to All Customers.'),

                            TextInput::make('max_free_units')
                                ->label('Optional Free-Unit Quota Cap (Units / Pegs)')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->nullable()
                                ->placeholder('Leave blank for unlimited')
                                ->helperText('e.g. 6 free 750ml bottles = 150 free 30ml pegs. Offer stops automatically once reached.'),
                        ]),

                        Grid::make(3)->schema([
                            DatePicker::make('effective_from')
                                ->label('Effective From')
                                ->required()
                                ->default(now()->toDateString()),

                            DatePicker::make('effective_to')
                                ->label('Effective Until (Optional)')
                                ->nullable()
                                ->afterOrEqual('effective_from')
                                ->helperText('Leave blank for ongoing offer until disabled.'),

                            Placeholder::make('redeemed_counter')
                                ->label('Redeemed Free Units So Far')
                                ->content(fn (?Promotion $record) => $record
                                    ? ($record->max_free_units !== null
                                        ? "{$record->redeemed_free_units} / {$record->max_free_units} units claimed"
                                        : "{$record->redeemed_free_units} units claimed (No cap)")
                                    : '0 units claimed'),
                        ]),

                        Textarea::make('notes')
                            ->label('Supplier Scheme / Management Authorization Notes')
                            ->rows(2)
                            ->placeholder('e.g. Funded by 6-bottle distributor cash discount on Inv #KSBC-2026-10492 (12+0 case).')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

