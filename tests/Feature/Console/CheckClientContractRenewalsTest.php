<?php

use App\Console\Commands\CheckClientContractRenewals;
use App\Enums\RoleName;
use App\Enums\TenderStatus;
use App\Models\ServiceCategory;
use App\Models\Tender;
use App\Models\User;
use App\Notifications\ClientContractRenewalReminderNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('sends the 12-month reminder once and not twice on a second run', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $tender = Tender::factory()->create([
        'owner_id' => $owner->id,
        'contract_end_date' => now()->addMonths(11),
    ]);

    $this->artisan(CheckClientContractRenewals::class)->assertSuccessful();
    $this->artisan(CheckClientContractRenewals::class)->assertSuccessful();

    Notification::assertSentToTimes($owner, ClientContractRenewalReminderNotification::class, 1);
    Notification::assertSentTo(
        $owner,
        ClientContractRenewalReminderNotification::class,
        fn (ClientContractRenewalReminderNotification $n) => $n->monthsUntilEnd === 12,
    );
    expect($tender->refresh()->reminder_12_months_sent_at)->not->toBeNull();
    expect($tender->reminder_9_months_sent_at)->toBeNull();
    expect($tender->reminder_6_months_sent_at)->toBeNull();
});

it('sends nothing for a tender more than 12 months from its contract end date', function () {
    Notification::fake();

    Tender::factory()->create(['contract_end_date' => now()->addMonths(18)]);

    $this->artisan(CheckClientContractRenewals::class)->assertSuccessful();

    Notification::assertNothingSent();
});

it('fires the 9-month reminder once a tender reaches that threshold', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $tender = Tender::factory()->create([
        'owner_id' => $owner->id,
        'contract_end_date' => now()->addMonths(9),
    ]);

    $this->artisan(CheckClientContractRenewals::class)->assertSuccessful();

    Notification::assertSentTo(
        $owner,
        ClientContractRenewalReminderNotification::class,
        fn (ClientContractRenewalReminderNotification $n) => $n->monthsUntilEnd === 9,
    );
    expect($tender->refresh()->reminder_9_months_sent_at)->not->toBeNull();
    expect($tender->reminder_6_months_sent_at)->toBeNull();
});

it('fires the reminder on a lost tender too', function () {
    Notification::fake();

    $owner = User::factory()->create();
    Tender::factory()->create([
        'owner_id' => $owner->id,
        'status' => TenderStatus::LOST,
        'contract_end_date' => now()->addMonths(6),
    ]);

    $this->artisan(CheckClientContractRenewals::class)->assertSuccessful();

    Notification::assertSentTo($owner, ClientContractRenewalReminderNotification::class);
});

it('notifies team leads and department heads in the tender\'s service category', function () {
    Notification::fake();

    $category = ServiceCategory::factory()->create();
    $otherCategory = ServiceCategory::factory()->create();

    $teamLead = tap(User::factory()->create(['service_category_id' => $category->id]))
        ->assignRole(RoleName::TEAM_LEAD);
    $outsideTeamLead = tap(User::factory()->create(['service_category_id' => $otherCategory->id]))
        ->assignRole(RoleName::TEAM_LEAD);
    $staff = tap(User::factory()->create(['service_category_id' => $category->id]))
        ->assignRole(RoleName::STAFF);

    Tender::factory()->create([
        'service_category_id' => $category->id,
        'contract_end_date' => now()->addMonths(6),
    ]);

    $this->artisan(CheckClientContractRenewals::class)->assertSuccessful();

    Notification::assertSentTo($teamLead, ClientContractRenewalReminderNotification::class);
    Notification::assertNotSentTo($outsideTeamLead, ClientContractRenewalReminderNotification::class);
    Notification::assertNotSentTo($staff, ClientContractRenewalReminderNotification::class);
});
