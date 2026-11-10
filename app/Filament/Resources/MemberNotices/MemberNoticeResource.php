<?php

namespace App\Filament\Resources\MemberNotices;

use App\Domain\Communication\Models\MemberNotice;
use App\Filament\Resources\MemberNotices\Pages\CreateMemberNotice;
use App\Filament\Resources\MemberNotices\Pages\EditMemberNotice;
use App\Filament\Resources\MemberNotices\Pages\ListMemberNotices;
use App\Filament\Resources\MemberNotices\Schemas\MemberNoticeForm;
use App\Filament\Resources\MemberNotices\Tables\MemberNoticesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MemberNoticeResource extends Resource
{
    protected static ?string $model = MemberNotice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Membership & Guest Desk';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Member Bulletins & Notices';

    protected static ?string $recordTitleAttribute = 'title';

    protected static int $globalSearchResultsLimit = 5;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant', 'reception']) || $user->hasPermissionTo('notices.manage')));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant', 'reception']) || $user->hasPermissionTo('notices.manage')));
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager', 'accountant', 'reception']) || $user->hasPermissionTo('notices.manage')));
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole(['admin', 'manager']) || $user->hasPermissionTo('notices.manage')));
    }

    public static function form(Schema $schema): Schema
    {
        return MemberNoticeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberNoticesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberNotices::route('/'),
            'create' => CreateMemberNotice::route('/create'),
            'edit' => EditMemberNotice::route('/{record}/edit'),
        ];
    }
}
