<?php

namespace App\Filament\Resources\Members\Tables;

use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\MemberStatus;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('membership_number')
                    ->label('Member #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                \Filament\Tables\Columns\TextColumn::make('person.first_name')
                    ->label('Member Name')
                    ->state(fn ($record) => ($record->person?->first_name ?? '') . ' ' . ($record->person?->last_name ?? ''))
                    ->searchable(['first_name', 'last_name']),
                \Filament\Tables\Columns\TextColumn::make('person.phone')
                    ->label('Phone')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('primary'),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        'resigned' => 'gray',
                        default => 'warning',
                    }),
                \Filament\Tables\Columns\TextColumn::make('wallet.balance_paise')
                    ->label('Prepaid Balance')
                    ->state(fn ($record) => '₹' . number_format(($record->wallet?->balance_paise ?? 0) / 100, 2))
                    ->sortable()
                    ->weight('bold')
                    ->color(fn ($record) => ($record->wallet?->balance_paise ?? 0) > 0 ? 'success' : 'gray'),
                \Filament\Tables\Columns\TextColumn::make('activeCard.card_number')
                    ->label('Active Card')
                    ->badge()
                    ->color('info')
                    ->default('No card'),
                \Filament\Tables\Columns\TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'resigned' => 'Resigned',
                        'expired' => 'Expired',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('membership_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordUrl(fn (Member $record): string => MemberResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('viewProfile')
                    ->label('View Profile')
                    ->icon(Heroicon::OutlinedUser)
                    ->color('primary')
                    ->url(fn (Member $record): string => MemberResource::getUrl('view', ['record' => $record])),

                \Filament\Actions\Action::make('issueOrReplaceCard')
                    ->label(fn ($record) => $record->activeCard ? 'Replace RFID Card' : 'Issue RFID Card')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedCreditCard)
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Placeholder::make('current_card')
                            ->label('Current Registered Card')
                            ->content(function ($record) {
                                if (!$record->activeCard) {
                                    return 'No card currently registered';
                                }
                                $fee = number_format((float) \App\Domain\Shared\Models\SystemSetting::get('lost_card_fee', 150.00), 2);
                                return "{$record->activeCard->card_number} (Status: Active) — Statutory Replacement Fee: ₹{$fee}";
                            }),
                        \Filament\Forms\Components\TextInput::make('card_number')
                            ->label('New RFID Card UID / Number')
                            ->required()
                            ->placeholder('e.g. CARD-VENU-002 or scan physical RFID card'),
                        \Filament\Forms\Components\TextInput::make('reason')
                            ->label('Issuance / Replacement Reason')
                            ->default(fn ($record) => $record->activeCard ? 'Lost / Damaged Card Replacement' : 'Initial Card Issuance')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(\App\Domain\Membership\Services\CardService::class);
                        if ($record->activeCard) {
                            $newCard = $service->replaceCard($record->activeCard, $data['card_number'], $data['reason']);
                            \Filament\Notifications\Notification::make()
                                ->title("Card Replaced Successfully")
                                ->body("Old card blocked. New card {$newCard->card_number} issued to {$record->name}. Ledger balance is preserved.")
                                ->success()
                                ->send();
                        } else {
                            $newCard = $service->issueCard($record, $data['card_number'], $data['reason']);
                            \Filament\Notifications\Notification::make()
                                ->title("Card Issued Successfully")
                                ->body("RFID card {$newCard->card_number} issued to {$record->name}.")
                                ->success()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('statement')
                    ->label('Statement')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->modalHeading(fn ($record) => "Member Account Statement — {$record->membership_number}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function ($record) {
                        $entries = $record->wallet?->entries()->latest()->take(20)->get() ?? collect();
                        return view('filament.components.member-statement-modal', [
                            'member' => $record,
                            'entries' => $entries,
                            'balanceRupees' => ($record->wallet?->balance_paise ?? 0) / 100,
                        ]);
                    }),

                \Filament\Actions\Action::make('dependents')
                    ->label('Dependents')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedUserGroup)
                    ->color('info')
                    ->modalHeading(fn ($record) => "Family Dependents — {$record->membership_number}")
                    ->modalDescription("View and add family dependents linked to this member's profile.")
                    ->form([
                        \Filament\Forms\Components\Placeholder::make('existing_dependents')
                            ->label('Registered Family Dependents')
                            ->columnSpanFull()
                            ->content(function ($record) {
                                $deps = $record->dependents()->with('person')->get();
                                if ($deps->isEmpty()) {
                                    return 'No dependents currently registered.';
                                }
                                return view('filament.components.member-dependents-list', ['dependents' => $deps]);
                            }),
                        \Filament\Schemas\Components\Section::make('Register New Family Dependent')
                            ->columnSpanFull()
                            ->schema([
                                \Filament\Schemas\Components\Grid::make(2)->schema([
                                    \Filament\Forms\Components\TextInput::make('dep_first_name')
                                        ->label('Dependent First Name')
                                        ->required()
                                        ->placeholder('e.g. Anjali'),
                                    \Filament\Forms\Components\TextInput::make('dep_last_name')
                                        ->label('Last Name')
                                        ->placeholder('e.g. Menon'),
                                ]),
                                \Filament\Schemas\Components\Grid::make(2)->schema([
                                    \Filament\Forms\Components\Select::make('dep_relationship')
                                        ->label('Relationship to Member')
                                        ->required()
                                        ->options([
                                            'Spouse' => 'Spouse',
                                            'Child' => 'Child / Son / Daughter',
                                            'Parent' => 'Parent / Mother / Father',
                                        ])
                                        ->default('Spouse'),
                                    \Filament\Forms\Components\TextInput::make('dep_phone')
                                        ->label('Contact Mobile Number')
                                        ->placeholder('e.g. +91 98470 54321'),
                                ]),
                                \Filament\Forms\Components\Toggle::make('dep_can_spend_wallet')
                                    ->label('Authorize Prepaid Wallet Counter Debits')
                                    ->default(false)
                                    ->helperText('Strict Club Policy: By default, dependents cannot debit the primary member\'s wallet. Only enable if member has provided written authorization.'),
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(\App\Domain\Membership\Services\MemberService::class);
                        $dep = $service->registerDependent(
                            $record,
                            [
                                'first_name' => $data['dep_first_name'],
                                'last_name' => $data['dep_last_name'] ?? null,
                                'phone' => $data['dep_phone'] ?? null,
                            ],
                            $data['dep_relationship'],
                            (bool) ($data['dep_can_spend_wallet'] ?? false)
                        );

                        \Filament\Notifications\Notification::make()
                            ->title("Dependent Registered")
                            ->body("Registered {$dep->person->full_name} ({$dep->relationship}) for Member #{$record->membership_number}.")
                            ->success()
                            ->send();
                    }),

                Action::make('changeStatus')
                    ->label('Lifecycle Status')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('warning')
                    ->modalHeading(fn ($record) => "Member Lifecycle Status — {$record->membership_number}")
                    ->modalDescription('Manage membership status. Changing to Suspended, Resigned, or Expired automatically revokes and blocks RFID door access.')
                    ->form([
                        Select::make('status')
                            ->label('New Membership Status')
                            ->options([
                                'active' => 'Active (Full Club & Facility Access)',
                                'suspended' => 'Suspended (Temporary Bar / Disciplinary)',
                                'expired' => 'Expired (Annual Dues Lapsed)',
                                'resigned' => 'Resigned (Voluntary Surrender)',
                                'terminated' => 'Terminated (Committee Expulsion)',
                            ])
                            ->default(fn ($record) => $record->status instanceof \BackedEnum ? $record->status->value : (string) $record->status)
                            ->required(),
                        TextInput::make('reason')
                            ->label('Reason / Audit Notation')
                            ->placeholder('e.g. Relocated abroad / Disciplinary hold')
                            ->required(fn ($get) => $get('status') !== 'active'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->transitionStatus($data['status'], $data['reason'] ?? null);

                        Notification::make()
                            ->title("Member Status Updated")
                            ->body("Member #{$record->membership_number} status changed to " . ucfirst($data['status']) . ".")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),

                DeleteAction::make()
                    ->label('Delete Unused Profile')
                    ->color('danger')
                    ->visible(fn ($record) => 
                        !$record->bills()->exists() && 
                        (!$record->wallet || ($record->wallet->balance_paise === 0 && !$record->wallet->entries()->exists())) && 
                        !$record->courtBookings()->exists() &&
                        !$record->subscriptions()->exists() &&
                        !$record->roomReservations()->exists()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Delete Unused Member Profile')
                    ->modalDescription('Only newly created member profiles with zero financial history, unspent wallet deposits, or facility bookings can be deleted.')
                    ->successNotificationTitle('Pristine member record removed successfully.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkSuspend')
                        ->label('Suspend Selected')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Suspend Selected Members')
                        ->modalDescription('Selected members will have their access revoked and all active RFID access cards immediately blocked from turnstiles.')
                        ->form([
                            TextInput::make('reason')
                                ->label('Administrative Suspension Reason')
                                ->placeholder('e.g. Annual audit / Non-payment of subscription')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $count = 0;
                            foreach ($records as $member) {
                                $member->transitionStatus(MemberStatus::SUSPENDED, $data['reason']);
                                $count++;
                            }

                            Notification::make()
                                ->title("Members Suspended")
                                ->body("{$count} member(s) suspended and RFID cards blocked.")
                                ->warning()
                                ->send();
                        }),

                    BulkAction::make('bulkResign')
                        ->label('Mark Selected as Resigned')
                        ->icon(Heroicon::OutlinedUserMinus)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Members as Resigned')
                        ->modalDescription('Selected members will be marked as Resigned and active cards blocked. Financial history and wallet ledger remain immutable.')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $member) {
                                $member->transitionStatus(MemberStatus::RESIGNED, 'Bulk administrative resignation');
                                $count++;
                            }

                            Notification::make()
                                ->title("Members Marked as Resigned")
                                ->body("{$count} member(s) marked as Resigned.")
                                ->info()
                                ->send();
                        }),

                    BulkAction::make('bulkActivate')
                        ->label('Reactivate Selected')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Reactivate Selected Members')
                        ->modalDescription('Selected members will be restored to Active status.')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $member) {
                                $member->transitionStatus(MemberStatus::ACTIVE, 'Bulk administrative reactivation');
                                $count++;
                            }

                            Notification::make()
                                ->title("Members Reactivated")
                                ->body("{$count} member(s) restored to Active status.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
