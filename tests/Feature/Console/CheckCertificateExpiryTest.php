<?php

use App\Console\Commands\CheckCertificateExpiry;
use App\Enums\RoleName;
use App\Models\Certificate;
use App\Models\User;
use App\Notifications\CertificateExpiringNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->manager = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $this->staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
});

it('sends the 90-day reminder once and not twice on a second run', function () {
    Notification::fake();

    $certificate = Certificate::factory()->create(['expiry_date' => now()->addDays(85)]);

    $this->artisan(CheckCertificateExpiry::class)->assertSuccessful();
    $this->artisan(CheckCertificateExpiry::class)->assertSuccessful();

    Notification::assertSentToTimes($this->manager, CertificateExpiringNotification::class, 1);
    Notification::assertSentTo($this->manager, CertificateExpiringNotification::class, fn ($n) => $n->thresholdDays === 90);
    expect($certificate->refresh()->last_reminder_threshold_days)->toBe(90);
});

it('sends nothing for a certificate below no threshold yet', function () {
    Notification::fake();

    Certificate::factory()->create(['expiry_date' => now()->addDays(120)]);

    $this->artisan(CheckCertificateExpiry::class)->assertSuccessful();

    Notification::assertNothingSent();
});

it('sends the final expired notice once and not twice on a second run', function () {
    Notification::fake();

    $certificate = Certificate::factory()->create([
        'valid_from' => now()->subYears(2),
        'expiry_date' => now()->subDays(5),
    ]);

    $this->artisan(CheckCertificateExpiry::class)->assertSuccessful();
    $this->artisan(CheckCertificateExpiry::class)->assertSuccessful();

    $expiredNotices = Notification::sent($this->manager, CertificateExpiringNotification::class)
        ->filter(fn (CertificateExpiringNotification $n) => $n->thresholdDays === null);

    expect($expiredNotices)->toHaveCount(1);
    expect($certificate->refresh()->last_reminder_threshold_days)->toBe(0);
});

it('notifies exactly the manage-certificates right holders, not other users', function () {
    Notification::fake();

    Certificate::factory()->create(['expiry_date' => now()->addDays(5)]);

    $this->artisan(CheckCertificateExpiry::class)->assertSuccessful();

    Notification::assertSentTo($this->manager, CertificateExpiringNotification::class);
    Notification::assertNotSentTo($this->staff, CertificateExpiringNotification::class);
});
