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

    protected static ?string $recordTitleAttribute = 'subscription_number';

    protected static int $globalSearchResultsLimit = 6;

    protected static ?int $globalSearchSort = 3;

    public static function getGloballySearchableAttributes(): array
    {
        return ['subscription_number', 'facility_code', 'plan_type', 'member.membership_number', 'member.person.first_name', 'member.person.last_name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Subscription #{$record->subscription_number}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $memberInfo = $record->member?->name
            ? "{$record->member->name} ({$record->member->membership_number})"
            : 'N/A';

        $validity = $record->starts_at && $record->ends_at
            ? $record->starts_at->format('d M') . ' – ' . $record->ends_at->format('d M Y')
            : 'Active';

        return [
            'Member' => $memberInfo,
            'Facility' => strtoupper($record->facility_code ?? 'SPORTS'),
            'Plan' => ucfirst($record->plan_type ?? 'monthly') . " ({$validity})",
            'Status' => ucfirst($record->status ?? 'active'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['member.person', 'tariff']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['subscriptions.view', 'subscriptions.manage']));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('subscriptions.manage'));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('subscriptions.manage'));
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasClubPermission('subscriptions.manage'));
    }

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
