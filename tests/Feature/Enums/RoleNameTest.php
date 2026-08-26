<?php

use App\Enums\RoleName;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (RoleName::cases() as $role) {
        expect($role->getLabel())
            ->not->toBe($role->name)
            ->not->toBe($role->value)
            ->toBe(__('roles.'.$role->value));
    }
});
