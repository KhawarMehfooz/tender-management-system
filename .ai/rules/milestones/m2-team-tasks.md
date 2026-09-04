# M2 — Team & Tasks

Full spec: [idea.md](../../../idea.md)'s M2 section. Index: [../milestones.md](../milestones.md).

**M2 — Team & Tasks is complete** — team assignment, the core Task feature, attachments,
comments, cross-task dependencies, the final-submission dependency gate, the notification
centre foundation, and the User administration panel (below) are all done.

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

**M1 and M2 acceptance points (idea.md) are now all met** — every checklist item above is
checked. Don't build ahead into M3+ scope without the user asking for it explicitly — e.g.
don't wire up the M3 escalation levels or the M5 calculation engine just because it seems
convenient.
