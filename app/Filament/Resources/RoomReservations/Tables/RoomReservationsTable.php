<?php

namespace App\Filament\Resources\RoomReservations\Tables;

use App\Domain\Facility\Services\RoomReservationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reservation_number')
                    ->label('Reservation #')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('facility.name')
                    ->label('Room')
                    ->searchable(),
                TextColumn::make('check_in_date')
                    ->label('Check-In')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('check_out_date')
                    ->label('Check-Out')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('total_charge_paise')
                    ->label('Total Charge')
                    ->state(fn ($record) => '₹' . number_format($record->total_charge_paise / 100, 2))
                    ->sortable(),
                TextColumn::make('paid_amount_paise')
                    ->label('Paid')
                    ->state(fn ($record) => '₹' . number_format($record->paid_amount_paise / 100, 2)),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->state(fn ($record) => $record->isFullyPaid() ? 'Fully Paid' : 'Deposit / Unpaid')
                    ->color(fn ($record) => $record->isFullyPaid() ? 'success' : 'danger'),
                TextColumn::make('housekeeping_status')
                    ->label('Housekeeping')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'clean' => 'success',
                        'dirty' => 'warning',
                        'maintenance' => 'danger',
                        default => 'secondary',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'checked_in' => 'success',
                        'reserved' => 'info',
                        'checked_out' => 'gray',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->recordActions([
                Action::make('checkIn')
                    ->label('Check In')
                    ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'reserved' && (bool) auth()->user()?->hasClubPermission('facilities.manage'))
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => "Confirm Check-In: {$record->reservation_number}")
                    ->modalDescription(fn ($record) => "Check in guest for Room {$record->facility?->name}. Requires full payment and a clean room.")
                    ->action(function ($record) {
                        try {
                            app(RoomReservationService::class)->checkIn($record, auth()->user());
                            Notification::make()
                                ->title('Check-In Successful')
                                ->body("Room {$record->facility?->name} is now occupied by {$record->occupant_name}.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Check-In Blocked')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('checkOut')
                    ->label('Check Out')
                    ->icon(Heroicon::OutlinedArrowLeftStartOnRectangle)
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'checked_in' && (bool) auth()->user()?->hasClubPermission('facilities.manage'))
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => "Confirm Check-Out: {$record->reservation_number}")
                    ->modalDescription(fn ($record) => "Conclude stay for Room {$record->facility?->name}. Room will be automatically flagged as Dirty for housekeeping.")
                    ->action(function ($record) {
                        try {
                            app(RoomReservationService::class)->checkOut($record, auth()->user());
                            Notification::make()
                                ->title('Check-Out Completed')
                                ->body("Room {$record->facility?->name} checked out and marked dirty.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Check-Out Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make(),
            ]);
    }
}
