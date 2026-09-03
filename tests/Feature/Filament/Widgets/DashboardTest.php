<?php

use App\Enums\DeadlineType;
use App\Enums\RoleName;
use App\Filament\Widgets\ActivityFeedWidget;
use App\Filament\Widgets\DeadlineRadarWidget;
use App\Filament\Widgets\EmployeeOpenTasksWidget;
use App\Filament\Widgets\ManagementKpiWidget;
use App\Filament\Widgets\TeamLeadDepartmentOverviewWidget;
use App\Models\ServiceCategory;
use App\Models\Task;
use App\Models\TaskStatusChange;
use App\Models\Tender;
use App\Models\TenderDeadline;
use App\Models\TenderStatusChange;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('widget visibility by audience', function () {
    it('shows the employee and deadline/activity widgets to a plain staff user, but not the team-lead or management ones', function () {
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $this->actingAs($staff);

        expect(EmployeeOpenTasksWidget::canView())->toBeTrue();
        expect(DeadlineRadarWidget::canView())->toBeTrue();
        expect(ActivityFeedWidget::canView())->toBeTrue();
        expect(TeamLeadDepartmentOverviewWidget::canView())->toBeFalse();
        expect(ManagementKpiWidget::canView())->toBeFalse();
    });

    it('shows the team-lead department overview to a team lead with a department', function () {
        $category = ServiceCategory::factory()->create();
        $teamLead = tap(User::factory()->create(['service_category_id' => $category->id]))->assignRole(RoleName::TEAM_LEAD);
        $this->actingAs($teamLead);

        expect(TeamLeadDepartmentOverviewWidget::canView())->toBeTrue();
        expect(ManagementKpiWidget::canView())->toBeFalse();
    });

    it('shows the management KPIs to a user holding view-employee-statistics', function () {
        $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
        $this->actingAs($departmentHead);

        expect(ManagementKpiWidget::canView())->toBeTrue();
    });
});

describe('deadline radar', function () {
    it('sorts upcoming deadlines soonest first', function () {
        $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
        $this->actingAs($departmentHead);

        $soon = Tender::factory()->create(['title' => 'Soon Tender']);
        TenderDeadline::factory()->create(['tender_id' => $soon->id, 'type' => DeadlineType::SUBMISSION, 'due_at' => now()->addDays(2)]);

        $later = Tender::factory()->create(['title' => 'Later Tender']);
        TenderDeadline::factory()->create(['tender_id' => $later->id, 'type' => DeadlineType::SUBMISSION, 'due_at' => now()->addDays(10)]);

        $component = Livewire::test(DeadlineRadarWidget::class)->assertSuccessful();

        $titles = collect($component->instance()->getTableRecords())->pluck('tender.title')->all();

        expect(array_search('Soon Tender', $titles, true))
            ->toBeLessThan(array_search('Later Tender', $titles, true));
    });

    it('respects category scoping', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();

        $tenderA = Tender::factory()->create(['service_category_id' => $categoryA->id]);
        TenderDeadline::factory()->create(['tender_id' => $tenderA->id, 'type' => DeadlineType::SUBMISSION, 'due_at' => now()->addDay()]);

        $tenderB = Tender::factory()->create(['service_category_id' => $categoryB->id]);
        TenderDeadline::factory()->create(['tender_id' => $tenderB->id, 'type' => DeadlineType::SUBMISSION, 'due_at' => now()->addDay()]);

        $scopedUser = tap(User::factory()->create(['service_category_id' => $categoryA->id]))->assignRole(RoleName::STAFF);
        $this->actingAs($scopedUser);

        $component = Livewire::test(DeadlineRadarWidget::class)->assertSuccessful();
        $tenderIds = collect($component->instance()->getTableRecords())->pluck('tender_id')->all();

        expect($tenderIds)->toContain($tenderA->id);
        expect($tenderIds)->not->toContain($tenderB->id);
    });
});

describe('activity feed', function () {
    it('merges entries from multiple source tables in reverse-chronological order', function () {
        $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
        $this->actingAs($departmentHead);

        $tender = Tender::factory()->create(['title' => 'Feed Tender']);
        TenderStatusChange::factory()->create([
            'tender_id' => $tender->id,
            'changed_by' => $departmentHead->id,
            'changed_at' => now()->subHours(2),
        ]);

        $task = Task::factory()->create(['tender_id' => $tender->id, 'title' => 'Feed Task']);
        TaskStatusChange::factory()->create([
            'task_id' => $task->id,
            'changed_by' => $departmentHead->id,
            'changed_at' => now()->subMinutes(5),
        ]);

        Livewire::test(ActivityFeedWidget::class)
            ->assertSuccessful()
            ->assertSee('Feed Tender')
            ->assertSee('Feed Task');
    });
});
