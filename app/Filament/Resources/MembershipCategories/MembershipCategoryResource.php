<?php

namespace App\Filament\Resources\MembershipCategories;

use App\Domain\Membership\Models\MembershipCategory;
use App\Filament\Resources\MembershipCategories\Pages\CreateMembershipCategory;
use App\Filament\Resources\MembershipCategories\Pages\EditMembershipCategory;
use App\Filament\Resources\MembershipCategories\Pages\ListMembershipCategories;
use App\Filament\Resources\MembershipCategories\Schemas\MembershipCategoryForm;
use App\Filament\Resources\MembershipCategories\Tables\MembershipCategoriesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MembershipCategoryResource extends Resource
{
    protected static ?string $model = MembershipCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Membership & Guest Desk';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Membership Categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant']) || $user->hasPermissionTo('members.manage')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->hasPermissionTo('members.manage')));
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->hasPermissionTo('members.manage')));
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        /** @var MembershipCategory $record */
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->hasPermissionTo('members.manage')) && !$record->members()->exists());
    }

    public static function form(Schema $schema): Schema
    {
        return MembershipCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembershipCategories::route('/'),
            'create' => CreateMembershipCategory::route('/create'),
            'edit' => EditMembershipCategory::route('/{record}/edit'),
        ];
    }
}

