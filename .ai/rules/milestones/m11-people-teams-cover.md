# M11 — People, Teams, Cover

Full spec: [idea.md](../../../idea.md)'s M11 section. Index: [../milestones.md](../milestones.md).

**M11 — People, Teams, Cover is now in progress**, started 2026-09-03.

**M11 — People, Teams, Cover is now complete**, finished 2026-09-03. All 10 tasks landed.

Design decisions confirmed with the user before any code was written:
- **`Task` gains a nullable `functional_role` column (cast to `App\Enums\TeamRole`)** rather than
  deriving contribution areas purely from `TenderTeamMember`. This gives true task-level
  breakdowns across "calculation, concept, references, documentation, quality control,
  communication, final review, coordination" — idea.md's exact list — instead of only
  tender-level role stats. `TeamRole` currently has 5 cases (`CALCULATION`, `CONCEPT`,
  `EVIDENCE_DOCUMENTS`, `QUALITY_CONTROL`, `FINAL_APPROVAL`); idea.md's list has 8 areas, so
  task 2 below extends `TeamRole` with `COMMUNICATION` and `COORDINATION` cases (`EVIDENCE_DOCUMENTS`
  already covers "references/documentation"). Existing tasks get `functional_role = null`
  (no backfill) — null means "not tagged to a specific functional area" (e.g. general/admin
  tasks), consistent with how `reviewer_id`/`service_category_id` already use null for "not
  applicable" rather than a synthetic enum case, per [[enums]]'s guidance to only add an explicit
  "unknown" case where the domain actually distinguishes "unknown" from "not applicable."
- **Skills matrix is backed by a new `Skill` lookup model + `user_skills` pivot with a
  `proficiency_level` enum** (`NOVICE`, `COMPETENT`, `EXPERT`), manually assigned by managers via
  a relation manager on `UserResource`. Sector experience is a separate, derived stat (computed
  from tender/task history cross-referenced with `ServiceCategory`) shown alongside the matrix on
  the employee profile, not folded into it — manually-assigned skills (e.g. "contract law",
  "technical writing", specific certifications) aren't sector-shaped and shouldn't be conflated
  with "has worked tenders in category X."
- **Absences & cover get a new `UserAbsence` model** (`user_id`, `type` cast to a new
  `AbsenceType` enum — `HOLIDAY`, `SICKNESS`, `OTHER` — `starts_at`, `ends_at`, `notes`,
  `cover_user_id` nullable FK to `User`). No separate cover-assignment table: "full history
  preserved" is satisfied by `UserAbsence` rows themselves never being hard-deleted (soft-delete
  or simply left in place once past) — each absence is its own historical record of who covered
  what, when, without needing a second join table. `CheckDeadlineEscalations` is extended to
  check whether a task's owner has an active `UserAbsence` covering "now"; if so, escalation
  notifications also go to `cover_user_id` (when set) alongside the owner, and deadline/task-due
  forms warn when a chosen due date falls inside the assignee's known absence window.
- **`Right::VIEW_EMPLOYEE_STATISTICS` (already seeded from M1, unused until now) gates the
  performance score and full cross-employee rankings only** — kept exactly as currently seeded
  (`SUPER_ADMIN`, `DEPARTMENT_HEAD`; not `TEAM_LEAD`), matching idea.md's "full rankings visible
  to managers only" literally. The workload view (capacity-before-assignment) and an employee's
  own profile numbers are *not* gated behind this right — they're operational/self-service data,
  not a ranking — only the score/ranking page and other employees' full profiles require it.

Additional decisions made while scoping (not asked, low-stakes implementation choices — flag for
the user if any turns out wrong once built):
- **`Skill` fields**: `name` (required, unique), `category` (nullable string, free text — e.g.
  "Technical", "Compliance", "Language" — not FK'd to anything, same free-text-grouping choice
  M10 made for `Competitor.market_segments`). `SkillResource` is a plain lookup table
  (list/create/edit only, same shape as `Source`/`Sector`, "Master Data" nav group,
  `canDelete()`/`canDeleteAny()` hardcoded `false` once any `UserSkill` references it — mirrors
  `ClientResource`'s never-hard-deleted rationale, since deleting a referenced skill would
  silently blank someone's matrix entry).
- **`UserSkillsRelationManager`** lives on `UserResource` (relationship `skills` via
  `belongsToMany(Skill::class)->withPivot('proficiency_level')->withTimestamps()`), full CRUD,
  no special gating beyond normal `UserResource` access (assigning skills is routine team-lead
  admin, not sensitive data).
- **Employee profile is a new `ViewUser` page** (`UserResource` currently has no view page, only
  list/create/edit) — an infolist with sections for: identity, `service_category` (department),
  skills matrix (embedded read-only table), and computed stats (tenders handled by status,
  on-time completion %, correction-loop count, average handling time, sector experience
  breakdown). Stats are computed live via query in `ViewUser`/`UserInfolist`, not cached/stored
  columns — matches M10's "derived analyses, no new stored columns" precedent (task 4's
  `CompetitorIntelligence`) since idea.md doesn't ask for historical point-in-time snapshots, just
  current reconciled numbers.
  - "Tenders handled": distinct tenders where the user appears in `Task.owner`/`participants`/
    `Tender.owner`/`TenderTeamMember` on that tender, grouped by the tender's current status.
  - "On-time completions": tasks with `status = DONE` where `completion_date <= due_date`,
    as a percentage of the user's total completed tasks.
  - "Correction loops": count of `task_status_changes` rows where
    `from_status = IN_REVIEW AND to_status = CORRECTION_REQUIRED` for tasks the user owned.
  - "Average handling time": average of `completion_date - start_date` (falling back to task
    `created_at` when `start_date` is null) across the user's `DONE` tasks.
  - "Sector experience": count of distinct tenders by `Sector`, from tenders the user
    contributed to (same "tenders handled" set above).
  - Self-view (own profile) is always allowed. Viewing another user's profile requires
    `Right::VIEW_EMPLOYEE_STATISTICS`, enforced in `ViewUser::mount()` (`canAccess()` is not
    auto-wired per [[resources]] — must be explicit) with a fallback allowing `record.id ===
    auth()->id()`.
