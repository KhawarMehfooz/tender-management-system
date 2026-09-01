# M7 — References, Certificates, Concept Library

Full spec: [idea.md](../../../idea.md)'s M7 section. Index: [../milestones.md](../milestones.md).

**M7 — References, Certificates, Concept Library is now in progress**, started 2026-09-02.

Three design decisions were confirmed with the user before any code was written:
- **Global library + optional tender link.** idea.md frames these as company-wide assets that
  "feed into bids repeatedly" (M7) — distinct from the existing per-tender `REFERENCES`/
  `CONCEPTS` document *categories* ([[documents]], M4), which are per-bid evidence files, not
  the reusable master content. `Reference`, `Certificate`, and `ConceptBlock` are standalone
  top-level Filament resources (own nav group, not tender relation-manager-only records), each
  with a lightweight many-to-many pivot to `Tender` so a bid can record which ones were
  actually used — this is what makes "reusable across multiple tenders" (idea.md's acceptance
  point) demonstrable rather than theoretical.
- **Certificate expiry: new `Right::MANAGE_CERTIFICATES`, reminders at 90/30/7 days plus one
  more on actual expiry.** Certificates are called out in idea.md as a hard disqualification
  risk needing reliable handling, which justifies a dedicated right (mirrors M6's
  `MAKE_BID_DECISION` pattern) rather than reusing the blanket "every super-admin" approach
  [[deadlines]]'s `CheckDeadlineEscalations` uses for submission deadlines. Default grants:
  super-admin and department-head (admin/compliance-level, not team-lead — unlike M6's bid
  decision, this isn't a per-tender judgment call). The right gates the whole `Certificate`
  resource (view+manage), not just the write actions, since a top-level resource has no
  separate "informational tab" surface to leave open the way `BidDecisionRelationManager`
  could. Holders are the reminder notification's recipients.
- **Concept library: immutable version history, mirroring `TenderDocument`/
  `TenderDocumentVersion` ([[documents]]) exactly.** `ConceptBlock` (one row per reusable text
  block — category, title) + `ConceptBlockVersion` (one row per edit — version_number, content,
  created_by, no `updated_at`, immutable by construction). `ConceptBlock::currentVersion()` is
  an explicit `orderByDesc('version_number')->limit(1)` `HasOne`, not `ofMany()`, per
  [[models]]'s UUID-PK trap. When a concept block is attached to a tender, the pivot pins the
  *specific version* used (`concept_block_version_id`, defaulted to the current version at
  attach time) — editing the block later must not silently change what a past bid is recorded
  as having submitted.

Additional decisions made while scoping (not asked, low-stakes implementation choices):
- `Reference`'s table is named `bid_references`, not `references` — `REFERENCES` is a
  reserved SQL keyword (used in FK constraint syntax); Postgres would require quoting it
  everywhere, so the model keeps a clean `Reference` name but maps to `bid_references` via an
  explicit `protected $table`. Recorded as a new [[models]]-style trap.
- Reference letters / supporting documents (idea.md lists both, plural) get a dedicated
  `ReferenceAttachment` model — mirrors `TaskAttachment` exactly (private disk,
  `preserveFilenames()`/`preventFilePathTampering()`, dedicated signed-download controller per
  [[controllers]]) — since a reference can carry more than one file. Certificates get a single
  `FileUpload` field directly on the `certificates` row (one certificate document, replaced in
  place on renewal) — idea.md doesn't ask for certificate file history, so no separate
  attachment/version model there; keep it as plain as the requirement.
- New nav group `groups.reference_library` (own group, not folded into `master_data` — these
  are rich CRUD entities with file uploads and pivots, not simple lookup tables like
  ServiceCategory/Sector) in `lang/en/navigation.php`, per [[app-filament]]'s "don't
  consolidate groups speculatively" rule (this is a real new need, not speculative).
- `Reference` and `ConceptBlock` resources are NOT rights-gated (any panel user can CRUD them),
  matching the existing master-data-resource convention (no dedicated right exists for
  ServiceCategory/Sector/etc. either) — only `Certificate` gets a right, because only
  certificates carry the idea.md-stated disqualification-risk reliability requirement.

Planned tasks for M7:
- [x] Enums + `Right` + lang scaffolding: `App\Enums\CertificateType` (`INSURANCE`,
  `ISO_CERTIFICATE`, `TRADE_REGISTRATION`, `SECTOR_LICENCE`, `TAX_CLEARANCE`,
  `WAGE_LABOUR_COMPLIANCE`, `PREQUALIFICATION`, `OTHER`, `HasLabel`), one case per idea.md's
  certificate list; `App\Enums\ConceptBlockCategory` (`QUALITY_MANAGEMENT`, `STAFFING_CONCEPT`,
  `COVER_ARRANGEMENTS`, `ESCALATION`, `COMPLAINTS`, `SUSTAINABILITY`, `TRAINING`,
  `DEPLOYMENT_ORGANISATION`, `CATEGORY_SPECIFIC`, `HasLabel`), one case per idea.md's
  concept-library list. No `HasColor`/`color()` on either yet — mirrors `BidDecision`'s
  trajectory (color added later, in the table/badge task, not the bare-enum task); nothing
  consumes either enum visually yet. `Right::MANAGE_CERTIFICATES` case (`'manage-certificates'`)
  added to the existing `Right` enum, seeded into
  `RolesAndPermissionsSeeder::DEFAULT_ROLE_RIGHTS` for super-admin and department-head only
  (team-lead/calculation/staff do not get it, unlike `MAKE_BID_DECISION`). New lang files:
  `certificate_types.php`, `concept_block_categories.php`, `rights.php` gains the new key,
  `navigation.php` gains `groups.reference_library`. Tests: `CertificateTypeTest`/
  `ConceptBlockCategoryTest` (label-resolution loop, mirrors `BidDecisionTest`/`RightTest`'s
  shape), `RolesAndPermissionsSeederTest` updated with a `MANAGE_CERTIFICATES`-is-false
  assertion added to the existing calculation-role test and a new "does not grant the manage
  certificates right to team lead" test (the existing super-admin/department-head test already
  loops over all `Right::cases()`, so it covers the new right's positive grant for free). Full
  suite (345 tests, up from 342), Pint, and `phpstan --memory-limit=1G` all clean (the 67
  pre-existing phpstan errors are all in unrelated files — none touch anything this task
  created or edited).
- [x] `Reference` + `ReferenceAttachment`: migration for `bid_references` (UUID PK, `client`,
  `service_category_id`/`sector_id` FK nullable `restrictOnDelete` to the existing lookup
  tables — chose structured FKs over idea.md's free-text "service type" field, per
  [[data-integrity]]'s "ask whether it should be an enum/lookup" rule, `location`,
  `period_start`/`period_end` dates, `contract_volume` nullable decimal +
  `contract_volume_unknown` bool mirroring `Tender::estimated_contract_volume_unknown` exactly,
  `headcount` nullable unsigned int (no unknown flag — only contract volume mirrors Tender's
  precedent), `contact_person_name`/`contact_person_email`/`contact_person_phone`, `description`
  text, `created_by` FK users `restrictOnDelete`, timestamps) and `reference_attachments`
  (mirrors `task_attachments` columns exactly: `reference_id` FK `cascadeOnDelete`, `file_path`,
  `original_filename`, `mime_type`, `size`, `uploaded_by` FK users `restrictOnDelete`,
  timestamps). Table is `bid_references`, not `references` — confirmed the reserved-keyword trap
  from the design-decisions section above; `Reference::class` maps to it via `protected $table`.
  `ReferenceResource` generated via `make:filament-resource Reference --view --not-embedded`
  (own nav group `reference_library`, icon `OutlinedIdentification` — grepped
  `Heroicon.php` first per [[resources]], no clash with `TenderResource`'s `OutlinedDocumentText`
  or any other resource's icon), `Section`/`Grid` balanced 2-column form (details section +
  a 3-column contact-person section) per [[resources]], not rights-gated (any panel user can
  CRUD, matching the master-data-resource convention). `contract_volume`/
  `contract_volume_unknown` reuse `TenderForm`'s exact disabled-on-toggle `Get`/`Set` pattern.
  File uploads via a new `AttachmentsRelationManager` (mirrors `TaskAttachment`'s pattern
  exactly, minus the ownership/notification logic Task's version has — References have no
  per-user assignment concept to check) including the dedicated signed-download controller
  (`reference-attachments.download` route, [[controllers]]'s 5-minute signed-URL pattern) — no
  category-scope re-check in the controller since References are a global library, not
  tender-scoped. Dropped `->tel()` from the contact-phone field mid-task: Filament's tel-format
  validation rejected `fake()->phoneNumber()`'s output in tests, and idea.md doesn't call for
  strict phone validation — kept as plain text like the rest of the contact fields. Factory
  (`volumeUnknown()` state mirrors `Tender`'s pattern) + tests: `ReferenceTest` (relations,
  cascade-delete of attachments, factory state), `ReferenceResourceTest` (create incl.
  volume-unknown clearing, edit, delete permission, attachments relation-manager scoping,
  download incl. unsigned/expired/missing-file rejection). Full suite (357 tests), Pint, and
  `phpstan --memory-limit=1G` all clean (one `TenderResourceTest` failure seen on a single run
  was confirmed a pre-existing flake by rerunning — passed both before and after, in isolation
  and in the full suite, unrelated to anything this task touched).
- [x] `Certificate`: migration for `certificates` (UUID PK, `type` string, cast to
  `CertificateType`, `name`, `issuing_body` nullable, `valid_from` date, `expiry_date` date,
  `file_path`/`original_filename`/`mime_type`/`size` nullable (file optional at create, addable
  later), `notes` text nullable, `last_reminder_threshold_days` nullable unsigned tiny int and
  `last_reminder_sent_at` nullable datetime — both excluded from `#[Fillable(...)]`, forceFill-
  only, written only by task 4's expiry-check command, mirroring [[deadlines]]'s escalation-
  column pattern exactly — `created_by` FK users `restrictOnDelete`, timestamps). New
  `App\Enums\CertificateStatus` (`VALID`/`EXPIRING_SOON`/`EXPIRED`, `HasLabel` + a plain
  `color()` method, mirroring `TenderStatus`/`BidDecision`'s exact shape — no enum in this
  codebase implements Filament's actual `HasColor` contract, they all resolve color manually via
  `->color(fn ($state) => $state->color())` on the column/entry). `Certificate::status()`
  computed method (not persisted, always reflects "today"): expired if `expiry_date` is past,
  else expiring-soon if within 30 days (the lowest reminder threshold task 4 will use), else
  valid. `CertificateResource` gated end-to-end by `Right::MANAGE_CERTIFICATES` — mirrors
  `UserResource`'s `canManage()`-per-`can*()`-override pattern exactly (found by grepping for
  existing resource-level authorization precedent rather than inventing a new shape), with
  `canViewAny()` gating the whole list/nav so an ungated `EditAction`/`ViewAction` in the table
  never becomes a reachable dead link (per [[resources]]'s "actions aren't auto-wired to
  can*()" trap — gating navigation itself sidesteps that trap rather than fighting it action by
  action). `FileUpload::make('file_path')` bound directly to the real column (no separate
  attachment model, per the design decision above) with `original_filename`/`mime_type`/`size`
  derived server-side in `CreateCertificate`/`EditCertificate`'s mutate hooks, mirroring
  `AttachmentsRelationManager`'s `mutateDataUsing` derivation but inline since there's no
  relation manager here. Dedicated signed-download controller (`certificates.download` route)
  re-checks `Right::MANAGE_CERTIFICATES` itself (not just relying on the resource gate), same
  "re-check at the point the file is served" reasoning as `TenderDocumentDownloadController`'s
  see-prices re-check ([[controllers]]). Status badge (green/orange/red via
  `CertificateStatus::color()`) in both the table (sortable by `expiry_date`, default-sorted by
  it) and infolist. Factory with `expiringSoon()`/`expired()`/`withFile()` states. Tests:
  `CertificateStatusTest` (label + color per case), `CertificateTest` (status computation across
  all 3 buckets incl. the 30-day boundary, factory states, reminder-columns-not-fillable via
  `->update()` per `TenderTest`'s existing convention), `CertificateResourceTest` (list/create/
  edit `assertForbidden()` without the right, `canDelete`/`canDeleteAny` gating, expiry-before-
  valid-from form validation, download incl. without-the-right/unsigned/missing-file rejection).
  One phpstan finding fixed mid-task: `EditCertificate`'s `mutateFormDataBeforeSave()` accessed
  `$this->record->file_path` directly, which phpstan flags as a property access on
  `Model|int|string` (no existing page in this codebase read `$this->record`'s properties
  directly to check the accepted pattern) — fixed with a local `/** @var Certificate $record */`
  typed variable. The `canManage()`-called-through-`static::`-is-unsafe warning phpstan raises
  on `CertificateResource` is pre-existing baseline noise, confirmed identical on
  `UserResource`'s own `canManage()`. Full suite (373 tests), Pint, and
  `phpstan --memory-limit=1G` all clean.
- [x] `CheckCertificateExpiry` scheduled command (`certificates:check-expiry`,
  `App\Console\Commands`) + `CertificateExpiringNotification` (dual-channel per
  [[notifications]]'s pattern — `ShouldQueue`, mail gated by
  `User::wantsEmailFor(NotificationType::CERTIFICATE_EXPIRING)` — new `NotificationType` case
  added, `toDatabase()` via `Filament\Notifications\Notification::make()->getDatabaseMessage()`,
  `toMail()` links to the certificate's edit page). Notification takes a nullable
  `thresholdDays` — a real value (90/30/7) for a reminder, `null` for the final "already expired"
  notice — and switches copy accordingly. Command runs daily (registered in `routes/console.php`,
  `->daily()` — no hourly urgency like deadline escalation). No `MAKE_BID_DECISION`-holder lookup
  precedent existed anywhere yet (BidDecision doesn't send notifications) — used Spatie's built-in
  `User::permission(Right::MANAGE_CERTIFICATES->value)->get()` (`HasPermissions::scopePermission`,
  confirmed by reading the vendor trait) since it already covers both role-granted and
  directly-granted rights and needs no bespoke join. Guarded with an
  `App\Models\Permission::where('name', ...)->exists()` check first, mirroring
  `CheckDeadlineEscalations`'s `Role::where(...)->exists()` guard — `scopePermission()` throws
  `PermissionDoesNotExist` outright on a fresh install before the seeder runs.
  `last_reminder_threshold_days` (`0` reserved as an `EXPIRED_MARKER`, distinct from every real
  threshold which is `>0`) only ever moves downward through `[90, 30, 7]` then to the marker,
  exactly mirroring [[deadlines]]'s escalation-column shape — including its catch-up behavior:
  a certificate that crosses multiple thresholds between runs (e.g. no run for 3 months) fires
  every newly-crossed threshold's notification in the same run, not just the nearest one, since
  each threshold's own "already sent" check is independent. Tests
  (`CheckCertificateExpiryTest`): 90-day reminder fires once and not twice on a second run; a
  certificate outside every threshold sends nothing; the final expired notice fires once and not
  twice on a second run (even though the same first run also fires the 90/30/7 catch-up
  reminders ahead of it); recipients are exactly `Right::MANAGE_CERTIFICATES` holders (a
  `STAFF`-role user, which the seeder doesn't grant the right to, gets nothing). Full suite (377
  tests, up from 373), Pint, and `phpstan --memory-limit=1G` clean (72 pre-existing errors seen,
  up from the prior task's noted 67 — confirmed by file-by-file diffing the phpstan output that
  every erroring file is unrelated to anything this task touched, so the drift predates this
  task).
- [x] `ConceptBlock` + `ConceptBlockVersion`: migration for `concept_blocks` (UUID PK,
  `category` enum-cast `ConceptBlockCategory`, `title`, `created_by` FK users
  `restrictOnDelete`, timestamps) and `concept_block_versions` (mirrors
  `tender_document_versions` exactly: UUID PK, `concept_block_id` FK `cascadeOnDelete`,
  `version_number` unsigned int, `content` long text, `created_by` FK users
  `restrictOnDelete`, `created_at` only via `const UPDATED_AT = null`, unique on
  `[concept_block_id, version_number]`). `ConceptBlock::currentVersion(): HasOne` (explicit
  `orderByDesc('version_number')->limit(1)`, per [[models]]'s UUID-PK/`ofMany()` trap).
  `ConceptBlockResource` (own `reference_library` nav group, `OutlinedBookOpen` icon — grepped
  `Heroicon.php` first, no clash with `Reference`'s `OutlinedIdentification` or `Certificate`'s
  `OutlinedShieldCheck`; not rights-gated, matching the master-data-resource convention per the
  design decision above). `ConceptBlockForm`'s `content` field is NOT a real `concept_blocks`
  column — `EditConceptBlock` overrides `mutateFormDataBeforeFill()` to inject
  `currentVersion?->content` into the form on load, then `mutateFormDataBeforeSave()` diffs the
  submitted content against that same value: unchanged content is stripped from `$data` and
  nothing else happens; changed content is captured on a private property (same
  capture-before-`afterSave()` shape [[resources-pages]] documents for `CreateTender`/
  `EditTender`, needed here because `content` isn't a `#[Fillable(...)]` column so it can't just
  flow through `update()`) and `afterSave()` creates a new `ConceptBlockVersion` row at
  `versions()->max('version_number') + 1`. `CreateConceptBlock` mirrors the same capture shape
  for the always-created version 1. A new `VersionsRelationManager` tab lists a block's own
  version history (`recordActions([])`, `headerActions([])`, `toolbarActions([])` — no
  create/edit/delete anywhere, since rows are immutable by construction; the only writer is
  `EditConceptBlock`/`CreateConceptBlock` above), newest version first. Tests:
  `ConceptBlockTest` (versions ordering, `currentVersion()`, cascade-delete of versions, factory
  category), `ConceptBlockResourceTest` (create writes both the block and its version-1 row;
  editing with changed content creates version 2 and `currentVersion` moves to it; editing with
  unchanged content leaves exactly one version row; the relation manager shows a block's own
  versions newest-first and not another block's). Full suite (385 tests, up from 377), Pint, and
  `phpstan --memory-limit=1G` clean (no new errors — confirmed no `ConceptBlock*` file appears
  in phpstan's output). One environment quirk hit and worked around: the app container's clock
  was a day behind the host, so `make:migration` generated `2026_09_01_...` filenames that would
  have sorted before the same day's already-applied `2026_09_02_...` migrations; renamed both
  files to `2026_09_02_1100{00,01}_...` before running them, no functional impact since neither
  table has an FK dependency on that day's other new tables.
- [x] Tender-side linking: three pivots — `tender_bid_reference`, `tender_certificate` (plain
  composite-PK pivots, `primary(['tender_id', 'x_id'])`, deliberately with NO separate `id`
  column, to sidestep [[migrations]]'s known `task_participants.id` bug outright rather than
  fix it after the fact), and `tender_concept_block` (same shape plus `concept_block_version_id`
  FK `restrictOnDelete` to `concept_block_versions`, pinning the version used). `Tender`'s
  `bidReferences()`/`certificates()`/`conceptBlocks()` `BelongsToMany`s plus the inverse
  `tenders()` on `Reference`/`Certificate`/`ConceptBlock`; `Reference`'s pivot table name doesn't
  match Eloquent's model-name-derived default key (`bid_reference_id`, not `reference_id`, since
  the pivot is named after the table not the model — see [[models]]'s `bid_references` trap), so
  both sides of that one relation pass explicit foreign/related pivot keys — Certificate/
  ConceptBlock don't need this since their table names already match their model names.
  Asked the user whether `TenderResource` should get 3 separate relation-manager tabs or one
  combined "Reference Library" tab with 3 sections; they chose combined. Implemented via
  Filament's built-in `Filament\Resources\RelationManagers\RelationGroup::make($label, [...])`
  (found by reading the vendor source — not previously used anywhere in this app) wrapping three
  ordinary `RelationManager` classes (`ReferencesRelationManager`, `CertificatesRelationManager`,
  `ConceptBlocksRelationManager`), which Filament natively renders as one "Reference Library" tab
  with a sub-tab per manager — no custom Blade/Livewire needed. Each uses Filament's built-in
  `AttachAction`/`DetachAction`/`DetachBulkAction` (also new to this app; the existing
  `DocumentsRelationManager`/`AttachmentsRelationManager` precedents are `hasMany`, not
  `belongsToMany`, so they use `CreateAction` instead). Gating: References/ConceptBlocks reuse
  `TenderForm::canManageTeam() || $tender->linkedToDocuments($user)` (same gate
  `DocumentsRelationManager` uses for uploads — any tender-linked user or team manager); Certificates
  is instead gated behind `Right::MANAGE_CERTIFICATES`, matching the resource's own gate — a
  deliberate deviation, since recording which certificate backs a bid carries the same
  disqualification-risk weight idea.md assigns certificates generally, not a routine tender-team
  task. `ConceptBlocksRelationManager`'s attach action is fully custom (`->action(...)` overrides
  `AttachAction`'s default entirely) rather than using its `->schema()` extra-pivot-field
  mechanism: it looks up the selected block's `currentVersion` server-side and pins that id via
  `syncWithoutDetaching()`, with no version-picker exposed in the UI — idea.md never asks for
  choosing an older version at attach time, only for the pin to exist. No extra category-scope
  code was needed for the "own tender's attached records" query: `$tender->bidReferences()` etc.
  are accessed through the already-fetched (and thus already `ServiceCategoryScope`-scoped)
  Tender, the same "automatic for relation-manager access" reasoning [[scopes-models]] documents
  for `Task`; Reference/Certificate/ConceptBlock themselves carry no `service_category_id` at all
  (they're an explicitly global library, per the design decision above), so the *attachable*
  record list is correctly unscoped too. `lang/en/reference_library.php` added for the shared tab
  label and per-section attach-button labels. Three phpstan findings fixed mid-task, all in
  `ConceptBlocksRelationManager` (none elsewhere): `$record->pivot` needed a
  `@property-read Pivot|null $pivot` addition to `ConceptBlock`'s docblock (no prior pivot-column
  read existed anywhere in this app to copy from); `ConceptBlockVersion::find()` and
  `ConceptBlock::findOrFail()` both resolve to a `Model|Collection` union under Larastan when
  called as static facades — fixed via `::query()->first()`/`::query()->findOrFail()` plus a
  local `/** @var */` typed variable, mirroring `EditCertificate`'s existing fix for the same
  class of issue (task 3 above); and `Pivot`'s base class doesn't declare dynamic pivot columns,
  fixed with `->getAttribute('concept_block_version_id')` instead of magic-property access.
  Tests: `TenderTest` (attach/detach for references and certificates; concept-block version
  pinning is unaffected by a later new version), `TenderResourceTest` (three new `describe()`
  blocks — list-scoping to the owning tender for all three, attach/detach gating and success for
  each, the certificates gate specifically rejecting a non-`MANAGE_CERTIFICATES` user, and the
  concept-block attach pinning `currentVersion` at that moment). Full suite (395 tests, up from
  385 — one unrelated pre-existing flake seen and confirmed by rerun, same class of Filament
  tel-format/faker flake noted in task 2 above, in `TenderResourceTest`'s "team assignment"
  group, nothing to do with this task), Pint, and `phpstan --memory-limit=1G` clean (72
  pre-existing errors, same count/files as the prior task, none in anything touched here). Same
  migration-filename-ordering workaround as task 4 needed again: renamed the three new pivot
  migrations from container-clock-generated `2026_09_01_...` to `2026_09_02_1200{00,01,02}_...`.
- [ ] Docs: new standalone page `docs/12-references-certificates-concepts.md` (mirrors how
  09/10/11 were added as milestones landed) covering the three library resources, certificate
  expiry reminders and who receives them, concept block versioning, and attaching library
  records to a tender (including the frozen-version-on-attach behavior). Cross-linked from
  `03-managing-tenders.md` and `08-administration.md` (new nav group, new right); all touched
  pages' timestamps bumped per [[docs]]'s sync rule.

M7 is additive: three new top-level resources, one new right, one new scheduled command, three
new tender-tender pivots/relation managers. It does not touch `Tender::changeStatusTo()` or any
existing transition guard — idea.md's acceptance points for M7 are CRUD/reminders/versioning
only, no status-gating requirement.
