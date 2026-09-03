<?php

use App\Enums\AbsenceType;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\Absences\Pages\EditAbsence;
use App\Filament\Resources\Absences\Pages\ListAbsences;
use App\Models\User;
use App\Models\UserAbsence;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('creation', function () {
    it('creates an absence with valid data', function () {
        $employee = User::factory()->create();
        $cover = User::factory()->create();

        Livewire::test(CreateAbsence::class)
            ->fillForm([
                'user_id' => $employee->id,
                'type' => AbsenceType::HOLIDAY->value,
                'starts_at' => now()->addWeek()->toDateString(),
                'ends_at' => now()->addWeek()->addDays(3)->toDateString(),
                'cover_user_id' => $cover->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(UserAbsence::where('user_id', $employee->id)->exists())->toBeTrue();
    });

    it('rejects an end date before the start date', function () {
        $employee = User::factory()->create();

        Livewire::test(CreateAbsence::class)
            ->fillForm([
                'user_id' => $employee->id,
                'type' => AbsenceType::SICKNESS->value,
                'starts_at' => now()->addWeek()->toDateString(),
                'ends_at' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['ends_at' => 'after_or_equal']);
    });

    it('accepts an end date equal to the start date', function () {
        $employee = User::factory()->create();

        Livewire::test(CreateAbsence::class)
            ->fillForm([
                'user_id' => $employee->id,
                'type' => AbsenceType::OTHER->value,
                'starts_at' => now()->addWeek()->toDateString(),
                'ends_at' => now()->addWeek()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    });
});

describe('editing', function () {
    it('updates an existing absence', function () {
        $absence = UserAbsence::factory()->create(['type' => AbsenceType::HOLIDAY]);

        Livewire::test(EditAbsence::class, ['record' => $absence->getRouteKey()])
            ->fillForm(['type' => AbsenceType::SICKNESS->value])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($absence->refresh()->type)->toBe(AbsenceType::SICKNESS);
    });
});

describe('listing', function () {
    it('lists absences across employees', function () {
        $absence = UserAbsence::factory()->create();

        Livewire::test(ListAbsences::class)
            ->assertCanSeeTableRecords([$absence]);
    });
});
