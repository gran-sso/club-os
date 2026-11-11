<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Domain\Billing\Models\Shift;
use App\Domain\Facility\Services\SubscriptionService;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tariff\Models\Tariff;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                                ->default(fn () => request()->integer('member_id') ?: null)
                                ->options(fn () => Member::with('person')
                                    ->where('status', 'active')
                                    ->get()
                                    ->mapWithKeys(fn ($m) => [
                                        $m->id => "{$m->membership_number} — " . ($m->person?->first_name ?? '') . ' ' . ($m->person?->last_name ?? '')
                                    ]))
                                ->searchable()
                                ->preload()
                                ->live()
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
                                ->live()
                                ->afterStateUpdated(function ($state, $get, $set) {
                                    $startsAt = $get('starts_at');
                                    if ($startsAt && $state) {
                                        $end = app(SubscriptionService::class)->calculateEndDate(
                                            (string) $state,
                                            Carbon::parse($startsAt)
                                        );
                                        $set('ends_at', $end->toDateString());
                                    }
                                }),

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
                                            $taxPaise = (int) round(($tariff->rate_paise * (float) ($tariff->tax_percent ?? 0)) / 100);
                                            $set('total_fee_paise', round(($tariff->rate_paise + $taxPaise) / 100, 2));
                                        }
                                    }
                                })
                                ->helperText('Auto-populates tariff rate plus applicable GST (matching Counter POS billing).'),
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
                                        $planType = (string) ($get('plan_type') ?? 'monthly');
                                        $end = app(SubscriptionService::class)->calculateEndDate(
                                            $planType,
                                            Carbon::parse($state)
                                        );
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
                                ->label('Total Payable Amount Collected (₹ incl. GST)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->live(onBlur: true)
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Stored securely in exact integer paise. Generates an official Paid Bill, Payment Receipt & GL 4040 posting.'),

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

                Section::make('Payment Collection & Ledger Settlement (Zero-Credit Policy)')
                    ->description('Upfront confirmed payment is mandatory. Collections automatically create a Paid Bill (Sports), Payment Receipt, GL Journal (A/C 4040 & 2030), and update Collections & Revenue charts.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('tender_type')
                                ->label('Payment Mode / Tender')
                                ->required()
                                ->options([
                                    TenderType::CASH->value => 'Cash (Till / Secretariat Office)',
                                    TenderType::DIGITAL->value => 'UPI / Card Terminal (Digital)',
                                    TenderType::BANK_TRANSFER->value => 'Bank Transfer (NEFT / IMPS / RTGS)',
                                    TenderType::CHEQUE->value => 'Cheque (Bank Clearing)',
                                    TenderType::PREPAID->value => 'Member Prepaid Wallet (Debit A/C 2010)',
                                ])
                                ->default(TenderType::CASH->value)
                                ->live(),

                            Select::make('shift_id')
                                ->label('Counter Cash Shift (Optional)')
                                ->options(fn () => Shift::with(['counter', 'user'])
                                    ->where('status', ShiftStatus::OPEN)
                                    ->orderByDesc('opened_at')
                                    ->get()
                                    ->mapWithKeys(fn ($shift) => [
                                        $shift->id => "{$shift->shift_number} — " . ($shift->counter?->name ?? 'Counter') . ' (' . ($shift->user?->name ?? 'Staff') . ')',
                                    ]))
                                ->default(fn () => Shift::where('user_id', auth()->id())->where('status', ShiftStatus::OPEN)->latest('opened_at')->value('id'))
                                ->searchable()
                                ->placeholder('Secretariat / Back-Office (No POS Shift)')
                                ->visible(fn ($get) => !in_array($get('tender_type'), [TenderType::BANK_TRANSFER->value, TenderType::CHEQUE->value], true))
                                ->helperText('Optional: link cash/digital/prepaid collection to an open counter shift for till reconciliation.'),

                            TextInput::make('payment_reference')
                                ->label('Transaction / Cheque / Receipt Reference')
                                ->maxLength(100)
                                ->placeholder('UPI Ref / Card Auth / UTR / Cheque #')
                                ->visible(fn ($get) => $get('tender_type') !== TenderType::PREPAID->value)
                                ->helperText('Recommended for UPI, Card, NEFT, or Cheque settlements.'),
                        ]),

                        Placeholder::make('settlement_preview')
                            ->label('Live Fee, Membership & Wallet Verification')
                            ->content(function ($get) {
                                $memberId = $get('member_id');
                                $tariffId = $get('tariff_id');
                                $tender = (string) ($get('tender_type') ?? 'cash');
                                $enteredRupees = (float) ($get('total_fee_paise') ?? 0);
                                $enteredPaise = (int) round($enteredRupees * 100);

                                $lines = [];

                                if ($tariffId && $enteredPaise > 0) {
                                    $tariff = Tariff::find($tariffId);
                                    if ($tariff) {
                                        $split = app(SubscriptionService::class)->splitTariffFee($tariff, $enteredPaise);
                                        $baseFmt = number_format($split['gross_paise'] / 100, 2);
                                        $gstFmt = number_format($split['tax_paise'] / 100, 2);
                                        $netFmt = number_format($split['net_paise'] / 100, 2);
                                        $gstPct = number_format($split['tax_percent'], 2);
                                        $lines[] = "<div><strong>Fee Split:</strong> Base Revenue (A/C 4040): <span class='font-mono'>₹{$baseFmt}</span> &bull; GST {$gstPct}% (A/C 2030): <span class='font-mono'>₹{$gstFmt}</span> &bull; <strong>Total Receipt: <span class='font-mono'>₹{$netFmt}</span></strong></div>";
                                    }
                                }

                                if ($memberId) {
                                    $member = Member::with(['wallet', 'person'])->find($memberId);
                                    if ($member) {
                                        $walletBal = (int) ($member->wallet?->balance_paise ?? 0);
                                        $walletAvail = max(0, $walletBal - (int) ($member->wallet?->reserved_paise ?? 0));
                                        $availFmt = number_format($walletAvail / 100, 2);
                                        $validUntil = $member->valid_until ? $member->valid_until->format('d M Y') : 'No Expiry / Life';
                                        $isBaseOk = $member->isActive();

                                        $baseBadge = $isBaseOk
                                            ? "<span class='text-emerald-700 font-semibold'>Active (Valid Until: {$validUntil})</span>"
                                            : "<span class='text-rose-700 font-bold'>Base Membership Expired / Inactive — Activation Blocked</span>";

                                        $walletStatus = "<span class='font-mono font-semibold'>₹{$availFmt}</span>";
                                        if ($tender === TenderType::PREPAID->value && $enteredPaise > 0) {
                                            if ($walletAvail < $enteredPaise) {
                                                $shortFmt = number_format(($enteredPaise - $walletAvail) / 100, 2);
                                                $walletStatus = "<span class='text-rose-700 font-bold'>₹{$availFmt} (Insufficient — Short by ₹{$shortFmt}; Top-up Wallet First)</span>";
                                            } else {
                                                $afterFmt = number_format(($walletAvail - $enteredPaise) / 100, 2);
                                                $walletStatus = "<span class='text-emerald-700 font-semibold'>₹{$availFmt} (Sufficient; Remaining After Debit: ₹{$afterFmt})</span>";
                                            }
                                        }

                                        $lines[] = "<div><strong>Member Status:</strong> {$baseBadge} &bull; <strong>Available Wallet Balance:</strong> {$walletStatus}</div>";
                                    }
                                }

                                $dest = match ($tender) {
                                    TenderType::CASH->value => 'Cash Till / Secretariat (DR 1010 Cash in Hand &rarr; CR 4040 Sports Revenue + CR 2030 GST)',
                                    TenderType::PREPAID->value => 'Member Prepaid Wallet Debit (DR 2010 Prepaid Liability &rarr; CR 4040 Sports Revenue + CR 2030 GST; No second cash receipt)',
                                    default => 'Bank Clearing / Terminal (DR 1020 Bank Clearing &rarr; CR 4040 Sports Revenue + CR 2030 GST)',
                                };
                                $lines[] = "<div class='text-xs text-slate-600 mt-1'><strong>Accounting Flow:</strong> {$dest}</div>";

                                return new HtmlString('<div class="text-xs space-y-1 p-3 rounded-lg bg-slate-50 border border-slate-200">' . implode('', $lines) . '</div>');
                            }),
                    ]),
            ]);
    }
}
