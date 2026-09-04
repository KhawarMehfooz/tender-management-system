<?php

use App\Enums\ConceptBlockCategory;

test('every case resolves a translated label instead of the raw case name', function () {
    foreach (ConceptBlockCategory::cases() as $category) {
        expect($category->getLabel())
            ->not->toBe($category->name)
            ->not->toBe($category->value)
            ->toBe(__('concept_block_categories.'.$category->value));
    }
});
