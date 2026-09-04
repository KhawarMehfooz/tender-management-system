<?php

use App\Enums\CertificateType;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (CertificateType::cases() as $type) {
        expect($type->getLabel())
            ->not->toBe($type->name)
            ->not->toBe($type->value)
            ->toBe(__('certificate_types.'.$type->value));
    }
});
