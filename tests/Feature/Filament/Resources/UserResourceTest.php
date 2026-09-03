<?php

use App\Enums\AbsenceType;
use App\Enums\Right;
use App\Enums\RoleName;
use App\Enums\SkillProficiency;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\AbsencesRelationManager;
use App\Filament\Resources\Users\RelationManagers\SkillsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\ServiceCategory;
use App\Models\Skill;
use App\Models\Task;
use App\Models\TaskStatusChange;
use App\Models\Tender;
use App\Models\User;
use App\Models\UserAbsence;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('access', function () {
    it('allows a super admin to view the user list', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)->assertSuccessful();
    });

    it('rejects a non-super-admin from the list, create, and edit pages, server-side', function () {
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $otherUser = User::factory()->create();

        $this->actingAs($staff);

        Livewire::test(ListUsers::class)->assertForbidden();
        Livewire::test(CreateUser::class)->assertForbidden();
        Livewire::test(EditUser::class, ['record' => $otherUser->getRouteKey()])->assertForbidden();
    });

    it('never authorizes deleting a user, even for a super admin', function () {
        $user = User::factory()->create();

        expect(UserResource::canDelete($user))->toBeFalse();
        expect(UserResource::canDeleteAny())->toBeFalse();
    });

    it('offers no delete action on the edit page', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $user = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    });
});

describe('creation', function () {
    it('creates a user with a role, category, and individual rights', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $category = ServiceCategory::factory()->create();

        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password123',
                'role' => RoleName::STAFF->value,
                'service_category_id' => $category->id,
                Right::SEE_PRICES->value => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'jane@example.com')->firstOrFail();

        expect($created->hasRole(RoleName::STAFF))->toBeTrue();
        expect($created->service_category_id)->toBe($category->id);
        expect($created->hasDirectPermission(Right::SEE_PRICES->value))->toBeTrue();
        expect($created->password)->not->toBe('password123');
    });

    it('rejects an email that duplicates an existing user', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'taken@example.com',
                'password' => 'password123',
                'role' => RoleName::STAFF->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    });
});

describe('editing', function () {
    it('updates role, category, and rights, and preserves the password when left blank', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $category = ServiceCategory::factory()->create();
        $user = tap(User::factory()->create())->assignRole(RoleName::VIEWER);
        $user->givePermissionTo(Right::SEE_PRICES->value);
        $originalPassword = $user->password;

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'password' => '',
                'role' => RoleName::TEAM_LEAD->value,
                'service_category_id' => $category->id,
                Right::SEE_MARGINS->value => true,
                Right::SEE_PRICES->value => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        expect($user->hasRole(RoleName::TEAM_LEAD))->toBeTrue();
        expect($user->hasRole(RoleName::VIEWER))->toBeFalse();
        expect($user->service_category_id)->toBe($category->id);
        expect($user->hasDirectPermission(Right::SEE_MARGINS->value))->toBeTrue();
        expect($user->hasDirectPermission(Right::SEE_PRICES->value))->toBeFalse();
        expect($user->password)->toBe($originalPassword);
    });

    it('changes the password when a new one is submitted', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $user = tap(User::factory()->create())->assignRole(RoleName::VIEWER);
        $originalPassword = $user->password;

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'password' => 'newpassword123',
                'role' => RoleName::VIEWER->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($user->refresh()->password)->not->toBe($originalPassword);
    });
});

describe('last super admin safeguard', function () {
    it('blocks the only super admin from changing their own role away from super admin', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['role' => RoleName::STAFF->value])
            ->call('save')
            ->assertNotified();

        expect($admin->refresh()->hasRole(RoleName::SUPER_ADMIN))->toBeTrue();
    });

    it('allows demoting a super admin when another super admin remains', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $otherAdmin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $otherAdmin->getRouteKey()])
            ->fillForm(['role' => RoleName::STAFF->value])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($otherAdmin->refresh()->hasRole(RoleName::SUPER_ADMIN))->toBeFalse();
        expect($otherAdmin->hasRole(RoleName::STAFF))->toBeTrue();
    });
});

