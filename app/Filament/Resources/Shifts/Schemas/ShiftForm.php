<?php

namespace App\Filament\Resources\Shifts\Schemas;

use App\Domain\Billing\Models\Counter;
use App\Domain\Billing\Models\Shift;
use App\Domain\Shared\Enums\ShiftStatus;
use App\Models\User;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Counter Station & Cashier Assignment')
                    ->description('Assign the physical billing terminal and front-desk cashier.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('counter_id')
                                ->label('Billing Counter Station')
                                ->required()
                                ->options(fn () => Counter::pluck('name', 'id'))
                                ->default(fn () => Counter::whereKey(request()->integer('counter_id'))->value('id') ?? Counter::first()?->id)
                                ->searchable()
                                ->rules([
                                    fn (string $operation): Closure => self::preventDuplicateOpenShiftRule(
                                        operation: $operation,
                                        lookup: fn (int $counterId): ?Shift => Shift::with('counter')
                                            ->where('counter_id', $counterId)
                                            ->where('status', ShiftStatus::OPEN)
                                            ->first(),
                                        message: function (Shift $existingShift, int $counterId): string {
                                            $counterName = $existingShift->counter?->name ?? "Counter #{$counterId}";

                                            return "Counter {$counterName} already has an open shift (#{$existingShift->shift_number}). Close the active shift before opening a new one.";
                                        },
                                    ),
                                ])
                                ->helperText('Physical POS station terminal. Only one active open shift is allowed per counter at a time.'),

                            Select::make('user_id')
                                ->label('Station Cashier / Staff')
                                ->required()
                                ->options(fn () => User::pluck('name', 'id'))
                                ->default(fn () => auth()->id())
                                ->searchable()
                                ->rules([
                                    fn (string $operation): Closure => self::preventDuplicateOpenShiftRule(
                                        operation: $operation,
                                        lookup: fn (int $userId): ?Shift => Shift::with(['user', 'counter'])
                                            ->where('user_id', $userId)
                                            ->where('status', ShiftStatus::OPEN)
                                            ->first(),
                                        message: function (Shift $existingShift): string {
                                            $staffName = $existingShift->user?->name ?? 'Selected staff member';
                                            $counterName = $existingShift->counter?->name ? " on {$existingShift->counter->name}" : '';

                                            return "{$staffName} already has an active open shift (#{$existingShift->shift_number}{$counterName}). Close that shift before opening another one.";
                                        },
                                    ),
                                ])
                                ->helperText('Staff user responsible for drawer cash balancing. Each staff member can hold only one open shift at a time.'),

                            DatePicker::make('business_date')
                                ->label('Fiscal Business Date')
                                ->required()
                                ->default(now()->toDateString())
                                ->helperText('Operating day to which sales and drawer collections are attributed.'),
                        ]),
                    ]),

                Section::make('Drawer Cash & Shift Float (₹)')
                    ->description('Initial physical cash float in drawer and shift status.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('opening_float_paise')
                                ->label('Opening Cash Float (₹)')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->prefix('₹')
                                ->default(1000.00)
                                ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                ->helperText('Physical starting change handed over to cashier at shift opening.'),

                            Select::make('status')
                                ->label('Shift Operational Status')
                                ->required()
                                ->options([
                                    'open' => 'Open (Active Billing)',
                                    'closed' => 'Closed (Reconciled)',
                                ])
                                ->default('open')
                                ->rules([
                                    fn (string $operation): Closure => function (string $attribute, mixed $value, Closure $fail) use ($operation): void {
                                        if ($operation === 'create' && $value !== 'open') {
                                            $fail('New shifts can only be created in Open (Active Billing) status.');
                                        }
                                    },
                                ])
                                ->helperText('New shifts open in Active Billing status and are locked via Close Shift reconciliation.'),
                        ]),

                        Textarea::make('notes')
                            ->label('Shift Notes & Observations')
                            ->rows(3)
                            ->placeholder('Handover notes, drawer discrepancies, or shift remarks.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Build a create-only validation rule that blocks opening a duplicate active shift.
     *
     * @param  Closure(int): ?Shift  $lookup
     * @param  Closure(Shift, int): string  $message
     */
    private static function preventDuplicateOpenShiftRule(string $operation, Closure $lookup, Closure $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($operation, $lookup, $message): void {
            if ($operation !== 'create' || blank($value)) {
                return;
            }

            $existingShift = $lookup((int) $value);
            if ($existingShift) {
                $fail($message($existingShift, (int) $value));
            }
        };
    }
}
