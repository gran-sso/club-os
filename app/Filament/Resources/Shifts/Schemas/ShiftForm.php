<?php

namespace App\Filament\Resources\Shifts\Schemas;

use App\Domain\Billing\Models\Counter;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Counter Station & Cashier Assignment')
                    ->description('Assign the physical billing terminal and front-desk cashier.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('counter_id')
                                ->label('Billing Counter Station')
                                ->required()
                                ->options(fn () => Counter::pluck('name', 'id'))
                                ->default(fn () => Counter::first()?->id)
                                ->searchable()
                                ->helperText('Physical POS station terminal.'),

                            Select::make('user_id')
                                ->label('Station Cashier / Staff')
                                ->required()
                                ->options(fn () => User::pluck('name', 'id'))
                                ->default(fn () => auth()->id())
                                ->searchable()
                                ->helperText('Staff user responsible for drawer cash balancing.'),

                            DatePicker::make('business_date')
                                ->label('Fiscal Business Date')
                                ->required()
                                ->default(now()->toDateString())
                                ->helperText('Operating day to which sales and drawer collections are attributed.'),
                        ]),
                    ]),

                Section::make('Drawer Cash & Shift Float (₹)')
                    ->description('Initial physical cash float in drawer and shift status.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('opening_float_paise')
                                ->label('Opening Cash Float (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->default(1000.00)
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Physical starting change handed over to cashier at shift opening.'),

                            Select::make('status')
                                ->label('Shift Operational Status')
                                ->required()
                                ->options([
                                    'open' => 'Open (Active Billing)',
                                    'closed' => 'Closed (Reconciled)',
                                ])
                                ->default('open'),
                        ]),

                        Textarea::make('notes')
                            ->label('Shift Notes & Observations')
                            ->rows(3)
                            ->placeholder('Handover notes, drawer discrepancies, or shift remarks.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
