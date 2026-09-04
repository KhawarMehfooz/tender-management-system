---
glob: "app/Models/TenderDocument.php,app/Models/TenderDocumentVersion.php,app/Enums/DocumentCategory.php,app/Filament/Resources/Tenders/RelationManagers/DocumentsRelationManager.php,database/migrations/*tender_documents*,database/migrations/*tender_document_versions*"
title: Tender document library (M4) — categories, versioning, locking, rights gating
---

# Tender documents

A versioned document store per tender ([[milestones]]'s M4). Two models:
`TenderDocument` (one row per logical document — `tender_id`, `category`, `title`,
`created_by`) and `TenderDocumentVersion` (one row per uploaded file — `tender_document_id`,
`version_number`, `file_path`/`original_filename`/`mime_type`/`size`, `uploaded_by`, no
`updated_at`, immutable by construction). `TenderDocument::currentVersion()` is a `HasOne`
ordered `orderByDesc('version_number')->limit(1)` — deliberately not `ofMany()`, which can't
run `MAX()` against this app's UUID PKs ([[models]]). Neither model registers its own
category-scoping global scope; both are only ever reached through the parent `Tender`
(already `ServiceCategoryScope`-scoped), same as `TaskAttachment` ([[scopes-models]]).

## Categories
`App\Enums\DocumentCategory`, 11 cases per idea.md's M4 brief: `TENDER_DOCUMENTS`,
`CALCULATION`, `CONCEPTS`, `EVIDENCE_DOCUMENTS`, `REFERENCES`, `BIDDER_QUESTIONS`,
`COMMUNICATION`, `SITE_VISIT`, `FINAL_BID_DOCUMENTS`, `RESULT`, `POST_ANALYSIS` — labels in
`lang/en/document_categories.php`, mirroring `DeadlineType`'s exact pattern ([[enums]]).

## Rights gating: only CALCULATION is see-prices gated
Only the `CALCULATION` category is gated behind the `see-prices` right (idea.md's explicit
example) — every other category is visible to anyone with tender access. This is enforced in
two independent places, per [[permissions]]'s "check it everywhere" rule:
- `DocumentsRelationManager`'s table query (`->modifyQueryUsing()`) excludes CALCULATION rows
  entirely for users without the right, and the category `Select`'s options list drops
  `CALCULATION` from what can be picked when creating a document.
- `TenderDocumentDownloadController` re-checks `Right::SEE_PRICES` on the parent document's
  category — the table-query gating doesn't protect a leaked/guessed signed download URL, so
  it must be re-checked at the point the file is actually served ([[controllers]]).

## Upload/delete authorization
Mirrors the existing Task-attachment pattern (`AttachmentsRelationManager`/
`Task::isLinkedTo()`), scoped to the tender instead of the task:
- **Upload / new version**: the tender's owner, any `tender_team_members` row
  (`Tender::linkedToDocuments(User $user)`), or a manager
  (`TenderForm::canManageTeam()` — team lead/department head/super admin).
- **Delete**: the version's uploader (`created_by` on the parent `TenderDocument`) or a
  manager, and only while the document is unlocked.

Every one of these is re-checked server-side in the action's `before()` via
`abort_unless(...)` — never `->visible()` alone ([[permissions]]).

## Locking on submission
When a tender's status reaches `SUBMISSION`, every `TenderDocument` that already exists at
that moment gets locked (no new version, no delete) via `Tender::lockDocuments(User $actor)`,
called from inside `changeStatusTo()`'s existing `DB::transaction()`. Documents created
*after* that point (e.g. Result, Post-analysis, further Communication in later milestones)
are untouched — they're new documents, not edits to already-submitted ones. Rejected
alternatives: locking only the `FINAL_BID_DOCUMENTS` category, and locking every category
tender-wide forever (the latter would block M8/M9 document uploads once built). See
[[milestones]]'s m4-documents-versioning.md file for the full build history.

## Downloads
`TenderDocumentVersion::downloadUrl()` and `TenderDocumentDownloadController` are documented
in [[controllers]] (short-lived signed URLs, `ServiceCategoryScope` re-check, see-prices
re-check) — read that file for the download path specifically.

## Docs
Documented for end users in `docs/09-tender-documents.md`. If categories, locking behavior,
or upload/delete rules change, update that page too — see [[docs]].
