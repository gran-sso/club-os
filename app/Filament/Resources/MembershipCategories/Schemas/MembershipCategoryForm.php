<?php

namespace App\Filament\Resources\MembershipCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MembershipCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Category Identity & Classification')
                    ->description('Define the unique code and formal title for this membership classification quota.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Category Code')
                                ->required()
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. ORD, LIFE, CORP, HON, ASSOC')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                                ->helperText('Short reference code stored in membership records.'),

                            TextInput::make('name')
                                ->label('Category Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Honorary Member or Associate Member')
                                ->helperText('Official classification printed on member smartcards and certificates.'),
                        ]),
                    ]),

                Section::make('Admission & Annual Fees')
                    ->description('Configure one-time admission capital fee, recurring annual subscription dues, and general meeting voting eligibility under Malabar Club bylaws.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('admission_fee_paise')
                                ->label('One-Time Admission Fee (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->prefix('₹')
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : 0)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Capital entrance fee charged once upon member admission.'),

                            TextInput::make('annual_fee_paise')
                                ->label('Annual Membership Fee (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->prefix('₹')
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : 0)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Recurring annual maintenance dues (leave ₹0 for Life/Permanent categories).'),
                        ]),

                        Grid::make(2)->schema([
                            Toggle::make('has_voting_rights')
                                ->label('Club Voting Rights')
                                ->default(true)
                                ->helperText('Eligible to vote in annual general meetings and committee elections.'),

                            Toggle::make('is_active')
                                ->label('Admissions Active')
                                ->default(true)
                                ->helperText('When inactive, category cannot be assigned to new applicants.'),
                        ]),
                    ]),
            ]);
    }
}

