<?php

namespace App\Filament\Resources\Subscriptions;

use App\Domain\Facility\Models\FacilitySubscription;
use App\Filament\Resources\Subscriptions\Pages\CreateFacilitySubscription;
use App\Filament\Resources\Subscriptions\Pages\ListFacilitySubscriptions;
use App\Filament\Resources\Subscriptions\Schemas\FacilitySubscriptionForm;
use App\Filament\Resources\Subscriptions\Tables\FacilitySubscriptionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FacilitySubscriptionResource extends Resource
{
    protected static ?string $model = FacilitySubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Membership & Guest Desk';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Sports Subscriptions';

    public static function form(Schema $schema): Schema
    {
        return FacilitySubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacilitySubscriptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFacilitySubscriptions::route('/'),
            'create' => CreateFacilitySubscription::route('/create'),
        ];
    }
}
