<?php

namespace App\Filament\Resources\Facilities;

use App\Domain\Tariff\Models\Facility;
use App\Filament\Resources\Facilities\Pages\ListFacilities;
use App\Filament\Resources\Facilities\Tables\FacilitiesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Club Master Setup';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Facilities Master';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 18;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'type'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Facility: {$record->name} ({$record->code})";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Type' => ucfirst($record->type ?? 'facility'),
            'Capacity' => $record->capacity ? $record->capacity . ' persons' : 'N/A',
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->hasRole(['admin', 'manager']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                \Filament\Schemas\Components\Section::make('Club Facility Details')
                    ->description('Configure accommodation rooms, sports courts, gymnasiums, and clubhouse venues.')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\TextInput::make('code')
                                ->label('Facility Identifier Code')
                                ->required()
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. ROOM-101 or COURT-1')
                                ->helperText('Unique reference identifier for booking and maintenance logs.'),

                            \Filament\Forms\Components\TextInput::make('name')
                                ->label('Facility Display Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Deluxe Room 101 or Indoor Wooden Badminton Court'),
                        ]),

                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\Select::make('type')
                                ->label('Facility Classification')
                                ->required()
                                ->options([
                                    'room' => 'Accommodation Room (Nightly Stays)',
                                    'court' => 'Badminton Court (Hourly 60-min Slots)',
                                    'gym' => 'Gymnasium (Monthly / Annual Plans & Daily Access)',
                                    'bar' => 'Bar & Lounge (Counter & Seating)',
                                    'card_room' => 'Card Room (Table Usage)',
                                    'general' => 'General Clubhouse Infrastructure',
                                ])
                                ->default('room')
                                ->helperText('Determines availability rules, booking intervals, and capacity checks.'),

                            \Filament\Forms\Components\TextInput::make('capacity')
                                ->label('Maximum Guest / Player Capacity')
                                ->numeric()
                                ->minValue(1)
                                ->default(2)
                                ->required()
                                ->helperText('Max simultaneous occupants (e.g. 2 for standard room, 4 for badminton court).'),
                        ]),

                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Operational & Available for Bookings')
                            ->default(true)
                            ->helperText('When disabled, this facility is removed from reservation schedules and front-desk booking.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return FacilitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFacilities::route('/'),
        ];
    }
}
