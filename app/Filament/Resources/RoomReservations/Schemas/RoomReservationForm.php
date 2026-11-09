<?php

namespace App\Filament\Resources\RoomReservations\Schemas;

use App\Domain\Access\Models\Guest;
use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoomReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Room & Occupant Identification')
                    ->description('Select the accommodation room and assign the visiting guest or sponsoring club member.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('facility_id')
                                ->label('Accommodation Room')
                                ->required()
                                ->options(fn () => Facility::where('type', 'room')
                                    ->where('is_active', true)
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->helperText('Malabar Club guest rooms (ROOM-101 to ROOM-105).'),

                            Select::make('sponsor_member_id')
                                ->label('Sponsoring Club Member (Optional)')
                                ->options(fn () => Member::with('person')
                                    ->where('status', 'active')
                                    ->get()
                                    ->mapWithKeys(fn ($m) => [
                                        $m->id => "{$m->membership_number} — " . ($m->person?->first_name ?? '') . ' ' . ($m->person?->last_name ?? '')
                                    ]))
                                ->searchable()
                                ->preload()
                                ->helperText('Required if room is booked by or on behalf of a member.'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('guest_id')
                                ->label('Visiting Guest Occupant (Optional)')
                                ->options(fn () => Guest::with('person')->get()->mapWithKeys(
                                    fn ($g) => [$g->id => ($g->person?->first_name ?? '') . ' ' . ($g->person?->last_name ?? '') . ' (' . ($g->person?->phone ?? 'No phone') . ')']
                                ))
                                ->searchable()
                                ->preload()
                                ->helperText('Required if occupant is a registered guest.'),

                            TextInput::make('notes')
                                ->label('Stay Remarks / Arrival Notes')
                                ->placeholder('Special requirements, expected arrival time, or luggage storage.')
                                ->maxLength(255),
                        ]),
                    ]),

                Section::make('Stay Schedule & Pricing (Indian Club Tariff)')
                    ->description('Check-in and check-out dates are evaluated on non-overlapping stay boundaries.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('check_in_date')
                                ->label('Check-In Date')
                                ->required()
                                ->default(now()->toDateString())
                                ->helperText('Standard club check-in: 14:00.'),

                            DatePicker::make('check_out_date')
                                ->label('Check-Out Date')
                                ->required()
                                ->default(now()->addDay()->toDateString())
                                ->helperText('Standard club check-out: 11:00. Must be after check-in date.'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('rate_per_night_paise')
                                ->label('Tariff Rate Per Night (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->default(2500.00)
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Daily room charge in Rupees.'),

                            TextInput::make('paid_amount_paise')
                                ->label('Advance Deposit / Paid Amount (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->default(0.00)
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Strict No-Credit Rule: Must be fully paid before room key handover.'),
                        ]),
                    ]),

                Section::make('Stay Status & Housekeeping')
                    ->description('Current reservation progress and room cleanliness for front desk visual rack.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('status')
                                ->label('Reservation Progress Status')
                                ->required()
                                ->options([
                                    'reserved' => 'Reserved (Advance Confirmed)',
                                    'checked_in' => 'Checked In (Active Occupancy)',
                                    'checked_out' => 'Checked Out (Departed)',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->default('reserved'),

                            Select::make('housekeeping_status')
                                ->label('Housekeeping Cleanliness Status')
                                ->required()
                                ->options([
                                    'clean' => 'Clean (Ready for Guest Check-In)',
                                    'dirty' => 'Dirty (Awaiting Housekeeping Turnover)',
                                    'maintenance' => 'Under Maintenance',
                                ])
                                ->default('clean'),
                        ]),
                    ]),
            ]);
    }
}

