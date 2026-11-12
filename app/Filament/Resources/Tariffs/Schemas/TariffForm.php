<?php

namespace App\Filament\Resources\Tariffs\Schemas;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TariffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Item / Service Details')
                    ->description('Define the service, beverage, room, or club facility charge.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Item Code')
                                ->required()
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. TAR-BAR-001 or RM-DLX-NIGHT')
                                ->helperText('Unique reference identifier for billing and reports.'),

                            TextInput::make('barcode')
                                ->label('Barcode / EAN (Optional)')
                                ->maxLength(64)
                                ->placeholder('e.g. 8901234567890')
                                ->helperText('Used for quick item lookup via physical barcode scanner at the counter.'),
                        ]),

                        TextInput::make('name')
                            ->label('Menu / Item Name')
                            ->required()
                            ->maxLength(150)
                            ->live(onBlur: true)
                            ->placeholder('e.g. Signature Rare Aged Whisky (30ml Peg) or Deluxe Room Daily Tariff'),

                        Select::make('department_code')
                            ->label('Club Operating Department')
                            ->required()
                            ->searchable()
                            ->live()
                            ->options([
                                'bar' => 'Bar & Lounge',
                                'food' => 'Food & Restaurant',
                                'accommodation' => 'Accommodation (Rooms)',
                                'sports' => 'Sports & Fitness (Badminton / Gym)',
                                'card_room' => 'Card Room',
                                'membership' => 'Membership & Front Desk',
                                'admin' => 'Administration',
                            ])
                            ->default('bar')
                            ->helperText('Determines departmental revenue attribution and P&L reporting.'),
                    ]),

                Section::make('Indian Pricing & GST Slabs')
                    ->description('Configure the base price in Rupees and the statutory Goods & Services Tax (GST) rate.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('rate_paise')
                                ->label('Selling Price / Base Rate (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->live(onBlur: true)
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Stored securely in exact integer paise. Enter selling price in Rupees.'),

                            Select::make('tax_percent')
                                ->label('GST Tax Rate (%)')
                                ->required()
                                ->options([
                                    '0.00' => '0% (Nil / Exempted / Member Subscriptions)',
                                    '5.00' => '5% (Restaurant Food Services)',
                                    '12.00' => '12% (Accommodation Tariff under ₹7,500/night)',
                                    '18.00' => '18% (Standard Club Services / Bar Pegs / Guest Passes)',
                                    '28.00' => '28% (Luxury / Specific Goods)',
                                ])
                                ->default('18.00')
                                ->helperText('CGST & SGST are automatically split 50:50 on customer tax invoices.'),
                        ]),
                    ]),

                Section::make('Stock Deduction Link (Bar & Packaged Goods)')
                    ->description('Optionally connect this selling item to an Inventory Item so every Counter POS sale automatically deducts cellar stock and posts Cost of Goods Sold (COGS).')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('track_inventory')
                            ->label('Deduct from Club Inventory when sold?')
                            ->default(false)
                            ->live()
                            ->helperText('Turn ON for bar pours (30ml/60ml pegs), full bottles, cans, or club-stocked merchandise. Keep OFF for services (rooms, courts, card room, guest passes) and contractor-supplied food.'),

                        Placeholder::make('bar_stock_warning')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => $get('department_code') === 'bar' && ! $get('track_inventory'))
                            ->content(new HtmlString(
                                '<div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-200">'
                                .'<strong>Heads up:</strong> This item is in <strong>Bar &amp; Lounge</strong> without a stock link. '
                                .'Counter POS sales will record revenue, but liquor stock and COGS will <strong>not</strong> be deducted until you enable stock deduction.'
                                .'</div>'
                            )),

                        Select::make('serving_inventory_item_id')
                            ->label('Physical Stock Item (from Inventory Items)')
                            ->visible(fn (Get $get): bool => (bool) $get('track_inventory'))
                            ->required(fn (Get $get): bool => (bool) $get('track_inventory'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(function (Get $get): array {
                                $currentId = $get('serving_inventory_item_id');

                                return InventoryItem::query()
                                    ->where(function ($query) use ($currentId): void {
                                        $query->where('is_active', true);
                                        if ($currentId) {
                                            $query->orWhere('id', $currentId);
                                        }
                                    })
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(function (InventoryItem $item): array {
                                        $decimals = $item->base_unit === 'ml' ? 0 : 2;
                                        $stockFmt = number_format((float) $item->current_stock_qty, $decimals);
                                        $statusTag = $item->is_active ? '' : ' [INACTIVE]';

                                        return [
                                            $item->id => "{$item->name} (SKU: {$item->sku} • Stock: {$stockFmt} {$item->base_unit}){$statusTag}",
                                        ];
                                    })
                                    ->all();
                            })
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (! $state) {
                                    $set('serving_preset', 'custom');

                                    return;
                                }

                                /** @var InventoryItem|null $item */
                                $item = InventoryItem::find($state);
                                if (! $item) {
                                    return;
                                }

                                // Auto-populate a sensible starting serving size only when fields are empty
                                if (blank($get('serving_qty')) && blank($get('serving_name'))) {
                                    $defaultPreset = match ($item->base_unit) {
                                        'ml' => '30ml',
                                        'bottle' => 'full_bottle',
                                        'can' => '1_can',
                                        'pcs' => '1_pc',
                                        'portion' => '1_portion',
                                        default => null,
                                    };

                                    $resolved = InventoryService::resolveServingPreset($item->base_unit, $defaultPreset);
                                    if ($resolved) {
                                        $set('serving_preset', $defaultPreset);
                                        $set('serving_name', $resolved['serving_name']);
                                        $set('serving_qty', $resolved['serving_qty']);

                                        return;
                                    }
                                }

                                $set('serving_preset', 'custom');
                            })
                            ->createOptionForm([
                                Grid::make(2)->schema([
                                    TextInput::make('sku')
                                        ->label('Item Code (SKU)')
                                        ->required()
                                        ->maxLength(64)
                                        ->unique(InventoryItem::class, 'sku', ignoreRecord: false)
                                        ->placeholder('e.g. LIQ-SIG-750ML')
                                        ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state))),
                                    TextInput::make('barcode')
                                        ->label('Bottle Barcode (Optional)')
                                        ->maxLength(64),
                                ]),
                                TextInput::make('name')
                                    ->label('Stock Item Name / Brand')
                                    ->required()
                                    ->maxLength(150)
                                    ->placeholder('e.g. Signature Rare Aged Whisky 750ml'),
                                Grid::make(3)->schema([
                                    Select::make('department_code')
                                        ->label('Department')
                                        ->required()
                                        ->options([
                                            'bar' => 'Bar & Lounge',
                                            'food' => 'Food & Restaurant',
                                            'consumables' => 'Clubhouse Amenities',
                                        ])
                                        ->default('bar'),
                                    Select::make('category')
                                        ->label('Category')
                                        ->required()
                                        ->options([
                                            'liquor' => 'IMFL / Spirits',
                                            'beer_wine' => 'Beer & Wine',
                                            'beverages' => 'Mixers & Soft Drinks',
                                            'tobacco' => 'Tobacco',
                                            'consumables' => 'Club Supplies',
                                        ])
                                        ->default('liquor'),
                                    Select::make('base_unit')
                                        ->label('Tracking Unit')
                                        ->required()
                                        ->options([
                                            'ml' => 'Milliliters (ml)',
                                            'bottle' => 'Bottles',
                                            'can' => 'Cans',
                                            'pcs' => 'Pieces',
                                            'portion' => 'Portions',
                                        ])
                                        ->default('ml'),
                                ]),
                                TextInput::make('reorder_level')
                                    ->label('Low Stock Alert Level')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(1500)
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $user = auth()->user();
                                if (! $user || ! $user->hasClubPermission('stock.manage')) {
                                    throw new UnauthorizedActionException('You need inventory management permission (stock.manage) to create new stock items.');
                                }

                                $item = InventoryItem::create([
                                    'sku' => strtoupper(trim((string) $data['sku'])),
                                    'barcode' => isset($data['barcode']) && trim((string) $data['barcode']) !== '' ? trim((string) $data['barcode']) : null,
                                    'name' => trim((string) $data['name']),
                                    'department_code' => $data['department_code'] ?? 'bar',
                                    'category' => $data['category'] ?? 'liquor',
                                    'base_unit' => $data['base_unit'] ?? 'ml',
                                    'reorder_level' => (float) ($data['reorder_level'] ?? 0),
                                    'current_stock_qty' => 0,
                                    'current_avg_cost_paise' => 0,
                                    'is_active' => true,
                                ]);

                                return $item->id;
                            })
                            ->createOptionAction(fn (Action $action) => $action
                                ->label('Quick-Register Stock Item')
                                ->modalHeading('Register New Inventory Item')
                                ->visible(fn (): bool => (bool) auth()->user()?->hasClubPermission('stock.manage')))
                            ->helperText('You can type to search by bottle name or SKU. Multiple menu prices (e.g. 30ml Peg, 60ml Peg, Full Bottle) can link to the same stock item.'),

                        Grid::make(3)
                            ->visible(fn (Get $get): bool => (bool) $get('track_inventory'))
                            ->schema([
                                Select::make('serving_preset')
                                    ->label('Quick Pour / Unit Helper')
                                    ->options(function (Get $get): array {
                                        $itemId = $get('serving_inventory_item_id');
                                        $baseUnit = $itemId ? InventoryItem::whereKey($itemId)->value('base_unit') : null;

                                        return InventoryService::servingPresetOptionsForUnit($baseUnit);
                                    })
                                    ->default('custom')
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                        $itemId = $get('serving_inventory_item_id');
                                        $baseUnit = $itemId ? InventoryItem::whereKey($itemId)->value('base_unit') : null;
                                        $resolved = InventoryService::resolveServingPreset($baseUnit, $state);
                                        if ($resolved) {
                                            $set('serving_name', $resolved['serving_name']);
                                            $set('serving_qty', $resolved['serving_qty']);
                                        }
                                    })
                                    ->helperText('Pick a preset to auto-fill serving label and quantity, or leave as Custom.'),

                                TextInput::make('serving_name')
                                    ->label('Serving Size Description')
                                    ->required(fn (Get $get): bool => (bool) $get('track_inventory'))
                                    ->maxLength(100)
                                    ->live(onBlur: true)
                                    ->placeholder('e.g. 30 ml (Small Peg) or 1 Can')
                                    ->helperText('Recorded on stock issue audit notes.'),

                                TextInput::make('serving_qty')
                                    ->label('Stock Deducted Per Sale')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step('any')
                                    ->required(fn (Get $get): bool => (bool) $get('track_inventory'))
                                    ->live(onBlur: true)
                                    ->suffix(function (Get $get): string {
                                        $itemId = $get('serving_inventory_item_id');

                                        return $itemId ? (string) (InventoryItem::whereKey($itemId)->value('base_unit') ?? 'units') : 'units';
                                    })
                                    ->helperText('Exact stock subtracted for each 1 quantity billed.'),
                            ]),

                        Placeholder::make('stock_deduction_preview')
                            ->label('How This Works at the Counter POS')
                            ->visible(fn (Get $get): bool => (bool) $get('track_inventory'))
                            ->content(function (Get $get): HtmlString {
                                $itemId = $get('serving_inventory_item_id');
                                $qty = (float) ($get('serving_qty') ?? 0);
                                $menuName = trim((string) ($get('name') ?? 'this menu item')) ?: 'this menu item';

                                if (! $itemId) {
                                    return new HtmlString('<span class="text-xs text-gray-500">Select a physical stock item above to preview how stock will be deducted per sale.</span>');
                                }

                                /** @var InventoryItem|null $item */
                                $item = InventoryItem::find($itemId);
                                if (! $item) {
                                    return new HtmlString('<span class="text-xs text-gray-500">Selected stock item could not be found.</span>');
                                }

                                $unit = $item->base_unit;
                                $stockDecimals = $unit === 'ml' ? 0 : 2;
                                $qtyDecimals = fmod($qty, 1.0) === 0.0 ? 0 : 3;
                                $stockFmt = number_format((float) $item->current_stock_qty, $stockDecimals);

                                if ($qty <= 0) {
                                    return new HtmlString('<span class="text-xs text-amber-700">Enter a deduction quantity greater than 0 '.$unit.' per sale.</span>');
                                }

                                $qtyFmt = number_format($qty, $qtyDecimals);
                                $servingsLeft = (int) floor((float) $item->current_stock_qty / $qty);
                                $estCogs = ($qty * (int) $item->current_avg_cost_paise) / 100;

                                return new HtmlString(
                                    '<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">'
                                    .'Selling <strong>1 × '.e($menuName).'</strong> will deduct '
                                    .'<strong>'.e($qtyFmt.' '.$unit).'</strong> from <strong>'.e($item->name).'</strong> '
                                    .'(Current Stock: <strong>'.e($stockFmt.' '.$unit).'</strong> ≈ '.$servingsLeft.' servings on hand; '
                                    .'Est. Cost ₹'.number_format($estCogs, 2).' per sale).'
                                    .'</div>'
                                );
                            }),
                    ]),

                Section::make('Effective Dates & Active Status')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('effective_from')
                                ->label('Effective From')
                                ->default(now()->toDateString())
                                ->required()
                                ->helperText('Tariff rate becomes active on this business date.'),

                            DatePicker::make('effective_to')
                                ->label('Effective Until (Optional)')
                                ->nullable()
                                ->helperText('Leave empty for ongoing indefinite tariffs.'),
                        ]),

                        Toggle::make('is_active')
                            ->label('Active for Counter POS Billing')
                            ->default(true)
                            ->helperText('When disabled, this item will not appear in POS item search or counter menus.'),
                    ]),
            ]);
    }
}
