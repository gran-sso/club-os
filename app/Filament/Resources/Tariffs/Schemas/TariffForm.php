<?php

namespace App\Filament\Resources\Tariffs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->placeholder('e.g. Signature Rare Aged Whisky (30ml Peg) or Deluxe Room Daily Tariff'),

                        Select::make('department_code')
                            ->label('Club Operating Department')
                            ->required()
                            ->searchable()
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
