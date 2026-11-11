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

    protected static ?string $recordTitleAttribute = 'booking_number';

    protected static int $globalSearchResultsLimit = 6;

    protected static ?int $globalSearchSort = 6;

    public static function getGloballySearchableAttributes(): array
    {
        return ['booking_number', 'member.membership_number', 'member.person.first_name', 'member.person.last_name', 'facility.name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Court Booking #{$record->booking_number}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $memberInfo = $record->member?->name
            ? "{$record->member->name} ({$record->member->membership_number})"
            : 'Direct Player';

        $slot = ($record->booking_date ? $record->booking_date->format('d M') : '') . " {$record->start_time}–{$record->end_time}";

        return [
            'Facility' => $record->facility?->name ?? 'Badminton Court',
            'Member' => $memberInfo,
            'Slot' => $slot,
            'Status' => ucfirst($record->status ?? 'confirmed'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['facility', 'member.person']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['courts.view_schedule', 'courts.manage_bookings']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('courts.manage_bookings'));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('courts.manage_bookings'));
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

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
