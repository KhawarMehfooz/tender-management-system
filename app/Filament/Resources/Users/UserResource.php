<?php

namespace App\Filament\Resources\Users;

use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
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
     * User administration is restricted to super admins only — no other role manages who has
     * access, what role they hold, or which individually-assignable rights they're granted.
     */
    private static function canManage(): bool
    {
        return auth()->user()?->hasRole(RoleName::SUPER_ADMIN->value) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
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

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
