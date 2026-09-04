<?php

use App\Enums\CertificateStatus;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (CertificateStatus::cases() as $status) {
        expect($status->getLabel())
            ->not->toBe($status->name)
            ->not->toBe($status->value)
            ->toBe(__('certificate_statuses.'.$status->value));
    }
});

test('every case resolves to a status colour', function () {
    expect(CertificateStatus::VALID->color())->toBe('success');
    expect(CertificateStatus::EXPIRING_SOON->color())->toBe('warning');
    expect(CertificateStatus::EXPIRED->color())->toBe('danger');
});
