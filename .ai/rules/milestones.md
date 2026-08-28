---
glob: "**/*"
title: Milestone scope tracker
---

Full milestone specs live in [idea.md](../../idea.md) (M1–M13). This file just tracks where
the build currently stands so an assistant knows what's in scope right now vs. not-yet-built
vs. explicitly deferred.

**M1 — Foundation is complete.** **M2 — Team & Tasks is now complete** — team assignment, the
core Task feature, attachments, comments, cross-task dependencies, the final-submission
dependency gate, the notification centre foundation, and the User administration panel
(below) are all done. M2's acceptance points (idea.md) are now met. Don't build ahead into
M3+ without the user asking for it explicitly.

**Planned next steps beyond M2 (recorded so a future session doesn't need to re-derive this
from chat history):**
- [x] Realistic-data seeder: `Database\Seeders\DemoDataSeeder`, called from `DatabaseSeeder`
  only under `app()->environment(['local', 'testing'])` (never production — every demo
  account uses the well-known password "password", confirmed explicitly with the user).
  9 roles × 3 users each (27 total): one user per non-super-admin role has a known-credential
  demo account (`{role-value}@example.com` / `password`, e.g. `team-lead@example.com`) plus
  two realistic `UserFactory` users; super admin reuses the existing `admin@example.com`.
  Management roles (super-admin/department-head/team-lead — mirrors
  `TenderForm::canManageTeam()`'s role set) get `service_category_id = null`; the other 6
  roles are round-robin assigned across the 3 seeded categories. Department-head/team-lead
  demo users get all 5 `Right`s, the calculation demo user gets `see-prices`, for rights-gating
  demos. All 12 `TenderStatus::cases()` get exactly 3 tenders each (36 total), reached by
  walking `Tender::changeStatusTo()` through `ACTIVE_PHASES` in order (never setting `status`
  directly) so the status-change audit trail is real, with the walked distance varied by
  variant index for realistic-looking history; each gets a 3-5 person team, 3-5 tasks
  (checklist items, comments, 1-2 file-backed attachments each, task-status walked the same
  way, a first-two-tasks dependency chain), some archived/invalidated tenders and some
  overdue tasks for screenshot variety. Hit and worked around three pre-existing traps along
  the way (all recorded as rules, not fixed as app-code changes since out of scope for a
  seeder task): `DatabaseSeeder`'s `WithoutModelEvents` muting `Tender`'s `internal_id`-
  generating `creating` hook (fixed *for this seeder* via `Model::setEventDispatcher()` at the
  top of `DemoDataSeeder::run()` — see [[database-seeders]]); `Task.status`'s DB-level default
  not reflecting on a freshly-`create()`d in-memory model, needed explicit
  `'status' => TaskStatus::OPEN` before calling `changeStatusTo()` (see
  [[factories-seeders]]); and the known `task_participants.id` uuid-pivot bug from
  [[migrations]], routed around with a direct `DB::table('task_participants')->insert()`
  instead of `attach()`/`sync()`. Verified via an actual `migrate:fresh --seed` run (36
  tenders across all 12 statuses, 139 tasks, 109 team members, 295 comments, 216 attachments,
  410 checklist items, 5 archived, 2 invalidated, 18 overdue) plus the full test suite (176
  passed). Later revised once the final-submission task gate landed (below) — task creation
  now happens before the tender's status is walked forward, and every task is forced to `done`
  for the four statuses only reachable via `SUBMISSION` (`submission`/`follow-up`/`won`/
  `lost`), so seeded history never puts a tender past `submission` with an open task. Re-
  verified via `migrate:fresh --seed` (36 tenders, 135 tasks, 9 overdue — down from 18 since
  the 12 submission+ tenders' tasks can no longer be overdue-and-open) plus the full suite
  (187 passed).
- Product documentation for potential customers: English, user-story style ("as a team
  lead, I..."), no architecture content — the user will add screenshots themselves once the
  seeded data exists to screenshot.

Progress within M2:
- [x] Final-submission dependency gate: `Tender::changeStatusTo()` throws
  `TenderTasksNotCompleteException` when transitioning to `TenderStatus::SUBMISSION` while any
  of the tender's tasks aren't `done` (`Tender::tasksComplete()`) — since `SUBMISSION` is only
  reachable from `QUALITY` in the transition map, this is effectively a quality→submission
  gate. Deliberately built against what M2 already has (Task + Tender status) rather than
  M5's not-yet-built 6-step approval chain (calculation/concept/evidence/review/management
  sign-off) — a design choice confirmed explicitly with the user via two questions (gate the
  status transition vs. a dedicated task vs. both; all tasks vs. a subset) before coding, per
  the milestone note this replaced. Vacuously true (transition allowed) when the tender has no
  tasks yet, mirroring `Task::dependenciesComplete()`'s same convention. UI belt-and-braces:
  `TendersTable`'s changeStatus action Select rejects the `SUBMISSION` option via `->reject()`
  when `!$record->tasksComplete()`, the same pattern `TasksTable::changeStatusAction()` uses
  for `DONE`/`dependenciesComplete()`. See [[tenders]] for the full rule — including that this
  may need revisiting once M5's approval chain actually exists. Tests: `TenderTest.php`'s
  "final submission task gate" group (model-level: blocks/allows, no-tasks vacuous case, no
  audit entry on rejection) and `TenderResourceTest.php`'s "status change action" group
  (table-level: rejected/allowed via the Select's offered options). Docs:
  `docs/03-managing-tenders.md` gained a "Final submission is gated on tasks" subsection.
- [x] User administration panel: `UserResource` (list/create/edit only — no delete path, since
  every FK referencing users, e.g. task owner/creator/reviewer/attachment-uploader/
  comment-author, is `restrictOnDelete`, so a real delete would just fail once a user has any
  history; mirrors the `canDelete()`/`canDeleteAny() => false` pattern from [[resources]]).
  Access is restricted to super admins only — the user's explicit pick over the two broader
  options (department-head/team-lead inclusion) idea.md's M2 "User administration" subsection
  had left as an open "TBD". No `App\Policies` class exists anywhere in this codebase yet, so
  gating is done the same way as `RolesAndPermissions`' page-level `canAccess()`: static
  `UserResource::canViewAny()`/`canCreate()`/`canEdit()` overrides checking
  `hasRole(RoleName::SUPER_ADMIN)` directly (Filament's `HasAuthorization` trait falls back to
  allow-all when no policy exists and strict mode is off, so every other resource in this app
  is currently open by default — `UserResource` is the first to need resource-level role
  restriction). `CanAuthorizeResourceAccess` (mounted on every resource page) means this alone
  blocks List/Create/Edit server-side, not just navigation. Form: `name`/`email`/`password`
  (create-required, blank-on-edit-preserves-current via `dehydrated(fn (?string $state) =>
  filled($state))`, hashed automatically by `User`'s existing `'password' => 'hashed'` cast) in
  an "Account" section; `role` (single-value `Select` over `RoleName::cases()`),
  `service_category_id` (nullable `serviceCategory` relationship `Select`, null = management/
  all-categories per [[scopes-models]]), and `rights` (`CheckboxList` over `Right::cases()`) in
  an "Access" section. `role`/`rights` aren't `users` columns — they're Spatie role/permission
  pivots, so both are stripped from `$data` in `mutateFormDataBeforeCreate`/`BeforeSave` and
  instead applied in `afterCreate()`/`afterSave()` via `assignRole()`/`givePermissionTo()`
  (create) or `syncRoles()`/`syncPermissions()` (edit, so a removed role/right is actually
  revoked, not just left alongside a newly added one); `EditUser::mutateFormDataBeforeFill()`
  hydrates the transient `role`/`rights` fields from `$record->roles->first()?->name` /
  `getDirectPermissions()->pluck('name')` since they have no real form-state backing.
  `rights` here means the user's own *direct* Spatie permissions specifically — additive to
  whatever their role already grants via `RolesAndPermissions`, matching idea.md M1's "a user
  without the matching role can still hold a right if explicitly granted" rule in
  [[permissions]]. Tests in `UserResourceTest`'s "access"/"creation"/"editing" groups cover:
  super admin can reach the list, a non-super-admin is rejected server-side on all three pages
  (not just hidden from nav), no delete action exists, create sets role+category+direct rights
  and hashes the password, edit syncs role/category/rights (including revoking a removed one)
  and preserves the existing password when the field is left blank, and a submitted new
  password does change it. Rights and role also use `Toggle` switches (with
  `onIcon(Heroicon::Check)`/`offIcon(Heroicon::XMark)`, matching `RolesAndPermissions`' table
  toggles) rather than a `CheckboxList`/`ToggleButtons` array field — one `Toggle` per
  `Right::value` in a nested "Rights" section, so `UserForm::selectedRights()` reads them back
  by key instead of a single array field. Both form sections stack fields in one column
  (`Section` without `->columns()`), not the 2-column layout used elsewhere. Guards against
  locking the system out of user administration entirely: `EditUser::beforeSave()` blocks (via
  `Filament\Support\Exceptions\Halt` + a danger `Notification`) a save that would change the
  `role` away from super admin on a record that currently holds it when
  `User::role(RoleName::SUPER_ADMIN->value)->count() <= 1` — i.e. the last super admin in the
  system, whether they're demoting themselves or another super admin is doing it. `UserForm`'s
  role `Select` also shows a `helperText` warning on the only remaining super admin's record as
  a UI hint (`UserForm::isOnlyRemainingSuperAdmin()`) — the actual enforcement is the
  server-side `beforeSave()` check, never the hint alone. Tests in `UserResourceTest`'s "last
  super admin safeguard" group cover: the only super admin can't demote themselves (role
  unchanged, notified), and a super admin *can* be demoted when another one still exists.
- [x] In-app notification centre (foundation): Filament's built-in database-notifications panel
  feature (`->databaseNotifications()` on `AdminPanelProvider`) backs the bell/slide-over UI —
  `User` already had `Notifiable`+`HasUuids` wired, so this was mostly config plus fixing the
  published `notifications` migration's default `morphs('notifiable')` to `uuidMorphs()` to match
  this app's all-UUID domain PKs (same trap as spatie/laravel-permission's bigint-default
  migration, see [[models]]). A new `App\Enums\NotificationType` (task-status-changed,
  task-comment-added, task-attachment-added) plus `notification_preferences` table/model
  (`user_id`+`notification_type` unique, `email_enabled` default `true`) implement "email
  optional per user/notification type" — in-app is always on; email is opt-out, not opt-in
  (`User::wantsEmailFor()` defaults `true` when no preference row exists yet). Three Laravel
  `Notification` classes (`app/Notifications/`, all `ShouldQueue` — the `tms-queue` container
  already runs `queue:work` against `QUEUE_CONNECTION=database`) feed both channels: `toDatabase()`
  builds the message via `Filament\Notifications\Notification::make()->getDatabaseMessage()` (the
  documented pattern for a traditional Notification class populating Filament's database-
  notifications UI), `toMail()` a plain `MailMessage`, `via()` gating `mail` on
  `wantsEmailFor()`. `Task::isLinkedTo()` was refactored into a reusable `linkedUsers(): Collection`
  (owner+creator+reviewer+participants, deduplicated) — the same "who's assigned to this task" set
  proven correct by the attachment/comment visibility tests — now doubling as the recipient list
  for all three notifications, always excluding the acting user (via `->reject()`). Wired at the
  three trigger points that already had a single clean hook: `Task::changeStatusTo()` (dispatches
  after its existing DB transaction commits), and `CreateAction::after()` on both
  `CommentsRelationManager` and `AttachmentsRelationManager`. Task **assignment** notifications are
  explicitly deferred — owner/reviewer/participants are set across scattered Filament mutate hooks
  (`TaskForm`/`CreateTask`/`EditTask`), not one place, and this codebase has no Observer pattern
  anywhere to introduce as a clean hook; wiring it is a follow-up once one exists, not part of this
  foundation. A new `App\Filament\Pages\NotificationPreferences` page (mirrors
  `RolesAndPermissions`' `HasTable`/`InteractsWithTable`+`ToggleColumn` pattern, not a bespoke
  form) lets every authenticated user manage their own email toggles per type — no role gate,
  unlike `RolesAndPermissions`' super-admin-only page — `firstOrCreate`-ing a default
  `email_enabled = true` row per `NotificationType` case on `mount()` so the table always shows
  every type even before the user has touched anything. Tests: `TaskTest.php`'s "notifications"
  group (recipients exclude the actor, mail suppressed by an opted-out preference),
  `TaskResourceTest.php`'s "notifications" group (comment/attachment creation notifies other
  linked users but not the author/uploader), and `NotificationPreferencesTest.php` (default rows
  created, a toggle persists, one user's preferences don't leak into another's) — all using
  `Notification::fake()`/`assertSentTo`/`assertNotSentTo`.
- [x] Cross-task dependencies: self-referencing `task_dependencies` pivot (composite PK on
  `task_id`+`depends_on_task_id`, both `cascadeOnDelete` — no own uuid `id` column, sidestepping
  the `task_participants` pivot-uuid bug in [[migrations]] entirely) backing
  `Task::dependencies()`/`dependents()`. A task cannot be marked `done` while any dependency
  isn't `done` yet: `Task::changeStatusTo()` throws `TaskDependenciesNotCompleteException`, and
  `TasksTable::changeStatusAction()` also filters `done` out of the offered next-statuses when
  `!$record->dependenciesComplete()`, mirroring the existing "hide the invalid choice, then
  enforce it again server-side" pattern. Dependencies are scoped to same-tender tasks only, and
  cycles/self-dependency are prevented not via a validation rule but by excluding
  `Task::transitiveDependentIds()` (BFS over `dependents()`) plus the record's own id from the
  `dependencies` Select field's `relationship(modifyQueryUsing: ...)` query on `TaskForm` — see
  [[tasks]] for the full pattern, the `TaskForm::configure()` `$tenderId` param needed for the
  tender-scoped relation-manager context, and the `dependencies.0`-not-`dependencies` form-error
  key trap when testing rejected selections. Tests in `TaskTest.php`'s "dependencies" group
  (model-level: blocking, `dependenciesComplete()`, `transitiveDependentIds()`) and
  `TaskResourceTest.php`'s "dependencies" group (same-tender scoping, self/cycle rejection,
  status-change gating) cover it. Not yet built: nothing surfaces a task's dependents (what it
  blocks) in the UI, only its dependencies — deferred as out of scope until asked for.
