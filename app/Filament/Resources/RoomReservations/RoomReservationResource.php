<?php

namespace App\Filament\Resources\RoomReservations;

use App\Domain\Facility\Models\RoomReservation;
use App\Filament\Resources\RoomReservations\Pages\CreateRoomReservation;
use App\Filament\Resources\RoomReservations\Pages\EditRoomReservation;
use App\Filament\Resources\RoomReservations\Pages\ListRoomReservations;
use App\Filament\Resources\RoomReservations\Schemas\RoomReservationForm;
use App\Filament\Resources\RoomReservations\Tables\RoomReservationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoomReservationResource extends Resource
{
    protected static ?string $model = RoomReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations & Front Desk';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Room Bookings';

    protected static ?string $recordTitleAttribute = 'reservation_number';

    protected static int $globalSearchResultsLimit = 6;

    protected static ?int $globalSearchSort = 5;

    public static function getGloballySearchableAttributes(): array
    {
        return ['reservation_number', 'guest.person.first_name', 'guest.person.last_name', 'sponsorMember.membership_number', 'sponsorMember.person.first_name', 'sponsorMember.person.last_name', 'facility.name', 'facility.code'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        $room = $record->facility?->name ?? 'Room';
        return "Reservation #{$record->reservation_number} — {$room}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $occupant = $record->guest?->name ?? $record->sponsorMember?->name ?? 'Direct Guest';
        $dates = $record->check_in_date && $record->check_out_date
            ? $record->check_in_date->format('d M') . ' – ' . $record->check_out_date->format('d M Y')
            : 'N/A';

        return [
            'Guest / Member' => $occupant,
            'Dates' => $dates,
            'Total' => '₹' . number_format(($record->total_charge_paise ?? 0) / 100, 2),
            'Status' => ucfirst($record->status ?? 'confirmed'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['facility', 'guest.person', 'sponsorMember.person']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['rooms.view_rack', 'rooms.manage_reservations']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('rooms.manage_reservations'));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('rooms.manage_reservations'));
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return RoomReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomReservationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomReservations::route('/'),
            'create' => CreateRoomReservation::route('/create'),
            'edit' => EditRoomReservation::route('/{record}/edit'),
        ];
    }
}
