<?php

use App\Enums\DocumentRequestStatus;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (DocumentRequestStatus::cases() as $status) {
        expect($status->getLabel())
            ->not->toBe($status->name)
            ->not->toBe($status->value)
            ->toBe(__('document_request_statuses.'.$status->value));
    }
});
