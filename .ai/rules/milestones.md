---
glob: "**/*"
title: Milestone scope tracker
---

Full milestone specs live in [idea.md](../../idea.md) (M1–M13). This file just tracks where
the build currently stands so an assistant knows what's in scope right now vs. not-yet-built
vs. explicitly deferred.

**M1 — Foundation is complete.** **M2 — Team & Tasks** is now in progress, started at the
user's explicit request. Team assignment, the core Task feature, attachments, comments,
cross-task dependencies, and the notification centre foundation (below) are done; the
final-submission dependency gate is not started — don't begin it without the user asking
explicitly (it also needs an action that doesn't exist yet — final submission isn't built until
M5/M8).

Progress within M2:
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

**M1 acceptance points (idea.md) are now all met** — every checklist item above is checked.
M2 is in progress (team assignment, tasks, comments, cross-task dependencies, and the
notification centre foundation done; the final-submission dependency gate not started) — don't
build ahead into remaining M2 scope, or into M3+, without the user asking for it explicitly.

Update the checklist above as work lands, and flip the milestone line when M2's acceptance
points (idea.md) are all met. Don't build ahead into a later milestone's scope without the
user asking for it explicitly — e.g. don't wire up the M5 calculation engine while M2 is still
in progress, even if it seems convenient.

M13 (import connectors, AI-assisted extraction) is explicitly deferred — don't build it, but
per idea.md's architectural note, don't make first-class structured data choices in earlier
milestones (documents, deadlines/terms, certificates) that would make it harder to bolt on
later.