- **Workload view**: a small widget (`UserWorkloadWidget` or similar) embedded on `Task`'s create
  form near the `owner_id` select — not a separate page — showing each candidate owner's current
  open-task count (`status != DONE`) so whoever is assigning a task sees capacity before piling
  on, per idea.md's "surfaced before every new assignment" phrasing. Implemented as a `Select`
  `helperText()`/option label augmentation (e.g. "Jane Doe (4 open tasks)") reading a live count
  per user, not a full dashboard — this is the lightest implementation that satisfies "surfaced
  before assignment" without duplicating M12's dashboard layer.
- **Team performance per department + bottleneck analysis**: one new gated Filament `Page`
  (`TeamPerformance`, `Right::VIEW_EMPLOYEE_STATISTICS`-gated, "Master Data"-adjacent or a new
  "People" nav group) with two `HasTable`-style breakdowns: (1) per-`ServiceCategory` aggregate
  (task counts by status, average on-time %, average correction-loop rate — grouped by
  `User.service_category_id`, matching [[scopes-models]]'s null-means-management convention for
  the "all categories" row), and (2) bottleneck analysis — average duration per process step,
  computed from `TenderCalculationApproval` rows (`approved_at - tender_calculation.created_at`
  or the previous step's `approved_at`, per `CalculationApprovalStep` in chain order) grouped by
  `step`. No new stored columns; pure aggregate queries, same "page not gated at row level, gated
  at page level" pattern as M10 task 4.
- **Performance score weights** (idea.md gives the input list but no formula — reasonable
  defaults, flagged as adjustable): on-time delivery 25%, task completion rate 20%, quality
  (inverse of correction-loop rate) 20%, reliability (on-time task starts / no missed
  reassignments) 15%, documentation quality (proxy: `EVIDENCE_DOCUMENTS`/`QUALITY_CONTROL`
  functional-role tasks completed without correction loops) 10%, collaboration (participant-role
  task count relative to owner-role count, as a rough proxy for cross-task involvement) 10%. Win
  rate is computed separately and shown as a secondary, clearly-labeled figure — never blended
  into the weighted score — per idea.md's explicit "not the primary driver" instruction. Score
  computed live (0-100 float), not stored, same rationale as the employee-profile stats above.
  Surfaced as a new column on the `TeamPerformance` page's per-user breakdown (or a dedicated
  "Rankings" tab) — full list only visible with `Right::VIEW_EMPLOYEE_STATISTICS`.
