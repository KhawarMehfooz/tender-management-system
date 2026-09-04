---
paths:
  - 'app/Models/TaskAttachment.php,app/Http/Controllers/TaskAttachmentDownloadController.php,routes/web.php'
  - 'app/Models/TenderDocumentVersion.php,app/Http/Controllers/TenderDocumentDownloadController.php,routes/web.php'
---

# Controllers

## Task attachment downloads use short-lived signed URLs
`task-attachments.download` (routes/web.php) carries both `auth` and `signed` middleware. Every link to it must be generated via `TaskAttachment::downloadUrl()` (`URL::temporarySignedRoute(..., now()->addMinutes(5), ...)`), never `route('task-attachments.download', $record)` directly — a plain route() URL now 403s.

Why: the controller already enforces auth + `TaskTenderCategoryScope` re-derivation (see [[scopes-models]]), but that only protects the *session*. Signing means the URL itself expires in 5 minutes, so a leaked/cached/shared link (browser history, referrer header, a copied URL) stops working shortly after — defense in depth on top of, not instead of, the existing auth+scope check. User's explicit request, 2026-08-28.

Both call sites (`AttachmentsRelationManager`'s download action, `TaskInfolist`'s attachments table entry) go through `$record->downloadUrl()`. Tests: `TaskResourceTest.php`'s "attachment download" group covers a signed link working, an unsigned link being rejected (403), and an expired (>5 min old) signed link being rejected — using `$this->travel()` to simulate expiry.

## Docs
Attachment downloads are documented for end users in `docs/06-collaboration.md`. If the
download mechanism or its expiry changes, update that page too — see [[docs]].

## Tender document downloads use short-lived signed URLs, plus a see-prices check
See [[documents]] for the document library's categories, versioning, locking, and
upload/delete authorization — this section covers only the download path.

`tender-documents.download` (routes/web.php) carries `auth`+`signed` middleware. Every link must go through `TenderDocumentVersion::downloadUrl()` (`URL::temporarySignedRoute(..., now()->addMinutes(5), ...)`), never a plain `route()` call — mirrors [[controllers]]'s TaskAttachment pattern.

The controller re-runs `Tender::query()->findOrFail($document->tender_id)` so `ServiceCategoryScope` turns an out-of-category request into a 404. It additionally `abort_unless`s on `Right::SEE_PRICES` when the parent `TenderDocument`'s category is `CALCULATION` — the only place besides `DocumentsRelationManager`'s `modifyQueryUsing()` that this gate is enforced, since a signed download URL bypasses the table query entirely if leaked/guessed.

`DocumentsRelationManager`'s recordActions download `Action` uses `->url(fn ($record) => $record->currentVersion->downloadUrl())->openUrlInNewTab()` and is hidden when `currentVersion` is null (no version uploaded yet).

Tests: `TenderResourceTest.php`'s "document download" describe group covers in-category 200, out-of-category 404, CALCULATION without see-prices 403, CALCULATION with see-prices 200, unsigned link 403, expired (>5min via `$this->travel()`) link 403.

## Docs
Tender document downloads are documented for end users in `docs/09-tender-documents.md`. If
the download mechanism or its expiry changes, update that page too — see [[docs]].
