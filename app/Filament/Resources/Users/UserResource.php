<?php

namespace App\Filament\Resources\Users;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\AbsencesRelationManager;
use App\Filament\Resources\Users\RelationManagers\SkillsRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.administration');
    }

    /**
     * $recordTitleAttribute ('name', set above) already makes global search work by default —
     * this override adds 'email' on top, covering idea.md's M12 "employee" global-search field
     * more completely (name or email). Results are naturally restricted to the same audience as
     * this resource's own list page, since canGloballySearch() also checks canAccess() (->
     * canViewAny() -> canManage() || canViewStatistics()) — no separate gating needed here.
     *
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    /**
     * User administration is restricted to super admins only — no other role manages who has
     * access, what role they hold, or which individually-assignable rights they're granted.
     */
    private static function canManage(): bool
    {
        return auth()->user()?->hasRole(RoleName::SUPER_ADMIN->value) ?? false;
    }

    /**
     * Whether the acting user may see other employees' full statistics — the same right gates
     * this resource's list page (so a manager has somewhere to browse to a profile from) and
     * viewing any individual profile besides one's own. See canView() below for the self-view
     * exception.
     */
    private static function canViewStatistics(): bool
    {
        return auth()->user()?->can(Right::VIEW_EMPLOYEE_STATISTICS->value) ?? false;
    }

    public static function canViewAny(): bool
    {
        return self::canManage() || self::canViewStatistics();
    }

    public static function canCreate(): bool
    {
        return self::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canManage();
    }

    /**
     * The employee profile page (ViewUser) is reachable by the user themselves regardless of
     * rights — it's self-service transparency, not a ranking — or by anyone who can otherwise
     * manage/browse users (super admin, or a Right::VIEW_EMPLOYEE_STATISTICS holder).
     */
    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->is($record) || self::canManage() || self::canViewStatistics();
    }

    /**
     * Users are never hard-deleted from this panel — every FK referencing users
     * (task owner/creator/reviewer, attachment uploader, comment author, etc.) uses
     * restrictOnDelete, so a real delete would just fail once a user has any history.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SkillsRelationManager::class,
            AbsencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
