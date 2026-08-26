<?php

use App\Enums\Right;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (Right::cases() as $right) {
        expect($right->getLabel())
            ->not->toBe($right->name)
            ->not->toBe($right->value)
            ->toBe(__('rights.'.$right->value));
    }
});
