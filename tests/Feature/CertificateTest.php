<?php

use App\Enums\CertificateStatus;
use App\Models\Certificate;

test('status is valid when the expiry date is more than 30 days away', function () {
    $certificate = Certificate::factory()->create(['expiry_date' => now()->addDays(31)]);

    expect($certificate->status())->toBe(CertificateStatus::VALID);
});

test('status is expiring soon within 30 days of expiry', function () {
    $certificate = Certificate::factory()->create(['expiry_date' => now()->addDays(30)]);

    expect($certificate->status())->toBe(CertificateStatus::EXPIRING_SOON);
});

test('status is expired once the expiry date has passed', function () {
    $certificate = Certificate::factory()->create(['expiry_date' => now()->subDay()]);

    expect($certificate->status())->toBe(CertificateStatus::EXPIRED);
});

test('the expiringSoon and expired factory states produce the matching status', function () {
    expect(Certificate::factory()->expiringSoon()->create()->status())->toBe(CertificateStatus::EXPIRING_SOON);
    expect(Certificate::factory()->expired()->create()->status())->toBe(CertificateStatus::EXPIRED);
});

test('reminder-tracking columns are not mass-assignable', function () {
    $certificate = Certificate::factory()->create();

    $certificate->update([
        'last_reminder_threshold_days' => 30,
        'last_reminder_sent_at' => now(),
    ]);

    expect($certificate->fresh()->last_reminder_threshold_days)->toBeNull();
    expect($certificate->fresh()->last_reminder_sent_at)->toBeNull();
});
