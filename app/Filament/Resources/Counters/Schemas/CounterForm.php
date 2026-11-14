<?php

namespace App\Filament\Resources\Counters\Schemas;

use App\Domain\Tariff\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CounterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Billing Counter Station Information')
                    ->description('Configure physical POS terminals, billing stations, and counter till points.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Counter Identifier Code')
                                ->required()
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. COUNTER-4, BAR-2, or POOL-COUNTER')
                                ->helperText('Unique station code used on shift reconciliation, receipts, and audit logs.'),

                            TextInput::make('name')
                                ->label('Counter Display Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Poolside Bar Counter or Family Dining POS')
                                ->helperText('Descriptive name shown to cashiers when opening their counter shift.'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('department_id')
                                ->label('Primary Operating Department')
                                ->options(fn () => Department::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->helperText('Associates the station with a home department (e.g. Bar & Lounge, Front Desk).'),

                            Toggle::make('is_active')
                                ->label('Active for Shift Operations')
                                ->default(true)
                                ->helperText('When enabled, cashiers can select and open cash shifts at this counter.'),
                        ]),
                    ]),
            ]);
    }
}

