---
paths:
  - 'app/Models/Tender.php,app/Models/TenderHardDeletion.php,app/Filament/Resources/Tenders/**'
  - 'app/Models/Tender.php,app/Models/TenderTeamMember.php,app/Filament/Resources/Tenders/**'
---

# Resources Tenders

## Archive/invalid are separate columns from TenderStatus; hard-delete logs before deleting
idea.md M1: tenders are never hard-deleted by normal flow, only archived or flagged invalid, and only an admin-gated action may hard-delete a true junk entry (logged with a reason first).

`is_archived`/`archived_at`/`archived_by` and `invalidity_reason`/`invalidated_at`/`invalidated_by` are plain columns on `tenders`, deliberately NOT in Tender's #[Fillable(...)] list — they're only ever written via Tender::archive()/unarchive()/markInvalid()/clearInvalidFlag(), which use forceFill(), never through form mass assignment. This is a separate axis from TenderStatus: a tender can be archived from any status, including a terminal one like `won` — don't fold this into changeStatusTo()'s transition map.

Hard delete: Tender::hardDelete($actor, $reason) writes a TenderHardDeletion row (tender_id/internal_id/title snapshot + deleted_by + reason + deleted_at) inside the same DB transaction before calling $this->delete(). TenderHardDeletion.tender_id intentionally has no FK constraint — the referenced tenders row won't exist once the method returns, so the log can't reference it via a real foreign key. TenderResource::canDelete()/canDeleteAny() stay false (blocking Filament's built-in DeleteAction/bulk delete for everyone) — the hard-delete path is a wholly separate custom Filament Action gated with ->visible(fn () => auth()->user()?->hasRole(RoleName::SUPER_ADMIN) ?? false) in TendersTable, not wired to canDelete() at all (per [[resources]]'s note that canDelete() isn't auto-wired to actions).

## Team assignment: owner_id + TenderTeamMember, gated by canManageTeam()
idea.md M2: one required owner (`Tender.owner_id`, NOT NULL FK, restrictOnDelete) plus any number of team members in functional roles (`App\Enums\TeamRole`: CALCULATION/CONCEPT/EVIDENCE_DOCUMENTS/QUALITY_CONTROL/FINAL_APPROVAL) via `tender_team_members` (HasMany pivot-with-attributes model `TenderTeamMember`, unique on tender_id+user_id+functional_role — many users can share a role, one user can hold several roles via multiple rows).

UI lives in a 6th TenderForm wizard Step ("Team"): owner Select + a `Repeater::make('teamMembers')->relationship()->defaultItems(0)` (defaultItems(0) is required — Repeater defaults to 1 empty row otherwise, which fails required-field validation on every unrelated form submission).

`TenderForm::canManageTeam()` gates both fields to team-lead/department-head/super-admin (`hasAnyRole`) — everyone else gets them `->disabled()` but still visible (read-only), never hidden. Belt-and-braces per [[permissions]]: CreateTender/EditTender's mutate hooks force owner_id back (to the creator on create, to the existing record's owner on edit) when the actor lacks the role, since disabled fields can still be tampered with client-side. The Repeater additionally uses `->dehydrated(fn () => canManageTeam())` so an unauthorized submission never reaches the HasMany relationship-save step at all (mutateFormDataBeforeCreate/Save can't stop relationship saves — those bypass the returned $data array entirely).

Owner/team-member Select options are scoped by the *acting user's* own service_category_id (null → all users), not by the tender's own service_category_id field read via Get() — a Get()-based cross-field filter inside options()/relationship() modifyQueryUsing is fragile in a multi-step wizard (evaluated at both mount and submit-time validation, can go stale/mismatched) and Filament tests bypass ->disabled() when calling fillForm(), surfacing the mismatch as spurious "selection is invalid" errors. Since a scoped user's tender is always forced into their own category anyway, filtering by the actor's category lands on the same result without the fragility.
