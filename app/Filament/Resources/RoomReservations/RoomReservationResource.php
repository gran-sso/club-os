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
