<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\Supplier;
use Filament\Forms\Components\DatePicker;
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
                                        ->columnSpan(2),

                                    TextInput::make('quantity')
                                        ->label('Received Quantity')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0.001)
                                        ->placeholder('e.g. 750 or 12')
                                        ->helperText('In base unit (e.g. ml)'),

                                    TextInput::make('unit_cost_paise')
                                        ->label('Unit Cost (₹)')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix('₹')
                                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                        ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                        ->helperText('Per base unit in ₹'),
                                ]),

                                Grid::make(2)->schema([
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
                                        ->required(),

                                    TextInput::make('notes')
                                        ->label('Line Notes / Batch #')
                                        ->placeholder('e.g. Batch 492 / Exp 2028'),
                                ]),
                            ])
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

