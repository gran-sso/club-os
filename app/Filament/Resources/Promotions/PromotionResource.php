<?php

namespace App\Filament\Resources\Promotions;

use App\Domain\Billing\Models\BillLine;
use App\Domain\Billing\Models\Promotion;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\Schemas\PromotionForm;
use App\Filament\Resources\Promotions\Tables\PromotionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial & Counters';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Promotions & Offers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 5;

    protected static ?int $globalSearchSort = 12;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Offer: {$record->name} [{$record->code}]";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $quota = $record->max_free_units !== null
            ? "{$record->redeemed_free_units}/{$record->max_free_units} used"
            : 'Unlimited';

        return [
            'Rule' => $record->badgeLabel(),
            'Scope' => $record->customer_scope === Promotion::SCOPE_ALL ? 'All Customers' : 'Members & Room Guests',
            'Quota' => $quota,
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->checkPermissionTo('tariffs.manage')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->checkPermissionTo('tariffs.manage')));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->checkPermissionTo('tariffs.manage')));
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole(['admin', 'manager']) && !$user->checkPermissionTo('tariffs.manage'))) {
            return false;
        }

        // Preserve audit integrity once any bill has used this offer
        return (int) $record->redeemed_free_units === 0
            && !BillLine::where('promotion_id', $record->id)->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return PromotionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }
}

