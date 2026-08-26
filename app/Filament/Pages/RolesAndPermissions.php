<?php

namespace App\Filament\Pages;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Models\Role;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class RolesAndPermissions extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.roles-and-permissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(RoleName::SUPER_ADMIN->value) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('roles_and_permissions.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.administration');
    }

    public function getTitle(): string
    {
        return __('roles_and_permissions.title');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Role::query())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('roles_and_permissions.role_column'))
                    ->formatStateUsing(fn (string $state): string => RoleName::from($state)->getLabel())
                    ->weight('medium'),

                ...array_map(
                    fn (Right $right): ToggleColumn => ToggleColumn::make($right->value)
                        ->label($right->getLabel())
                        ->alignCenter()
                        ->onIcon(Heroicon::Check)
                        ->offIcon(Heroicon::XMark)
                        ->extraAttributes(['class' => 'scale-75'])
                        ->getStateUsing(fn (Role $record): bool => $record->hasPermissionTo($right->value))
                        ->updateStateUsing(function (Role $record, bool $state) use ($right): void {
                            abort_unless(static::canAccess(), 403);

                            $state
                                ? $record->givePermissionTo($right->value)
                                : $record->revokePermissionTo($right->value);
                        }),
                    Right::cases(),
                ),
            ]);
    }
}
