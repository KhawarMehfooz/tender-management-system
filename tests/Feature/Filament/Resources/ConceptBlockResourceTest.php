<?php

use App\Enums\ConceptBlockCategory;
use App\Filament\Resources\ConceptBlocks\Pages\CreateConceptBlock;
use App\Filament\Resources\ConceptBlocks\Pages\EditConceptBlock;
use App\Filament\Resources\ConceptBlocks\RelationManagers\VersionsRelationManager;
use App\Models\ConceptBlock;
use App\Models\ConceptBlockVersion;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('creation', function () {
    it('renders the create form fields', function () {
        // Regression guard: a private $content property on CreateConceptBlock once shadowed
        // Filament's magic `$this->content` schema-rendering property (see [[milestones]]),
        // which silently blanked the whole page without any exception — fillForm()-based tests
        // alone don't catch that since they bypass full HTML rendering.
        Livewire::test(CreateConceptBlock::class)
            ->assertSee(__('concept_blocks.fields.category'))
            ->assertSee(__('concept_blocks.fields.title'))
            ->assertSee(__('concept_blocks.fields.content'));
    });

    it('creates a concept block and its first version', function () {
        Livewire::test(CreateConceptBlock::class)
            ->fillForm([
                'category' => ConceptBlockCategory::QUALITY_MANAGEMENT->value,
                'title' => 'Our QM approach',
                'content' => 'Initial content.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $block = ConceptBlock::where('title', 'Our QM approach')->first();
        expect($block)->not->toBeNull();
        expect($block->created_by)->toBe(auth()->id());
        expect($block->currentVersion->version_number)->toBe(1);
        expect($block->currentVersion->content)->toBe('Initial content.');
        expect($block->currentVersion->created_by)->toBe(auth()->id());
    });
});

describe('editing', function () {
    it('creates a new version when content changes', function () {
        $block = ConceptBlock::factory()->create(['title' => 'Old title']);
        ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 1, 'content' => 'v1 content']);

        Livewire::test(EditConceptBlock::class, ['record' => $block->getRouteKey()])
            ->fillForm(['title' => 'New title', 'content' => 'v2 content'])
            ->call('save')
            ->assertHasNoFormErrors();

        $block->refresh();
        expect($block->title)->toBe('New title');
        expect($block->versions)->toHaveCount(2);
        expect($block->currentVersion->version_number)->toBe(2);
        expect($block->currentVersion->content)->toBe('v2 content');
    });

    it('does not create a new version when content is unchanged', function () {
        $block = ConceptBlock::factory()->create(['title' => 'Old title']);
        ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 1, 'content' => 'same content']);

        Livewire::test(EditConceptBlock::class, ['record' => $block->getRouteKey()])
            ->fillForm(['title' => 'New title', 'content' => 'same content'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($block->refresh()->versions)->toHaveCount(1);
    });
});

describe('versions relation manager', function () {
    it('lists a block\'s own version history, newest first', function () {
        $block = ConceptBlock::factory()->create();
        $v1 = ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 1]);
        $v2 = ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 2]);
        $foreignVersion = ConceptBlockVersion::factory()->create();

        Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $block, 'pageClass' => EditConceptBlock::class])
            ->assertCanSeeTableRecords([$v2, $v1], inOrder: true)
            ->assertCanNotSeeTableRecords([$foreignVersion]);
    });
});
