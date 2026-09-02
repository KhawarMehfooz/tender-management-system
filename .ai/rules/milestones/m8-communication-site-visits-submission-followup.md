# M8 — Communication, Site Visits, Submission, Follow-up

Full spec: [idea.md](../../../idea.md)'s M8 section. Index: [../milestones.md](../milestones.md).

**M8 — Communication, Site Visits, Submission, Follow-up is now in progress**, started 2026-09-02.

Four design decisions were confirmed with the user before any code was written:
- **Submission record is one-per-tender**, not a repeatable history. `TenderSubmission` carries
  a unique `tender_id` FK. A correction is an edit to the existing record, not a new row —
  matches idea.md's singular framing ("Submission record" fields, no mention of resubmission
  tracking).
- **Communication entries use a fixed enum**, `App\Enums\CommunicationType` (`BIDDER_QUESTION`,
  `CLARIFICATION`, `AMENDMENT`, `EMAIL`, `PHONE_CALL`, `INTERNAL_COMMENT` — the six kinds
  idea.md literally lists), not a free-text tag — consistent with this app's "structured field
  over free text wherever a fixed vocabulary exists" convention (see [[enums]]).
- **Document requests get an optional link back to the communication entry they arose from**
  (`tender_communication_id`, nullable FK, `nullOnDelete`) plus a small dedicated status enum
  `App\Enums\DocumentRequestStatus` (`OPEN`, `IN_PROGRESS`, `FULFILLED`, `WITHDRAWN`) with its
  own status-change history table, mirroring `TenderStatusChange`'s audit pattern exactly
  (append-only, `from_status`/`to_status`/`changed_by`/`reason`/`changed_at`, no `updated_at`).
- **Three dedicated file-attachment models**, not one shared polymorphic one:
  `TenderSiteVisitPhoto`, `TenderSubmissionFile`, `TenderDocumentRequestFile` — each mirrors
  `TaskAttachment` exactly (own migration, own signed-download controller per
  [[controllers]]), rather than introducing a new polymorphic-attachment abstraction this
  codebase doesn't otherwise use.

Additional decisions made while scoping (not asked, low-stakes implementation choices):
- **Communication entries do NOT carry their own file attachments.** idea.md's M4 already has a
  `DocumentCategory::COMMUNICATION` category on the generic tender document library
  ([[documents]]) for attaching files related to correspondence — adding a second, parallel
  file-attachment mechanism on `TenderCommunication` itself would duplicate that. The
  Communication tab's UI can point users at the Documents tab's `COMMUNICATION` category for
  attaching files.
