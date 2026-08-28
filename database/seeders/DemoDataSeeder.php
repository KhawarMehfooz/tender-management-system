<?php

namespace Database\Seeders;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use App\Models\ServiceCategory;
use App\Models\Task;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Roles whose users span all service categories (mirrors
     * TenderForm::canManageTeam()'s management role set — see [[scopes-models]]).
     *
     * @var array<int, RoleName>
     */
    private const array MANAGEMENT_ROLES = [
        RoleName::SUPER_ADMIN,
        RoleName::DEPARTMENT_HEAD,
        RoleName::TEAM_LEAD,
    ];

    /**
     * The 7 active-phase statuses in workflow order, per TenderStatus::allowedTransitions().
     *
     * @var array<int, TenderStatus>
     */
    private const array ACTIVE_PHASES = [
        TenderStatus::INTAKE,
        TenderStatus::REVIEW,
        TenderStatus::DECISION,
        TenderStatus::PROCESSING,
        TenderStatus::QUALITY,
        TenderStatus::SUBMISSION,
        TenderStatus::FOLLOW_UP,
    ];

    /** @var Collection<int, ServiceCategory> */
    private Collection $categories;

    /** @var array<string, Collection<int, User>> role-value => users */
    private array $usersByRole = [];

    public function run(): void
    {
        // DatabaseSeeder wraps every seeder call in Model::withoutEvents(), which mutes
        // Tender's `creating` hook that generates internal_id (see [[database-seeders]] for
        // the same class of issue with Spatie's permission cache). This seeder needs model
        // events, unlike the bulk CpvCodeSeeder/NutsCodeSeeder imports that benefit from
        // muting, so restore the dispatcher for its duration.
        Model::setEventDispatcher(app('events'));

        $this->categories = ServiceCategory::query()->orderBy('code')->get();

        $this->createUsers();

        foreach (TenderStatus::cases() as $status) {
            for ($i = 0; $i < 3; $i++) {
                $this->createTender($status, $i);
            }
        }
    }

    private function createUsers(): void
    {
        foreach (RoleName::cases() as $role) {
            $users = new Collection;

            if ($role === RoleName::SUPER_ADMIN) {
                $admin = User::query()->updateOrCreate(
                    ['email' => 'admin@example.com'],
                    ['name' => 'Admin', 'password' => Hash::make('password'), 'email_verified_at' => now()],
                );
                $admin->syncRoles($role);
                $users->push($admin);
            } else {
                $demo = User::query()->updateOrCreate(
                    ['email' => $role->value.'@example.com'],
                    [
                        'name' => ucwords(str_replace('-', ' ', $role->value)).' Demo',
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'service_category_id' => $this->categoryFor($role, 0),
                    ],
                );
                $demo->syncRoles($role);
                $users->push($demo);
            }

            for ($i = 0; $i < 2; $i++) {
                $extra = User::factory()->create([
                    'service_category_id' => $this->categoryFor($role, $i + 1),
                ]);
                $extra->syncRoles($role);
                $users->push($extra);
            }

            if (in_array($role, [RoleName::DEPARTMENT_HEAD, RoleName::TEAM_LEAD], true)) {
                $users->first()->syncPermissions(Right::cases());
            } elseif ($role === RoleName::CALCULATION) {
                $users->first()->syncPermissions([Right::SEE_PRICES]);
            }

            $this->usersByRole[$role->value] = $users;
        }
    }

    private function categoryFor(RoleName $role, int $index): ?string
    {
        if (in_array($role, self::MANAGEMENT_ROLES, true)) {
            return null;
        }

        return $this->categories[$index % $this->categories->count()]->id;
    }

    /**
     * Statuses only reachable by passing through SUBMISSION in the transition map — per
     * Tender::tasksComplete()'s gate, these require every task to be done before the status
     * walk gets there (see [[tenders]]).
     *
     * @var array<int, TenderStatus>
     */
    private const array REQUIRES_COMPLETE_TASKS = [
        TenderStatus::SUBMISSION,
        TenderStatus::FOLLOW_UP,
        TenderStatus::WON,
        TenderStatus::LOST,
    ];

    private function createTender(TenderStatus $status, int $variant): void
    {
        $category = $this->categories[$variant % $this->categories->count()];

        $team = $this->pickTeam($category);
        $owner = $team->first();

        $tender = Tender::factory()->create([
            'service_category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => fake()->catchPhrase().' — '.$category->name,
            'submission_deadline' => fake()->dateTimeBetween('+1 week', '+4 months'),
        ]);

        // Tasks must exist and (for statuses reached via SUBMISSION) be done *before* the
        // tender's status is walked forward, so Tender::tasksComplete()'s gate on the
        // quality->submission transition sees an accurate picture rather than a
        // not-yet-populated tender.
        $requiresCompleteTasks = in_array($status, self::REQUIRES_COMPLETE_TASKS, true);
        $taskCount = fake()->numberBetween(3, 5);
        $tasks = [];

        for ($i = 0; $i < $taskCount; $i++) {
            $tasks[] = $this->createTask($tender, $team, $i, $requiresCompleteTasks);
        }

        // Chain the first two tasks for a dependency-gate demo, where both exist and aren't
        // already forced done.
        if (count($tasks) >= 2 && $tasks[1]->status !== TaskStatus::DONE) {
            $tasks[1]->dependencies()->attach($tasks[0]->id);
        }

        $this->advanceTender($tender, $status, $owner, $variant);

        foreach ($team->skip(1) as $index => $member) {
            $tender->teamMembers()->create([
                'user_id' => $member->id,
                'functional_role' => TeamRole::cases()[$index % count(TeamRole::cases())],
            ]);
        }

        // Edge cases for documentation screenshots: archive/invalidate a slice of the data.
        if ($status->isTerminal() && $variant === 0) {
            $tender->archive($owner);
        } elseif ($variant === 2 && in_array($status, [TenderStatus::REVIEW, TenderStatus::CANCELLED], true)) {
            $tender->markInvalid($owner, fake()->sentence(fake()->numberBetween(6, 12)));
        }
    }

    /**
     * Pick a small team (owner + 2-4 members) from category-scoped users plus management.
     *
     * @return Collection<int, User>
     */
    private function pickTeam(ServiceCategory $category): Collection
    {
        $scoped = collect($this->usersByRole)
            ->except(array_map(fn (RoleName $role) => $role->value, self::MANAGEMENT_ROLES))
            ->flatten()
            ->filter(fn (User $user) => $user->service_category_id === $category->id)
            ->values();

        $management = collect($this->usersByRole[RoleName::TEAM_LEAD->value])
            ->merge($this->usersByRole[RoleName::DEPARTMENT_HEAD->value]);

        return $management->merge($scoped)->unique('id')->shuffle()->take(fake()->numberBetween(3, 5))->values();
    }

    private function advanceTender(Tender $tender, TenderStatus $target, User $actor, int $variant): void
    {
        $earlyExits = [TenderStatus::CANCELLED, TenderStatus::NOT_EVALUATED, TenderStatus::EXCLUDED];
        $afterSubmission = [TenderStatus::WON, TenderStatus::LOST];

        if (in_array($target, $earlyExits, true)) {
            // Vary how far into the active phases a tender got before exiting.
            $this->walkActivePhases($tender, min($variant, count(self::ACTIVE_PHASES) - 1), $actor);
            $tender->changeStatusTo($target, $actor, fake()->sentence(fake()->numberBetween(6, 12)));

            return;
        }

        if (in_array($target, $afterSubmission, true)) {
            // Vary whether the path passes through FOLLOW_UP before the final decision.
            $this->walkActivePhases($tender, $variant === 2 ? 6 : 5, $actor);
            $tender->changeStatusTo($target, $actor);

            return;
        }

        $targetIndex = array_search($target, self::ACTIVE_PHASES, true);
        $this->walkActivePhases($tender, $targetIndex, $actor);
    }

    /**
     * Step the tender forward through ACTIVE_PHASES up to (and including) the given index.
     */
    private function walkActivePhases(Tender $tender, int $upToIndex, User $actor): void
    {
        for ($i = 1; $i <= $upToIndex; $i++) {
            $tender->changeStatusTo(self::ACTIVE_PHASES[$i], $actor);
        }
    }

    private function createTask(Tender $tender, Collection $team, int $index, bool $forceDone = false): Task
    {
        $owner = $team->random();
        $reviewer = $team->count() > 1 ? $team->reject(fn (User $u) => $u->is($owner))->random() : null;

        $overdue = ! $forceDone && $index === 0 && fake()->boolean(40);

        $task = Task::factory()->create([
            'tender_id' => $tender->id,
            'owner_id' => $owner->id,
            'creator_id' => $team->first()->id,
            'reviewer_id' => $reviewer?->id,
            // TaskFactory doesn't set 'status' (it relies on the DB column default of 'open'),
            // but changeStatusTo() below needs it populated on the in-memory model, which a
            // bare create() doesn't pick up from the DB default without a refresh.
            'status' => TaskStatus::OPEN,
            'due_date' => $overdue ? fake()->dateTimeBetween('-3 weeks', '-1 day') : fake()->dateTimeBetween('+1 week', '+6 weeks'),
        ]);

        if ($team->count() > 2) {
            // Task::participants()->attach()/sync() hits the known task_participants.id
            // (uuid, no ->using() pivot) NOT NULL bug documented in [[migrations]] — not fixed
            // here since it's unrelated to this seeder. Insert the pivot rows directly instead.
            $now = now();
            DB::table('task_participants')->insert(
                $team->skip(2)->take(2)->map(fn (User $participant): array => [
                    'id' => (string) Str::uuid(),
                    'task_id' => $task->id,
                    'user_id' => $participant->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }

        foreach (range(1, fake()->numberBetween(2, 4)) as $position) {
            $task->checklistItems()->create([
                'description' => fake()->sentence(4),
                'is_done' => fake()->boolean(50),
                'position' => $position,
            ]);
        }

        if ($forceDone) {
            $this->advanceTaskStatus($task, $owner, TaskStatus::DONE);
        } elseif (! $overdue) {
            $this->advanceTaskStatus($task, $owner);
        }

        for ($i = 0, $commentCount = fake()->numberBetween(1, 3); $i < $commentCount; $i++) {
            $task->comments()->create([
                'user_id' => $task->linkedUsers()->random()->id,
                'body' => fake()->paragraph(),
            ]);
        }

        for ($i = 0, $attachmentCount = fake()->numberBetween(1, 2); $i < $attachmentCount; $i++) {
            $this->createAttachment($task);
        }

        return $task;
    }

    private function advanceTaskStatus(Task $task, User $actor, ?TaskStatus $target = null): void
    {
        $target ??= fake()->randomElement([
            TaskStatus::OPEN,
            TaskStatus::IN_PROGRESS,
            TaskStatus::WAITING_ON_ANOTHER_TASK,
            TaskStatus::IN_REVIEW,
            TaskStatus::CORRECTION_REQUIRED,
            TaskStatus::DONE,
        ]);

        $path = match ($target) {
            TaskStatus::OPEN => [],
            TaskStatus::IN_PROGRESS => [TaskStatus::IN_PROGRESS],
            TaskStatus::WAITING_ON_ANOTHER_TASK => [TaskStatus::WAITING_ON_ANOTHER_TASK],
            TaskStatus::IN_REVIEW => [TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW],
            TaskStatus::CORRECTION_REQUIRED => [TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW, TaskStatus::CORRECTION_REQUIRED],
            TaskStatus::DONE => [TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW, TaskStatus::DONE],
        };

        foreach ($path as $status) {
            $task->changeStatusTo($status, $actor);
        }
    }

    private function createAttachment(Task $task): void
    {
        $filename = fake()->slug(3).'.txt';
        $path = 'task-attachments/'.fake()->uuid().'.txt';
        $content = fake()->paragraphs(fake()->numberBetween(1, 3), true);

        Storage::disk('local')->put($path, $content);

        $task->attachments()->create([
            'uploaded_by' => $task->linkedUsers()->random()->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'text/plain',
            'size' => strlen($content),
        ]);
    }
}