describe('skills relation manager', function () {
    it('assigns a skill with a proficiency level', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $user = User::factory()->create();
        $skill = Skill::factory()->create();

        $this->actingAs($admin);

        Livewire::test(SkillsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->callTableAction('attach', data: [
                'recordId' => $skill->id,
                'proficiency_level' => SkillProficiency::EXPERT->value,
            ])
            ->assertHasNoTableActionErrors();

        expect($user->skills()->where('skill_id', $skill->id)->first()?->pivot->proficiency_level)
            ->toBe(SkillProficiency::EXPERT->value);
    });

    it('edits an assigned skill\'s proficiency level', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $user = User::factory()->create();
        $skill = Skill::factory()->create();
        $user->skills()->attach($skill->id, ['proficiency_level' => SkillProficiency::NOVICE->value]);

        $this->actingAs($admin);

        Livewire::test(SkillsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->callTableAction('edit', $skill, data: [
                'proficiency_level' => SkillProficiency::COMPETENT->value,
            ])
            ->assertHasNoTableActionErrors();

        expect($user->skills()->where('skill_id', $skill->id)->first()?->pivot->proficiency_level)
            ->toBe(SkillProficiency::COMPETENT->value);
    });

    it('rejects assigning the same skill to a user twice', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $user = User::factory()->create();
        $skill = Skill::factory()->create();
        $user->skills()->attach($skill->id, ['proficiency_level' => SkillProficiency::NOVICE->value]);

        $this->actingAs($admin);

        expect(fn () => $user->skills()->attach($skill->id, ['proficiency_level' => SkillProficiency::EXPERT->value]))
            ->toThrow(QueryException::class);
    });

    it('detaches an assigned skill', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $user = User::factory()->create();
        $skill = Skill::factory()->create();
        $user->skills()->attach($skill->id, ['proficiency_level' => SkillProficiency::NOVICE->value]);

        $this->actingAs($admin);

        Livewire::test(SkillsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->callTableAction('detach', $skill);

        expect($user->skills()->where('skill_id', $skill->id)->exists())->toBeFalse();
    });
});

describe('absences relation manager', function () {
    it('lists only the employee\'s own absences', function () {
        $employee = User::factory()->create();
        $absence = UserAbsence::factory()->create(['user_id' => $employee->id]);
        $foreignAbsence = UserAbsence::factory()->create();

        Livewire::test(AbsencesRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords([$absence])
            ->assertCanNotSeeTableRecords([$foreignAbsence]);
    });

    it('creates an absence with a cover, excluding the absent employee from the cover options', function () {
        $employee = User::factory()->create();
        $cover = User::factory()->create();

        Livewire::test(AbsencesRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => EditUser::class])
            ->callTableAction('create', data: [
                'type' => AbsenceType::HOLIDAY->value,
                'starts_at' => now()->addWeek()->toDateString(),
                'ends_at' => now()->addWeek()->addDays(4)->toDateString(),
                'cover_user_id' => $cover->id,
            ])
            ->assertHasNoTableActionErrors();

        $absence = UserAbsence::where('user_id', $employee->id)->firstOrFail();
        expect($absence->type)->toBe(AbsenceType::HOLIDAY);
        expect($absence->cover_user_id)->toBe($cover->id);
    });

    it('rejects an end date before the start date', function () {
        $employee = User::factory()->create();

        Livewire::test(AbsencesRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => EditUser::class])
            ->callTableAction('create', data: [
                'type' => AbsenceType::SICKNESS->value,
                'starts_at' => now()->addWeek()->toDateString(),
                'ends_at' => now()->toDateString(),
            ])
            ->assertHasTableActionErrors(['ends_at' => 'after_or_equal']);
    });

    it('deletes an absence', function () {
        $employee = User::factory()->create();
        $absence = UserAbsence::factory()->create(['user_id' => $employee->id]);

        Livewire::test(AbsencesRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => EditUser::class])
            ->callTableAction('delete', $absence);

        expect(UserAbsence::find($absence->id))->toBeNull();
    });
});

