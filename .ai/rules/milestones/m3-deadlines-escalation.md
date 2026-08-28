# M3 — Deadlines & Escalation

Full spec: [idea.md](../../../idea.md)'s M3 section. Index: [../milestones.md](../milestones.md).

**M3 — Deadlines & Escalation is complete.** Built incrementally task-by-task, started
2026-08-28.

Planned tasks for M3:
- [x] Dependency + enums: `guava/calendar` (v3.2.2) installed via composer, confirmed
  Filament v5-compatible (its `composer require` publishes assets and vendor config cleanly
  against `filament/filament` v5.7.6 already in this app). New `App\Enums\DeadlineType` (14
  cases — bidder-questions/site-visit/internal-calculation/concept/document-check/approval/
  quality-check/upload/submission/document-requests/presentation/negotiation/bid-validity/
  expected-decision, per idea.md's M3 list) and `App\Enums\EscalationLevel` (4 cases —
  assignee/team-lead/administrator/management, matching idea.md's 4 escalation levels in that
  order) both implement `HasLabel` only for now (no `color()`/transition logic yet — those are
  UI/scheduler concerns for later M3 tasks), backed by new `lang/en/deadline_types.php` /
  `lang/en/escalation_levels.php` flat kebab-case-key files, mirroring [[enums]]'s
  SCREAMING_SNAKE_CASE-case/kebab-case-value convention and `TeamRole`'s flat-lang-file
  pattern exactly. `EscalationLevel::level(): int` returns 1-4 for ordering comparisons the
  scheduler will need later. Full suite re-verified (188 passed) after the composer install.
