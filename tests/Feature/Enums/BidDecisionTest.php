<?php

use App\Enums\BidDecision;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (BidDecision::cases() as $decision) {
        expect($decision->getLabel())
            ->not->toBe($decision->name)
            ->not->toBe($decision->value)
            ->toBe(__('bid_decisions.'.$decision->value));
    }
});
