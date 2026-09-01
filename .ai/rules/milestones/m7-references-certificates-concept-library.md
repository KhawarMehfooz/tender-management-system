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
- [ ] `CheckCertificateExpiry` scheduled command (`certificates:check-expiry`,
  `App\Console\Commands`) + `CertificateExpiringNotification` (dual-channel per
  [[notifications]]'s pattern — `ShouldQueue`, mail gated by
  `User::wantsEmailFor(NotificationType::CERTIFICATE_EXPIRY)` — new `NotificationType` case
  needed, `toDatabase()` via `Filament\Notifications\Notification::make()`). Command runs daily
  (registered in `routes/console.php`, `->daily()` — no hourly urgency like deadline escalation),
  queries certificates with `expiry_date` in the future crossing the 90/30/7-day thresholds (or
  already past, one final "expired" notice) that haven't already had that threshold's reminder
  sent (`last_reminder_threshold_days` comparison, same "state only moves forward, fires once
  per threshold" shape as [[deadlines]]'s escalation columns), notifies every
  `Right::MANAGE_CERTIFICATES` holder (`User::query()->whereHas('roles.permissions', ...)`
  or the Spatie `permission` direct-grant equivalent — check how `MAKE_BID_DECISION` holders are
  queried elsewhere in the codebase and reuse that exact lookup, don't invent a new one).
  Tests: each threshold fires once and not twice on a second run; a certificate below no
  threshold yet sends nothing; expired-with-no-final-notice-yet fires once; recipients are
  exactly the right's holders.
- [ ] `ConceptBlock` + `ConceptBlockVersion`: migration for `concept_blocks` (UUID PK,
  `category` enum-cast `ConceptBlockCategory`, `title`, `created_by` FK users
  `restrictOnDelete`, timestamps) and `concept_block_versions` (mirrors
  `tender_document_versions`: UUID PK, `concept_block_id` FK `cascadeOnDelete`,
  `version_number` unsigned int, `content` long text, `created_by` FK users
  `restrictOnDelete`, `created_at` only, no `updated_at`). `ConceptBlock::currentVersion(): HasOne`
  (explicit `orderByDesc('version_number')->limit(1)`). `ConceptBlockResource` (list/create/edit/
  view; "edit" creates a new version rather than mutating an existing row — mirrors
  `DocumentsRelationManager`'s upload-new-version action shape) with a version-history table
  (view-only past versions, no delete/edit on old rows — immutable by construction). Not
  rights-gated (see design-decisions note above). Factory + tests (versioning: edit creates a
  new row not a mutation, `currentVersion()` picks the latest, history is browsable and old
  versions are unreachable via any edit/delete action).
- [ ] Tender-side linking: three pivots (`tender_bid_reference`, `tender_certificate`,
  `tender_concept_block` — the last with an extra `concept_block_version_id` FK pinning the
  version used, per the design decision above) plus relation managers on `TenderResource`
  attaching existing library records to a tender (attach/detach only — creation stays on the
  library resources, not inline). Confirm with the user whether this is 3 separate relation-
  manager tabs (consistent with the existing one-tab-per-concern pattern:
  Tasks/Documents/Deadlines/Calculations/BidDecision) or one combined "Reference Library" tab
  with 3 sections, before building — `TenderResource` already has 5 tabs and this adds real UI
  surface, worth a quick check rather than assuming. Tests: attach/detach, the concept-block
  pivot correctly freezes the version at attach time (editing the block afterward doesn't change
  what's shown as attached to the tender), category-scope re-check on the relation manager
  query (mirrors [[scopes-models]]'s existing pattern for tender-scoped relation managers).
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