- [x] Task attachments: `TaskAttachment` model/migration (`task_id` required FK
  `cascadeOnDelete`; `uploaded_by` required FK `restrictOnDelete` to `users`; `file_path`,
  `original_filename`, `mime_type`, `size` in bytes). Files live on the private `local` disk
  (`storage/app/private`) under `task-attachments/`, never the `public` disk — attachments can
  carry price/evidence-bearing documents. UI: an `AttachmentsRelationManager` on
  `TaskResource` (list/upload/delete only, no dedicated resource) using a single
  `FileUpload::make('file')` create form with `->preserveFilenames()` (so `file_path`'s
  basename doubles as `original_filename`, no separate client-supplied filename field to
  trust) and `->preventFilePathTampering()` (blocks a smuggled arbitrary existing-file path
  standing in for a fresh upload, since create forms have no record to diff against — see the
  Filament file-upload docs' "Authorizing existing file paths" section).
  `mutateFormDataUsing()` derives `mime_type`/`size` server-side via `Storage::mimeType()`/
  `size()` rather than trusting client-reported values, and stamps `uploaded_by` from
  `auth()->id()`. Download is a dedicated authenticated route
  (`task-attachments.download` → `TaskAttachmentDownloadController`) that re-runs
  `Task::query()->findOrFail()` on the attachment's `task_id` so `TaskTenderCategoryScope`
  gates access the same "404, not hidden-but-reachable" way as task/tender viewing (see
  [[scopes-models]]) — streamed via `Storage::download()`, not a public/signed URL. Permission
  model (a judgment call the user picked explicitly, not derived from idea.md): upload is
  open to anyone "linked" to the task (owner/creator/reviewer/participant, via new
  `Task::isLinkedTo(User)`) or `TaskForm::canManageTask()`'s management set — broader than
  `canManageTask()` alone, since attachments are evidence the assignees themselves produce;
  delete is uploader-or-manager. Tests in `TaskResourceTest`'s "attachments"/"attachment
  download" groups cover upload visibility (linked user, manager, unrelated user hidden),
  delete visibility (own upload, manager, a different linked user hidden), and download
  category-scoping (200 in-category, 404 out-of-category) — using `EditTask` as the relation
  manager's `pageClass` for the mutating assertions, since Filament v4's read-only-relation-
  managers-on-ViewRecord-pages default (the same one noted on `TasksRelationManager` above)
  would otherwise hide every action regardless of the permission logic being tested.
- [x] Task comments: `TaskComment` model/migration (`task_id` required FK `cascadeOnDelete`;
  `user_id` required FK `restrictOnDelete` to `users`; `body` text). Immutable — no edit path,
  delete only. UI: a `CommentsRelationManager` on `TaskResource` (list/create/delete) using a
  `Textarea::make('body')` modal create form with `user_id` stamped from `auth()->id()` in
  `mutateDataUsing()`. Table shows author name, body (truncated), and created_at, sorted
  newest-first. Delete visible only to comment author or task managers. Permission model
  mirrors attachments: create is open to anyone "linked" to the task or a task manager; delete
  is author-or-manager. ViewTask page shows a read-only comments timeline (Blade partial
  `filament.infolists.task-comments-timeline`) gated by the same `isLinkedTo()`/`canManageTask()`
  check. `TasksTable` gained `addCommentAction()` and `addAttachmentAction()` — modal row
  actions on every task table (standalone `TaskResource` list + `TasksRelationManager` on
  tenders) so users can add comments and attachments directly from the task list without
  navigating to the edit page. Both actions use the same `isLinkedTo()`/`canManageTask()`
  gating and server-side enforcement. Tests in `TaskResourceTest`'s "comments"/"table add
  comment action"/"table add attachment action"/"tasks relation manager table actions" groups
  cover: create visibility (linked user, manager, unrelated user hidden), create by linked
  owner, create by manager, delete visibility (own comment, different linked user hidden,
  manager visible), table-level add comment (linked owner creates, unrelated hidden, user_id
  stripped), table-level add attachment (linked owner creates, unrelated hidden), and relation
  manager action visibility — using `EditTask` as the page class for relation-manager
  assertions.
- [x] Tasks (core slice — dependencies/final-submission-gate explicitly deferred): `Task` model/migration (`tender_id` required FK `cascadeOnDelete`; `owner_id`/
  `creator_id` required, `reviewer_id` nullable, all `restrictOnDelete` to `users`; `priority`
  → `App\Enums\TaskPriority` low/medium/high/urgent; `status` → `App\Enums\TaskStatus`
  open/in-progress/waiting-on-another-task/in-review/correction-required/done, forward-with-
  review-loop transition map mirroring `TenderStatus`, enforced by `Task::changeStatusTo()`
  which also stamps `completion_date` on reaching `done`). `TaskChecklistItem` (`hasMany`,
  `Repeater::make('checklistItems')->relationship()`) and `task_participants` (plain
  `belongsToMany` — no functional-role dimension, unlike tender team members) round out the
  core fields from idea.md's field list. `task_status_changes` + `TaskStatusChange` mirror
  `tender_status_changes`/`TenderStatusChange` byte-for-byte as the audit trail. "Overdue" is
  deliberately **not** a stored status — `Task::isOverdue()` computes
  `due_date->isPast() && status !== DONE`, same "separate axis, no background job needed"
  reasoning as Tender's archived/invalid fields (see [[resources-tenders]]).
  Authorization reuses `TenderForm::canManageTeam()`'s exact role set (team lead/department
  head/super admin) via a new `TaskForm::canManageTask()`, gating owner/reviewer/participants
  the same disabled-not-hidden + belt-and-braces-mutate-hook pattern as Tender's team step;
  status transitions are open to that same group. `TaskResource` (list/create/edit/view, no
  hard-delete path — nothing in idea.md calls for one the way Tender's junk-entry escape hatch
  exists) plus a `TasksRelationManager` on `TenderResource` for browsing/managing a tender's
  tasks in place. Category scoping needed a new `App\Models\Scopes\TaskTenderCategoryScope`
  (Task has no `service_category_id` of its own; relation-manager access inherits scoping for
  free via the already-scoped parent `Tender`, but the standalone `TaskResource`'s direct
  `Task::query()` needed its own scope re-deriving the restriction via `whereRelation('tender',
  'service_category_id', ...)` — see [[scopes-models]]). Tests in `TaskTest.php` (status
  chain, overdue, cascade delete, lookup delete protection) and
  `Filament/Resources/TaskResourceTest.php` (creation, deletion, status-change action,
  assignment gating, checklist, view page, category scoping, relation manager — including that
  Filament v4's read-only-relation-managers-on-ViewRecord-pages default means the relation
  manager is browse-only on `ViewTender` and live on `EditTender`, left as the framework
  default rather than overridden).
