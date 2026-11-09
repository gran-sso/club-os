<?php

namespace App\Filament\Resources\CourtBookings\Schemas;

use App\Domain\Membership\Models\Member;
use App\Domain\Tariff\Models\Facility;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourtBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Court & Member Selection')
                    ->description('Select the sports court and the club member making the reservation.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('facility_id')
                                ->label('Sports Court')
                                ->required()
                                ->options(fn () => Facility::where('type', 'court')
                                    ->where('is_active', true)
                                    ->pluck('name', 'id'))
                                ->default(fn () => Facility::where('type', 'court')->first()?->id)
                                ->searchable()
                                ->preload()
                                ->helperText('Badminton court facility at Malabar Club.'),

                            Select::make('member_id')
                                ->label('Booking Club Member')
                                ->required()
                                ->options(fn () => Member::with('person')
                                    ->where('status', 'active')
                                    ->get()
                                    ->mapWithKeys(fn ($m) => [
                                        $m->id => "{$m->membership_number} — " . ($m->person?->first_name ?? '') . ' ' . ($m->person?->last_name ?? '')
                                    ]))
                                ->searchable()
                                ->preload()
                                ->helperText('Only active members in good standing can reserve court slots.'),
                        ]),
                    ]),

                Section::make('Time Slot & Scheduling (60-Min Slots)')
                    ->description('Malabar Club badminton court operates in 60-minute hourly intervals from 06:00 to 21:00.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('booking_date')
                                ->label('Booking Date')
                                ->required()
                                ->minDate(today())
                                ->default(now()->toDateString())
                                ->helperText('Date of play (must be today or future).'),

                            Select::make('start_time')
                                ->label('Hourly Time Slot')
                                ->required()
                                ->options([
                                    '06:00:00' => '06:00 AM – 07:00 AM',
                                    '07:00:00' => '07:00 AM – 08:00 AM',
                                    '08:00:00' => '08:00 AM – 09:00 AM',
                                    '09:00:00' => '09:00 AM – 10:00 AM',
                                    '10:00:00' => '10:00 AM – 11:00 AM',
                                    '11:00:00' => '11:00 AM – 12:00 PM',
                                    '12:00:00' => '12:00 PM – 01:00 PM',
                                    '13:00:00' => '01:00 PM – 02:00 PM',
                                    '14:00:00' => '02:00 PM – 03:00 PM',
                                    '15:00:00' => '03:00 PM – 04:00 PM',
                                    '16:00:00' => '04:00 PM – 05:00 PM',
                                    '17:00:00' => '05:00 PM – 06:00 PM',
                                    '18:00:00' => '06:00 PM – 07:00 PM',
                                    '19:00:00' => '07:00 PM – 08:00 PM',
                                    '20:00:00' => '08:00 PM – 09:00 PM',
                                ])
                                ->default('06:00:00')
                                ->searchable()
                                ->rules([
                                    fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $date = $get('booking_date');
                                        if ($date) {
                                            $slotStart = \Carbon\Carbon::parse("{$date} {$value}");
                                            if ($slotStart->isPast()) {
                                                $fail("Cannot book a badminton court slot for a past time ({$slotStart->format('d M Y, g:i A')}).");
                                            }
                                        }
                                    },
                                ])
                                ->helperText('Strict slot capacity: at most 1 reservation per court per hour.'),

                            Select::make('status')
                                ->label('Booking Status')
                                ->required()
                                ->options([
                                    'confirmed' => 'Confirmed (Booked)',
                                    'completed' => 'Completed (Finished Play)',
                                    'cancelled' => 'Cancelled',
                                    'no_show' => 'No Show',
                                ])
                                ->default('confirmed'),
                        ]),

                        Toggle::make('is_plan_covered')
                            ->label('Covered by Member Sports Subscription Plan')
                            ->default(true)
                            ->helperText('Members with an active badminton plan play without an additional hourly court charge.'),

                        Textarea::make('notes')
                            ->label('Booking Notes')
                            ->rows(2)
                            ->placeholder('Opponent details or special tournament reservation notes.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

