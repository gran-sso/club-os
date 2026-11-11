<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Supplier & Consignment Information')
                    ->description('Details of the wholesale supplier, official tax invoice, and delivery date.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('supplier_id')
                                ->label('Wholesale Supplier / Distributor')
                                ->required()
                                ->options(fn () => Supplier::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Supplier / Distributor Name')
                                        ->required()
                                        ->maxLength(150)
                                        ->placeholder('e.g. Kerala State Beverages Corp (KSBC)'),
                                    TextInput::make('code')
                                        ->label('Supplier Reference Code')
                                        ->required()
                                        ->maxLength(32)
                                        ->placeholder('e.g. KSBC-PKD')
                                        ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state))),
                                    TextInput::make('gstin')
                                        ->label('GSTIN (15 Digits)')
                                        ->maxLength(15)
                                        ->placeholder('e.g. 32AABCK1234F1Z5'),
                                    TextInput::make('phone')
                                        ->label('Phone Number')
                                        ->tel()
                                        ->maxLength(30),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $supplier = Supplier::create([
                                        'name' => $data['name'],
                                        'code' => strtoupper(trim($data['code'])),
                                        'gstin' => isset($data['gstin']) ? strtoupper(trim($data['gstin'])) : null,
                                        'phone' => $data['phone'] ?? null,
                                        'is_active' => true,
                                    ]);

                                    return $supplier->id;
                                })
                                ->helperText('e.g. Kerala State Beverages Corporation (KSBC) or authorized distributor.'),

                            TextInput::make('invoice_number')
                                ->label('Supplier Invoice / Delivery Challan #')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. KSBC-INV-2026-10492')
                                ->helperText('Vendor bill reference for input tax credit audit.'),

                            DatePicker::make('invoice_date')
                                ->label('Vendor Invoice Date')
                                ->required()
                                ->default(now()->toDateString())
                                ->helperText('Date printed on supplier invoice.'),
                        ]),

                        Textarea::make('notes')
                            ->label('Consignment & Delivery Notes')
                            ->rows(2)
                            ->placeholder('Batch numbers, transport permit details, or packaging inspection notes.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Inward Stock Consignment Lines')
                    ->description('Add all items received in this consignment. Quantities must be in base units (e.g. ml for bulk liquor bottles).')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->label('Received Stock Items')
                            ->minItems(1)
                            ->schema([
                                Grid::make(4)->schema([
                                    Select::make('inventory_item_id')
                                        ->label('Inventory Item')
                                        ->required()
                                        ->options(fn () => InventoryItem::where('is_active', true)->get()->mapWithKeys(
                                            fn ($i) => [$i->id => "{$i->name} (SKU: {$i->sku}, Unit: {$i->base_unit})"]
                                        ))
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->columnSpan(2),

                                    TextInput::make('bottle_count')
                                        ->label('Bottles / Packs (Helper)')
                                        ->numeric()
                                        ->minValue(1)
                                        ->dehydrated(false)
                                        ->live(onBlur: true)
                                        ->placeholder('e.g. 12')
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $count = (float) ($state ?? 0);
                                            $packSize = (float) ($get('pack_size_qty') ?? 0);
                                            if ($count > 0 && $packSize > 0) {
                                                $qty = round($count * $packSize, 3);
                                                $set('quantity', $qty);
                                                $gross = (float) ($get('line_gross_paise') ?? 0);
                                                if ($gross > 0 && $qty > 0) {
                                                    $set('unit_cost_paise', round($gross / $qty, 2));
                                                }
                                            }
                                        })
                                        ->helperText('Optional helper to auto-fill ml'),

                                    TextInput::make('pack_size_qty')
                                        ->label('Size per Bottle (ml / unit)')
                                        ->numeric()
                                        ->minValue(0.001)
                                        ->dehydrated(false)
                                        ->live(onBlur: true)
                                        ->placeholder('e.g. 750')
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $packSize = (float) ($state ?? 0);
                                            $count = (float) ($get('bottle_count') ?? 0);
                                            if ($count > 0 && $packSize > 0) {
                                                $qty = round($count * $packSize, 3);
                                                $set('quantity', $qty);
                                                $gross = (float) ($get('line_gross_paise') ?? 0);
                                                if ($gross > 0 && $qty > 0) {
                                                    $set('unit_cost_paise', round($gross / $qty, 2));
                                                }
                                            }
                                        })
                                        ->helperText('e.g. 750 for 750ml bottle'),
                                ]),

                                Grid::make(4)->schema([
                                    TextInput::make('quantity')
                                        ->label('Total Received Qty (Base Unit)')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0.001)
                                        ->live(onBlur: true)
                                        ->placeholder('e.g. 9000')
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $qty = (float) ($state ?? 0);
                                            $gross = (float) ($get('line_gross_paise') ?? 0);
                                            if ($qty > 0 && $gross > 0) {
                                                $set('unit_cost_paise', round($gross / $qty, 2));
                                            }
                                        })
                                        ->helperText('All received qty including scheme bottles (e.g. 12 × 750ml = 9000 ml)'),

                                    TextInput::make('line_gross_paise')
                                        ->label('Invoice Gross Cost (₹ Total)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix('₹')
                                        ->live(onBlur: true)
                                        ->placeholder('e.g. 12000.00')
                                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round(((float) $state) * 100) : null)
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $qty = (float) ($get('quantity') ?? 0);
                                            $gross = (float) ($state ?? 0);
                                            if ($qty > 0 && $gross >= 0) {
                                                $set('unit_cost_paise', round($gross / $qty, 2));
                                            }
                                        })
                                        ->helperText('Total invoice cost before cash discount (avoids ₹/ml rounding loss)'),

                                    TextInput::make('unit_cost_paise')
                                        ->label('Gross Unit Cost (₹/unit)')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix('₹')
                                        ->live(onBlur: true)
                                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                        ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $qty = (float) ($get('quantity') ?? 0);
                                            $unit = (float) ($state ?? 0);
                                            if ($qty > 0 && $unit >= 0) {
                                                $set('line_gross_paise', round($qty * $unit, 2));
                                            }
                                        })
                                        ->helperText('Per base unit before discount'),

                                    TextInput::make('discount_paise')
                                        ->label('Cash / Trade Discount (₹)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->prefix('₹')
                                        ->live(onBlur: true)
                                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : 0)
                                        ->dehydrateStateUsing(fn ($state) => (int) round(((float) ($state ?? 0)) * 100))
                                        ->helperText('e.g. ₹6,000 supplier cash discount on 12-bottle case'),
                                ]),

                                Grid::make(3)->schema([
                                    Select::make('tax_percent')
                                        ->label('Input GST Slabs (%)')
                                        ->options([
                                            '0.00' => '0% (Exempt / Liquor State Excise)',
                                            '5.00' => '5% (Provisions & Food Raw Materials)',
                                            '12.00' => '12% (Processed Consumables)',
                                            '18.00' => '18% (Standard Rate)',
                                            '28.00' => '28% (Luxury / Carbonated Drinks)',
                                        ])
                                        ->default('0.00')
                                        ->live()
                                        ->required(),

                                    TextInput::make('scheme_notes')
                                        ->label('Scheme / Batch Notes')
                                        ->maxLength(255)
                                        ->placeholder('e.g. 12-bottle case with 6-bottle cash discount'),

                                    Placeholder::make('effective_cost_preview')
                                        ->label('Net Effective Stock Valuation')
                                        ->content(function ($get) {
                                            $qty = (float) ($get('quantity') ?? 0);
                                            $gross = $get('line_gross_paise');
                                            $unit = (float) ($get('unit_cost_paise') ?? 0);
                                            $grossAmount = ($gross !== null && $gross !== '') ? (float) $gross : ($qty * $unit);
                                            $discount = (float) ($get('discount_paise') ?? 0);
                                            $netGoods = max(0, $grossAmount - $discount);
                                            $taxPct = (float) ($get('tax_percent') ?? 0);
                                            $netWithTax = $netGoods + ($netGoods * $taxPct / 100);
                                            $effectivePerUnit = $qty > 0 ? ($netGoods / $qty) : 0;

                                            return sprintf(
                                                'Net: ₹%.2f (₹%.2f/base unit) | Incl. GST: ₹%.2f',
                                                $netGoods,
                                                $effectivePerUnit,
                                                $netWithTax
                                            );
                                        }),
                                ]),
                            ])
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

