# M4 — Documents & Versioning

Full spec: [idea.md](../../../idea.md)'s M4 section. Index: [../milestones.md](../milestones.md).

**M4 — Documents & Versioning is in progress**, started 2026-08-28 at the user's explicit
request, building incrementally task-by-task rather than in one pass (same rhythm as M3).

Three design decisions were confirmed with the user before any code was written:
- **Lock scope**: when a tender's status reaches `SUBMISSION`, every `TenderDocument` that
  already exists at that moment gets locked (no new version, no delete). Documents created
  *after* that point (e.g. Result, Post-analysis, further Communication in later milestones)
  are untouched — they're new documents, not edits to already-submitted ones. (Rejected
  alternatives: locking only the `FINAL_BID_DOCUMENTS` category, and locking every category
  tender-wide forever — the latter would block M8/M9 document uploads once built.)
- **Rights gating**: only the `CALCULATION` category is gated behind `see-prices` (mirrors
  idea.md's explicit example). Every other category is visible to anyone with tender access.
- **Upload/delete authorization**: mirrors the existing Task-attachment pattern
  (`AttachmentsRelationManager`/`Task::isLinkedTo()`), scoped to the tender instead of the
  task — the tender owner, any `tender_team_members` row, or a manager
  (`TenderForm::canManageTeam()`'s team-lead/department-head/super-admin set) can upload/add
  versions; delete is uploader-or-manager.

Planned tasks for M4:
- [x] Schema + models + enum: `App\Enums\DocumentCategory` (11 cases —
  `TENDER_DOCUMENTS`/`CALCULATION`/`CONCEPTS`/`EVIDENCE_DOCUMENTS`/`REFERENCES`/
  `BIDDER_QUESTIONS`/`COMMUNICATION`/`SITE_VISIT`/`FINAL_BID_DOCUMENTS`/`RESULT`/
  `POST_ANALYSIS`) + `lang/en/document_categories.php`, mirroring `DeadlineType`'s exact
  pattern ([[enums]]). Two new tables: `tender_documents` (`TenderDocument` model —
  `tender_id` FK `cascadeOnDelete`, `category`, `title`, `created_by` FK `restrictOnDelete`,
  `locked_at`/`locked_by` nullable and forceFill-only via `lock()`, excluded from
  `#[Fillable(...)]` same as `Tender`'s archive columns) and `tender_document_versions`
  (`TenderDocumentVersion` model — `tender_document_id` FK `cascadeOnDelete`,
  `version_number` unsigned int, unique per `(tender_document_id, version_number)`,
  `file_path`/`original_filename`/`mime_type`/`size`, `uploaded_by` FK `restrictOnDelete`, no
  `updated_at` (`const UPDATED_AT = null`) — immutable by construction).
  `TenderDocument::currentVersion()` is a `HasOne` ordered
  `orderByDesc('version_number')->limit(1)` — deliberately not `ofMany()`, which can't run
  `MAX()` against this app's UUID PKs ([[models]]'s trap). `Tender` gains `documents():
  HasMany` and `linkedToDocuments(User $user): bool` (true when the user is the tender's
  owner or a `teamMembers` row, mirroring `Task::isLinkedTo()`). Neither model registers a
  category-scoping global scope of its own yet — like `TaskAttachment`, they're only reached
  through the parent `Tender` (already `ServiceCategoryScope`-scoped) until/unless a
  standalone Filament resource queries them directly, per [[scopes-models]]'s "child models"
  rule. `TenderDocumentVersion::downloadUrl()` references the `tender-documents.download`
  named route, which doesn't exist yet — added by the download-controller task below.
  Factories for both new models. Tests (`TenderDocumentTest`): version ordering,
  `currentVersion()`, `isLocked()`/`lock()`, cascade delete (tender → document → version),
  `linkedToDocuments()` for owner/team-member/stranger.
- [x] Locking wired into `changeStatusTo()`: a new protected `Tender::lockDocuments(User
  $actor)` queries `$this->documents()->whereNull('locked_at')` and calls `TenderDocument::lock()`
  on each, called from inside `changeStatusTo()`'s existing `DB::transaction()` right after the
  status update/audit-row write, gated on `$newStatus === TenderStatus::SUBMISSION` — same
  transaction that runs the final-submission task-completeness gate ([[tenders]]). The
  `whereNull('locked_at')` filter means already-locked documents are never re-touched (no
  re-stamping `locked_by`/`locked_at`), and documents created after the tender is already in
  `SUBMISSION` are untouched since the loop only runs once, at the moment of transition — they
  stay unlocked by construction, no extra guard needed. Tests added to `TenderTest`'s new
  "document locking on submission" group: pre-existing document locked with the transitioning
  actor recorded, a document created after submission stays unlocked, and an
  already-locked document's original `locked_by`/`locked_at` survive a later submission
  transition untouched.
- [ ] `DocumentsRelationManager` on `TenderResource`: table grouped/filterable by category;
  "new document" action (title + category Select + first-version `FileUpload`) and a
  per-document "new version" row action (single `FileUpload`, next `version_number`), both
  using [[resources]]'s file-upload rule (`->preserveFilenames()`,
  `->preventFilePathTampering()`, server-derived `mime_type`/`size`, private `local` disk
  under `tender-documents/`). `CALCULATION` rows/options hidden from users without
  `see-prices`. Upload/new-version visible when `linkedToDocuments()` or
  `canManageTeam()`; delete visible when uploader-or-manager, additionally hidden once the
  parent document `isLocked()` — both enforced again server-side in the action's
  before()/mutate hook, never UI-hiding alone ([[permissions]]). Feature tests mirroring
  `TaskResourceTest`'s "attachments" group shape.
- [ ] Download controller + signed URLs: `tender-documents.download` route +
  `TenderDocumentDownloadController`, re-running `Tender::query()->findOrFail()` on the
  version's tender so `ServiceCategoryScope` turns an out-of-category request into a 404, not
  a hidden-but-reachable link. `TenderDocumentVersion::downloadUrl()` uses
  `URL::temporarySignedRoute(..., now()->addMinutes(5), ...)`, matching [[controllers]]'s
  `TaskAttachment` pattern — a plain `route()` URL must not work. Tests mirroring
  `TaskResourceTest`'s "attachment download" group (200 in-category, 404 out-of-category,
  signed link works, unsigned/expired rejected).
- [ ] Docs + wrap-up: a `[[documents]]` rule file consolidating the model/locking/gating
  decisions above (linked from `.ai/rules/index.md` for the relevant `app/**` paths), a new or
  extended `docs/*.md` page, this milestone entry's boxes checked off.
