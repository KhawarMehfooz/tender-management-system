<?php

use App\Enums\CommunicationType;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (CommunicationType::cases() as $type) {
        expect($type->getLabel())
            ->not->toBe($type->name)
            ->not->toBe($type->value)
            ->toBe(__('communication_types.'.$type->value));
    }
});
