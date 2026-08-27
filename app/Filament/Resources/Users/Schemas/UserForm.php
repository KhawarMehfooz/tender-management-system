<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    /**
     * The `rights` toggles are keyed by Right::value directly (one Toggle field per right,
     * not a single array field), so extract the ones switched on for syncing to Spatie.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function selectedRights(array $data): array
    {
        return collect(Right::cases())
            ->filter(fn (Right $right): bool => (bool) ($data[$right->value] ?? false))
            ->map(fn (Right $right): string => $right->value)
            ->values()
            ->all();
    }

    /**
     * Whether $record is a super admin and the only one left — used to warn before someone
     * changes their role away and locks everyone out of this super-admin-only panel. The
     * actual block is server-side in EditUser::beforeSave(); this is a UI hint only.
     */
    private static function isOnlyRemainingSuperAdmin(?User $record): bool
    {
        return $record !== null
            && $record->hasRole(RoleName::SUPER_ADMIN)
            && User::role(RoleName::SUPER_ADMIN->value)->count() <= 1;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('users.form.account_section_heading'))
                    ->description(__('users.form.account_section_description'))
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('users.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedUser)
                            ->required(),
                        TextInput::make('email')
                            ->label(__('users.fields.email'))
                            ->prefixIcon(Heroicon::OutlinedEnvelope)
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label(__('users.fields.password'))
                            ->helperText(__('users.fields.password_helper'))
                            ->prefixIcon(Heroicon::LockClosed)
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),

                Section::make(__('users.form.access_section_heading'))
                    ->description(__('users.form.access_section_description'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Select::make('role')
                            ->label(__('users.fields.role'))
                            ->prefixIcon(Heroicon::OutlinedIdentification)
                            ->helperText(fn (?User $record): ?string => self::isOnlyRemainingSuperAdmin($record)
                                ? __('users.fields.role_last_super_admin_helper')
                                : null)
                            ->options(collect(RoleName::cases())->mapWithKeys(
                                fn (RoleName $role): array => [$role->value => $role->getLabel()],
                            ))
                            ->required()
                            ->native(false),
                        Select::make('service_category_id')
                            ->label(__('users.fields.service_category_id'))
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->helperText(__('users.fields.service_category_id_helper'))
                            ->relationship('serviceCategory', 'name', fn (Builder $query) => $query->where('active', true))
                            ->searchable()
                            ->preload()
                            ->placeholder(__('users.fields.service_category_id_placeholder')),

                        Section::make(__('users.fields.rights'))
                            ->description(__('users.fields.rights_helper'))
                            ->schema(collect(Right::cases())->map(
                                fn (Right $right): Toggle => Toggle::make($right->value)
                                    ->label($right->getLabel())
                                    ->onIcon(Heroicon::Check)
                                    ->offIcon(Heroicon::XMark),
                            )->all()),
                    ]),
            ]);
    }
}
