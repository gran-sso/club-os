<?php

namespace App\Filament\Resources\Inventory\Tables;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Domain\Tariff\Models\Tariff;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class InventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['servingMappings.tariff'])
                ->withCount('servingMappings'))
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable(),
                TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('current_stock_qty')
                    ->label('Stock Qty')
                    ->state(fn ($record) => number_format($record->current_stock_qty, $record->base_unit === 'ml' ? 0 : 2) . ' ' . $record->base_unit)
                    ->sortable()
                    ->weight('bold')
                    ->color(fn ($record) => $record->current_stock_qty <= $record->reorder_level ? 'danger' : 'success'),
                TextColumn::make('current_avg_cost_paise')
                    ->label('Avg Purchase Cost')
                    ->state(fn ($record) => '₹' . number_format($record->current_avg_cost_paise / 100, 2) . '/' . $record->base_unit)
                    ->sortable(),
                TextColumn::make('menu_prices')
                    ->label('Menu Prices')
                    ->badge()
                    ->state(function (InventoryItem $record): string {
                        $count = (int) ($record->serving_mappings_count ?? $record->servingMappings->count());
                        if ($count === 0) {
                            return 'Not on Menu (0)';
                        }

                        $first = $record->servingMappings->first();
                        $sampleTariff = $first?->tariff;
                        if ($count === 1 && $sampleTariff) {
                            return '1 price (₹' . number_format($sampleTariff->rate_paise / 100, 0) . ')';
                        }

                        return "{$count} linked prices";
                    })
                    ->color(function (InventoryItem $record): string {
                        $count = (int) ($record->serving_mappings_count ?? $record->servingMappings->count());

                        return $count > 0 ? 'info' : 'warning';
                    })
                    ->description(function (InventoryItem $record): ?string {
                        if ($record->servingMappings->isEmpty()) {
                            return 'Needs Menu & Price List entry';
                        }

                        return $record->servingMappings
                            ->map(fn ($m) => $m->serving_name . ($m->tariff ? ' • ₹' . number_format($m->tariff->rate_paise / 100, 0) : ''))
                            ->take(2)
                            ->implode(' | ');
                    }),
                TextColumn::make('reorder_level')
                    ->label('Reorder Level')
                    ->state(fn ($record) => number_format($record->reorder_level, 0) . ' ' . $record->base_unit)
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'liquor' => 'Spirits & Liquor',
                        'beer_wine' => 'Beer & Wine',
                        'beverages' => 'Beverages & Soft Drinks',
                        'tobacco' => 'Tobacco',
                        'consumables' => 'Consumables',
                    ]),
                Filter::make('unlinked_menu')
                    ->label('Unlinked (Not on Menu & Price List)')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('servingMappings')),
            ])
            ->recordActions([
                Action::make('sellingPrices')
                    ->label('Selling Prices')
                    ->icon('heroicon-o-tag')
                    ->color('gray')
                    ->modalHeading(fn (InventoryItem $record): string => "Selling Prices — {$record->name}")
                    ->modalContent(fn (InventoryItem $record) => view('filament.components.inventory-serving-prices-list', ['item' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('addSellingPrice')
                    ->label('Add Selling Price')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (): bool => (bool) auth()->user()?->hasClubPermission('tariffs.manage'))
                    ->modalHeading(fn (InventoryItem $record): string => "Add Menu Selling Price for {$record->name}")
                    ->modalDescription('Creates an active Menu & Price List entry pre-linked to deduct this physical stock item at Counter POS.')
                    ->modalSubmitActionLabel('Create Price & Link Stock')
                    ->form(fn (InventoryItem $record): array => self::addSellingPriceFormSchema($record))
                    ->action(function (InventoryItem $record, array $data, Action $action): void {
                        try {
                            $tariff = app(InventoryService::class)->createTariffWithServingMapping(
                                tariffData: $data,
                                mappingData: [
                                    'inventory_item_id' => $record->id,
                                    'serving_name' => $data['serving_name'] ?? null,
                                    'serving_qty' => $data['serving_qty'] ?? null,
                                ],
                                actor: auth()->user(),
                            );

                            app(\App\Domain\Shared\Services\AuditLogService::class)->logTariffChange(
                                tariff: $tariff,
                                action: 'created',
                                new: $data
                            );

                            Notification::make()
                                ->title('Selling Price Created & Linked')
                                ->body("Added {$tariff->name} ({$tariff->code}) linked to deduct {$data['serving_qty']} {$record->base_unit} per sale.")
                                ->success()
                                ->send();
                        } catch (InvalidArgumentException|UnauthorizedActionException $e) {
                            Notification::make()
                                ->title('Could Not Create Selling Price')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),

                EditAction::make(),
            ]);
    }

    /**
     * Build the modal form schema for creating a linked Menu & Price List entry directly from Inventory Items.
     *
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    protected static function addSellingPriceFormSchema(InventoryItem $record): array
    {
        $defaultPreset = match ($record->base_unit) {
            'ml' => '30ml',
            'bottle' => 'full_bottle',
            'can' => '1_can',
            'pcs' => '1_pc',
            'portion' => '1_portion',
            default => null,
        };
        $resolvedDefault = InventoryService::resolveServingPreset($record->base_unit, $defaultPreset);

        $skuClean = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $record->sku));
        $nextSeq = $record->servingMappings()->count() + 1;
        $suggestedCode = substr("TAR-{$skuClean}", 0, 28) . "-{$nextSeq}";
        if (Tariff::where('code', $suggestedCode)->exists()) {
            $suggestedCode = substr('TAR-' . $record->id . '-' . now()->format('His'), 0, 32);
        }

        $defaultDepartment = in_array($record->department_code, ['bar', 'food', 'accommodation', 'sports', 'card_room', 'membership', 'admin'], true)
            ? $record->department_code
            : 'bar';

        return [
            Placeholder::make('existing_prices')
                ->hiddenLabel()
                ->content(fn () => view('filament.components.inventory-serving-prices-list', ['item' => $record])),

            Grid::make(2)->schema([
                TextInput::make('code')
                    ->label('Menu Item Code')
                    ->required()
                    ->maxLength(32)
                    ->default($suggestedCode)
                    ->unique(Tariff::class, 'code', ignoreRecord: false)
                    ->placeholder('e.g. TAR-BAR-101'),

                TextInput::make('barcode')
                    ->label('POS Barcode (Optional)')
                    ->maxLength(64)
                    ->default($record->barcode),
            ]),

            Grid::make(2)->schema([
                TextInput::make('name')
                    ->label('Menu / Item Name (Shown on POS & Receipt)')
                    ->required()
                    ->maxLength(150)
                    ->default($resolvedDefault ? "{$record->name} ({$resolvedDefault['serving_name']})" : $record->name),

                Select::make('department_code')
                    ->label('Club Operating Department')
                    ->required()
                    ->options([
                        'bar' => 'Bar & Lounge',
                        'food' => 'Food & Restaurant',
                        'accommodation' => 'Accommodation (Rooms)',
                        'sports' => 'Sports & Fitness',
                        'card_room' => 'Card Room',
                        'membership' => 'Membership & Front Desk',
                        'admin' => 'Administration',
                    ])
                    ->default($defaultDepartment),
            ]),

            Grid::make(3)->schema([
                Select::make('serving_preset')
                    ->label('Quick Pour / Unit Helper')
                    ->options(fn (): array => InventoryService::servingPresetOptionsForUnit($record->base_unit))
                    ->default($defaultPreset ?? 'custom')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($record): void {
                        $resolved = InventoryService::resolveServingPreset($record->base_unit, $state);
                        if ($resolved) {
                            $set('serving_name', $resolved['serving_name']);
                            $set('serving_qty', $resolved['serving_qty']);
                            $set('name', "{$record->name} ({$resolved['serving_name']})");
                        }
                    }),

                TextInput::make('serving_name')
                    ->label('Serving Size Description')
                    ->required()
                    ->maxLength(100)
                    ->default($resolvedDefault['serving_name'] ?? null)
                    ->placeholder('e.g. 30 ml (Small Peg) or 1 Can'),

                TextInput::make('serving_qty')
                    ->label('Stock Deducted Per Sale')
                    ->numeric()
                    ->minValue(0.001)
                    ->step('any')
                    ->required()
                    ->default($resolvedDefault['serving_qty'] ?? null)
                    ->suffix($record->base_unit),
            ]),

            Grid::make(3)->schema([
                TextInput::make('rate_paise')
                    ->label('Selling Price / Base Rate (₹)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₹')
                    ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),

                Select::make('tax_percent')
                    ->label('GST Tax Rate (%)')
                    ->required()
                    ->options([
                        '0.00' => '0% (Nil / Exempted)',
                        '5.00' => '5% (Food Services)',
                        '12.00' => '12% (Accommodation)',
                        '18.00' => '18% (Standard Bar / Club)',
                        '28.00' => '28% (Luxury / Specific)',
                    ])
                    ->default('18.00'),

                DatePicker::make('effective_from')
                    ->label('Effective From')
                    ->default(now()->toDateString())
                    ->required(),
            ]),

            Toggle::make('is_active')
                ->label('Active for Counter POS Billing')
                ->default(true),
        ];
    }
}
