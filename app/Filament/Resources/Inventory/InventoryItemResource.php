<?php

namespace App\Filament\Resources\Inventory;

use App\Domain\Inventory\Models\InventoryItem;
use App\Filament\Resources\Inventory\Pages\ListInventoryItems;
use App\Filament\Resources\Inventory\Tables\InventoryItemsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial & Counters';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Inventory Items';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 6;

    protected static ?int $globalSearchSort = 9;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'barcode', 'category'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Item: {$record->name} ({$record->sku})";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $stock = (float) ($record->current_stock_qty ?? 0) . ' ' . ($record->base_unit ?? 'units');
        $isLowStock = ($record->reorder_level && $record->current_stock_qty <= $record->reorder_level);

        return [
            'Category' => ucfirst($record->category ?? 'Stock'),
            'Stock on Hand' => $stock,
            'Barcode' => $record->barcode ?? 'N/A',
            'Stock Alert' => $isLowStock ? 'Low Stock Warning' : 'Normal',
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['stock.view', 'stock.manage']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('stock.manage'));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('stock.manage'));
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                \Filament\Schemas\Components\Section::make('Stock Item Master Details')
                    ->description('Register liquor, beer, wine, beverages, and club consumables.')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('sku')
                                ->label('Item Code (SKU)')
                                ->required()
                                ->maxLength(64)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. LIQ-SIG-750ML or BEV-COKE-330')
                                ->helperText('Unique stock-keeping unit reference.'),

                            \Filament\Forms\Components\TextInput::make('barcode')
                                ->label('Bottle / Packaging Barcode (EAN)')
                                ->maxLength(64)
                                ->placeholder('e.g. 8901234567890')
                                ->helperText('Used for fast receiving and bar stock reconciliation.'),
                        ]),

                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Item Name / Brand')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('e.g. Signature Rare Aged Whisky 750ml Bottle'),

                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\Select::make('department_code')
                                ->label('Club Department')
                                ->required()
                                ->options([
                                    'bar' => 'Bar & Lounge',
                                    'food' => 'Food & Restaurant',
                                    'consumables' => 'Housekeeping & Clubhouse Amenities',
                                ])
                                ->default('bar'),

                            \Filament\Forms\Components\Select::make('category')
                                ->label('Item Category')
                                ->required()
                                ->options([
                                    'liquor' => 'IMFL / Imported Spirits (Whisky, Brandy, Rum, Vodka)',
                                    'beer_wine' => 'Beer, Wine & Cider',
                                    'beverages' => 'Non-Alcoholic Beverages & Mixers',
                                    'tobacco' => 'Tobacco & Cigars',
                                    'food_raw' => 'Kitchen Raw Ingredients',
                                    'consumables' => 'General Club Supplies',
                                ])
                                ->default('liquor'),
                        ]),
                    ]),

                \Filament\Schemas\Components\Section::make('Unit of Measure, Costing & Reorder Thresholds')
                    ->description('Liquor stock is tracked in milliliters (ml) and costed on a perpetual moving weighted-average basis.')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\Select::make('base_unit')
                                ->label('Base Unit of Measurement')
                                ->required()
                                ->options([
                                    'ml' => 'Milliliters (ml) — Standard for spirits & draft',
                                    'bottle' => 'Bottles (Units) — Wine, Champagne',
                                    'can' => 'Cans (Units) — Beer, Soda, Tonics',
                                    'pcs' => 'Pieces / Packets',
                                    'kg' => 'Kilograms (kg)',
                                    'portion' => 'Standard Portions',
                                ])
                                ->default('ml')
                                ->helperText('All bar pours (30ml, 60ml) deduct from this base unit.'),

                            \Filament\Forms\Components\TextInput::make('reorder_level')
                                ->label('Low Stock Alert Level')
                                ->numeric()
                                ->minValue(0)
                                ->default(1500)
                                ->required()
                                ->helperText('System flags low stock when quantity drops below this threshold (in base units).'),
                        ]),

                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('current_stock_qty')
                                ->label('Stock Available Now')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->helperText('Physical quantity on hand in cellar & bar counters.'),

                            \Filament\Forms\Components\TextInput::make('current_avg_cost_paise')
                                ->label('Average Purchase Cost (₹)')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : 0)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Weighted average cost per base unit in Rupees. Used for perpetual COGS.'),
                        ]),

                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Active for Purchasing and Serving Issue')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return InventoryItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
        ];
    }
}
