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
                Section::make('Personal Details & ID Proof')
                    ->description('Member name, contact numbers, and government ID details.')
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
                                ->helperText('Used for receipts and finding the member at the counter.'),

                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->maxLength(150)
                                ->placeholder('e.g. ramesh.menon@example.com')
                                ->helperText('Used for monthly statements and receipts.'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('id_proof_type')
                                ->label('Government ID Proof')
                                ->options([
                                    'Aadhaar Card' => 'Aadhaar Card (12-digit)',
                                    'Passport' => 'Indian Passport',
                                    'Driving Licence' => 'Driving Licence',
                                    'Voter ID Card' => 'Voter ID Card',
                                    'PAN Card' => 'PAN Card',
                                ])
                                ->default('Aadhaar Card')
                                ->nullable()
                                ->helperText('ID document kept on file under club rules.'),

                            TextInput::make('id_proof_number')
                                ->label('ID Proof Number')
                                ->nullable()
                                ->maxLength(100)
                                ->placeholder('e.g. 5432 9876 1234')
                                ->helperText('ID card number (required for new admissions).'),
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
                                ->label('Fee Payment Status')
                                ->options([
                                    'paid' => 'Paid / Settled in Full',
                                    'pending' => 'Pending Payment / Unpaid',
                                    'waived' => 'Waived / Honorary Exemption',
                                ])
                                ->default('pending')
                                ->helperText('Tracks whether admission & subscription dues are paid.')
                                ->visibleOn('edit'),
                        ]),
                    ]),

                Section::make('Joining & Annual Membership Fees')
                    ->description('For new admissions, collect fees and issue a receipt immediately. For existing members, mark fees as already paid earlier.')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('historical_fees_already_paid')
                            ->label('Existing Member — Fees Already Paid Earlier (Do Not Ask for Payment)')
                            ->helperText('Turn ON when adding an existing member who already paid their joining & current dues before software rollout. Records them as paid without creating a cash receipt today.')
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
                                    ->label('Date Paid (Optional)')
                                    ->nullable()
                                    ->helperText('Defaults to Date of Admission if left blank.'),

                                TextInput::make('historical_receipt_ref')
                                    ->label('Old Receipt / Register # (Optional)')
                                    ->maxLength(50)
                                    ->placeholder('e.g. OLD-RCP-1042 or Register Vol 2'),
                            ]),

                        \Filament\Forms\Components\Toggle::make('collect_initial_fee')
                            ->label('Collect Membership Fees Now (New Admission)')
                            ->helperText('When turned ON, joining and annual fees are collected right away, a receipt is issued, and validity starts immediately.')
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
                                    ->helperText('One-time joining fee paid once upon admission.'),

                                TextInput::make('annual_fee_amount_rupees')
                                    ->label('First-Year Annual Fee (₹)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->prefix('₹')
                                    ->required(fn ($get) => (bool) $get('collect_initial_fee') && ! (bool) $get('historical_fees_already_paid'))
                                    ->helperText('Yearly subscription charge for Year 1 (₹0 if category is exempt).'),
                            ]),

                        Grid::make(2)
                            ->visible(fn ($get) => (bool) $get('collect_initial_fee') && ! (bool) $get('historical_fees_already_paid'))
                            ->schema([
                                Select::make('fee_tender_type')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash at Counter',
                                        'digital' => 'UPI / Card Machine',
                                        'bank_transfer' => 'Bank Transfer (NEFT / RTGS)',
                                        'cheque' => 'Cheque / DD',
                                    ])
                                    ->default('cash')
                                    ->required(fn ($get) => (bool) $get('collect_initial_fee') && ! (bool) $get('historical_fees_already_paid')),

                                TextInput::make('fee_reference')
                                    ->label('UPI / Bank / Cheque Ref #')
                                    ->placeholder('e.g. UPI-12345678 or Cheque #004521')
                                    ->helperText('Optional reference number for payment tracking.'),
                            ]),
                    ])
                    ->visibleOn('create'),

                Section::make('Member Smartcard (Optional)')
                    ->description('Link a physical smartcard number so the member can tap & pay quickly at the counter.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('rfid_card_number')
                            ->label('Smartcard Number (Tap or Type)')
                            ->maxLength(64)
                            ->placeholder('e.g. CARD-RAMESH-001 or tap card on USB reader')
                            ->helperText('If entered, an active smartcard is linked immediately to this member.'),
                    ])
                    ->visibleOn('create'),
            ]);
    }
}
