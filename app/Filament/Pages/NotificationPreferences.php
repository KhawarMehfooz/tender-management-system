<?php

namespace App\Filament\Pages;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class NotificationPreferences extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.notification-preferences';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    public static function getNavigationLabel(): string
    {
        return __('notifications.navigation_label');
    }

    public function getTitle(): string
    {
        return __('notifications.title');
    }

    public function mount(): void
    {
        foreach (NotificationType::cases() as $type) {
            NotificationPreference::query()->firstOrCreate([
                'user_id' => auth()->id(),
                'notification_type' => $type,
            ], [
                'email_enabled' => true,
            ]);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NotificationPreference::query()->where('user_id', auth()->id()))
            ->paginated(false)
            ->columns([
                TextColumn::make('notification_type')
                    ->label(__('notifications.type_column'))
                    ->formatStateUsing(fn (NotificationType $state): string => $state->getLabel())
                    ->weight('medium'),

                ToggleColumn::make('email_enabled')
                    ->label(__('notifications.email_column'))
                    ->alignCenter(),
            ]);
    }
}
