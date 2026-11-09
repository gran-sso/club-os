<?php

namespace App\Filament\Resources\CourtBookings;

use App\Domain\Facility\Models\CourtBooking;
use App\Filament\Resources\CourtBookings\Pages\CreateCourtBooking;
use App\Filament\Resources\CourtBookings\Pages\EditCourtBooking;
use App\Filament\Resources\CourtBookings\Pages\ListCourtBookings;
use App\Filament\Resources\CourtBookings\Schemas\CourtBookingForm;
use App\Filament\Resources\CourtBookings\Tables\CourtBookingsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CourtBookingResource extends Resource
{
    protected static ?string $model = CourtBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations & Front Desk';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Court Bookings';

    public static function form(Schema $schema): Schema
    {
        return CourtBookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourtBookingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourtBookings::route('/'),
            'create' => CreateCourtBooking::route('/create'),
            'edit' => EditCourtBooking::route('/{record}/edit'),
        ];
    }
}
