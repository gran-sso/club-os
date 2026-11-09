<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Domain\Membership\Models\MembershipCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Personal Details & Statutory KYC')
                    ->description('Member identification, contact details, and government-approved KYC documentation.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_name')
                                ->label('First Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Ramesh'),

                            TextInput::make('last_name')
                                ->label('Last Name / Surname')
                                ->maxLength(100)
                                ->placeholder('e.g. Menon'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('phone')
                                ->label('Mobile Number (+91)')
                                ->tel()
                                ->required()
                                ->maxLength(25)
                                ->placeholder('e.g. +91 98470 12345')
                                ->helperText('Used for SMS/WhatsApp receipts and member identification at the counter.'),

                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->maxLength(150)
                                ->placeholder('e.g. ramesh.menon@example.com')
                                ->helperText('Used for monthly statements and tax invoices.'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('id_proof_type')
                                ->label('Statutory Identity Proof (KYC)')
                                ->options([
                                    'Aadhaar Card' => 'Aadhaar Card (UIDAI 12-digit)',
                                    'Passport' => 'Indian Passport',
                                    'Driving Licence' => 'Driving Licence (MoRTH)',
                                    'Voter ID Card' => 'Election Photo Identity Card (EPIC)',
                                    'PAN Card' => 'Permanent Account Number (PAN)',
                                ])
                                ->default('Aadhaar Card')
                                ->nullable()
                                ->helperText('Identity verification document under club bylaws.'),

                            TextInput::make('id_proof_number')
                                ->label('Identity Proof Document #')
                                ->nullable()
                                ->maxLength(100)
                                ->placeholder('e.g. 5432 9876 1234')
                                ->helperText('Unique government document number (mandatory for new admissions).'),
                        ]),

                        Textarea::make('notes')
                            ->label('Residential Address & Club Notes')
                            ->rows(3)
                            ->placeholder('Permanent residential address in Kerala, emergency contact details, or committee notes.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Club Membership Classification')
                    ->description('Membership quota category, membership sequence number, and entitlement validity.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('membership_number')
                                ->label('Membership Number')
                                ->required()
                                ->maxLength(50)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. MC-001 or MC-L-102')
                                ->helperText('Unique club membership roster number printed on member identity card.'),

                            Select::make('membership_category_id')
                                ->label('Membership Category')
                                ->required()
                                ->options(fn () => MembershipCategory::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->helperText('Determines voting rights, facility tariffs, and annual subscription charges.')
                                ->createOptionForm([
                                    TextInput::make('code')
                                        ->label('Category Code')
                                        ->required()
                                        ->maxLength(32)
                                        ->unique(MembershipCategory::class, 'code')
                                        ->placeholder('e.g. HON, ASSOC, SPORTS')
                                        ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state))),

                                    TextInput::make('name')
                                        ->label('Category Designation')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('e.g. Honorary Member'),

                                    TextInput::make('annual_fee_rupees')
                                        ->label('Annual Subscription Fee (₹)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->prefix('₹')
                                        ->helperText('Standard yearly membership dues.'),

                                    \Filament\Forms\Components\Toggle::make('has_voting_rights')
                                        ->label('Club Voting Rights')
                                        ->default(true),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $category = MembershipCategory::create([
                                        'code' => strtoupper(trim($data['code'])),
                                        'name' => trim($data['name']),
                                        'annual_fee_paise' => (int) round(((float) ($data['annual_fee_rupees'] ?? 0)) * 100),
                                        'has_voting_rights' => (bool) ($data['has_voting_rights'] ?? true),
                                        'is_active' => true,
                                    ]);

                                    return $category->id;
                                }),
                        ]),

                        Grid::make(3)->schema([
                            Select::make('status')
                                ->label('Membership Standing')
                                ->required()
                                ->options([
                                    'active' => 'Active (Good Standing)',
                                    'suspended' => 'Suspended (Temporary Bar)',
                                    'expired' => 'Expired (Annual Dues Pending)',
                                    'terminated' => 'Terminated / Resigned',
                                ])
                                ->default('active')
                                ->helperText('Non-negotiable rule: only active members can spend from prepaid wallet or sponsor guests.'),

                            DatePicker::make('joined_at')
                                ->label('Date of Admission')
                                ->required()
                                ->default(now()->toDateString())
                                ->helperText('Official date of admission approved by the Managing Committee.'),

                            DatePicker::make('valid_until')
                                ->label('Membership Valid Until (Optional)')
                                ->nullable()
                                ->helperText('Leave empty for Life / Permanent memberships without fixed expiry.'),
                        ]),
                    ]),

                Section::make('Smartcard & RFID Hardware Token')
                    ->description('Optionally link an RFID Mifare/NFC contactless card UID for high-speed POS checkouts.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('rfid_card_number')
                            ->label('RFID Smartcard UID / Token (Optional)')
                            ->maxLength(64)
                            ->placeholder('e.g. CARD-RAMESH-001 or tap physical card on USB reader')
                            ->helperText('If supplied, an active RFID card record will be immediately issued to this member.'),
                    ])
                    ->visibleOn('create'),
            ]);
    }
}
