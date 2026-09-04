<?php

use App\Enums\SkillCategory;
use App\Filament\Resources\Skills\Pages\CreateSkill;
use App\Filament\Resources\Skills\Pages\EditSkill;
use App\Filament\Resources\Skills\SkillResource;
use App\Models\Skill;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('creation', function () {
    it('creates a skill with valid data', function () {
        Livewire::test(CreateSkill::class)
            ->fillForm([
                'name' => 'Contract Law',
                'category' => SkillCategory::COMPLIANCE->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $skill = Skill::where('name', 'Contract Law')->firstOrFail();
        expect($skill->category)->toBe(SkillCategory::COMPLIANCE);
    });

    it('rejects a name that duplicates an existing skill', function () {
        Skill::factory()->create(['name' => 'Contract Law']);

        Livewire::test(CreateSkill::class)
            ->fillForm(['name' => 'Contract Law'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    });
});

describe('deletion', function () {
    it('never authorizes deleting a skill', function () {
        $skill = Skill::factory()->create();

        expect(SkillResource::canDelete($skill))->toBeFalse();
        expect(SkillResource::canDeleteAny())->toBeFalse();
    });

    it('offers no delete action on the edit page', function () {
        $skill = Skill::factory()->create();

        Livewire::test(EditSkill::class, ['record' => $skill->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    });
});