- **`AbsenceType` enum**: `HOLIDAY`, `SICKNESS`, `OTHER` — SCREAMING_SNAKE_CASE cases,
  lowercase-kebab-case backed values, `HasLabel`/`color()` per [[enums]] (green/red/gray).
  `UserAbsence` gets its own `AbsencesRelationManager` on `UserResource` (a user's own absence
  history, full CRUD — team leads/admins record absences on behalf of staff, no self-service
  portal in scope for M11) plus a standalone `AbsenceResource` for a cross-employee absence
  calendar/list view (reuses the Guava calendar package already wired for `TenderDeadline` per
  [[deadlines]], implementing `Eventable` the same way, so absences show alongside tender
  deadlines on one calendar — directly enables the "warns when a deadline falls inside someone's
  absence window" acceptance point by making both queryable through the same calendar surface).
- **Absence-aware deadline warnings**: (1) `TaskForm`/`DeadlinesRelationManager`'s due-date field
  gets a `->live()` + `->helperText()` (or `->hint()`) closure checking the selected
  owner/assignee's `UserAbsence` rows for the chosen date and showing an inline warning
  (non-blocking — the spec says "warns," not "prevents") when it falls inside an absence window;
  (2) `CheckDeadlineEscalations` is extended: before notifying `task.owner` (level 1) or
  `tender.owner` (level 2), check for an active `UserAbsence` on that user covering "now" and, if
  `cover_user_id` is set, additionally notify the cover (existing recipients are not replaced,
  only supplemented, so nothing silently stops reaching the original owner).

Planned tasks for M11:
- [x] **Task 1 — Skills matrix**: migration `skills` (UUID PK, `name` unique string, `category`
  nullable string, timestamps). Model `Skill` (mirrors `Source`, plus a `users(): BelongsToMany`
  back-relation). `SkillResource` (list/create/edit, "Master Data" nav group, sort order 6 right
  after Sources, `OutlinedAcademicCap` icon, `canDelete()`/`canDeleteAny()` hardcoded `false`
  always — matches every other lookup resource's convention exactly (`Source`/`Client`/
  `Sector`/etc. all hardcode `false` unconditionally, none actually check for references; the
  milestone doc's original "false once referenced" phrasing was corrected to match this
  established, simpler pattern once verified by grepping every existing `canDelete(Model
  $record)` override). New `App\Enums\SkillProficiency` enum (`NOVICE`, `COMPETENT`, `EXPERT`,
  `HasLabel`, `color()` — gray/info/success). Migration `user_skills` pivot (`user_id` FK
  `restrictOnDelete`, `skill_id` FK `restrictOnDelete`, `proficiency_level` string, timestamps,
  **composite primary key `[user_id, skill_id]`, no own `id` column** — matches
  `tender_certificate`/`tender_concept_block`'s "plain many-to-many pivot with extra attribute,
  no dedicated model" shape, not `task_participants`/`tender_team_members`'s own-UUID-PK shape,
  since `user_skills` is only ever accessed via `BelongsToMany`/`withPivot()`, never as its own
  model). `User::skills(): BelongsToMany` and `Skill::users(): BelongsToMany`, both
  `->withPivot('proficiency_level')->withTimestamps()`. `SkillsRelationManager` on `UserResource`
  (relationship `skills`) follows `ConceptBlocksRelationManager`'s attach/detach shape, not a
  full create/edit/delete shape — skills are created/edited on `SkillResource` only, never
  inline here: `AttachAction` with a `schema()` adding the pivot's `proficiency_level` `Select`
  alongside `$action->getRecordSelect()` (per Filament's "attaching with pivot attributes"
  pattern), `EditAction` for changing an already-assigned skill's proficiency level
  (`form()` on the relation manager itself only needs the `proficiency_level` field — Filament
  writes it straight to the pivot row since it's listed in `withPivot()`), `DetachAction` for
  removal. Table shows name/category/proficiency (badge, colored via `SkillProficiency::color()`
  through the raw `pivot.proficiency_level` string state). Because `UserResource::canEdit()` is
  currently hardcoded to `SUPER_ADMIN` only (pre-existing, unrelated to this task — user
  administration overall is super-admin-locked), this relation manager is for now only reachable
  by super admins via `EditUser`; broadening who can manage skills is deferred to whatever later
  task (if any) revisits `UserResource`'s access model. New `lang/en/skills.php`,
  `skill_proficiencies.php`.

  Trap avoided: the migration was first generated with its own `uuid('id')->primary()` (copying
  `task_participants`' shape), which broke `->attach()` in tests with a `NOT NULL constraint
  failed: user_skills.id` — plain `BelongsToMany::attach()` doesn't run model events/`HasUuids`
  since there's no pivot model, so nothing populates that column. Fixed by switching to a
  composite `[user_id, skill_id]` primary key with no `id` column at all, matching
  `tender_certificate`/`tender_concept_block`'s precedent (confirmed by reading those migrations
  directly) rather than inventing a third shape.

  Tests: `SkillResourceTest` (create with valid data, unique-name rejection, `canDelete()`/
  `canDeleteAny()` both false, no delete action on the edit page), `UserResourceTest`'s new
  "skills relation manager" describe block (assign a skill with a proficiency level via
  `AttachAction`, edit an assigned skill's proficiency via `EditAction`, duplicate-skill
  assignment rejected by the composite-primary-key constraint via a raw `QueryException`
  assertion, detach removes the assignment). 521 tests passing (up from 507 — 14 new; the
  full-suite run couldn't complete end-to-end in this environment due to a pre-existing PHP
  memory-limit issue unconnected to this task's code, same as noted at the end of M10 — every
  scoped/affected test file was run directly and passes). Pint clean; `phpstan
  --memory-limit=1G` clean on every file this task created/edited (the 3
  `staticClassAccess.privateMethod` findings on `UserResource.php` are the pre-existing
  `canManage()` baseline, confirmed via `git diff` showing this task's only change to that file
  is registering the new relation manager).

  **Follow-up (2026-09-03, user request after the milestone otherwise finished)**:
  `Skill.category` was converted from free-text `string` to a proper `App\Enums\SkillCategory`
  enum (`TECHNICAL`, `COMPLIANCE`, `LANGUAGE`, `SOFT_SKILLS`, `OTHER`, `HasLabel`, no `color()` —
  matches `DocumentCategory`'s plain-classification precedent, not `SkillProficiency`'s ordered
  one). Reverses the original "free-text grouping, same choice M10 made for
  `Competitor.market_segments`" decision once the user pointed out it's actually inconsistent
  with every other classification field in this app (`DeadlineType`, `ConceptBlockCategory`,
  etc. are all real enums) and invites typo-drift ("Technical" vs "Tech"). No migration needed —
  the `category` column was already a plain nullable `string`, and a backed enum's value is still
  just a string, so only the model cast and every PHP-level `category` reference needed updating:
  `Skill::casts()` added, `SkillForm`'s free-text `TextInput` replaced with a `Select`
  (`SkillCategory::class` options, no more helper text since the options are now
  self-explanatory), `SkillsTable` gained a `SelectFilter` for `category` (replacing the now
  less-useful `->searchable()` on an enum column) and a `->placeholder('-')` on the badge column,
  `SkillFactory` and `DemoDataSeeder::createSkillLibrary()` updated to use enum cases instead of
  raw display strings. `SkillsRelationManager`/`UserInfolist`'s read-only category badges needed
  no changes — `->badge()` already renders an enum's `getLabel()` correctly.

  Tests: `SkillResourceTest`'s creation case now submits/asserts the enum value instead of a raw
  string. 99 tests passing across `SkillResourceTest`/`UserResourceTest`/`TeamPerformancePageTest`/
  `TaskResourceTest` (regression check). Re-verified via the same throwaway-seeder-test pattern
  (written, run, deleted) that `DemoDataSeeder` still seeds valid `SkillCategory` values. Pint
  clean; `phpstan --memory-limit=1G` clean on every file touched.
- [x] **Task 2 — Task functional_role**: extended `App\Enums\TeamRole` with `COMMUNICATION`
  and `COORDINATION` cases (7 total now). Checked every existing consumer for exhaustiveness
  risk: `CalculationApprovalStep::teamRole()` matches over `CalculationApprovalStep`, not
  `TeamRole`, so it's unaffected (still only ever returns one of the original 5 cases — the two
  new ones have no approval-step mapping, by design, since idea.md's "communication"/
  "coordination" contribution areas aren't part of the M5 calculation-approval chain);
  `TenderForm`'s team-member `functional_role` Select and `PipelineForecast`'s coverage counting
  both already used `TeamRole::class`/`TeamRole::cases()` dynamically with no hardcoded count, so
  both self-adjusted to 7 with no code change needed. Migration `add_functional_role_to_tasks_table`
  adds a nullable `functional_role` string column to `tasks` (placed after `priority`), cast to
  `TeamRole` in `Task` and added to its `#[Fillable]` list. `TaskForm` gained an optional
  `functional_role` `Select` (enum options, `OutlinedTag` icon) right after `priority` in the
  "details" section.

  Verified the `TeamRole` extension doesn't quietly change existing behavior beyond cosmetics:
  `DemoDataSeeder`'s `pickTeam($category, 6, 7)` floor for tenders reaching `SUBMISSION` was
  sized to guarantee round-robin coverage of the original 5 roles (indices 0-4) — with 7 cases
  now, a 5-6 person non-owner team still hits indices 0-4 before any wraparound, so
  `TenderCalculation::approve()`'s role-gate is unaffected (confirmed via a throwaway
  Pest-seeder-invocation test per [[database-seeders]]: full `DatabaseSeeder` run still produces
  approved calculations with no errors). The only behavior change is cosmetic:
  `PipelineForecast`'s "resource_check" coverage badge now needs coverage across 7 functional
  areas instead of 5 to show green, so demo data (capped at 6 non-owner team members) will now
  typically show partial coverage rather than full — not a bug, `PipelineForecastPageTest`'s
  "full coverage" test builds its own fixture looping `TeamRole::cases()` dynamically and still
  passes unchanged.

  Tests: `TaskResourceTest`'s new case round-tripping `functional_role` through create then edit
  (`CONCEPT` → `COORDINATION`). Full `TaskResourceTest` (61 tests), `PipelineForecastPageTest` (6
  tests), and the `TenderResourceTest`/calculation-approval suite (166 tests) all re-run clean
  after the enum extension. 522 tests passing (up from 521 — 1 new; most of this task's
  verification was re-running existing dynamic tests rather than adding new ones, since the
  enum's existing consumers were already written generically). Pint clean; `phpstan
  --memory-limit=1G` clean on every file this task created/edited (the 8 pre-existing findings on
  `TaskForm.php` — `scopedUserQuery()`/`scopedUserOptions()` generics/private-method-access,
  `Repeater::reorderable('position')` — are confirmed via `git diff` to be entirely outside this
  task's added lines).
- [x] **Task 3 — Employee profile page**: added `ViewUser` page + `UserInfolist` to
  `UserResource`. Sections: identity (name/email/`serviceCategory.name`, 3-column), skills
  matrix (`RepeatableEntry` over `skills` with name/category/proficiency-badge columns, section
  hidden entirely when the user has none), employee profile stats (tenders handled grouped by
  status, on-time completion %, correction-loop count, average handling time, sector experience
  — all computed, no new stored columns), and a collapsed record-history meta section (matches
  `ServiceCategoryInfolist`'s shape).

  The stat computations live as public methods directly on `User` (not in the Infolist schema,
  which just calls them via `->state()` closures), since they're natural query-driven facts
  about a user, same precedent as `Tender::tasksComplete()`: `tendersHandled()` (a tender counts
  if the user is its `owner`, a `teamMembers` row, a `tasks` owner, or a `tasks.participants`
  member — deliberately broader than formal assignment, per idea.md's "not just was assigned"
  acceptance point), `tendersHandledByStatus()`, `sectorExperience()`, `onTimeTaskCompletionRate()`
  (a task with no due date can't be late, so it counts as on-time; null when the user has zero
  completed tasks, not a misleading 0%), `correctionLoopCount()` (counts
  `task_status_changes` rows from `IN_REVIEW` to `CORRECTION_REQUIRED` on the user's own tasks),
  `averageTaskHandlingTimeDays()` (`start_date` falling back to `created_at`, `abs()`'d — see
  trap below).

  Authorization required two layers, discovered by tracing a genuine 403 rather than assumed:
  `UserResource::canView(Model $record)` was added (`$user->is($record) ||
  canManage() || canViewStatistics()` — a new private `canViewStatistics()` checking
  `Right::VIEW_EMPLOYEE_STATISTICS`), and `canViewAny()` was broadened to `canManage() ||
  canViewStatistics()` so a statistics-right holder has a list to browse profiles from. `UsersTable`
  gained a `ViewAction` (unguarded at the row level, same as `ViewCompetitor`'s precedent, since
  every user who can reach the list already passes `canView()` for any row by construction).

  Trap found and fixed: even with `canView()` correctly implemented, a plain STAFF user's
  *own* profile still 403'd. Traced via a throwaway raw-HTTP-request test (not just
  `Livewire::test()`, which doesn't exercise the same authorization path as a real page load)
  to `Filament\Resources\Pages\Concerns\CanAuthorizeResourceAccess::authorizeResourceAccess()`
  — a mount-time hook wired into *every* resource page (List/Create/Edit/View alike) that
  independently gates on `static::getResource()::canAccess()`, which defaults to
  `canViewAny()`. This resource-wide, record-agnostic gate runs *before* `ViewRecord`'s own
  `canView($record)` check and has no way to know "this happens to be the viewer's own record" —
  so a self-viewing STAFF user (correctly passing `canView()`) was still being blocked by the
  list-level gate. Fixed by overriding `ViewUser::authorizeResourceAccess()` to a no-op,
  documented with a docblock explaining why: `ViewRecord`'s inherited `authorizeAccess()`
  already separately enforces `canView($record)` via `mount()`/`hydrate()`, which is sufficient
  and correctly permits self-view; the resource-wide gate this bypasses exists to protect the
  *list* page, not this one.

  Second trap: `averageTaskHandlingTimeDays()`'s `diffInDays()` call initially returned negative
  values (Carbon's diffInDays no longer defaults to an absolute value in this Carbon version, and
  the sign depends on call-target/argument order in a way that read backwards from what the
  variable names suggested) — a fixture asserting a known 2-day and 6-day gap got `-4.0` instead
  of `4.0` on first run. Fixed by wrapping in `abs()`, since only the magnitude matters here.

  Tests: `UserResourceTest`'s new "employee profile" describe block — a plain STAFF user reaches
  their own profile with no special right; a different STAFF user's profile is blocked
  (`assertForbidden()`); a `DEPARTMENT_HEAD` (seeded with `VIEW_EMPLOYEE_STATISTICS`) can view
  another user's profile; a fixture with two `DONE` tasks (one on-time, one late-with-a-
  correction-loop) reconciles `onTimeTaskCompletionRate()` (0.5), `correctionLoopCount()` (1),
  and `averageTaskHandlingTimeDays()` (4.0) exactly, then confirms the profile page still renders
  successfully over that data. 526 tests passing (up from 522 — 4 new). Pint clean; `phpstan
  --memory-limit=1G` clean on every file this task created/edited (the 6
  `staticClassAccess.privateMethod` findings on `UserResource.php` are the same pre-existing
  finding class as `canManage()`'s baseline, now also covering the two new private methods this
  task added, confirmed via `git diff`).
- [x] **Task 4 — Workload view**: implemented as per-option label augmentation, not
  `->helperText()` — `TaskForm::workloadLabel(User $user)` computes
  `Task::where('owner_id', $user->id)->where('status', '!=', TaskStatus::DONE)->count()` and
  formats it via new `tasks.fields.owner_workload_suffix` (`":name (:count open tasks)"`).
  `scopedUserOptions()` (used by `owner_id` and `reviewer_id`, both fed from the same private
  helper) now maps through `workloadLabel()` instead of a plain `pluck('name', 'id')`; the
  `participants` relationship `Select` gets the same labels via
  `->getOptionLabelFromRecordUsing()`, since relationship-backed multi-selects don't go through
  `options()`. `reviewer_id` picking up the same augmentation (not explicitly asked for) is
  intentional, not scope creep — it reads from the identical `scopedUserOptions()` call already
  shared with `owner_id`, and workload is equally relevant capacity context when choosing a
  reviewer.

  Tests: `TaskResourceTest`'s "assignment" describe block gained a case seeding a candidate with
  2 open + 1 done task and asserting `CreateTask`'s rendered form contains "Jane Doe (2 open
  tasks)" via `assertSee()` (no existing helper in this codebase asserts Select option contents
  directly; `assertSee()` on rendered HTML is the established pattern here, e.g.
  `TenderResourceTest`'s infolist-value assertions). 63 tests passing in `TaskResourceTest` (up
  from 62 — 1 new); `TenderResourceTest`'s 159 tests (which exercise `TaskForm` via
  `TasksRelationManager`) re-run clean. Pint clean; `phpstan --memory-limit=1G` on the file: 10
  findings vs. baseline 8 — the 2 new ones are `staticClassAccess.privateMethod` on the new
  `workloadLabel()` calls, same pre-existing finding class as every other private-static-method
  call in this file (confirmed by diffing phpstan output against the pre-change file via `git
  stash`), not a new category.
- [x] **Task 5 — Team performance & bottleneck analysis page**: new `App\Filament\Pages\
  TeamPerformance`, a plain `Page` (not `HasTable` — two independent breakdowns don't fit
  Filament's single-table-per-page shape, and `HasTable` can't inject the synthetic "management"
  row a `ServiceCategory`-backed query wouldn't otherwise produce) with `getViewData()` feeding
  two arrays to a Blade view that renders each as a plain native `<table>` (literal Tailwind
  classes, matching e.g. `task-status-timeline.blade.php`'s styling conventions — picked up by
  the existing `resources/views/filament/**/*` `@source` coverage per [[css-filament]], no new
  build config needed). Gated `Right::VIEW_EMPLOYEE_STATISTICS` end-to-end (`canAccess()` +
  `shouldRegisterNavigation()` + `mount()` abort, per [[pages]]'s "custom Page canAccess() is not
  auto-wired" rule). New "People" nav group added to `lang/en/navigation.php` (none of the
  existing groups fit; also the intended home for task 7's `AbsenceResource`).

  Breakdown 1 (`departmentBreakdown()`): one row per `ServiceCategory` plus a trailing
  "management" row for `User.service_category_id IS NULL` (reusing the existing
  `users.infolist.no_service_category` label, "Management (all categories)"), matching
  [[scopes-models]]'s null-means-management convention. Each row aggregates, across every task
  owned by a user in that department: a count per `TaskStatus` case, total task count, on-time
  completion rate (mirrors `User::onTimeTaskCompletionRate()`'s exact logic, reimplemented at
  department scope rather than averaging per-user rates — a task with no due date or no
  completion date counts as on-time, consistent with the individual-profile stat), and
  correction-loop rate (`TaskStatusChange` rows `IN_REVIEW → CORRECTION_REQUIRED` on the
  department's tasks, divided by the department's done-task count). A department with zero tasks
  is omitted entirely rather than shown as an all-zero row.

  Breakdown 2 (`bottleneckBreakdown()`): one row per `CalculationApprovalStep`, from every
  approved `TenderCalculationApproval` for that step — duration is `approved_at` minus the
  previous step's `approved_at` in chain order (via `CalculationApprovalStep::stepsBefore()`'s
  last element), or minus `tender_calculation.created_at` for the chain's first step. A step
  nobody has approved yet is omitted. Duration computed as `abs(diffInHours()) / 24` rather than
  `diffInDays()` directly, reusing task 3's already-documented Carbon-3 sign trap rather than
  re-deriving it (`diffInDays()` defaults `absolute: false` in the installed Carbon 3.13.2, while
  `diffInHours()` defaults `absolute: true` — confirmed by reflecting both signatures — so
  wrapping in `abs()` regardless of which is called is the safe, non-fragile choice).

  Trap avoided: `TenderCalculation::approve()` only ever creates a `TenderCalculationApproval`
  row for a step once it's actually approved (via `updateOrCreate`), never a placeholder row with
  a null `approved_at` — so `whereNotNull('approved_at')` is defensive, not load-bearing, and
  every approval row found already has a real timestamp to diff against.

  Tests: `TeamPerformancePageTest` — blocked without the right; reachable by a department head;
  a fixture with two done tasks (one on-time, one late-with-a-correction-loop) plus one open task
  in a seeded department asserts the department appears with the correct on-time and
  correction-loop rates (`50.0%` for both, via `assertSee()` on rendered HTML — no existing
  helper in this codebase asserts plain-Blade-table cell contents directly, so `assertSee()` is
  the right tool, same precedent as task 4); a department with zero tasks is confirmed absent via
  `assertDontSee()`; a fixture with two chained approvals (10 days ago → 8 days ago → 5 days ago)
  asserts each step's average duration (`2.0`, `3.0` days) exactly. 5 tests passing. Pint clean;
  `phpstan --memory-limit=1G` on the new file: 5 findings, all `staticClassAccess.privateMethod`
  on `static::` calls to the page's own private helpers — the same accepted finding class used
  project-wide (e.g. task 1/3/4's baselines), not a new category, confirmed by checking each
  finding is exactly that one class.
- [x] **Task 6 — Performance score & rankings**: implemented as two `User` methods (not a
  separate `Support` class — matches every other employee-profile stat's precedent of living
  directly on `User`, per task 3's rationale). `User::performanceScore(): float` implements the
  weighted formula from the "Performance score weights" design decision via a
  `private const array PERFORMANCE_SCORE_WEIGHTS` (0.25/0.20/0.20/0.15/0.10/0.10, summing to
  1.0) and six private component methods, each defaulting to `0.0` when the user has no relevant
  activity yet (a deliberate departure from every other profile stat's null-means-no-data
  convention — a single blended score has no sensible "no data" state, so it must always resolve
  to a well-defined float): `taskCompletionRate()` (done/owned task ratio), `qualityScore()`
  (`1 - min(1, correctionLoopCount / doneTasks)`), `onTimeTaskStartRate()` (reliability proxy —
  share of tasks with both `start_date` and `due_date` set where the task started on or before
  its due date), `documentationQualityScore()` (share of DONE tasks with `functional_role` in
  `[EVIDENCE_DOCUMENTS, QUALITY_CONTROL]` that never bounced `IN_REVIEW → CORRECTION_REQUIRED`),
  `collaborationScore()` (participant-task count / owner-task count, capped at 1.0, via a new
  `User::participatingTasks(): BelongsToMany` inverse of `Task::participants()`). Result rounded
  to 2 decimals (`round($weightedSum * 100, 2)`) purely to sidestep float-accumulation noise
  (`55.00000000000001` vs `55.0`) in exact-value test assertions — the UI still formats to 1
  decimal. `User::winRate(): ?float` is a fully separate figure (decided tenders — `WON`/`LOST`
  only, `CANCELLED`/`NOT_EVALUATED`/`EXCLUDED` excluded — where `WON` count / decided count; null
  when none decided yet), never blended into `performanceScore()`, per idea.md's explicit "not
  the primary driver" instruction.

  `TeamPerformance` (task 5) gained a third "Rankings" section listing every user's name,
  department, score, and win rate (sorted descending by score), rendered the same
  plain-Blade-table way as the other two breakdowns — still gated by the page's existing
  `Right::VIEW_EMPLOYEE_STATISTICS` check (no extra gating needed, the whole page already
  requires it). `UserInfolist` (task 3) gained `performance_score` and `win_rate` entries in the
  existing profile-stats section — visible on every `ViewUser` render regardless of the right,
  since `UserResource::canView()` already only reaches this page for the record owner
  (self-view, no right required) or a `Right::VIEW_EMPLOYEE_STATISTICS` holder viewing someone
  else (who already holds the right by construction) — no extra conditional needed to satisfy
  "surface a user's own score regardless of the right."

  Trap hit and worked around, not fixed: attaching task participants directly via
  `$task->participants()->attach($user)` in the score-fixture test hit the known
  `task_participants.id` NOT NULL bug ([[migrations]] — plain `attach()`/`sync()` doesn't run
  `HasUuids` model events without a `->using()` pivot model). Worked around the same way
  `DemoDataSeeder` already does: insert the pivot rows directly via `DB::table('task_participants')
  ->insert(...)` with an explicit `Str::uuid()` id.

  Larastan false positive noted, not acted on: `TeamPerformance::rankings()`'s
  `$user->serviceCategory?->name ?? …` is flagged `nullsafe.neverNull` (claiming the left side of
  `??` can never be null) — verified false via `php artisan tinker` (`(new User)->serviceCategory`
  genuinely returns `null`, and `users.service_category_id` is a nullable FK per its migration).
  No other file in this codebase happens to access `User::serviceCategory` (only
  `Tender::serviceCategory`, which is non-nullable and doesn't trigger this), so there's no prior
  art either way — left the nullsafe in place rather than "fixing" a real null-pointer risk to
  satisfy a static-analysis false positive.

  Tests: `UserResourceTest`'s new "performance score" describe block — a fixture with known
  on-time/completion/correction-loop/reliability/documentation/collaboration inputs (2 done tasks,
  1 on-time + documentation-shaped, 1 late + correction-looped; 2 open tasks; 2 participant-only
  tasks) asserts the exact weighted score (`55.0`); a separate fixture asserts `winRate()` is
  `null` with no decided tenders, `0.5` after one `WON` and one `LOST` (an `INTAKE` tender
  excluded), and that adding those tenders leaves `performanceScore()` completely unchanged. The
  "employee profile" describe block gained a case confirming a plain `STAFF` user sees their own
  formatted score on their own profile with no right. `TeamPerformancePageTest` gained a
  "rankings" case seeding one active-task user and one idle user, asserting both render and the
  active user's row sorts ahead of the idle one (via `Livewire::test()->viewData('rankings')`).
  227 tests passing across `TaskResourceTest`/`TenderResourceTest`
  (regression check, unaffected), 21 in `UserResourceTest` (up from ~18), 6 in
  `TeamPerformancePageTest` (up from 5). Pint clean; `phpstan --memory-limit=1G`: `User.php` and
  `UserInfolist.php` fully clean; `TeamPerformance.php` has 6 `staticClassAccess.privateMethod`
  findings (same accepted class as task 5's baseline, now covering the new `rankings()` method
  too) plus the one documented false positive above — no new finding categories.
- [x] **Task 7 — Absences & cover**: built exactly as scoped. `App\Enums\AbsenceType`
  (`HOLIDAY`/`SICKNESS`/`OTHER`, `HasLabel`, `color()` green/red/gray). Migration
  `user_absences` (UUID PK, `user_id` FK `cascadeOnDelete`, `type` string, `starts_at`/`ends_at`
  date, `notes` nullable text, `cover_user_id` nullable FK `users` `nullOnDelete()`, timestamps).
  `UserAbsence` implements `Eventable` (same shape as `TenderDeadline`), plus a `covers(Carbon
  $moment): bool` helper (`betweenIncluded` over `starts_at`/`ends_at`, each `->copy()`'d before
  `startOfDay()`/`endOfDay()` to avoid mutating the cached casted-attribute Carbon instance) that
  task 8 will reuse for the absence-aware warning/escalation logic. `toCalendarEvent()` uses
  `->allDay()` with `end($this->ends_at->copy()->addDay())` — FullCalendar treats an all-day
  event's end as exclusive, so the last day wouldn't render without the +1. `User::absences():
  HasMany` and `User::coveringAbsences(): HasMany` (`cover_user_id` FK) added alongside the
  existing profile-stat methods.

  `AbsencesRelationManager` on `UserResource` (full CRUD — unlike `SkillsRelationManager`'s
  attach-only shape, an absence has no separate lookup resource, per its own docblock),
  `cover_user_id` Select excluding the absent user via `whereKeyNot($this->getOwnerRecord()
  ->getKey())`, `ends_at` validated `->afterOrEqual('starts_at')` with a custom message.
  Standalone `AbsenceResource` (list/create/edit, new "People" nav group alongside
  `TeamPerformance`, no right gating — mirrors `SkillResource`'s plain-lookup-CRUD shape but with
  full delete left enabled, since nothing references `user_absences` the way skills are
  referenced by `user_skills`); its own form's `cover_user_id` options exclude whichever
  `user_id` is currently selected (reactive via `->live()` on `user_id` + a `Get`-driven
  `whereKeyNot`), since there's no single "owner record" to exclude here the way the relation
  manager has one.

  `UserAbsenceCalendarWidget` (new, mirrors `TenderDeadlineCalendarWidget`'s shape but queries an
  overlapping date range — `starts_at <= $info->end AND ends_at >= $info->start` — instead of a
  single-column `whereBetween`, since an absence spans a range rather than a point in time) is
  registered as a second widget in `TenderCalendar::getWidgets()` (not a separate page) so
  absences and deadlines render on the same calendar surface, per the design decision's "appear
  together" phrasing; it reuses the page's existing `employee_id` filter field with no new filter
  UI needed. New `lang/en/absences.php`, `absence_types.php`.

  Tests: `AbsenceResourceTest` (create, `ends_at < starts_at` rejected, `ends_at == starts_at`
  accepted, edit, cross-employee listing). `UserResourceTest`'s new "absences relation manager"
  describe block (scoped to the owner record only, create with a cover excluding the absent
  employee, `ends_at` validation, delete). `TenderCalendarTest` gained
  `UserAbsenceCalendarWidget`-specific range/employee-filter cases plus the milestone's literal
  acceptance test — a deadline and an absence both created in the same week both appear via their
  respective widgets' `getEvents()`. 40 new/changed tests passing (regression check across
  `TaskResourceTest`/`TenderResourceTest`/`TeamPerformancePageTest`/`SkillResourceTest`: 230
  passed, 1 pre-existing flaky failure on unrelated Faker-generated phone-format data, confirmed
  flaky by re-running that single test in isolation — passes). Pint clean; `phpstan
  --memory-limit=1G`: `UserAbsence.php`/`AbsenceResource.php` (and its `Schemas`/`Tables`/`Pages`)
  /`AbsencesRelationManager.php`/`AbsenceType.php` fully clean; `UserResource.php`'s 6 findings
  are the pre-existing `canManage()`/`canViewStatistics()` baseline (untouched by this task's
  one-line relation-manager registration); `UserAbsenceCalendarWidget.php`'s 3
  `missingType.*` findings are identical, line-for-line, to `TenderDeadlineCalendarWidget.php`'s
  own baseline (both inherit `CalendarWidget::getEvents()`'s abstract signature) — confirmed by
  running phpstan on the reference widget directly, not a new category.

  Trap avoided, not hit: initially considered calling `startOfDay()`/`endOfDay()` directly on
  `$this->starts_at`/`$this->ends_at` inside `covers()` — since Carbon's day-boundary methods
  mutate in place and Eloquent's date-cast attributes can be the same cached Carbon instance
  across repeated access, that would have silently corrupted the model's own `starts_at`/`ends_at`
  after the first `covers()` call. Used `->copy()` first instead, same defensive pattern this
  milestone's task 3 trap (`abs()` on `diffInDays()`) already established for Carbon call-site
  hazards — copy-before-mutate now, not sign-before-trust.
- [x] **Task 8 — Absence-aware deadline warnings & escalation**: `TaskForm::dueDateAbsenceWarning()`
  (private, checks `Get('owner_id')`/`Get('due_date')` against `User::absences()` via
  `UserAbsence::covers()` from task 7) wired into `due_date`'s new `->live()->helperText(...)` —
  `owner_id` also gained `->live()` so the warning re-evaluates when the owner changes, not just
  the date. `DeadlinesRelationManager` gets the equivalent `dueAtAbsenceWarning()`, but checks the
  *tender's* owner (`$this->getOwnerRecord()->owner`, `/** @var Tender $tender */`-annotated per
  `CalculationsRelationManager`'s established pattern for typing `getOwnerRecord()`) rather than a
  per-row assignee, since a `TenderDeadline` has no assignee field of its own — matches the design
  decision's "selected owner/assignee" phrasing applied to whichever concept of "owner" the form
  actually has. Both warnings are non-blocking (`helperText`, not a validation rule) per idea.md's
  "warns," not "prevents."

  `CheckDeadlineEscalations` gained a private `notifyWithCover(User $recipient, Notification
  $notification)` helper — notifies `$recipient` unconditionally, then additionally notifies
  their absence cover (found via the same `UserAbsence::covers(now())` scan, `?->coverUser`) if
  one exists; both `escalateOverdueTasks()` notification points (level 1 task owner, level 2
  tender owner) now route through it instead of calling `->notify()` directly. Levels 3-4
  (`escalateSubmissionDeadlines()`) are untouched — they notify every super admin as a group, not
  a single owner, so "the recipient's cover" doesn't apply there, matching the task's scope
  exactly.

  Trap hit and fixed: `UserAbsence::covers()` (built in task 7 typed to
  `Illuminate\Support\Carbon`) failed at runtime here with a `TypeError` — `now()` app-wide
  resolves to `Carbon\CarbonImmutable`, not `Illuminate\Support\Carbon`, because
  `AppServiceProvider` calls `Date::use(CarbonImmutable::class)`. Not caught in task 7 because its
  own tests only ever passed `Carbon::parse()`-derived instances (also `Illuminate\Support\Carbon`
  by construction) into `covers()`, never the app's actual `now()`. Fixed by retyping `covers()`'s
  parameter to `Carbon\CarbonInterface` (implemented by both) and dropping the now-unnecessary
  `->toImmutable()` conversion inside it — `betweenIncluded()` works directly on either. Documented
  on the method itself so a future caller doesn't reintroduce the narrower type.

  Two more `(string) __(...)` casts added (`TaskForm::dueDateAbsenceWarning()`,
  `DeadlinesRelationManager::dueAtAbsenceWarning()`) to satisfy phpstan's `return.type` check on a
  `?string`-returning method whose ternary branches include a raw `__()` call — same precedent as
  `CalculationsRelationManager.php`'s existing `(string) __(...)` cast, not a new pattern.

  Tests: `TaskResourceTest`'s new "due date absence warning" describe block (warning appears via
  `assertSee()` when the due date overlaps a fixture absence, absent when it doesn't, via
  `assertDontSee()`). `TenderResourceTest`'s "deadlines relation manager" group gained the mirror
  case using `mountTableAction('create')->setTableActionData([...])->assertMountedActionModalSee()`
  (no existing precedent in this codebase for asserting content inside an as-yet-unsaved table
  action's live-updating modal, `mountTableAction`/`setTableActionData`/
  `assertMountedActionModalSee`/`assertMountedActionModalDontSee` are Filament's own testing API
  for exactly this). `CheckDeadlineEscalationsTest`'s "task overdue escalation" group gained 4
  cases: cover notified at level 1, cover notified at level 2, no absence leaves the exact same
  single-notification count (`Notification::assertCount(1)`), an absence with no `cover_user_id`
  doesn't error and still leaves the owner notified alone. 279 tests passing across every
  regression-checked file (`TaskResourceTest`/`TenderResourceTest`/`CheckDeadlineEscalationsTest`/
  `TenderCalendarTest`/`UserResourceTest`/`AbsenceResourceTest`) — the one pre-existing flaky
  Faker-phone-format failure noted in task 7 did not reappear on this run. Pint clean (one
  auto-fix: import ordering + operator spacing in `CheckDeadlineEscalations.php`); `phpstan
  --memory-limit=1G`: `UserAbsence.php` and `CheckDeadlineEscalations.php` fully clean;
  `TaskForm.php`/`DeadlinesRelationManager.php`'s remaining findings are entirely the
  pre-existing `staticClassAccess.privateMethod`/generics baselines from tasks 4 and 7 — verified
  no new finding categories were introduced by diffing against each file's pre-task-8 phpstan
  output.
- [x] **Task 9 — Demo seeding**: built exactly as scoped. `createSkillLibrary()` — 10 fixed,
  realistic `Skill` rows across 4 categories (Compliance/Language/Technical/Soft Skills, e.g.
  "Contract Law", "Public Procurement Law", "German (Native)") rather than Faker-generated names,
  since `Skill.name` is meant to read as a real capability, not noise — created once up front
  alongside the M7/M10 libraries. `assignSkillsToUsers()` gives every seeded user 2-4 random
  skills (`Collection::random($n)` without replacement, so no duplicate-pivot risk) each with a
  random `SkillProficiency`; plain `BelongsToMany::attach()` is safe here since `user_skills` has
  no own uuid `id` column (task 1's composite-PK fix), unlike `task_participants`.

  `createTask()`'s `Task::factory()->create()` call gained
  `'functional_role' => fake()->boolean(70) ? fake()->randomElement(TeamRole::cases()) : null`
  — matches the milestone's "majority tagged, remainder null" requirement in one line, no new
  method needed.

  `createAbsenceLibrary()` runs once at the very end of `run()` (after every tender/task exists,
  since it needs a real task to overlap) — picks 3 random users, gives each a
  `HOLIDAY`/`SICKNESS` `UserAbsence` in a `-2 weeks`..`+3 weeks` window, the first two with a
  shared `cover_user_id` (a 4th random user excluded from the absentee set) and the third with
  none, to demo both the covered and uncovered cases side by side. Then finds any task with a
  future `due_date` (`Task::whereNotNull('due_date')->where('due_date', '>=', now())
  ->inRandomOrder()->first()`) and creates one more absence directly straddling that date
  (`due_date ± 1 day`) for the task's owner, with the same cover — this is the row that actually
  demonstrates `TaskForm`'s warning and `CheckDeadlineEscalations`' cover-notification once that
  task goes overdue.

  Verified via a throwaway `tests/Feature/Database/Seeders/ThrowawayDemoDataSeederTest.php`
  (written, run twice to check stability against Faker's randomness, then deleted — never
  committed, per [[database-seeders]]'s standing pattern): seeds via `DatabaseSeeder` (the real
  entry point, not calling `DemoDataSeeder` directly, so the `local`/`testing`-env gate and
  seeder ordering are exercised too), then asserts `Skill::count()` is 8-10, at least one user has
  2-4 skills attached, at least one task has a `functional_role` and at least one doesn't,
  `UserAbsence::count() >= 3` with both a covered and an uncovered row present, and — the
  milestone's key acceptance point — at least one `UserAbsence` overlaps an owned task's
  `due_date` (checked by scanning `UserAbsence::all()` for one whose owner has a task with
  `due_date` between `starts_at` and `ends_at`). Passed cleanly both runs. Pint clean; `phpstan
  --memory-limit=1G database/seeders/DemoDataSeeder.php`: 21 findings, identical in count and
  line numbers to the pre-task-9 baseline (confirmed via `git stash`/`git stash pop` diffing the
  full output) — every finding sits in code this task didn't touch; the 3 new methods and the
  one-line `createTask()` addition introduce zero new findings.
- [x] **Task 10 — Docs**: new `docs/16-people-teams-cover.md`, general/public audience
  (confirmed with the user, matching pages 14/15's standing precedent) with HTML-comment
  screenshot placeholders (user confirmed no screenshots ready yet, per [[docs]]'s convention —
  fixed a first-draft mistake where real `![...](screenshots/...)` tags pointing at
  not-yet-existing files were written instead of placeholders). Covers the skills matrix,
  employee profile (self-view needs no right, another user's needs "view employee statistics"),
  the workload indicator, `TeamPerformance`'s department/bottleneck/rankings breakdowns, the
  performance score's six weighted inputs with win rate explicitly called out as separate, and
  absences & cover including the non-blocking due-date warning and cover-notification.

  Cross-linked from `05-tasks.md` (workload indicator and due-date warning added directly into
  the existing Assignment section, new "Where to go next" entry) and `08-administration.md` (new
  Skills reference-data row, new "Where to go next" entry) as scoped. Also updated
  `03-managing-tenders.md` beyond the original scope, per [[docs]]'s sync rule ("when app code
  changes in an area a docs page already covers, update that page too, in the same session") —
  its existing "Deadlines, the calendar & escalation" section documented the tender calendar and
  the escalation recipient list before task 7/8 changed both (absences now appear on that same
  calendar; escalation now also notifies an absent recipient's cover), so leaving it as-is would
  have left it actively wrong, not just incomplete. Added a one-line deadline-warning mention
  next to the existing bid-validity paragraph, a cover-notification sentence in "Automatic
  escalation", an absences mention in "The tender calendar", and a new "Where to go next" entry,
  all cross-linking back to the new page's anchored subsections. All three touched/added pages
  re-stamped `_Last updated: 03/09/2026_`. [[docs]]'s own planned-sequence tracker updated with
  the new page 16 entry.
