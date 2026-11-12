<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Domain\Membership\Models\MembershipCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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

                        Grid::make(2)->schema([
                            FileUpload::make('id_proof_path')
                                ->label('Upload Identity Proof (Optional)')
                                ->disk('local')
                                ->directory('documents/members/kyc')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(5120)
                                ->nullable()
                                ->downloadable()
                                ->openable()
                                ->helperText('Clear scan or photo of Aadhaar, Passport, or DL (PDF, JPG, PNG up to 5MB).'),

                            FileUpload::make('application_form_path')
                                ->label('Upload Application Form Scan (Optional)')
                                ->disk('local')
                                ->directory('documents/members/applications')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(10240)
                                ->nullable()
                                ->downloadable()
                                ->openable()
                                ->helperText('Signed physical membership application form (PDF, JPG, PNG up to 10MB).'),
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
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $cat = MembershipCategory::find($state);
                                        if ($cat) {
                                            $set('admission_fee_amount_rupees', $cat->admission_fee_rupees);
                                            $set('annual_fee_amount_rupees', $cat->annual_fee_rupees);
                                        }
                                    }
                                })
                                ->helperText('Determines admission entrance charges, voting rights, and recurring annual subscription dues.')
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

                                    Grid::make(2)->schema([
                                        TextInput::make('admission_fee_rupees')
                                            ->label('One-Time Admission Fee (₹)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->prefix('₹')
                                            ->helperText('Capital fee charged only once upon admission.'),

                                        TextInput::make('annual_fee_rupees')
                                            ->label('Annual Subscription Dues (₹)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->prefix('₹')
                                            ->helperText('Recurring yearly maintenance dues (₹0 for Life/Hon).'),
                                    ]),

                                    \Filament\Forms\Components\Toggle::make('has_voting_rights')
                                        ->label('Club Voting Rights')
                                        ->default(true),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $category = MembershipCategory::create([
                                        'code' => strtoupper(trim($data['code'])),
                                        'name' => trim($data['name']),
                                        'admission_fee_paise' => (int) round(((float) ($data['admission_fee_rupees'] ?? 0)) * 100),
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

                            Select::make('fee_status')
                                ->label('Membership Fee Settlement Status')
                                ->options([
                                    'paid' => 'Paid / Settled in Full',
                                    'pending' => 'Pending Payment / Unpaid',
                                    'waived' => 'Waived / Honorary Exemption',
                                ])
                                ->default('pending')
                                ->helperText('Tracks statutory admission dues settlement.')
                                ->visibleOn('edit'),
                        ]),
                    ]),

                Section::make('Statutory Membership Fees & Admission Collection')
                    ->description('For new admissions, collect fees and issue a receipt immediately. For existing pre-migration members, mark fees as already paid historically.')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('historical_fees_already_paid')
                            ->label('Existing Club Member — Fees Already Paid Historically (Do Not Ask for Payment)')
                            ->helperText('Enable when adding an existing member whose admission fee & current dues were already paid prior to software rollout. Records settled status with zero current-day receipt.')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('collect_initial_fee', false);
                                }
                            }),

                        Grid::make(2)
                            ->visible(fn ($get) => (bool) $get('historical_fees_already_paid'))
                            ->schema([
                                DatePicker::make('historical_fee_paid_at')
                                    ->label('Historical Settlement Date (Optional)')
                                    ->nullable()
                                    ->helperText('Defaults to Date of Admission if left blank.'),

                                TextInput::make('historical_receipt_ref')
                                    ->label('Old Register / Receipt Ref # (Optional)')
                                    ->maxLength(50)
                                    ->placeholder('e.g. OLD-RCP-1042 or Register Vol 2'),
                            ]),

                        \Filament\Forms\Components\Toggle::make('collect_initial_fee')
                            ->label('Collect Membership Fees Now (New Admission)')
                            ->helperText('If enabled, admission fee and subscription dues are immediately recorded, an official receipt is generated, and annual validity is activated.')
                            ->default(false)
                            ->visible(fn ($get) => ! (bool) $get('historical_fees_already_paid'))
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state) {
                                    $catId = $get('membership_category_id');
                                    $cat = $catId ? MembershipCategory::find($catId) : null;
                                    if ($cat) {
                                        $set('admission_fee_amount_rupees', $cat->admission_fee_rupees);
                                        $set('annual_fee_amount_rupees', $cat->annual_fee_rupees);
                                    }
                                }
                            }),

                        Grid::make(2)
                            ->visible(fn ($get) => (bool) $get('collect_initial_fee') && ! (bool) $get('historical_fees_already_paid'))
                            ->schema([
                                TextInput::make('admission_fee_amount_rupees')
                                    ->label('One-Time Admission Fee (₹)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->prefix('₹')
                                    ->required(fn ($get) => (bool) $get('collect_initial_fee') && ! (bool) $get('historical_fees_already_paid'))
                                    ->helperText('Statutory entrance fee paid only once in member lifetime.'),

                                TextInput::make('annual_fee_amount_rupees')
                                    ->label('First-Year Annual Subscription Dues (₹)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->prefix('₹')
                                    ->required(fn ($get) => (bool) $get('collect_initial_fee') && ! (bool) $get('historical_fees_already_paid'))
                                    ->helperText('Recurring annual maintenance dues for Year 1 (₹0 if category is exempt).'),
                            ]),

                        Grid::make(2)
                            ->visible(fn ($get) => (bool) $get('collect_initial_fee') && ! (bool) $get('historical_fees_already_paid'))
                            ->schema([
                                Select::make('fee_tender_type')
                                    ->label('Payment Tender / Mode')
                                    ->options([
                                        'cash' => 'Cash in Hand (Counter)',
                                        'digital' => 'Digital (UPI / POS Terminal)',
                                        'bank_transfer' => 'Bank Remittance (NEFT / RTGS)',
                                        'cheque' => 'Bank Cheque / DD',
                                    ])
                                    ->default('cash')
                                    ->required(fn ($get) => (bool) $get('collect_initial_fee') && ! (bool) $get('historical_fees_already_paid')),

                                TextInput::make('fee_reference')
                                    ->label('Banking / Instrument Ref #')
                                    ->placeholder('e.g. UPI-12345678 or Cheque #004521')
                                    ->helperText('Optional transaction / instrument reference for audit.'),
                            ]),
                    ])
                    ->visibleOn('create'),

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
