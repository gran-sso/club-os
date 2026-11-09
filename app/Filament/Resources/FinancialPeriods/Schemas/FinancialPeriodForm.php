<?php

namespace App\Filament\Resources\FinancialPeriods\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FinancialPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Financial Period Definition')
                    ->description('Set calendar and fiscal boundary dates for the accounting period.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('period_code')
                            ->label('Period Reference Code')
                            ->required()
                            ->maxLength(32)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. FY2026-M11 or 2026-Q3')
                            ->helperText('Unique reference identifier for auditing and Tally fiscal mapping.'),

                        Grid::make(2)->schema([
                            DatePicker::make('start_date')
                                ->label('Period Start Date')
                                ->required()
                                ->default(now()->startOfMonth()->toDateString()),

                            DatePicker::make('end_date')
                                ->label('Period End Date')
                                ->required()
                                ->default(now()->endOfMonth()->toDateString()),
                        ]),
                    ]),

                Section::make('Status & Closing Lock Policy')
                    ->description('Strictly control backdating and transaction posting into this fiscal period.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('status')
                            ->label('Period Accounting Status')
                            ->required()
                            ->options([
                                'open' => 'Open (Live Billing & Expense Posting Allowed)',
                                'locked' => 'Locked (Auditing in Progress - Edits Restricted)',
                                'closed' => 'Closed (Strict Lock - Backdating Strictly Blocked)',
                            ])
                            ->default('open')
                            ->helperText('Once closed, no bill, refund, or expense can be back-dated into this period range.'),

                        Textarea::make('notes')
                            ->label('Audit & Management Notes')
                            ->rows(3)
                            ->placeholder('Closing notes, auditor review remarks, or committee approval references.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

