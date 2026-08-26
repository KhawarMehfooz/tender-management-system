---
paths:
  - 'app/Models/Tender.php,app/Models/TenderHardDeletion.php,app/Filament/Resources/Tenders/**'
---

# Resources Tenders

## Archive/invalid are separate columns from TenderStatus; hard-delete logs before deleting
idea.md M1: tenders are never hard-deleted by normal flow, only archived or flagged invalid, and only an admin-gated action may hard-delete a true junk entry (logged with a reason first).

`is_archived`/`archived_at`/`archived_by` and `invalidity_reason`/`invalidated_at`/`invalidated_by` are plain columns on `tenders`, deliberately NOT in Tender's #[Fillable(...)] list — they're only ever written via Tender::archive()/unarchive()/markInvalid()/clearInvalidFlag(), which use forceFill(), never through form mass assignment. This is a separate axis from TenderStatus: a tender can be archived from any status, including a terminal one like `won` — don't fold this into changeStatusTo()'s transition map.

Hard delete: Tender::hardDelete($actor, $reason) writes a TenderHardDeletion row (tender_id/internal_id/title snapshot + deleted_by + reason + deleted_at) inside the same DB transaction before calling $this->delete(). TenderHardDeletion.tender_id intentionally has no FK constraint — the referenced tenders row won't exist once the method returns, so the log can't reference it via a real foreign key. TenderResource::canDelete()/canDeleteAny() stay false (blocking Filament's built-in DeleteAction/bulk delete for everyone) — the hard-delete path is a wholly separate custom Filament Action gated with ->visible(fn () => auth()->user()?->hasRole(RoleName::SUPER_ADMIN) ?? false) in TendersTable, not wired to canDelete() at all (per [[resources]]'s note that canDelete() isn't auto-wired to actions).
