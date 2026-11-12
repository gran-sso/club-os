<?php

namespace App\Filament\Widgets;

use App\Domain\Billing\Models\Bill;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\TenderType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivityTableWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['reports.view', 'bills.view', 'bills.manage']));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Club Billing & Operational Activity')
            ->query(
                Bill::query()
                    ->with(['customer', 'paymentReceipts'])
                    ->latest('created_at')
                    ->limit(8)
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),

                TextColumn::make('bill_number')
                    ->label('Bill Number')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('customer')
                    ->label('Customer / Member')
                    ->state(function (Bill $record) {
                        $customer = $record->customer;
                        if ($customer instanceof Member) {
                            return "{$customer->name} ({$customer->membership_number})";
                        }
                        return $customer?->name ?? (class_basename($record->customer_type) . ' #' . $record->customer_id);
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : (string) $state) {
                        'bar' => 'warning',
                        'food' => 'success',
                        'room', 'accommodation' => 'info',
                        'court', 'sports' => 'primary',
                        'card_room', 'guest_entry' => 'purple',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state instanceof \BackedEnum ? $state->value : (string) $state)),

                TextColumn::make('net_amount_paise')
                    ->label('Net Total')
                    ->state(fn (Bill $record) => '₹' . number_format($record->net_amount_paise / 100, 2))
                    ->fontFamily('mono')
                    ->weight('bold'),

                TextColumn::make('tenders')
                    ->label('Tender Mode')
                    ->state(function (Bill $record) {
                        $types = $record->paymentReceipts
                            ->pluck('tender_type')
                            ->filter()
                            ->unique()
                            ->map(fn ($t) => $t instanceof TenderType ? $t->label() : ($t instanceof \BackedEnum ? $t->value : ucfirst((string) $t)))
                            ->all();
                        return !empty($types) ? implode(', ', $types) : 'Prepaid Wallet';
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : (string) $state) {
                        'paid' => 'success',
                        'draft' => 'warning',
                        'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state instanceof \BackedEnum ? $state->value : (string) $state)),
            ])
            ->paginated(false);
    }
}
