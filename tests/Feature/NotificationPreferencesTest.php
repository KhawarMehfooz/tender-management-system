<?php

use App\Enums\NotificationType;
use App\Filament\Pages\NotificationPreferences;
use App\Models\NotificationPreference;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('preference table', function () {
    it('creates a default enabled row per notification type on first visit', function () {
        Livewire::test(NotificationPreferences::class);

        expect(NotificationPreference::query()->where('user_id', auth()->id())->count())
            ->toBe(count(NotificationType::cases()));
        expect(NotificationPreference::query()->where('user_id', auth()->id())->where('email_enabled', false)->exists())
            ->toBeFalse();
    });

    it('lets a user toggle email off for one notification type', function () {
        Livewire::test(NotificationPreferences::class);
        $preference = NotificationPreference::query()
            ->where('user_id', auth()->id())
            ->where('notification_type', NotificationType::TASK_COMMENT_ADDED)
            ->firstOrFail();

        Livewire::test(NotificationPreferences::class)
            ->call('updateTableColumnState', 'email_enabled', $preference->getKey(), false)
            ->assertSuccessful();

        expect($preference->fresh()->email_enabled)->toBeFalse();
    });

    it('does not show another user\'s preferences', function () {
        $otherUser = User::factory()->create();
        $otherPreference = NotificationPreference::factory()->for($otherUser)->create([
            'notification_type' => NotificationType::TASK_STATUS_CHANGED,
        ]);

        Livewire::test(NotificationPreferences::class)
            ->assertCanNotSeeTableRecords([$otherPreference]);
    });
});