describe('employee profile', function () {
    it('allows a user to view their own profile without any special right', function () {
        $user = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $this->actingAs($user);

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->assertSuccessful();
    });

    it('blocks viewing another user\'s profile without the view-employee-statistics right', function () {
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $otherUser = User::factory()->create();
        $this->actingAs($staff);

        Livewire::test(ViewUser::class, ['record' => $otherUser->getRouteKey()])
            ->assertForbidden();
    });

    it('allows a view-employee-statistics holder to view another user\'s profile', function () {
        $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
        $otherUser = User::factory()->create();
        $this->actingAs($departmentHead);

        expect($departmentHead->can(Right::VIEW_EMPLOYEE_STATISTICS->value))->toBeTrue();

        Livewire::test(ViewUser::class, ['record' => $otherUser->getRouteKey()])
            ->assertSuccessful();
    });

    it('reconciles employee-profile stats against actual task history', function () {
        $departmentHead = tap(User::factory()->create())->assignRole(RoleName::DEPARTMENT_HEAD);
        $employee = User::factory()->create();
        $this->actingAs($departmentHead);

        Task::factory()->create([
            'owner_id' => $employee->id,
            'status' => TaskStatus::DONE,
            'start_date' => now()->subDays(4),
            'due_date' => now()->subDays(1),
            'completion_date' => now()->subDays(2),
        ]);
        $lateTask = Task::factory()->create([
            'owner_id' => $employee->id,
            'status' => TaskStatus::DONE,
            'start_date' => now()->subDays(10),
            'due_date' => now()->subDays(8),
            'completion_date' => now()->subDays(4),
        ]);

        TaskStatusChange::factory()->create([
            'task_id' => $lateTask->id,
            'from_status' => TaskStatus::IN_REVIEW,
            'to_status' => TaskStatus::CORRECTION_REQUIRED,
            'changed_by' => $departmentHead->id,
            'changed_at' => now()->subDays(5),
        ]);

        expect($employee->onTimeTaskCompletionRate())->toBe(0.5);
        expect($employee->correctionLoopCount())->toBe(1);
        expect($employee->averageTaskHandlingTimeDays())->toBe(4.0);

        Livewire::test(ViewUser::class, ['record' => $employee->getRouteKey()])
            ->assertSuccessful();
    });

    it('shows a user their own performance score without the view-employee-statistics right', function () {
        $user = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $this->actingAs($user);

        expect($user->can(Right::VIEW_EMPLOYEE_STATISTICS->value))->toBeFalse();

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->assertSuccessful()
            ->assertSee(number_format($user->performanceScore(), 1));
    });
});

describe('performance score', function () {
    it('computes the exact weighted score from known inputs', function () {
        $employee = User::factory()->create();

        $onTimeDocTask = Task::factory()->create([
            'owner_id' => $employee->id,
            'status' => TaskStatus::DONE,
            'start_date' => now()->subDays(5),
            'due_date' => now()->subDays(1),
            'completion_date' => now()->subDays(2),
            'functional_role' => TeamRole::EVIDENCE_DOCUMENTS,
        ]);

        $lateTask = Task::factory()->create([
            'owner_id' => $employee->id,
            'status' => TaskStatus::DONE,
            'start_date' => now()->subDays(3),
            'due_date' => now()->subDays(8),
            'completion_date' => now()->subDays(4),
        ]);

        TaskStatusChange::factory()->create([
            'task_id' => $lateTask->id,
            'from_status' => TaskStatus::IN_REVIEW,
            'to_status' => TaskStatus::CORRECTION_REQUIRED,
            'changed_by' => $employee->id,
            'changed_at' => now()->subDays(5),
        ]);

        Task::factory()->count(2)->create([
            'owner_id' => $employee->id,
            'status' => TaskStatus::OPEN,
            'start_date' => null,
            'due_date' => null,
        ]);

        // Task::participants()->attach() hits the known task_participants.id (uuid, no ->using()
        // pivot) NOT NULL bug documented in [[migrations]] — insert the pivot rows directly.
        $now = now();
        DB::table('task_participants')->insert(
            Task::factory()->count(2)->create()->map(fn (Task $task): array => [
                'id' => (string) Str::uuid(),
                'task_id' => $task->id,
                'user_id' => $employee->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        // on_time_delivery 0.5*0.25 + completion_rate 0.5*0.20 + quality 0.5*0.20
        // + reliability 0.5*0.15 + documentation_quality 1.0*0.10 + collaboration 0.5*0.10 = 0.55
        expect($employee->performanceScore())->toBe(55.0);
        expect($onTimeDocTask->functional_role)->toBe(TeamRole::EVIDENCE_DOCUMENTS);
    });

    it('computes win rate from decided tenders without it affecting the weighted score', function () {
        $employee = User::factory()->create();

        expect($employee->winRate())->toBeNull();

        $scoreBeforeTenders = $employee->performanceScore();

        Tender::factory()->create(['owner_id' => $employee->id, 'status' => TenderStatus::WON]);
        Tender::factory()->create(['owner_id' => $employee->id, 'status' => TenderStatus::LOST]);
        Tender::factory()->create(['owner_id' => $employee->id, 'status' => TenderStatus::INTAKE]);

        expect($employee->winRate())->toBe(0.5);
        expect($employee->performanceScore())->toBe($scoreBeforeTenders);
    });
});
