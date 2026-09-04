<?php

use App\Enums\ConceptBlockCategory;
use App\Models\ConceptBlock;
use App\Models\ConceptBlockVersion;

describe('versions', function () {
    it('orders versions by version_number descending', function () {
        $block = ConceptBlock::factory()->create();
        $first = ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 1]);
        $latest = ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 2]);

        expect($block->versions()->pluck('id')->all())->toBe([$latest->id, $first->id]);
    });

    it('exposes the highest version_number via currentVersion', function () {
        $block = ConceptBlock::factory()->create();
        ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 1]);
        $latest = ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 2]);

        expect($block->currentVersion()->first()->id)->toBe($latest->id);
    });
});

describe('cascade delete', function () {
    it('deletes a block\'s versions when the block is deleted', function () {
        $block = ConceptBlock::factory()->create();
        $version = ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id]);

        $block->delete();

        expect(ConceptBlockVersion::find($version->id))->toBeNull();
    });
});

describe('factory', function () {
    it('creates a block with a category', function () {
        $block = ConceptBlock::factory()->create();

        expect($block->category)->toBeInstanceOf(ConceptBlockCategory::class);
    });
});
