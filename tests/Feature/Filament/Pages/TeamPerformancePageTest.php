<?php

use App\Enums\CalculationApprovalStep;
use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Filament\Pages\TeamPerformance;
use App\Models\ServiceCategory;
use App\Models\Task;
use App\Models\TaskStatusChange;
use App\Models\TenderCalculation;
use App\Models\TenderCalculationApproval;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('blocks a user without the view-employee-statistics right', function () {
    $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
    $this->actingAs($staff);

    Livewire::test(TeamPerformance::class)
        ->assertForbidden();
});

it('is reachable by a department head', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    Livewire::test(TeamPerformance::class)
        ->assertSuccessful();
});

it('aggregates task counts, on-time rate, and correction-loop rate per department', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    $category = ServiceCategory::factory()->create(['name' => 'Construction']);
    $employee = User::factory()->create(['service_category_id' => $category->id]);

    Task::factory()->create([
        'owner_id' => $employee->id,
        'status' => TaskStatus::DONE,
        'due_date' => now()->subDays(1),
        'completion_date' => now()->subDays(2),
    ]);
    $lateTask = Task::factory()->create([
        'owner_id' => $employee->id,
        'status' => TaskStatus::DONE,
        'due_date' => now()->subDays(8),
        'completion_date' => now()->subDays(4),
    ]);
    Task::factory()->create([
        'owner_id' => $employee->id,
        'status' => TaskStatus::OPEN,
    ]);

    TaskStatusChange::factory()->create([
        'task_id' => $lateTask->id,
        'from_status' => TaskStatus::IN_REVIEW,
        'to_status' => TaskStatus::CORRECTION_REQUIRED,
        'changed_by' => $departmentHead->id,
        'changed_at' => now()->subDays(5),
    ]);

    Livewire::test(TeamPerformance::class)
        ->assertSuccessful()
        ->assertSee('Construction')
        ->assertSee('50.0%'); // both on-time rate and correction-loop rate land on 1/2
});

it('excludes a department with no task activity from the breakdown', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    ServiceCategory::factory()->create(['name' => 'Idle Category']);

    Livewire::test(TeamPerformance::class)
        ->assertSuccessful()
        ->assertDontSee('Idle Category');
});

it('computes average approval-step duration from the previous step\'s approval', function () {
    $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    $calculation = TenderCalculation::factory()->create();
    $calculation->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    TenderCalculationApproval::factory()->create([
        'tender_calculation_id' => $calculation->id,
        'step' => CalculationApprovalStep::CALCULATION_CHECKED,
        'approved_by' => $departmentHead->id,
        'approved_at' => now()->subDays(8),
    ]);
    TenderCalculationApproval::factory()->create([
        'tender_calculation_id' => $calculation->id,
        'step' => CalculationApprovalStep::CONCEPT_CHECKED,
        'approved_by' => $departmentHead->id,
        'approved_at' => now()->subDays(5),
    ]);

    Livewire::test(TeamPerformance::class)
        ->assertSuccessful()
        ->assertSee(CalculationApprovalStep::CALCULATION_CHECKED->getLabel())
        ->assertSee('2.0') // 10 days ago -> 8 days ago = 2 days for the first step
        ->assertSee(CalculationApprovalStep::CONCEPT_CHECKED->getLabel())
        ->assertSee('3.0'); // 8 days ago -> 5 days ago = 3 days for the second step
});

it('lists every user in the rankings table sorted by score descending', function () {
    $departmentHead = tap(User::factory()->create(['name' => 'Head Person']))->assignRole(RoleName::DEPARTMENT_HEAD);
    $this->actingAs($departmentHead);

    $topPerformer = User::factory()->create(['name' => 'Top Performer']);
    Task::factory()->create([
        'owner_id' => $topPerformer->id,
        'status' => TaskStatus::DONE,
        'due_date' => now()->subDays(1),
        'completion_date' => now()->subDays(2),
    ]);

    $idleUser = User::factory()->create(['name' => 'Idle User']);

    $component = Livewire::test(TeamPerformance::class)
        ->assertSuccessful()
        ->assertSee('Top Performer')
        ->assertSee('Idle User');

    $rankedNames = collect($component->viewData('rankings'))->pluck('name')->all();

    expect(array_search('Top Performer', $rankedNames, true))
        ->toBeLessThan(array_search('Idle User', $rankedNames, true));
});
