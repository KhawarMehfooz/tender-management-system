<?php

use App\Enums\WinLossReason;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (WinLossReason::cases() as $reason) {
        expect($reason->getLabel())
            ->not->toBe($reason->name)
            ->not->toBe($reason->value)
            ->toBe(__('win_loss_reasons.'.$reason->value));
    }
});