- [x] Team assignment: one required tender owner (`Tender.owner_id`, NOT NULL FK to `users`,
  `restrictOnDelete`) plus any number of team members in functional roles
  (`App\Enums\TeamRole`: calculation/concept/evidence-documents/quality-control/
  final-approval) via a new `tender_team_members` table (`TenderTeamMember` model, HasMany
  from `Tender`, unique on tender_id+user_id+functional_role — many users can share a
  functional role, one user can hold several roles via multiple rows). UI: a 6th
  `TenderForm` wizard step ("Team") with an owner `Select` and a
  `Repeater::make('teamMembers')->relationship()`. Gated by `TenderForm::canManageTeam()`
  (team lead/department head/super admin via `hasAnyRole`) — everyone else sees the step
  read-only (`->disabled()`, never hidden), with server-side belt-and-braces enforcement in
  `CreateTender`/`EditTender`'s mutate hooks (owner forced to the creator on create, to the
  existing owner on edit, for unauthorized actors) and `->dehydrated()` gating on the
  Repeater so an unauthorized submission never reaches the relationship-save step at all. See
  [[resources-tenders]] for the full pattern and the Get()-fragility trap it avoids in the
  owner/team-member option scoping. `TenderInfolist` gained a matching read-only "Team"
  section. Tests in `TenderResourceTest`'s "team assignment" group cover: field
  enabled/disabled by role, default-to-creator on create, smuggled-value stripping on create
  and edit, and a team lead successfully setting owner + adding a team member.