- **`TenderSubmission` and `TenderFollowUp` are modeled as HasOne (unique `tender_id` FK) but
  exposed through an ordinary Filament RelationManager table**, same as every other tender
  relation manager — Filament's RelationManager machinery doesn't have a first-class
  "singleton child" mode, and this codebase has no precedent for a HasOne-only relation
  manager. The table naturally holds 0 or 1 rows (enforced by the DB unique constraint); the
  RelationManager's `CreateAction` is hidden once a record already exists (`->visible(fn ()
  => $this->getOwnerRecord()->submission === null)` / same for follow-up), and the row's own
  edit action is the only way to change it afterward. No new abstraction, just a table that
  happens to cap at one row.
- **Follow-up tracking fields, resolved against idea.md's bullet list**: "receipt confirmation"
  and "document requests" are already covered elsewhere (submission's own
  `receipt_confirmed`/`receipt_confirmed_at`, and the dedicated document-request sub-workflow
  above) so `TenderFollowUp` doesn't duplicate them. "Queries" during the follow-up phase are
  tracked as ordinary `TenderCommunication` entries (`EMAIL`/`PHONE_CALL`/`INTERNAL_COMMENT`
  types cover this), not a separate field. What's left and genuinely follow-up-specific becomes
  `TenderFollowUp`'s columns: `presentation_scheduled_at` (nullable datetime),
  `presentation_notes`, `negotiation_notes`, `bid_validity_until` (nullable date),
  `expected_result_date` (nullable date), `expected_result_notes`.
- **Site visits are repeatable** (`TenderSiteVisit` is a plain HasMany, no uniqueness
  constraint) — idea.md's field list (attendees, photos, access routes, etc.) describes one
  visit's worth of detail, and a tender can reasonably have more than one site visit over its
  lifecycle (e.g. an initial pre-bid visit and a later follow-up visit).
- **Write access on all six new relation managers follows the existing `linkedToDocuments()` /
  `TenderForm::canManageTeam()` pattern** ([[resources-tenders]], [[scopes-models]]) — owner,
  team member, or team-lead/department-head/super-admin — the same authorization shape
  `DocumentsRelationManager`/`CalculationsRelationManager` already use. No new `Right` enum
  case is introduced for M8; idea.md doesn't call out a disqualification-risk-level need for
  one here the way M6 (`MAKE_BID_DECISION`) and M7 (`MANAGE_CERTIFICATES`) did.
- **`attendees` on `TenderSiteVisit` is a free-text field**, not a `User` multi-select — site
  visit attendees are frequently external people (client staff, competitors' representatives)
  who aren't system users, so a structured `User` relation would only capture a subset.

Planned tasks for M8:
- [x] Enums + lang scaffolding: `App\Enums\CommunicationType` (`HasLabel`, 6 cases:
  `BIDDER_QUESTION`/`CLARIFICATION`/`AMENDMENT`/`EMAIL`/`PHONE_CALL`/`INTERNAL_COMMENT`) and
  `App\Enums\DocumentRequestStatus` (`HasLabel`, plain `color()` method matching
  `BidDecision`/`TaskStatus`'s convention rather than Filament's `HasColor` interface —
  `FULFILLED` success, `WITHDRAWN`/`OPEN` gray, `IN_PROGRESS` warning — plus `isTerminal()` and
  an `allowedTransitions()`/`canTransitionTo()` pair mirroring `TaskStatus`'s shape, for the
  document-request task to consume). New lang files `communication_types.php`,
  `document_request_statuses.php`. Tests: `CommunicationTypeTest`/`DocumentRequestStatusTest`
  (label-resolution loop over all cases, mirrors `BidDecisionTest`). No dedicated
  transition-matrix test at the enum level — this codebase tests status transitions through the
  owning model's feature tests instead (no `TaskStatusTest`/`TenderStatusTest` exist either),
  so `DocumentRequestStatus::allowedTransitions()` will get covered via
  `TenderDocumentRequest`'s own tests in that later task. 347 tests passing (up from 345),
  Pint clean.
- [x] Communication log: migration `tender_communications` (UUID PK, `tender_id` FK
  `cascadeOnDelete`, `type` string, `subject` string, `content` text, `contact_person` nullable
  string, `occurred_at` datetime, `logged_by` FK users `restrictOnDelete`, timestamps). Model
  `TenderCommunication` (casts `type` to `CommunicationType`, `occurred_at` to datetime,
  `#[Fillable(...)]`, `tender()`/`loggedBy()` BelongsTo). `Tender::communications(): HasMany`,
  ordered `orderByDesc('occurred_at')`. `CommunicationRelationManager` (table + form, `type`
  Select, `subject`/`content`/`contact_person` inputs, `occurred_at` DateTimePicker defaulting
  to now, gated per the `linkedToDocuments()`/`canManageTeam()` pattern; append-only by design
  — an `EditAction` exists (author or manager only, via a private `canEditCommunication()`
  helper — named to avoid colliding with `RelationManager`'s own `canEdit()`, same trap
  `DocumentsRelationManager::canDeleteDocument()` sidesteps) but there is deliberately no
  delete action at all, not even a gated-hidden one. New lang file `tender_communications.php`
  (no `tab_label` key — the tab title falls back to Filament's default titleization of the
  `communications` relationship name, matching every other relation manager in this app, none
  of which override the tab title). Registered on `TenderResource::getRelations()` right after
  `BidDecisionRelationManager`. Tests added to the existing `TenderResourceTest.php` (matching
  this codebase's one-file-per-resource test convention, not a separate file): a "communication
  relation manager" describe block covering category-scoped listing, create hidden for an
  unlinked user, create stamping `logged_by` as the acting user, a team-lead manager creating
  while unrelated, edit visible for the entry's own author, edit hidden for a different linked
  user, edit visible for a manager, and `assertTableActionDoesNotExist('delete', ...)` proving
  no delete action was wired at all. 413 tests passing (up from 405 — 8 new, one pre-existing
  unrelated flaky test in the same file confirmed via isolated re-run: a faker-generated
  `contact_phone` occasionally fails format validation in the "field-level rights" group, not
  touched by this task). Pint and `phpstan --memory-limit=1G` clean on every file this task
  touched.
- [x] Site visits + photos: migrations `tender_site_visits` (UUID PK, `tender_id` FK
  `cascadeOnDelete`, `visit_date` date, `attendees` text, `contact_person` nullable string,
  `access_routes`/`parking`/`areas`/`risks`/`technical_particularities`/
  `staffing_requirement`/`competitors_spotted`/`open_questions`/`notes` all nullable text,
  `created_by` FK users `restrictOnDelete`, timestamps) and `tender_site_visit_photos` (mirrors
  `task_attachments` columns exactly, FK `tender_site_visit_id` `cascadeOnDelete`). Models
  `TenderSiteVisit` (HasMany `photos()`) + `TenderSiteVisitPhoto` (mirrors `TaskAttachment`,
  `downloadUrl()` via a new signed route). `TenderSiteVisitPhotoDownloadController` (mirrors
  `TaskAttachmentDownloadController`, re-scopes via `Tender::query()->findOrFail()` through the
  visit's `tender_id`), new route `tender-site-visit-photos.download`.
  `SiteVisitsRelationManager`'s create/edit form covers only the visit's own fields — photos
  are NOT a `Repeater` embedded in that form (a private-disk `FileUpload` inside a
  `->relationship()` Repeater has no clean built-in way to derive `mime_type`/`size` server-side
  per item without a bespoke `mutateRelationshipDataBeforeCreateUsing()` closure per row, more
  moving parts than the alternative). Instead: a dedicated "Upload photo" row `Action` (mirrors
  `DocumentsRelationManager::addVersion()` exactly — single `FileUpload`, server-derived
  mime/size, `preserveFilenames()`/`preventFilePathTampering()`), and a new
  `TenderSiteVisitInfolist::components()` (mirrors `TaskInfolist`'s extracted-components
  pattern) rendering the visit's read-only fields plus a `RepeatableEntry::make('photos')`
  gallery with per-row download links — exactly `TaskInfolist`'s attachments-table pattern,
  wired into the RelationManager's `ViewAction::make()->schema(...)` per
  [[tenders-relation-managers]]'s rule that a generic `ViewAction` shows the disabled form, not
  an infolist, unless told. Table adds a `photos_count` column via `->counts('photos')`. Delete
  (creator-or-manager, mirrors `canDeleteDocument()`) removes the visit's photo files from disk
  first, same as `DocumentsRelationManager`'s version-file cleanup. New lang file
  `tender_site_visits.php`. Registered on `TenderResource::getRelations()` right after
  `CommunicationRelationManager`. Tests added to `TenderResourceTest.php`: a "site visits
  relation manager" describe block (category-scoped listing, create hidden/visible by link,
  create stamping `created_by`, manager override, photo upload stamping `uploaded_by`, upload
  hidden for an unlinked user, creator delete cascading photo-file cleanup, delete hidden for a
  non-creating linked user) and a "site visit photo download" describe block mirroring
  [[controllers]]'s document-download test shape (in-category 200, out-of-category 404,
  unsigned 403, expired 403 — calculation-style see-prices gate doesn't apply here, so no
  equivalent case). 429 tests passing (up from 417 — 12 new), same pre-existing unrelated flaky
  faker-phone test reconfirmed via isolated re-run. Pint and `phpstan --memory-limit=1G` clean
  on every file this task touched.
- [x] Submission record + files: migrations `tender_submissions` (UUID PK, `tender_id` FK
  unique `cascadeOnDelete`, `submission_date` date, `submission_time` time,
  `responsible_employee_id` FK users `restrictOnDelete`, `portal` string,
  `transmission_route` string, `receipt_confirmed` boolean default false,
  `receipt_confirmed_at` nullable datetime, `notes` nullable text, `created_by` FK users
  `restrictOnDelete`, timestamps) and `tender_submission_files` (mirrors `task_attachments`, FK
  `tender_submission_id` `cascadeOnDelete`). Models `TenderSubmission` (`Tender::submission():
  HasOne`; `submission_time` is a Postgres `time` column left uncast — Laravel has no built-in
  `time` cast, and Filament's `TimePicker` round-trips the raw `"HH:MM:SS"` string fine) +
  `TenderSubmissionFile` with `downloadUrl()`. `TenderSubmissionFileDownloadController`
  (mirrors the site-visit-photo controller, re-scopes via the submission's `tender_id`) + route
  `tender-submission-files.download`. `SubmissionRelationManager` uses the hide-Create-
  once-exists pattern from the scoping notes above (a `submissionAlreadyExists()` helper,
  needed because Larastan flags `$this->getOwnerRecord()->submission` directly — `Model` has no
  `submission` property without the local `/** @var Tender $tender */` narrowing this helper
  does once, rather than repeating the docblock in every closure). `receipt_confirmed_at` is
  stamped/cleared server-side on both create and edit whenever `receipt_confirmed` flips (never
  trusts a client-supplied timestamp). Responsible-employee `Select` options reuse
  `TenderForm::scopedUserOptions()`'s category-scoping logic (re-implemented locally, since the
  original is `private` on `TenderForm` — same "duplicate the small helper" call every other
  RelationManager in this codebase already makes rather than widening an unrelated class's
  visibility). File upload is a dedicated "Upload file" row action mirroring
  `DocumentsRelationManager::addVersion()`, same as site visit photos; files display via a new
  `TenderSubmissionInfolist::components()` (details + files `RepeatableEntry`, same
  `TaskInfolist`-derived pattern used for site visits) wired into the RelationManager's
  `ViewAction::make()->schema(...)`. New lang file `tender_submissions.php`. Registered on
  `TenderResource::getRelations()` right after `SiteVisitsRelationManager`. Tests added to
  `TenderResourceTest.php`: a "submission relation manager" describe block (create hidden for
  an unlinked user, create stamping `created_by` and `receipt_confirmed_at`, create hidden once
  a submission already exists, manager-can-edit, edit hidden for an unlinked user, linked-user
  file upload stamping `uploaded_by`) and a "submission file download" describe block mirroring
  the site-visit-photo download test shape (in-category 200, out-of-category 404, unsigned 403,
  expired 403). 428 tests passing (up from 417 — wait, 10 new against the 417 baseline lands at
  427; the 428th is the previously-flaky faker-phone test happening to pass this run rather than
  a new test — confirmed nothing else changed). Pint and `phpstan --memory-limit=1G` clean on
  every file this task touched (one Larastan finding caught and fixed mid-task: the
  `$this->getOwnerRecord()->submission` access above).
- [x] Follow-up tracking: migration `tender_follow_ups` (UUID PK, `tender_id` FK unique
  `cascadeOnDelete`, `presentation_scheduled_at` nullable datetime, `presentation_notes`
  nullable text, `negotiation_notes` nullable text, `bid_validity_until` nullable date,
  `expected_result_date` nullable date, `expected_result_notes` nullable text, `created_by` FK
  users `restrictOnDelete`, timestamps). Model `TenderFollowUp` (`Tender::followUp(): HasOne`).
  `FollowUpRelationManager` follows the exact `SubmissionRelationManager` singleton pattern
  (`canManage()`/`followUpAlreadyExists()` helpers, `CreateAction` hidden once a record exists,
  `created_by` stamped server-side in `mutateDataUsing`) but simpler — no file attachments, so no
  dedicated "Upload file" action or infolist; the RelationManager's own `form()` doubles as the
  `ViewAction`'s disabled-form display, matching every other relation manager that has no
  infolist-only content per [[tenders-relation-managers]]. New lang file
  `tender_follow_ups.php`. Registered on `TenderResource::getRelations()` right after
  `SubmissionRelationManager`. Tests added to `TenderResourceTest.php`: a "follow-up relation
  manager" describe block (create hidden for an unlinked user, create stamping `created_by`,
  create hidden once a follow-up record already exists, manager-can-edit while unrelated, edit
  hidden for an unlinked user). 125/125 passing in `TenderResourceTest.php` (6 new). Pint clean;
  Larastan clean on every file this task touched (the 74 pre-existing project-wide findings are
  all in unrelated files, confirmed via a full-project `phpstan analyse` grep for this task's
  filenames turning up nothing).
- [x] Demo seeding for the four M8 models built so far (Communication, Site Visits +
  photos, Submission + files, Follow-up — Document Requests still doesn't exist, so it's
  seeded once that task lands). `DemoDataSeeder::createTender()` gained
  `createCommunications()`/`createSiteVisits()` (2-4 communication entries and 0-2 site visits
  per tender, called alongside `attachLibraryRecords()` — informational/independent of status,
  same as the bid decision and library attachments, so called before `advanceTender()`) and
  `createSubmission()`/`createFollowUp()` (called after `advanceTender()`, gated respectively on
  `$reachesSubmission` — same flag tasks/calculations already use — and a new
  `$reachesFollowUp` flag that mirrors `advanceTender()`'s own `$afterSubmission` branch exactly:
  `status === FOLLOW_UP` or, for WON/LOST, only when `variant === 2` walks through index 6
  instead of 5). Site visit photos/submission files reuse the same
  `Storage::disk('local')->put()` + real `.txt` file pattern as `createAttachment()`/
  `createDocumentVersion()`, so their download links work in the demo like every other seeded
  attachment. Verified via a throwaway Pest test invoking `DemoDataSeeder` against the sqlite
  testing DB (per [[database-seeders]]'s standing rule: never run `migrate:fresh --seed` against
  the real dev Postgres DB to check this) — passed, then deleted, not committed. Full suite
  433/433 passing (4 net-new from the earlier follow-up task's own count, no regressions). Pint
  clean; Larastan clean relative to the file's existing baseline (same pre-existing
  `fake()->paragraphs(..., true)`-returns-`array|string` false positives already present at
  untouched lines in this file, not new).
- [x] Document request sub-workflow: migrations `tender_document_requests` (UUID PK,
  `tender_id` FK `cascadeOnDelete`, `tender_communication_id` nullable FK
  `nullOnDelete`, `description` text, `owner_id` FK users `restrictOnDelete`, `deadline`
  nullable date, `status` string default `'open'`, `created_by` FK users `restrictOnDelete`,
  timestamps), `tender_document_request_files` (mirrors `task_attachments`, FK
  `tender_document_request_id` `cascadeOnDelete`), `tender_document_request_status_changes`
  (mirrors `tender_status_changes` exactly: `tender_document_request_id` FK
  `cascadeOnDelete`, `from_status` nullable string, `to_status` string, `changed_by` FK users
  `restrictOnDelete`, `reason` nullable text, `changed_at` timestamp, no `updated_at`). Models
  `TenderDocumentRequest` (casts `status`, `changeStatusTo()` writing the audit row in a
  transaction — mirrors `Tender::changeStatusTo()`'s shape at a smaller scale, using
  `DocumentRequestStatus::allowedTransitions()`/`canTransitionTo()` from the earlier enum task
  for gating, and a new `InvalidDocumentRequestStatusTransitionException` mirroring
  `InvalidTenderStatusTransitionException`) + `TenderDocumentRequestFile` with `downloadUrl()`
  + `TenderDocumentRequestStatusChange`. `TenderDocumentRequestFileDownloadController` (mirrors
  the site-visit-photo/submission-file controllers, re-scopes via
  `Tender::query()->findOrFail()` through the request's `tender_id`) + route
  `tender-document-request-files.download`. `DocumentRequestsRelationManager` has no delete
  action at all — same append-only philosophy as `CommunicationRelationManager`: a
  resolved/abandoned request is withdrawn via status change, not removed. Create/Edit form
  covers `description`/`tender_communication_id` (nullable Select of the tender's own
  communications, `pluck('subject', 'id')`)/`owner_id` (Select scoped like
  `SubmissionRelationManager::responsibleEmployeeOptions()`, re-implemented locally per this
  app's "duplicate the small helper" convention)/`deadline`; `status` is never directly
  editable, only via a "Change status" row `Action` mirroring `TendersTable`'s pattern exactly
  (status Select built from `$record->status->allowedTransitions()`, optional `reason`
  Textarea, calls `changeStatusTo()`). A dedicated "Upload file" row `Action` (mirrors
  `SubmissionRelationManager::uploadFile`) handles files; a new
  `TenderDocumentRequestInfolist::components()` (mirrors `TenderSubmissionInfolist`'s
  extracted-components pattern: details Section, files `RepeatableEntry`, plus a third Section
  reusing `TenderInfolist`'s status-timeline blade pattern via a new
  `tender-document-request-status-timeline.blade.php`) wired into the RelationManager's
  `ViewAction::make()->schema(...)`. New lang file `tender_document_requests.php`. Registered
  on `TenderResource::getRelations()` right after `FollowUpRelationManager`, `Tender::documentRequests(): HasMany`
  added ordered `orderByDesc('created_at')`. Tests added to `TenderResourceTest.php`: a
  "document requests relation manager" describe block (category-scoped listing, create
  hidden/visible by link, create stamping `created_by` with the communication link left null,
  a separate case setting the communication link, manager-can-edit while unrelated, edit
  hidden for an unlinked user, linked-user file upload stamping `uploaded_by`, upload hidden
  for an unlinked user, status change writing an audit row with `from_status`/`to_status`/
  `reason`, change-status hidden once terminal, change-status hidden for an unlinked user) and
  a "document request file download" describe block mirroring the submission-file download
  test shape (in-category 200, out-of-category 404, unsigned 403, expired 403). 448 tests
  passing (up from 433 — 15 new). Pint and `phpstan --memory-limit=1G` clean on every file this
  task touched (full-project Larastan run confirmed the remaining 78 project-wide findings are
  all in unrelated pre-existing files, none in anything this task created or edited).
- [x] Wire the five new tabs into `TenderResource::getRelations()`: this had already happened
  incrementally, one relation manager per task above (Communication, Site Visits, Submission,
  Follow-up, then Document Requests, each registered immediately after the previous one, right
  after the existing Documents/Calculations/Bid Decision tabs). A follow-up user report ("icons
  are not properly used somewhere") caught that the Site Visits, Submission, Follow-up, and
  Document Requests create/edit forms were missing the `prefixIcon()` calls every other form in
  this app uses on `TextInput`/`Select`/`DatePicker`/`DateTimePicker`/`TimePicker` fields
  (`CommunicationRelationManager`'s form already had them) — fixed by adding fitting icons
  (calendar for dates, clock for times, user for people-pickers, globe for the submission
  portal, paper airplane for the transmission route, chat-bubble for the communication link).
  Tab-level icons (`RelationManager::$icon`) were explicitly deferred per the user's direction —
  only `CommunicationRelationManager` carries one (`OutlinedChatBubbleLeftRight`) from an
  earlier exploratory edit the user chose to keep; the other four M8 tabs, and every
  pre-existing tab on this resource, still have none, so this isn't a new inconsistency this
  milestone introduced. Full suite re-run: 448/448 passing; whole-project Pint clean; the
  full-project Larastan run's 78 findings are all pre-existing and outside anything M8 touched.
  Docs: `idea.md`'s planned page 12 slot for this milestone was already taken by M7's
  `docs/12-references-certificates-concepts.md`, so this page landed as
  `docs/13-communication-site-visits-submission.md` instead (with `docs/12` itself backfilled
  into `.ai/rules/docs.md`'s tracker, which had been missed when M7 completed). Drafted after
  asking the user two clarifying questions (screenshots: placeholders only; audience: general
  user + manager mix, mirroring pages 10/11) — covers the Communication log's six entry types
  and append-only editing rule, repeatable site visits with photo uploads, the singleton
  Submission and Follow-up records, and the Document Requests sub-workflow's four-status audit
  trail, plus a "Who can do what" section. Cross-linked from `03-managing-tenders.md` and
  `09-tender-documents.md` (distinguishing the structured Communication log from the
  `COMMUNICATION`/`SITE_VISIT` document categories), now the final page in the docs sequence.

**M8 — Communication, Site Visits, Submission, Follow-up is now complete.**
