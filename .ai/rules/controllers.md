---
paths:
  - 'app/Models/TaskAttachment.php,app/Http/Controllers/TaskAttachmentDownloadController.php,routes/web.php'
---

# Controllers

## Task attachment downloads use short-lived signed URLs
`task-attachments.download` (routes/web.php) carries both `auth` and `signed` middleware. Every link to it must be generated via `TaskAttachment::downloadUrl()` (`URL::temporarySignedRoute(..., now()->addMinutes(5), ...)`), never `route('task-attachments.download', $record)` directly — a plain route() URL now 403s.

Why: the controller already enforces auth + `TaskTenderCategoryScope` re-derivation (see [[scopes-models]]), but that only protects the *session*. Signing means the URL itself expires in 5 minutes, so a leaked/cached/shared link (browser history, referrer header, a copied URL) stops working shortly after — defense in depth on top of, not instead of, the existing auth+scope check. User's explicit request, 2026-08-28.

Both call sites (`AttachmentsRelationManager`'s download action, `TaskInfolist`'s attachments table entry) go through `$record->downloadUrl()`. Tests: `TaskResourceTest.php`'s "attachment download" group covers a signed link working, an unsigned link being rejected (403), and an expired (>5 min old) signed link being rejected — using `$this->travel()` to simulate expiry.

## Docs
Attachment downloads are documented for end users in `docs/06-collaboration.md`. If the
download mechanism or its expiry changes, update that page too — see [[docs]].