Progress within M1:
- [x] Roles & rights: `spatie/laravel-permission` (UUID-adapted, see [[models]]/[[seeders]]),
  `App\Enums\RoleName` (9 roles) / `App\Enums\Right` (5 rights), `RolesAndPermissionsSeeder`,
  `Gate::before` super-admin bypass in `AppServiceProvider`.
- [x] Service categories: `ServiceCategory` model/migration/Filament resource, admin-managed,
  no hard-delete (deactivate via `active` flag instead — see [[resources]]), seeded via
  `ServiceCategorySeeder`.
- [x] Lookup tables: `Source`, `CpvCode`, `NutsCode` models/migrations/Filament resources, all
  admin-managed (add/edit/deactivate via `active` flag, no hard-delete, same pattern as
  [[resources]]'s ServiceCategory). `NutsCode` is self-referencing (`parent_id`) to model the
  country > state > region > district hierarchy. Each has a starter dev seed (real German
  procurement portal names for Source; a small CPV/NUTS subset) plus `import:cpv-codes` /
  `import:nuts-codes` Artisan commands that upsert from a CSV placed at
  `database/data/{cpv,nuts}_codes.csv` — seeders auto-detect the file and use it instead of the
  dev subset. Full official lists are now imported: 9,454 CPV codes (SIMAP/TED `cpv_2008` XLS,
  exported to CSV) and 1,971 NUTS codes (Eurostat/GISCO `NUTS_AT_2024.csv` attribute table,
  using its `NAME_LATN` column for Latin-script labels since `NUTS_NAME` keeps native scripts
  for EL/BG/etc; `level`/`parent_code` derived from `NUTS_ID` length/prefix, not present in the
  source file). Both CSVs live at `database/data/` — re-running `migrate:fresh --seed` picks
  them up automatically.
- [x] Tender model: `Tender` model/migration/factory with ~27 master fields (see
  `app/Models/Tender.php`), plus its two remaining lookup dependencies `Sector` and
  `ProcurementProcedure` (same admin-managed pattern as [[resources]]'s Source, each seeded
  with an explicit "Unknown" row). `ServiceCategory` gained a `code` column (3-4 uppercase
  letters, nullable) used as the tender-ID prefix. Internal ID scheme:
  `{ServiceCategory.code}-{year}-{sequence}` (e.g. `SEC-2026-0001`), sequence resets per
  category per year, generated via a `creating` model event backed by a DB-locked
  `tender_number_sequences` counter table (race-safe, survives future hard-deletes).
  `estimated_contract_volume_unknown` is a boolean companion flag (not a null/sentinel value)
  per the explicit-unknown rule in [[data-integrity]]. `service_category_id`/`sector_id`/
  `procurement_procedure_id`/`source_id` use `restrictOnDelete` (required stats-bearing
  dimensions); `nuts_code_id`/`cpv_code_id` stay nullable+`nullOnDelete` (optional
  classification). `TenderResource` built as a 5-step Filament Wizard (basic info; location &
  classification; dates & deadlines; contract terms; source & notes) per [[resources]]'s
  size/grouping rule — List/Create/Edit only, no delete path (tenders are never hard-deleted,
  see [[data-integrity]]).
- [x] Lifecycle state machine: `TenderStatus::allowedTransitions()`/`canTransitionTo()` encode
  the transition map — the 7 active phases (intake→review→decision→processing→quality→
  submission→follow-up) only move forward one step at a time (no skipping, no going back);
  `cancelled`/`not-evaluated`/`excluded` are reachable from any active phase; `won`/`lost` only
  from `submission`/`follow-up` (a bid must exist first); terminal statuses have no further
  transitions. `Tender::changeStatusTo()` enforces the map (throws
  `InvalidTenderStatusTransitionException` otherwise) and, in the same DB transaction, writes
  an audit row to `tender_status_changes` (`TenderStatusChange` model: from/to status, actor
  `changed_by`, optional `reason`, `changed_at` — immutable, no `updated_at`). UI: per
  [[resources]]'s decision, status is no longer editable via the create/edit wizard (removed
  from `TenderForm`; DB still defaults new tenders to `intake`) — instead a dedicated
  "Change status" table row action (`TendersTable`) offers only the record's currently-valid
  next statuses via a modal (status select + optional reason), hidden once a tender reaches a
  terminal status. A `TenderInfolist`/`ViewTender` page now exists (route `view`, reachable via
  a `ViewAction` on the table and on `EditTender`'s header) showing an overview section and a
  "Status history" section (`RepeatableEntry` over the `statusChanges` relation: changed_at,
  from/to, actor, reason) — this is the audit log's only UI surface right now.
- [x] Field-level rights enforcement wired into the Tender resource: `estimated_contract_volume`
  (the only price-bearing field on `Tender` today — margins/competitor-data/employee-stats
  fields don't exist until M5/M10/M11, so `see-margins`/`see-competitor-data`/
  `view-employee-statistics` have nothing to gate yet on this resource) is gated behind the
  `see-prices` right (`App\Enums\Right`) in three places: `TenderForm` (both the amount field
  and its `_unknown` companion toggle hidden via `->visible()`), `TenderInfolist` (a new
  gated `TextEntry` on the Overview section, formatted as either the money value or "unknown"),
  and — since a Livewire request can smuggle a value into a hidden field's state — server-side
  stripping in `CreateTender::mutateFormDataBeforeCreate()` /
  `EditTender::mutateFormDataBeforeSave()` that unsets both keys whenever the acting user lacks
  the right, per [[permissions]]'s "never trust UI hiding alone" rule. `execute-final-submission`
  has no action to gate yet (final submission doesn't exist until M5/M8). Tests in
  `TenderResourceTest` cover: field hidden/visible on the form by right, smuggled value stripped
  on both create and edit, and the view-page entry hidden/visible by right.
- [x] Category-scoped views: `App\Models\Scopes\ServiceCategoryScope`, a global scope on
  `Tender` (registered in `Tender::booted()`), restricts every query to
  `auth()->user()->service_category_id` when it's set; a `null` category (the seeded super
  admin's state) means management-level and spans all categories — see [[scopes-models]] for
  the full rationale and why this is keyed off the nullable FK rather than role. Write-side
  enforced too: `TenderForm`'s category Select defaults to and disables on the user's own
  category when scoped, and `CreateTender`/`EditTender` force `service_category_id` back to it
  regardless of submitted value. A scoped user hitting a foreign tender's view/edit route gets
  a `ModelNotFoundException` (404), not a hidden-but-reachable page. Tests in
  `TenderResourceTest`'s "category-scoped views" group cover: management sees all, a scoped
  user sees only their own, foreign view/edit blocked, and create is pinned to the user's own
  category despite a different submitted value.
- [x] Archive/invalid field: `is_archived`/`archived_at`/`archived_by` and
  `invalidity_reason`/`invalidated_at`/`invalidated_by` columns on `tenders`, a separate axis
  from `TenderStatus` (a tender can be archived from any status, including a terminal one like
  `won`) — see [[resources-tenders]] for the full rationale. Deliberately excluded from
  `Tender`'s `#[Fillable(...)]` list; only writable via `Tender::archive()`/`unarchive()`/
  `markInvalid()`/`clearInvalidFlag()` (all using `forceFill()`). UI: `TendersTable` row actions
  (archive/unarchive toggle pair, flag-invalid modal requiring a reason / clear-invalid-flag),
  each pair visible only in its applicable state; `TenderInfolist` gained an "Archive & validity"
  section, visible only when relevant. Tests cover the model methods, mass-assignment
  resistance, and the table actions (visibility toggling, required reason validation).
- [x] Admin hard-delete of junk tenders: `Tender::hardDelete($actor, $reason)` writes a
  `TenderHardDeletion` snapshot (tender_id/internal_id/title/deleted_by/reason/deleted_at —
  `tender_id` has no FK, since the row it describes won't exist once the method returns) inside
  the same DB transaction before calling `$this->delete()`. UI: a `hardDelete` `TendersTable`
  row action, gated `->visible(fn () => auth()->user()?->hasRole(RoleName::SUPER_ADMIN) ?? false)`
  with a required-reason modal — a wholly separate custom Action from Filament's built-in
  `DeleteAction`, so `TenderResource::canDelete()`/`canDeleteAny()` staying `false` is unaffected
  (see [[resources]] on `canDelete()` not being auto-wired to actions) and every other user still
  has no delete path at all. Tests cover: hidden from non-super-admins, visible/functional for a
  super admin, row actually gone afterward, log captures who/when/why, reason required.

**M1 and M2 acceptance points (idea.md) are now all met** — every checklist item above is
checked. Don't build ahead into M3+ scope without the user asking for it explicitly — e.g.
don't wire up the M3 escalation levels or the M5 calculation engine just because it seems
convenient.

**M3 — Deadlines & Escalation is now in progress**, started 2026-08-28 at the user's explicit
request, building incrementally task-by-task rather than in one pass.

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

**M3 — Deadlines & Escalation is now complete.** All planned M3 tasks above are checked. Don't
build ahead into M4+ scope without the user asking for it explicitly.

M13 (import connectors, AI-assisted extraction) is explicitly deferred — don't build it, but
per idea.md's architectural note, don't make first-class structured data choices in earlier
milestones (documents, deadlines/terms, certificates) that would make it harder to bolt on
later.
