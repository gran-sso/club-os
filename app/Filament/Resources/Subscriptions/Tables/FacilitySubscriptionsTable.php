<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Domain\Shared\Enums\TenderType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FacilitySubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('subscription_number')
                    ->label('Plan #')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('member.membership_number')
                    ->label('Member #')
                    ->searchable()
                    ->badge(),
                TextColumn::make('member_name')
                    ->label('Member Name')
                    ->state(fn ($record) => $record->member?->name ?? '—')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('member.person', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('facility_code')
                    ->label('Facility')
                    ->badge()
                    ->color('info'),
                TextColumn::make('plan_type')
                    ->label('Plan Type')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Valid Until')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('total_fee_paise')
                    ->label('Fee Collected')
                    ->state(fn ($record) => '₹' . number_format($record->total_fee_paise / 100, 2))
                    ->sortable(),
                TextColumn::make('paymentReceipt.receipt_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->placeholder('Legacy / Unlinked')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('tender_mode')
                    ->label('Paid Via')
                    ->state(function ($record) {
                        $tender = $record->paymentReceipt?->tender_type;
                        if ($tender instanceof TenderType) {
                            return $tender->label();
                        }

                        return $tender ? ucfirst((string) $tender) : '—';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $tender = $record->paymentReceipt?->tender_type;
                        $value = $tender instanceof TenderType ? $tender->value : (string) $tender;

                        return match ($value) {
                            'cash' => 'success',
                            'digital', 'bank_transfer', 'cheque' => 'info',
                            'prepaid' => 'warning',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'gray',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    }),
            ]);
    }
}
