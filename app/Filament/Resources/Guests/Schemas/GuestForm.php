<?php

namespace App\Filament\Resources\Guests\Schemas;

use App\Domain\Membership\Models\AffiliatedMember;
use App\Domain\Membership\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GuestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Guest Personal Identification & KYC')
                    ->description('Full guest details and mandatory government identity proof under club safety rules.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_name')
                                ->label('Guest First Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Suresh'),

                            TextInput::make('last_name')
                                ->label('Last Name / Surname')
                                ->maxLength(100)
                                ->placeholder('e.g. Varma'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('phone')
                                ->label('Mobile Number (+91)')
                                ->tel()
                                ->required()
                                ->maxLength(25)
                                ->placeholder('e.g. +91 94471 23456')
                                ->helperText('Required for verification and gate security check.'),

                            TextInput::make('email')
                                ->label('Email Address (Optional)')
                                ->email()
                                ->maxLength(150)
                                ->placeholder('e.g. suresh.varma@example.com'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('id_proof_type')
                                ->label('Statutory Identity Proof (KYC)')
                                ->options([
                                    'Aadhaar Card' => 'Aadhaar Card (UIDAI 12-digit)',
                                    'Passport' => 'Indian / Foreign Passport',
                                    'Driving Licence' => 'Driving Licence (MoRTH)',
                                    'Voter ID Card' => 'Election Photo Identity Card (EPIC)',
                                    'PAN Card' => 'Permanent Account Number (PAN)',
                                ])
                                ->default('Aadhaar Card')
                                ->required()
                                ->helperText('Club bylaws mandate KYC verification before granting entry to Bar or Card Room.'),

                            TextInput::make('id_proof_number')
                                ->label('Identity Document Number')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. 9876 5432 1098'),
                        ]),

                        Textarea::make('notes')
                            ->label('Visit Remarks & Security Notes')
                            ->rows(2)
                            ->placeholder('Purpose of visit, visiting city, or front desk observations.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Sponsoring Member & Visit Authorization')
                    ->description('Every guest requires an active, eligible club member or affiliated club member sponsor.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('sponsor_type')
                                ->label('Sponsor Classification')
                                ->required()
                                ->options([
                                    Member::class => 'Malabar Club Primary Member',
                                    AffiliatedMember::class => 'Affiliated Reciprocal Club Member',
                                ])
                                ->default(Member::class)
                                ->live()
                                ->helperText('Select whether sponsor is a permanent club member or visiting from an affiliated club.'),

                            Select::make('sponsor_id')
                                ->label('Sponsoring Member')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->options(function ($get) {
                                    $type = $get('sponsor_type') ?? Member::class;
                                    if ($type === AffiliatedMember::class || $type === 'affiliated_member') {
                                        return AffiliatedMember::with(['person', 'club'])
                                            ->where('is_verified', true)
                                            ->get()
                                            ->mapWithKeys(fn ($a) => [
                                                $a->id => ($a->club?->name ?? 'Club') . " ({$a->home_membership_number}) — " . ($a->person?->first_name ?? '') . ' ' . ($a->person?->last_name ?? '')
                                            ]);
                                    }

                                    return Member::with('person')
                                        ->where('status', 'active')
                                        ->get()
                                        ->mapWithKeys(fn ($m) => [
                                            $m->id => "{$m->membership_number} — " . ($m->person?->first_name ?? '') . ' ' . ($m->person?->last_name ?? '')
                                        ]);
                                })
                                ->helperText('Sponsor must be in active standing with no dues.'),
                        ]),

                        Grid::make(2)->schema([
                            DatePicker::make('visit_date')
                                ->label('Date of Visit')
                                ->required()
                                ->default(now()->toDateString())
                                ->helperText('Passes and entry rights are valid on this calendar date.'),

                            Select::make('status')
                                ->label('Visit / Admission Status')
                                ->required()
                                ->options([
                                    'registered' => 'Registered (Pass Pending / At Reception)',
                                    'checked_in' => 'Checked In (Active on Club Premises)',
                                    'departed' => 'Departed (Concluded Visit)',
                                ])
                                ->default('registered'),
                        ]),
                    ]),
            ]);
    }
}