- [x] `tender_deadlines` schema + model: new `TenderDeadline` (`tender_id` FK `cascadeOnDelete`,
  `type` → `App\Enums\DeadlineType`, `due_at` datetime, `escalation_level`/`last_escalated_at`
  — the table has no unique constraint on tender+type, so multiple rows per type are allowed,
  e.g. a rescheduled submission deadline or several document requests), indexed on `type` and
  `due_at` for the scheduler/calendar's future access patterns. `App\Models\Scopes\
  TenderDeadlineCategoryScope` mirrors `TaskTenderCategoryScope` byte-for-byte (`TenderDeadline`
  has no `service_category_id` of its own, inherits scoping from its parent `Tender` via
  `whereRelation`) — needed for the future standalone tender-calendar page's direct queries, the
  same reasoning as [[scopes-models]]'s "child models with no service_category_id of their own"
  rule. `Tender` gained `deadlines(): HasMany`, `submissionDeadline(): ?TenderDeadline` (latest
  by `due_at` when more than one `SUBMISSION` row exists, e.g. after a reschedule — matches
  idea.md's "submission deadline always visible" requirement with a single deterministic pick),
  and `syncBidValidityDeadline()` (upserts a single derived `BID_VALIDITY` row at `submission
  due_at + bid_validity_days` via `updateOrCreate(['type' => BID_VALIDITY], ...)`, deleting it
  once either input is unknown) — not yet called from anywhere, since nothing writes a
  `SUBMISSION` row until the next task wires the UI. `escalation_level`/`last_escalated_at`
  columns (both nullable, `EscalationLevel`-cast) added to `tender_deadlines` (levels 3-4,
  `SUBMISSION` row) and `tasks` (levels 1-2, task-overdue) via a separate migration — no
  separate audit table, and deliberately excluded from `Task`'s `#[Fillable(...)]` list since
  only the not-yet-built M3 scheduler will ever write them (forceFill-only, mirroring
  `Tender`'s archive-field pattern in [[resources-tenders]]).
  **Scope adjustment confirmed with the user:** the milestone note originally bundled "drop
  `Tender.submission_deadline`/`bidder_question_deadline`/`site_visit_date`" into this same
  task, but those 3 columns are still read directly by `TenderForm`/`TenderInfolist`/
  `TendersTable`/`TenderFactory`/`DemoDataSeeder` and asserted on by `TenderResourceTest` —
  dropping them here would have broken the app until the next task's UI rewiring lands. Deferred
  the actual `dropColumn` migration + all of that UI/factory/seeder/test rework to the next task
  ("Wire the wizard + existing tender UI"), which already covers exactly that ground. Tests:
  `TenderTest.php`'s new "deadlines" group (canonical-submission-deadline picking including the
  no-rows-yet case, BID_VALIDITY sync create/resync/removal, category scope on a direct
  `TenderDeadline::query()`). Full suite re-verified (194 passed, up from 188).
- [x] Wire the wizard + existing tender UI: dropped `Tender.submission_deadline`/
  `bidder_question_deadline`/`site_visit_date` (the deferred half of the previous task) via a
  new migration; `Tender`'s docblock/casts/`#[Fillable(...)]` list cleaned up to match.
  `TenderForm`'s "Dates & deadlines" step keeps the same 3 field names as transient form state
  (not bound to `Tender` columns) — `CreateTender`/`EditTender` strip them in
  `mutateFormDataBeforeCreate`/`BeforeSave` and write them into `tender_deadlines` via
  `Tender::upsertDeadline()` (new: create/update/delete-by-type, added alongside
  `latestDeadlineOfType()` which `submissionDeadline()` now delegates to) in
  `afterCreate()`/`afterSave()`, then call `syncBidValidityDeadline()` — mirrors `UserResource`'s
  `role`/rights transient-field pattern, including `EditTender::mutateFormDataBeforeFill()`
  hydrating the 3 fields back from the record's `tender_deadlines` rows for editing.
  **Non-obvious trap hit and fixed, recorded as [[resources-pages]]:** naively re-calling
  `$this->form->getState()` inside `afterCreate()`/`afterSave()` to read the transient deadline
  values (rather than capturing them earlier) silently wiped the `teamMembers` Repeater's
  already-saved rows on the same form — no exception, just missing `TenderTeamMember` rows.
  Fixed by capturing the 3 values on a private property inside
  `mutateFormDataBeforeCreate`/`BeforeSave` (before stripping them from `$data`) and reading
  that property in `afterCreate`/`afterSave` instead of calling `getState()` again. `TenderForm`
  itself needed no changes — same field names, still fully transient. `TenderInfolist`'s
  submission-deadline entry is now a computed countdown (`state()` closure reading
  `submissionDeadline()`, formatted `d.m.Y H:i (relative)`); `TendersTable`'s column is a
  computed `state()` column with a correlated-subquery `sortable(query: ...)` against
  `tender_deadlines` (ordering by the latest `SUBMISSION` row's `due_at`). `TenderFactory` drops
  the 3 fields and instead `afterCreating()`-hooks a default `SUBMISSION` deadline (preserving
  the old "every tender has a submission deadline" guarantee that existing tests/forms rely on);
  `DemoDataSeeder` overrides that default via `upsertDeadline()` for its wider realistic date
  range. Re-verified via `migrate:fresh --seed` (36 tenders, 36 tender_deadlines rows, all
  `SUBMISSION` type) and the full suite (194 passed, Pint clean).
- [x] `DeadlinesRelationManager` on `TenderResource` (form + table): a plain 2-field form
  (`type` Select over `DeadlineType::cases()`, `due_at` `DateTimePicker`) — matches
  `TenderDeadline`'s `#[Fillable(...)]` list exactly, since `escalation_level`/
  `last_escalated_at` are forceFill-only system fields (see the earlier schema task) and stay
  read-only table columns instead. `DeadlineType::BID_VALIDITY` is excluded from the type
  Select's options (`manageableTypes()`) since that row is derived and kept in sync
  automatically by `Tender::syncBidValidityDeadline()` on every tender save — a manually
  created/edited one would just be silently overwritten; it still displays read-only in the
  table once synced. Gated by `TenderForm::canManageTeam()` (team lead/department head/super
  admin) — the same role set that manages the tender's team, reused rather than introducing a
  new permission concept for tender-level scheduling. Unlike `TasksRelationManager`, create/
  edit/delete are plain header/record actions with no dedicated status-change or comment/
  attachment actions (deadlines have no lifecycle beyond escalation, which the not-yet-built
  scheduler owns). New `lang/en/tender_deadlines.php` (`fields.type`/`due_at`/
  `escalation_level`/`last_escalated_at`), following [[i18n]]'s semantic-key convention. Tests
  in `TenderResourceTest`'s "deadlines relation manager" group cover: tender-scoped listing,
  manage actions hidden for a non-manager, a team lead creating a deadline, `BID_VALIDITY`
  rejected as a submitted type, and a team lead deleting a deadline. Full suite re-verified
  (199 passed, up from 194), Pint clean.
- [x] Escalation notifications: 4 new `NotificationType` cases (`task-escalated-assignee`/
  `task-escalated-team-lead`/`task-escalated-administrator`/`tender-escalated-management`,
  named after `EscalationLevel`'s own case values for consistency) + 4 new Notification
  classes, mirroring `TaskStatusChangedNotification`'s dual-channel pattern (`ShouldQueue`,
  `via()` always includes `database` and gates `mail` on `User::wantsEmailFor()`, `toDatabase()`
  via `Filament\Notifications\Notification::make()->getDatabaseMessage()`) + lang keys in
  `lang/en/notifications.php`. Levels 1-3 (`TaskEscalatedToAssigneeNotification`/
  `ToTeamLeadNotification`/`ToAdministratorNotification`) take a single `Task $task` — matches
  idea.md's singular "a task" wording for those levels; recipients are the task owner, the
  task's `Tender.owner_id`, and every `RoleName::SUPER_ADMIN` user respectively (no distinct
  administrator role exists, so level 3 and level 4 both land on super admins). Level 4
  (`TenderEscalatedToManagementNotification`) deliberately takes `Tender $tender` +
  `int $openCriticalTaskCount` instead — idea.md phrases it as "critical items" (plural) still
  open tender-wide, not one task, so it doesn't fit the same single-task shape as the other
  three. "Critical" is `TaskPriority::URGENT` per the milestone note, though the notification
  classes themselves don't yet enforce that filter — that condition, along with the actual
  overdue/48h/24h-before-submission trigger logic, is the next task's job (the scheduler);
  this task only builds the dispatchable classes + recipient-shape decisions. Not yet wired to
  any trigger point. Tests: new `tests/Feature/EscalationNotificationsTest.php` (one describe
  block per class) dispatches each directly via `$user->notify(new X(...))` under
  `Notification::fake()` and asserts recipient + channel-gating (database always, mail
  suppressed by an opted-out `NotificationPreference`) — the same pattern
  `TaskTest.php`'s "notifications" group uses, adapted since there's no trigger action to call
  yet. Full suite re-verified (205 passed, up from 199), Pint clean.
- [x] Escalation scheduler: `App\Console\Commands\CheckDeadlineEscalations`
  (`tenders:check-deadline-escalations`, PHP-attribute `#[Signature]`/`#[Description]` per the
  `ImportCpvCodes`/`ImportNutsCodes` convention), scheduled hourly via `Schedule::command(...)`
  in `routes/console.php` — this app's first scheduled task; `tms-scheduler`'s existing
  `php artisan schedule:work` container picks it up with no further config. Escalation state
  is one-directional (highest level reached so far, never reset — no reset path exists yet):
  `Task.escalation_level` only ever holds `ASSIGNEE`/`TEAM_LEAD` (levels 1-2), and the
  *tender's canonical* `submissionDeadline()` row's `escalation_level` only ever holds
  `ADMINISTRATOR`/`MANAGEMENT` (levels 3-4) — each level fires its notification at most once
  per task/tender. `escalateOverdueTasks()`: queries open (`status != DONE`) tasks past
  `due_date`, notifies the owner (level 1, unconditional) then — once `due_date->diffInHours(now()) >= 24`
  — the tender's `owner_id` (level 2); both can fire in the same run if a task is already
  ≥24h overdue on first check (e.g. after downtime), which is treated as legitimate catch-up
  behavior, not a bug. `escalateSubmissionDeadlines()`: for every tender with a `SUBMISSION`
  deadline, skips if that canonical deadline is null/already past, then requires at least one
  open `TaskPriority::URGENT` task ("critical") — notifies every `RoleName::SUPER_ADMIN` user
  (level 3) once ≤48h remain, referencing the first critical task found, then (level 4) once
  ≤24h remain, via `TenderEscalatedToManagementNotification` with the open-critical-task count.
  Guards `User::role(RoleName::SUPER_ADMIN->value)` with a `Spatie\Permission\Models\Role::where('name', ...)->exists()`
  check first — `User::role()` throws `RoleDoesNotExist` outright when the role hasn't been
  seeded yet (hit in tests that don't seed `RolesAndPermissionsSeeder`, not just a theoretical
  fresh-install case). Both escalation paths write state via `forceFill()->save()`, matching
  `Task`/`TenderDeadline`'s `#[Fillable(...)]` exclusion of these columns. Tests in new
  `tests/Feature/Console/CheckDeadlineEscalationsTest.php` (10 cases, 2 describe blocks) cover:
  level-1-only vs level-1+2 (via a same-day vs yesterday `due_date`, working around `Task.due_date`
  being date-cast so date-only precision is all that's controllable), not-yet-overdue and
  done-task no-ops, second-run idempotency for both level 1 and level 3, level 3 vs level 4
  thresholds, no-critical-task no-op, and beyond-48h no-op. Full suite re-verified (215 passed,
  up from 205), Pint clean.
- [x] Tender calendar: standalone `Filament\Pages\Page` (`App\Filament\Pages\TenderCalendar`,
  top-level nav item, no role gate — read-only for everyone, category scoping does the real
  restricting) embedding a single `App\Filament\Widgets\TenderDeadlineCalendarWidget extends
  Guava\Calendar\Filament\CalendarWidget`, filtered by employee/tender/contracting authority/
  deadline type — the "department" filter from idea.md is explicitly skipped, since this app has
  no department concept distinct from `ServiceCategory` and the user chose not to assume that
  mapping. `TenderDeadline` implements `Guava\Calendar\Contracts\Eventable::toCalendarEvent()`
  (title `"{internal_id}: {type label}"`, a point event at `due_at`, `tender_id` in
  `extendedProps`); clicking an event redirects to the tender's `ViewTender` page
  (`onEventClick` override). Filtering reuses Filament's own Dashboard-widget-filter machinery
  rather than inventing a new mechanism: the page uses
  `Filament\Pages\Dashboard\Concerns\HasFiltersForm` (gives a `filters` state property, URL- and
  session-persisted) with a 4-field `filtersForm()` (`employee_id`/`tender_id` Selects populated
  from `User`/`Tender`, `contracting_authority` Select over distinct scoped values, `deadline_type`
  Select over `DeadlineType::class`), and the widget uses
  `Filament\Widgets\Concerns\InteractsWithPageFilters` (`#[Reactive] public ?array $pageFilters`,
  auto-populated by Filament's own `Page::getWidgetsSchemaComponents()` whenever
  `property_exists($this, 'filters')` on the page — no custom event wiring needed) plus a
  `updatedPageFilters()` hook calling the package's `refreshRecords()` to re-fetch events when a
  filter changes. `getEvents(FetchInfo $info)` queries `TenderDeadline::query()` scoped to
  `$info->start`/`$info->end` — category scoping is automatic via the existing
  `TenderDeadlineCategoryScope` global scope, no new scope needed. Employee filter matches tender
  owner OR team member (`whereHas('tender', ...->where('owner_id', ...)->orWhereHas('teamMembers',
  ...))`). Theme: `resources/css/filament/admin/theme.css` gained the package's `@import`/`@source`
  lines per its README (assets were already published to `public/{css,js}/guava` by the earlier
  composer-install task, and the package's service provider auto-registers its Alpine
  components/stylesheet — no `->plugin()` call needed in `AdminPanelProvider`). Tests: new
  `tests/Feature/Filament/Pages/TenderCalendarTest.php` — page loads for any authenticated user
  (no role gate), and the widget's `getEvents()` (invoked directly via `ReflectionMethod` on a
  bare `new TenderDeadlineCalendarWidget` with `pageFilters` set by hand, sidestepping full
  Livewire component hydration since the method only reads that one public property) is checked
  for date-range windowing, each of the 4 filters individually, and category scoping. Full suite
  re-verified (222 passed, up from 215 — one run hit an unrelated pre-existing order-dependent
  flake in `TenderResourceTest`, gone on rerun and in isolation), Pint clean, `npm run build`
  clean.
- [x] Wrap-up: new [[deadlines]] rule file consolidating the M3 model/scheduler/calendar/
  relation-manager decisions (linked from the index for the relevant `app/**` paths). Docs: a
  new "Deadlines, the calendar & escalation" section added to `docs/03-managing-tenders.md`
  (not a new numbered page — folded into the existing tender-lifecycle page per the user's
  explicit pick, since deadlines are tender-scoped) covering all remaining `DeadlineType` cases
  beyond the 3 wizard fields, the Deadlines relation-manager tab and its team-lead/department-
  head/super-admin manage gating, the derived bid-validity row, the tender calendar and its 4
  filters, and the escalation levels/thresholds/recipients end-to-end (the user's explicit pick
  over a lighter "skip escalation internals" option). Full suite re-verified (222 passed, up
  from 215 pre-calendar-task — the calendar task's own run already covered the jump to 222),
  Pint clean.

**M3 — Deadlines & Escalation is now complete.** All planned M3 tasks above are checked.
