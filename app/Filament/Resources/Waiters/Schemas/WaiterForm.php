<?php

namespace App\Filament\Resources\Waiters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WaiterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Floor Staff & Service Bearer Information')
                    ->description('Register floor bearers and waiters for table order attribution and gratuity settlement.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Bearer Code / Badge #')
                                ->required()
                                ->maxLength(20)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. B01, B02, W10')
                                ->helperText('Short badge code used on POS quick selectors and printed bill receipts.'),

                            TextInput::make('name')
                                ->label('Bearer Full Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Rajesh Kumar')
                                ->helperText('Staff name displayed on customer receipts and tip disbursement sheets.'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('phone')
                                ->label('Contact Phone Number')
                                ->tel()
                                ->maxLength(30)
                                ->placeholder('+91 98470 00000'),

                            Select::make('department_code')
                                ->label('Primary Section / Department')
                                ->options([
                                    'bar' => 'Bar & Lounge',
                                    'food' => 'Food & Restaurant',
                                    'card_room' => 'Card Room',
                                ])
                                ->placeholder('All Departments / General Floor')
                                ->nullable(),
                        ]),

                        Grid::make(1)->schema([
                            Toggle::make('is_active')
                                ->label('Active for Order Attribution')
                                ->default(true)
                                ->helperText('When enabled, this bearer appears on Counter POS dropdowns and quick chips.'),

                            Textarea::make('notes')
                                ->label('Staff Remarks / Shift Notes')
                                ->rows(2)
                                ->placeholder('e.g. Evening shift primary bar floor bearer')
                                ->nullable(),
                        ]),
                    ]),
            ]);
    }
}

