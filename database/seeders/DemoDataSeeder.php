<?php

namespace Database\Seeders;

use App\Enums\AbsenceType;
use App\Enums\BidDecision;
use App\Enums\CalculationApprovalStep;
use App\Enums\CalculationModel;
use App\Enums\CertificateType;
use App\Enums\CommunicationType;
use App\Enums\CompetitorOutcome;
use App\Enums\ConceptBlockCategory;
use App\Enums\DeadlineType;
use App\Enums\DocumentCategory;
use App\Enums\DocumentRequestStatus;
use App\Enums\Right;
use App\Enums\RoleName;
use App\Enums\SkillCategory;
use App\Enums\SkillProficiency;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use App\Enums\WinLossReason;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Competitor;
use App\Models\CompetitorPriceEntry;
use App\Models\ConceptBlock;
use App\Models\Reference;
use App\Models\ServiceCategory;
use App\Models\Skill;
use App\Models\Task;
use App\Models\Tender;
use App\Models\TenderBidDecision;
use App\Models\TenderCalculation;
use App\Models\TenderCompetitor;
use App\Models\TenderDocument;
use App\Models\TenderDocumentRequest;
use App\Models\TenderParticipationScore;
use App\Models\TenderSiteVisit;
use App\Models\TenderSubmission;
use App\Models\User;
use App\Models\UserAbsence;
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

    /** @var Collection<int, Reference> the company-wide reference library, seeded once before any tender */
    private Collection $references;

    /** @var Collection<int, Certificate> the company-wide certificate library, seeded once before any tender */
    private Collection $certificates;

    /** @var Collection<int, ConceptBlock> the company-wide concept library, seeded once before any tender */
    private Collection $conceptBlocks;

    /** @var Collection<int, Client> the company-wide client list (M10), seeded once before any tender */
    private Collection $clients;

    /** @var Collection<int, Competitor> the company-wide competitor list (M10), seeded once before any tender */
    private Collection $competitors;

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

        // The three M7 libraries are company-wide, not per-tender, so they're seeded once
        // up front (mirrors ServiceCategory/Sector reference-data seeding) and then a slice of
        // each is linked to individual tenders below via attachLibraryRecords().
        $this->references = $this->createReferenceLibrary();
        $this->certificates = $this->createCertificateLibrary();
        $this->conceptBlocks = $this->createConceptLibrary();

        // M10 libraries: company-wide, same one-seed-up-front pattern as the M7 libraries
        // above — a slice of each is linked to individual tenders below.
        $this->clients = $this->createClientLibrary();
        $this->competitors = $this->createCompetitorLibrary();

        // M11: company-wide skill library, same one-seed-up-front pattern — assigned to every
        // seeded user immediately (skills aren't per-tender, so there's nothing to link later).
        $this->assignSkillsToUsers($this->createSkillLibrary());

        foreach (TenderStatus::cases() as $status) {
            for ($i = 0; $i < 3; $i++) {
                $this->createTender($status, $i);
            }
        }

        // M11: absences are user-level, not per-tender, so they're seeded once at the end —
        // one is deliberately pinned onto an existing task's due date so the absence-aware
        // warning/escalation logic has real overlapping data to demonstrate.
        $this->createAbsenceLibrary();
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

        // Statuses only reachable via SUBMISSION need every TeamRole represented on the team
        // (owner + one member per role) so TenderCalculation::approve()'s team-role gate can
        // find an eligible approver for all 5 role-gated steps below — a 3-5 person team drawn
        // without this floor can easily miss FINAL_APPROVAL (5th in TeamRole::cases()) entirely.
        $reachesSubmission = in_array($status, self::REQUIRES_COMPLETE_TASKS, true);
        $team = $reachesSubmission ? $this->pickTeam($category, 6, 7) : $this->pickTeam($category);
        $owner = $team->first();

        // M10: most tenders link to a company client (a minority stay unlinked, to demo the
        // "Unknown" grouping in MarketAnalysis's client breakdown). A handful get their
        // contract_end_date pinned into the 12/9/6-month reminder windows instead of the
        // factory's own wide random range, so tenders:check-client-renewals has something to
        // fire on once seeding is done — including one LOST tender, per idea.md's "reminders
        // fire on lost tenders too" requirement.
        $demoContractEndDate = $this->demoClientRenewalDate($status, $variant);

        $tender = Tender::factory()->create([
            'service_category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => fake()->catchPhrase().' — '.$category->name,
            'client_id' => fake()->boolean(75) ? $this->clients->random()->id : null,
            ...($demoContractEndDate !== null ? ['contract_end_date' => $demoContractEndDate] : []),
        ]);

        // Overrides the factory's own default SUBMISSION deadline with a wider realistic
        // range for screenshot variety.
        $tender->upsertDeadline(DeadlineType::SUBMISSION, fake()->dateTimeBetween('+1 week', '+4 months'));

        // M10: a minority of tenders get 0-3 competitor sightings, in a mix of outcomes, to
        // demo CompetitorIntelligence's derived counts and ClientResource's client-history tab.
        $this->attachCompetitors($tender);

        // Functional-role team members must exist before the calculation approval chain below
        // (and before the status walk further down), which both depend on tender_team_members
        // rows already being in place — same "create before consuming" ordering as tasks/
        // documents further down.
        //
        // ->values() re-keys from 0: Collection::skip() keeps each member's ORIGINAL index, so
        // without this the round-robin below started at whatever index the owner happened to
        // occupy (1, since the owner is always $team->first()) instead of 0 — TeamRole::CALCULATION
        // (index 0) was then only ever reachable once the team had 6+ members and the index
        // wrapped back around, silently starving TenderCalculation::approve()'s first step of any
        // eligible approver on every normally-sized team.
        foreach ($team->skip(1)->values() as $index => $member) {
            $tender->teamMembers()->create([
                'user_id' => $member->id,
                'functional_role' => TeamRole::cases()[$index % count(TeamRole::cases())],
            ]);
        }

        // Tasks must exist and (for statuses reached via SUBMISSION) be done *before* the
        // tender's status is walked forward, so Tender::tasksComplete()'s gate on the
        // quality->submission transition sees an accurate picture rather than a
        // not-yet-populated tender.
        $taskCount = fake()->numberBetween(3, 5);
        $tasks = [];

        for ($i = 0; $i < $taskCount; $i++) {
            $tasks[] = $this->createTask($tender, $team, $i, $reachesSubmission);
        }

        // Chain the first two tasks for a dependency-gate demo, where both exist and aren't
        // already forced done.
        if (count($tasks) >= 2 && $tasks[1]->status !== TaskStatus::DONE) {
            $tasks[1]->dependencies()->attach($tasks[0]->id);
        }

        // Same ordering trap as tasks above: documents created here (before the status walk)
        // are what Tender::lockDocuments() locks once the walk reaches SUBMISSION (see
        // [[documents]]). RESULT/POST_ANALYSIS are held back for tenders that reach
        // SUBMISSION-or-later and added after the walk instead, to demo a document created
        // post-lock staying unlocked.
        $initialCategories = $reachesSubmission
            ? array_filter(
                DocumentCategory::cases(),
                fn (DocumentCategory $category): bool => ! in_array($category, [DocumentCategory::RESULT, DocumentCategory::POST_ANALYSIS], true)
            )
            : DocumentCategory::cases();
        $this->createDocuments($tender, $team, $initialCategories);

        // Calculation + approval chain, same "before the status walk" ordering as tasks/
        // documents: Tender::changeStatusTo()'s SUBMISSION guard (see [[tenders]]) requires the
        // current calculation's 6-step chain fully approved. Tenders that don't reach
        // SUBMISSION still get a calculation (for the tab to have something to show), but only
        // a random prefix of steps approved, to demo the in-progress state too.
        if ($category->calculation_model !== null) {
            $calculation = $this->createCalculation($tender, $category->calculation_model, $team);
            $this->approveCalculationChain(
                $calculation,
                $tender,
                $reachesSubmission ? null : fake()->numberBetween(0, count(CalculationApprovalStep::cases()) - 1),
            );
        }

        // Informational only — independent of tender status. Placed after the calculation above
        // since the expected-margin factor reads the tender's current calculation, so it demos a
        // real derived value rather than the lowest bucket every time.
        $this->createBidDecision($tender, $team, $owner, $variant);

        // Also informational only, independent of tender status — links a slice of the
        // company-wide libraries seeded in run() to this tender's Reference Library tab.
        $this->attachLibraryRecords($tender);

        // Communication log, site visits, and document requests are informational/independent
        // of status too, same as the bid decision and library attachments above — a tender can
        // accumulate these at any phase of its lifecycle. Document requests are created last so
        // they can optionally link back to one of the communication entries just created.
        $this->createCommunications($tender, $team);
        $this->createSiteVisits($tender, $team);
        $this->createDocumentRequests($tender, $team);

        $this->advanceTender($tender, $status, $owner, $variant);

        if ($reachesSubmission) {
            $this->createDocuments($tender, $team, [DocumentCategory::RESULT, DocumentCategory::POST_ANALYSIS]);

            // Unlike documents/tasks, the submission record itself is only meaningful once the
            // tender has actually reached SUBMISSION-or-later, so it's created after the status
            // walk rather than before it (nothing upstream gates on its existence).
            $this->createSubmission($tender, $team);
        }

        // Mirrors advanceTender()'s own branching: FOLLOW_UP is only reached directly (status ===
        // FOLLOW_UP) or, for WON/LOST, when variant 2 walks through index 6 instead of 5 (see
        // advanceTender()'s $afterSubmission branch) — every other WON/LOST tender skips FOLLOW_UP
        // entirely, so it gets no follow-up record either.
        $reachesFollowUp = $status === TenderStatus::FOLLOW_UP
            || ($variant === 2 && in_array($status, [TenderStatus::WON, TenderStatus::LOST], true));

        if ($reachesFollowUp) {
            $this->createFollowUp($tender, $team);
        }

        // Result + lessons learned only make sense once the tender has actually closed —
        // gated on TenderStatus::isTerminal() per [[milestones]]'s m9 file, same
        // after-the-status-walk placement as submission/follow-up above.
        if ($status->isTerminal()) {
            $this->createResult($tender, $team, $status);
            $this->createLessonsLearned($tender, $team);
        }

        // Edge cases for documentation screenshots: archive/invalidate a slice of the data.
        if ($status->isTerminal() && $variant === 0) {
            $tender->archive($owner);
        } elseif ($variant === 2 && in_array($status, [TenderStatus::REVIEW, TenderStatus::CANCELLED], true)) {
            $tender->markInvalid($owner, fake()->sentence(fake()->numberBetween(6, 12)));
        }
    }

    /**
     * Pick a small team (owner + members) from category-scoped users plus management.
     *
     * @return Collection<int, User>
     */
    private function pickTeam(ServiceCategory $category, int $minSize = 3, int $maxSize = 5): Collection
    {
        $scoped = collect($this->usersByRole)
            ->except(array_map(fn (RoleName $role) => $role->value, self::MANAGEMENT_ROLES))
            ->flatten()
            ->filter(fn (User $user) => $user->service_category_id === $category->id)
            ->values();

        $management = collect($this->usersByRole[RoleName::TEAM_LEAD->value])
            ->merge($this->usersByRole[RoleName::DEPARTMENT_HEAD->value]);

        return $management->merge($scoped)->unique('id')->shuffle()->take(fake()->numberBetween($minSize, $maxSize))->values();
    }

    /**
     * Realistic cost-driver inputs per calculation model, matching CalculationEngineTest's
     * known fixtures (field keys must match ServiceCategorySeeder's configured fields).
     *
     * @param  Collection<int, User>  $team
     */
    private function createCalculation(Tender $tender, CalculationModel $model, Collection $team): TenderCalculation
    {
        $inputs = match ($model) {
            CalculationModel::DEPLOYMENT_HOURS => [
                'hours' => fake()->numberBetween(80, 400),
                'wage_rate' => fake()->randomFloat(2, 15, 30),
                'supplements_pct' => fake()->randomFloat(2, 0.05, 0.15),
                'social_costs_pct' => fake()->randomFloat(2, 0.15, 0.25),
                'target_margin_pct' => fake()->randomFloat(2, 0.10, 0.20),
                'min_margin_pct' => 0.08,
                'risk_surcharge_pct' => fake()->randomFloat(2, 0.02, 0.08),
            ],
            CalculationModel::AREA_BASED => [
                'area' => fake()->numberBetween(200, 2000),
                'labour_hours' => fake()->numberBetween(50, 300),
                'wage_rate' => fake()->randomFloat(2, 15, 25),
                'machines_consumables_cost' => fake()->randomFloat(2, 100, 800),
                'target_margin_pct' => fake()->randomFloat(2, 0.10, 0.18),
                'min_margin_pct' => 0.08,
                'risk_surcharge_pct' => fake()->randomFloat(2, 0.02, 0.06),
            ],
        };

        $calculation = $tender->calculations()->create([
            'version_number' => 1,
            'created_by' => $team->random()->id,
            'input_values' => $inputs,
        ]);

        $calculation->computeOutputs();

        return $calculation;
    }

    /**
     * Approves CalculationApprovalStep::cases() in order, using whichever tender_team_members
     * row matches each step's functional role (see CalculationApprovalStep::teamRole()), or the
     * rights-holding team-lead demo user for the final right-gated step. Stops at the first step
     * with no eligible approver rather than throwing, so a deliberately partial $upToStep still
     * degrades gracefully. Pass null to approve the full chain.
     */
    private function approveCalculationChain(TenderCalculation $calculation, Tender $tender, ?int $upToStep): void
    {
        $steps = CalculationApprovalStep::cases();
        $stepsToApprove = $upToStep === null ? $steps : array_slice($steps, 0, $upToStep);

        foreach ($stepsToApprove as $step) {
            $teamRole = $step->teamRole();

            $actor = $teamRole !== null
                ? $tender->teamMembers()->where('functional_role', $teamRole)->first()?->user
                : $this->usersByRole[RoleName::TEAM_LEAD->value]->first();

            if ($actor === null) {
                break;
            }

            $calculation->approve($step, $actor, fake()->optional(0.4)->sentence());
        }
    }

    /**
     * Seeds the participation score and, for 2 of every 3 tenders, a recorded bid decision.
     * The 3rd (variant 2) is left with only a partial score and no decision at all, to demo
     * the "incomplete" summary and the empty decision history state.
     *
     * @param  Collection<int, User>  $team
     */
    private function createBidDecision(Tender $tender, Collection $team, User $owner, int $variant): void
    {
        if ($variant === 2) {
            $partialFields = collect(TenderParticipationScore::MANUAL_RATING_FIELDS)->shuffle()->take(3);

            TenderParticipationScore::factory()->for($tender)->create(
                $partialFields->mapWithKeys(fn (string $field): array => [$field => fake()->numberBetween(1, 5)])->all(),
            );

            return;
        }

        $score = TenderParticipationScore::factory()->for($tender)->create(
            collect(TenderParticipationScore::MANUAL_RATING_FIELDS)
                ->mapWithKeys(fn (string $field): array => [$field => fake()->numberBetween(1, 5)])
                ->all(),
        );

        $decisionMaker = $team->first(fn (User $user): bool => $user->can(Right::MAKE_BID_DECISION->value)) ?? $owner;

        TenderBidDecision::create([
            'tender_id' => $tender->id,
            'decision' => $variant === 1 ? BidDecision::NO_BID : BidDecision::BID,
            'reason' => $variant === 1 ? fake()->sentence(fake()->numberBetween(8, 15)) : null,
            'score' => $score->score(),
            'decided_by' => $decisionMaker->id,
            'decided_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);
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
            // A majority get a functional_role tag; the remainder stay null to demo that not
            // every task is tagged to a specific contribution area (M11).
            'functional_role' => fake()->boolean(70) ? fake()->randomElement(TeamRole::cases()) : null,
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

    /**
     * @param  array<int, DocumentCategory>  $categories
     */
    private function createDocuments(Tender $tender, Collection $team, array $categories): void
    {
        foreach ($categories as $category) {
            $document = $tender->documents()->create([
                'category' => $category,
                'title' => $category->getLabel().' – '.ucfirst(fake()->words(3, true)),
                'created_by' => $team->random()->id,
            ]);

            // A minority of documents get a couple of extra versions, to demo version history.
            $versionCount = fake()->boolean(35) ? fake()->numberBetween(2, 3) : 1;

            for ($version = 1; $version <= $versionCount; $version++) {
                $this->createDocumentVersion($document, $team, $version);
            }
        }
    }

    private function createDocumentVersion(TenderDocument $document, Collection $team, int $versionNumber): void
    {
        $filename = fake()->slug(3).'.txt';
        $path = 'tender-documents/'.fake()->uuid().'.txt';
        $content = fake()->paragraphs(fake()->numberBetween(1, 3), true);

        Storage::disk('local')->put($path, $content);

        $document->versions()->create([
            'version_number' => $versionNumber,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'text/plain',
            'size' => strlen($content),
            'uploaded_by' => $team->random()->id,
        ]);
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

    /**
     * @param  Collection<int, User>  $team
     */
    private function createCommunications(Tender $tender, Collection $team): void
    {
        foreach (range(1, fake()->numberBetween(2, 4)) as $ignored) {
            $tender->communications()->create([
                'type' => fake()->randomElement(CommunicationType::cases()),
                'subject' => fake()->sentence(4),
                'content' => fake()->paragraph(),
                'contact_person' => fake()->boolean(50) ? fake()->name() : null,
                'occurred_at' => fake()->dateTimeBetween('-2 months', 'now'),
                'logged_by' => $team->random()->id,
            ]);
        }
    }

    /**
     * A minority of tenders get one or two site visits, each with a few photos, to demo both
     * the empty-tab state and the gallery.
     *
     * @param  Collection<int, User>  $team
     */
    private function createSiteVisits(Tender $tender, Collection $team): void
    {
        foreach (range(1, fake()->numberBetween(0, 2)) as $ignored) {
            $visit = $tender->siteVisits()->create([
                'visit_date' => fake()->dateTimeBetween('-2 months', '+1 month'),
                'attendees' => fake()->name().', '.fake()->name(),
                'contact_person' => fake()->boolean(50) ? fake()->name() : null,
                'access_routes' => fake()->boolean(50) ? fake()->sentence() : null,
                'parking' => fake()->boolean(50) ? fake()->sentence() : null,
                'areas' => fake()->boolean(50) ? fake()->sentence() : null,
                'risks' => fake()->boolean(30) ? fake()->sentence() : null,
                'technical_particularities' => fake()->boolean(30) ? fake()->sentence() : null,
                'staffing_requirement' => fake()->boolean(30) ? fake()->sentence() : null,
                'competitors_spotted' => fake()->boolean(30) ? fake()->sentence() : null,
                'open_questions' => fake()->boolean(30) ? fake()->sentence() : null,
                'notes' => fake()->boolean(40) ? fake()->paragraph() : null,
                'created_by' => $team->random()->id,
            ]);

            foreach (range(1, fake()->numberBetween(0, 3)) as $ignored2) {
                $this->createSiteVisitPhoto($visit, $team);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $team
     */
    private function createSiteVisitPhoto(TenderSiteVisit $visit, Collection $team): void
    {
        $filename = fake()->slug(3).'.txt';
        $path = 'tender-site-visit-photos/'.fake()->uuid().'.txt';
        $content = fake()->paragraphs(fake()->numberBetween(1, 2), true);

        Storage::disk('local')->put($path, $content);

        $visit->photos()->create([
            'uploaded_by' => $team->random()->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'text/plain',
            'size' => strlen($content),
        ]);
    }

    /**
     * A minority of tenders get one to three document requests, in a mix of statuses, to demo
     * both the empty-tab state and the status-change audit trail. A request is sometimes linked
     * back to one of the communication entries createCommunications() just created, and
     * sometimes left standalone, to demo both states of that optional link.
     *
     * @param  Collection<int, User>  $team
     */
    private function createDocumentRequests(Tender $tender, Collection $team): void
    {
        foreach (range(1, fake()->numberBetween(0, 3)) as $ignored) {
            $communication = fake()->boolean(40) ? $tender->communications()->inRandomOrder()->first() : null;

            $request = $tender->documentRequests()->create([
                'tender_communication_id' => $communication?->id,
                'description' => fake()->sentence(8),
                'owner_id' => $team->random()->id,
                'deadline' => fake()->boolean(60) ? fake()->dateTimeBetween('now', '+1 month') : null,
                'status' => DocumentRequestStatus::OPEN,
                'created_by' => $team->random()->id,
            ]);

            $targetStatus = fake()->randomElement(DocumentRequestStatus::cases());

            if ($targetStatus !== DocumentRequestStatus::OPEN) {
                if ($targetStatus !== DocumentRequestStatus::IN_PROGRESS && fake()->boolean(50)) {
                    $request->changeStatusTo(DocumentRequestStatus::IN_PROGRESS, $team->random());
                }

                if ($request->status !== $targetStatus) {
                    $request->changeStatusTo($targetStatus, $team->random(), fake()->boolean(50) ? fake()->sentence() : null);
                }
            }

            if ($targetStatus === DocumentRequestStatus::FULFILLED || fake()->boolean(30)) {
                $this->createDocumentRequestFile($request, $team);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $team
     */
    private function createDocumentRequestFile(TenderDocumentRequest $request, Collection $team): void
    {
        $filename = fake()->slug(3).'.txt';
        $path = 'tender-document-request-files/'.fake()->uuid().'.txt';
        $content = fake()->paragraphs(fake()->numberBetween(1, 2), true);

        Storage::disk('local')->put($path, $content);

        $request->files()->create([
            'uploaded_by' => $team->random()->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'text/plain',
            'size' => strlen($content),
        ]);
    }

    /**
     * @param  Collection<int, User>  $team
     */
    private function createSubmission(Tender $tender, Collection $team): void
    {
        $confirmed = fake()->boolean(70);

        $submission = $tender->submission()->create([
            'submission_date' => fake()->dateTimeBetween('-6 weeks', '-1 day'),
            'submission_time' => fake()->time(),
            'responsible_employee_id' => $team->random()->id,
            'portal' => fake()->randomElement(['e-Vergabe', 'TED eTendering', 'Subreport', 'Vergabe24']),
            'transmission_route' => fake()->randomElement(['Electronic portal upload', 'Email', 'Postal']),
            'receipt_confirmed' => $confirmed,
            'receipt_confirmed_at' => $confirmed ? fake()->dateTimeBetween('-6 weeks', 'now') : null,
            'notes' => fake()->boolean(30) ? fake()->paragraph() : null,
            'created_by' => $team->random()->id,
        ]);

        foreach (range(1, fake()->numberBetween(1, 2)) as $ignored) {
            $this->createSubmissionFile($submission, $team);
        }
    }

    /**
     * @param  Collection<int, User>  $team
     */
    private function createSubmissionFile(TenderSubmission $submission, Collection $team): void
    {
        $filename = fake()->slug(3).'.txt';
        $path = 'tender-submission-files/'.fake()->uuid().'.txt';
        $content = fake()->paragraphs(fake()->numberBetween(1, 2), true);

        Storage::disk('local')->put($path, $content);

        $submission->files()->create([
            'uploaded_by' => $team->random()->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'text/plain',
            'size' => strlen($content),
        ]);
    }

    /**
     * @param  Collection<int, User>  $team
     */
    private function createFollowUp(Tender $tender, Collection $team): void
    {
        $tender->followUp()->create([
            'presentation_scheduled_at' => fake()->boolean(50) ? fake()->dateTimeBetween('now', '+3 weeks') : null,
            'presentation_notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'negotiation_notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'bid_validity_until' => fake()->boolean(50) ? fake()->dateTimeBetween('now', '+3 months') : null,
            'expected_result_date' => fake()->boolean(50) ? fake()->dateTimeBetween('now', '+2 months') : null,
            'expected_result_notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'created_by' => $team->random()->id,
        ]);
    }

    /**
     * Varies fields by outcome: winner/winning_price/win_loss_reasons only make sense once
     * someone else won (LOST/EXCLUDED/NOT_EVALUATED) — left null/empty on WON (we're the
     * winner, our_rank is 1) and CANCELLED (procedure never concluded, our_price left unknown
     * too). price_gap mirrors ResultRelationManager::computePriceGap()'s own formula.
     *
     * @param  Collection<int, User>  $team
     */
    private function createResult(Tender $tender, Collection $team, TenderStatus $status): void
    {
        $isWon = $status === TenderStatus::WON;
        $othersWon = in_array($status, [TenderStatus::LOST, TenderStatus::EXCLUDED, TenderStatus::NOT_EVALUATED], true);
        $isCancelled = $status === TenderStatus::CANCELLED;

        $ourPrice = $isCancelled ? null : fake()->randomFloat(2, 50000, 300000);
        $winningPrice = match (true) {
            $isWon => $ourPrice,
            $othersWon => fake()->boolean(80) ? fake()->randomFloat(2, 50000, 300000) : null,
            default => null,
        };

        $tender->result()->create([
            'winner' => $othersWon ? fake()->company() : null,
            'our_rank' => match (true) {
                $isWon => 1,
                $othersWon => fake()->numberBetween(2, 5),
                default => null,
            },
            'winning_price' => $winningPrice,
            'our_price' => $ourPrice,
            'price_gap' => $winningPrice !== null && $ourPrice !== null ? round($winningPrice - $ourPrice, 2) : null,
            'award_date' => $isCancelled ? null : fake()->dateTimeBetween('-4 weeks', 'now'),
            'known_evaluation' => fake()->boolean(50) ? fake()->paragraph() : null,
            'reasoning' => fake()->paragraph(),
            'award_decision' => fake()->boolean(40) ? fake()->paragraph() : null,
            'win_loss_reasons' => $othersWon
                ? fake()->randomElements(array_column(WinLossReason::cases(), 'value'), fake()->numberBetween(1, 3))
                : [],
            'created_by' => $team->random()->id,
        ]);
    }

    /**
     * @param  Collection<int, User>  $team
     */
    private function createLessonsLearned(Tender $tender, Collection $team): void
    {
        $tender->lessonsLearned()->create([
            'went_well' => fake()->paragraph(),
            'differently_next_time' => fake()->paragraph(),
            'process_changes' => fake()->paragraph(),
            'created_by' => $team->random()->id,
        ]);
    }

    /**
     * A user to attribute a library record (reference/certificate/concept block/version) to —
     * these are company-wide, not scoped to one tender's team, so any seeded user will do.
     */
    private function randomLibraryAuthor(): User
    {
        return collect($this->usersByRole)->flatten()->random();
    }

    /**
     * @return Collection<int, Reference>
     */
    private function createReferenceLibrary(): Collection
    {
        return collect(range(1, 12))->map(function (): Reference {
            $category = $this->categories->random();
            $author = $this->randomLibraryAuthor();

            $factory = Reference::factory()->state([
                'service_category_id' => $category->id,
                'created_by' => $author->id,
            ]);

            // A minority are seeded with the volume-unknown toggle, to demo that state too.
            $reference = fake()->boolean(20) ? $factory->volumeUnknown()->create() : $factory->create();

            foreach (range(1, fake()->numberBetween(1, 2)) as $ignored) {
                $this->createReferenceAttachment($reference, $this->randomLibraryAuthor());
            }

            return $reference;
        });
    }

    private function createReferenceAttachment(Reference $reference, User $uploader): void
    {
        $filename = fake()->slug(3).'.txt';
        $path = 'reference-attachments/'.fake()->uuid().'.txt';
        $content = fake()->paragraphs(fake()->numberBetween(1, 3), true);

        Storage::disk('local')->put($path, $content);

        $reference->attachments()->create([
            'uploaded_by' => $uploader->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'text/plain',
            'size' => strlen($content),
        ]);
    }

    /**
     * One certificate per CertificateType, with a mix of valid/expiring-soon/expired statuses
     * and a majority carrying an uploaded file, so the status badge and file column both show
     * real variety in screenshots.
     *
     * @return Collection<int, Certificate>
     */
    private function createCertificateLibrary(): Collection
    {
        return collect(CertificateType::cases())->map(function (CertificateType $type): Certificate {
            $factory = Certificate::factory()->state([
                'type' => $type,
                'created_by' => $this->randomLibraryAuthor()->id,
            ]);

            $factory = match (fake()->randomElement(['valid', 'valid', 'expiringSoon', 'expired'])) {
                'expiringSoon' => $factory->expiringSoon(),
                'expired' => $factory->expired(),
                default => $factory,
            };

            if (fake()->boolean(60)) {
                $factory = $factory->withFile();
            }

            return $factory->create();
        });
    }

    /**
     * Company-wide skill library (M11), same one-seed-up-front pattern as the M7/M10 libraries
     * above — assignSkillsToUsers() below links a slice of it to every seeded user.
     *
     * @return Collection<int, Skill>
     */
    private function createSkillLibrary(): Collection
    {
        $skills = [
            ['name' => 'Contract Law', 'category' => SkillCategory::COMPLIANCE],
            ['name' => 'Public Procurement Law', 'category' => SkillCategory::COMPLIANCE],
            ['name' => 'ISO 9001 Auditing', 'category' => SkillCategory::COMPLIANCE],
            ['name' => 'Technical Writing', 'category' => SkillCategory::LANGUAGE],
            ['name' => 'German (Native)', 'category' => SkillCategory::LANGUAGE],
            ['name' => 'English (Business)', 'category' => SkillCategory::LANGUAGE],
            ['name' => 'Cost Calculation', 'category' => SkillCategory::TECHNICAL],
            ['name' => 'Quality Management', 'category' => SkillCategory::TECHNICAL],
            ['name' => 'Project Management', 'category' => SkillCategory::TECHNICAL],
            ['name' => 'Negotiation', 'category' => SkillCategory::SOFT_SKILLS],
        ];

        return collect($skills)->map(fn (array $skill): Skill => Skill::factory()->create($skill));
    }

    /**
     * Assigns 2-4 random skills (with a random proficiency each) to every seeded user. Plain
     * BelongsToMany::attach() is safe here — unlike task_participants, user_skills has a
     * composite [user_id, skill_id] primary key with no own uuid `id` column (see [[migrations]]/
     * [[models]] on the pivot-uuid trap), so there's no HasUuids-event workaround needed.
     *
     * @param  Collection<int, Skill>  $skills
     */
    private function assignSkillsToUsers(Collection $skills): void
    {
        collect($this->usersByRole)->flatten()->each(function (User $user) use ($skills): void {
            $skills->random(fake()->numberBetween(2, 4))->each(
                fn (Skill $skill) => $user->skills()->attach($skill->id, [
                    'proficiency_level' => fake()->randomElement(SkillProficiency::cases())->value,
                ])
            );
        });
    }

    /**
     * User-level absences (M11), seeded once after every tender exists — 2-3 users each get a
     * past-or-upcoming absence (a majority with a cover assigned), plus one absence deliberately
     * pinned onto an existing open task's due date so TaskForm's/DeadlinesRelationManager's
     * absence-aware warning and CheckDeadlineEscalations' cover-notification logic both have
     * real overlapping data to demonstrate.
     */
    private function createAbsenceLibrary(): void
    {
        $users = collect($this->usersByRole)->flatten()->values();
        $absentees = $users->random(min(3, $users->count()));
        $cover = $users->reject(fn (User $user): bool => $absentees->contains(fn (User $a): bool => $a->is($user)))->random();

        $absentees->values()->each(function (User $user, int $index) use ($cover): void {
            UserAbsence::factory()->create([
                'user_id' => $user->id,
                'type' => fake()->randomElement([AbsenceType::HOLIDAY, AbsenceType::SICKNESS]),
                'starts_at' => fake()->dateTimeBetween('-2 weeks', '+1 week'),
                'ends_at' => fake()->dateTimeBetween('+1 week', '+3 weeks'),
                'cover_user_id' => $index < 2 ? $cover->id : null,
            ]);
        });

        $overlapTask = Task::query()
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->inRandomOrder()
            ->first();

        if ($overlapTask !== null) {
            UserAbsence::factory()->create([
                'user_id' => $overlapTask->owner_id,
                'type' => AbsenceType::HOLIDAY,
                'starts_at' => $overlapTask->due_date->copy()->subDay(),
                'ends_at' => $overlapTask->due_date->copy()->addDay(),
                'cover_user_id' => $cover->id,
            ]);
        }
    }

    /**
     * One block per ConceptBlockCategory, each starting at version 1; a minority get 1-2 extra
     * versions to demo the version history tab, mirroring createDocuments()'s same
     * minority-gets-multiple-versions pattern.
     *
     * @return Collection<int, ConceptBlock>
     */
    private function createConceptLibrary(): Collection
    {
        return collect(ConceptBlockCategory::cases())->map(function (ConceptBlockCategory $category): ConceptBlock {
            $block = ConceptBlock::factory()->create([
                'category' => $category,
                'created_by' => $this->randomLibraryAuthor()->id,
            ]);

            $versionCount = fake()->boolean(35) ? fake()->numberBetween(2, 3) : 1;

            for ($version = 1; $version <= $versionCount; $version++) {
                $block->versions()->create([
                    'version_number' => $version,
                    'content' => fake()->paragraphs(fake()->numberBetween(2, 4), true),
                    'created_by' => $this->randomLibraryAuthor()->id,
                ]);
            }

            return $block;
        });
    }

    /**
     * @return Collection<int, Client>
     */
    private function createClientLibrary(): Collection
    {
        return Client::factory()->count(10)->create();
    }

    /**
     * Each competitor gets 1-3 sourced price entries, to demo CompetitorResource's price
     * history tab and give the derived analyses something non-trivial to aggregate.
     *
     * @return Collection<int, Competitor>
     */
    private function createCompetitorLibrary(): Collection
    {
        return Competitor::factory()->count(8)->create()->each(function (Competitor $competitor): void {
            foreach (range(1, fake()->numberBetween(1, 3)) as $ignored) {
                CompetitorPriceEntry::factory()->create([
                    'competitor_id' => $competitor->id,
                    'created_by' => $this->randomLibraryAuthor()->id,
                ]);
            }
        });
    }

    /**
     * Links 0-3 of the company-wide competitors to this tender, in a mix of outcomes.
     */
    private function attachCompetitors(Tender $tender): void
    {
        $count = min(fake()->numberBetween(0, 3), $this->competitors->count());

        if ($count === 0) {
            return;
        }

        $this->competitors->random($count)->each(fn (Competitor $competitor) => TenderCompetitor::factory()->create([
            'tender_id' => $tender->id,
            'competitor_id' => $competitor->id,
            'outcome' => fake()->randomElement(CompetitorOutcome::cases()),
        ]));
    }

    /**
     * Pins contract_end_date into the 12/9/6-month reminder windows for exactly 3 tenders
     * (one per threshold, spread across different statuses — including one LOST tender, since
     * idea.md's client-history reminders explicitly fire for lost tenders too), so
     * tenders:check-client-renewals has real rows to notify on right after seeding. Every other
     * tender keeps the factory's own wide random contract_end_date range (null here means "don't
     * override").
     */
    private function demoClientRenewalDate(TenderStatus $status, int $variant): ?\DateTimeInterface
    {
        return match (true) {
            $status === TenderStatus::INTAKE && $variant === 0 => now()->addMonths(11),
            $status === TenderStatus::PROCESSING && $variant === 1 => now()->addMonths(9),
            $status === TenderStatus::LOST && $variant === 2 => now()->addMonths(6),
            default => null,
        };
    }

    /**
     * Links a slice of the company-wide libraries to this tender's Reference Library tab.
     * Concept blocks pin currentVersion() at attach time, mirroring
     * ConceptBlocksRelationManager's real attach behavior (see [[milestones]]'s M7 file) —
     * editing the block afterward must not change what this tender is recorded as having used.
     */
    private function attachLibraryRecords(Tender $tender): void
    {
        $tender->bidReferences()->attach(
            $this->references->random(fake()->numberBetween(1, 3))->pluck('id')->all(),
        );

        if (fake()->boolean(70)) {
            $tender->certificates()->attach(
                $this->certificates->random(fake()->numberBetween(1, 2))->pluck('id')->all(),
            );
        }

        $this->conceptBlocks->random(fake()->numberBetween(1, 2))->each(
            fn (ConceptBlock $block) => $tender->conceptBlocks()->attach($block->id, [
                'concept_block_version_id' => $block->currentVersion?->id,
            ])
        );
    }
}
