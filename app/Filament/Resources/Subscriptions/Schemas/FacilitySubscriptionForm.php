<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Tariff;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FacilitySubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Member & Sports Facility Selection')
                    ->description('Select the club member and sports facility plan (Badminton or Gym).')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('member_id')
                                ->label('Primary Club Member')
                                ->required()
                                ->options(fn () => Member::with('person')
                                    ->where('status', 'active')
                                    ->get()
                                    ->mapWithKeys(fn ($m) => [
                                        $m->id => "{$m->membership_number} — " . ($m->person?->first_name ?? '') . ' ' . ($m->person?->last_name ?? '')
                                    ]))
                                ->searchable()
                                ->preload()
                                ->helperText('Prerequisite: Member must possess an active, non-expired club membership to hold a sports facility plan.'),

                            Select::make('facility_code')
                                ->label('Sports Facility')
                                ->required()
                                ->options([
                                    'badminton' => 'Badminton Court',
                                    'gym' => 'Gymnasium & Health Club',
                                ])
                                ->default('badminton')
                                ->live(),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('plan_type')
                                ->label('Subscription Duration')
                                ->required()
                                ->options([
                                    'monthly' => 'Monthly Plan (Calendar Month)',
                                    'annual' => 'Annual Plan (1 Full Calendar Year)',
                                ])
                                ->default('monthly')
                                ->live(),

                            Select::make('tariff_id')
                                ->label('Applicable Tariff Rate Master')
                                ->required()
                                ->options(fn () => Tariff::where('department_code', 'sports')
                                    ->orWhere('code', 'LIKE', '%PLAN%')
                                    ->orWhere('code', 'LIKE', '%GYM%')
                                    ->orWhere('code', 'LIKE', '%BADMINTON%')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function ($state, $set) {
                                    if ($state) {
                                        $tariff = Tariff::find($state);
                                        if ($tariff) {
                                            $set('total_fee_paise', $tariff->rate_paise / 100);
                                        }
                                    }
                                })
                                ->helperText('Select the approved club tariff for this sports plan.'),
                        ]),
                    ]),

                Section::make('Validity Period & Subscription Pricing')
                    ->description('Precise start and end dates according to facility membership rules.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('starts_at')
                                ->label('Plan Effective Start Date')
                                ->required()
                                ->default(now()->toDateString())
                                ->live()
                                ->afterStateUpdated(function ($get, $set, $state) {
                                    if ($state) {
                                        $planType = $get('plan_type') ?? 'monthly';
                                        $start = \Carbon\Carbon::parse($state);
                                        $end = $planType === 'annual'
                                            ? $start->copy()->addYear()->startOfDay()
                                            : $start->copy()->addMonth()->startOfDay();
                                        $set('ends_at', $end->toDateString());
                                    }
                                })
                                ->helperText('Date the member begins accessing the facility.'),

                            DatePicker::make('ends_at')
                                ->label('Plan Expiration Date (Exclusive Boundary)')
                                ->required()
                                ->default(now()->addMonth()->toDateString())
                                ->helperText('Access expires at end of day (23:59:59) preceding this date.'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('total_fee_paise')
                                ->label('Total Subscription Fee (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Stored securely in exact integer paise.'),

                            Select::make('status')
                                ->label('Subscription Status')
                                ->required()
                                ->options([
                                    'active' => 'Active (Access Granted)',
                                    'expired' => 'Expired',
                                    'cancelled' => 'Cancelled / Revoked',
                                ])
                                ->default('active'),
                        ]),
                    ]),
            ]);
    }
}
