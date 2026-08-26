---
glob: "**/*"
title: Milestone scope tracker
---

Full milestone specs live in [idea.md](../../idea.md) (M1–M13). This file just tracks where
the build currently stands so an assistant knows what's in scope right now vs. not-yet-built
vs. explicitly deferred.

**Current milestone: M1 — Foundation** (auth, permissions, service categories, tender master
data, lifecycle state machine).

Progress within M1:
- [x] Roles & rights: `spatie/laravel-permission` (UUID-adapted, see [[models]]/[[seeders]]),
  `App\Enums\RoleName` (9 roles) / `App\Enums\Right` (5 rights), `RolesAndPermissionsSeeder`,
  `Gate::before` super-admin bypass in `AppServiceProvider`.
- [x] Service categories: `ServiceCategory` model/migration/Filament resource, admin-managed,
  no hard-delete (deactivate via `active` flag instead — see [[resources]]), seeded via
  `ServiceCategorySeeder`.
- [x] Lookup tables: `Source`, `CpvCode`, `NutsCode` models/migrations/Filament resources, all
  admin-managed (add/edit/deactivate via `active` flag, no hard-delete, same pattern as
  [[resources]]'s ServiceCategory). `NutsCode` is self-referencing (`parent_id`) to model the
  country > state > region > district hierarchy. Each has a starter dev seed (real German
  procurement portal names for Source; a small CPV/NUTS subset) plus `import:cpv-codes` /
  `import:nuts-codes` Artisan commands that upsert from a CSV placed at
  `database/data/{cpv,nuts}_codes.csv` — seeders auto-detect the file and use it instead of the
  dev subset. Full official lists are now imported: 9,454 CPV codes (SIMAP/TED `cpv_2008` XLS,
  exported to CSV) and 1,971 NUTS codes (Eurostat/GISCO `NUTS_AT_2024.csv` attribute table,
  using its `NAME_LATN` column for Latin-script labels since `NUTS_NAME` keeps native scripts
  for EL/BG/etc; `level`/`parent_code` derived from `NUTS_ID` length/prefix, not present in the
  source file). Both CSVs live at `database/data/` — re-running `migrate:fresh --seed` picks
  them up automatically.
- [x] Tender model: `Tender` model/migration/factory with ~27 master fields (see
  `app/Models/Tender.php`), plus its two remaining lookup dependencies `Sector` and
  `ProcurementProcedure` (same admin-managed pattern as [[resources]]'s Source, each seeded
  with an explicit "Unknown" row). `ServiceCategory` gained a `code` column (3-4 uppercase
  letters, nullable) used as the tender-ID prefix. Internal ID scheme:
  `{ServiceCategory.code}-{year}-{sequence}` (e.g. `SEC-2026-0001`), sequence resets per
  category per year, generated via a `creating` model event backed by a DB-locked
  `tender_number_sequences` counter table (race-safe, survives future hard-deletes).
  `estimated_contract_volume_unknown` is a boolean companion flag (not a null/sentinel value)
  per the explicit-unknown rule in [[data-integrity]]. `service_category_id`/`sector_id`/
  `procurement_procedure_id`/`source_id` use `restrictOnDelete` (required stats-bearing
  dimensions); `nuts_code_id`/`cpv_code_id` stay nullable+`nullOnDelete` (optional
  classification). `TenderResource` built as a 5-step Filament Wizard (basic info; location &
  classification; dates & deadlines; contract terms; source & notes) per [[resources]]'s
  size/grouping rule — List/Create/Edit only, no delete path (tenders are never hard-deleted,
  see [[data-integrity]]).
- [x] Lifecycle state machine: `TenderStatus::allowedTransitions()`/`canTransitionTo()` encode
  the transition map — the 7 active phases (intake→review→decision→processing→quality→
  submission→follow-up) only move forward one step at a time (no skipping, no going back);
  `cancelled`/`not-evaluated`/`excluded` are reachable from any active phase; `won`/`lost` only
  from `submission`/`follow-up` (a bid must exist first); terminal statuses have no further
  transitions. `Tender::changeStatusTo()` enforces the map (throws
  `InvalidTenderStatusTransitionException` otherwise) and, in the same DB transaction, writes
  an audit row to `tender_status_changes` (`TenderStatusChange` model: from/to status, actor
  `changed_by`, optional `reason`, `changed_at` — immutable, no `updated_at`). UI: per
  [[resources]]'s decision, status is no longer editable via the create/edit wizard (removed
  from `TenderForm`; DB still defaults new tenders to `intake`) — instead a dedicated
  "Change status" table row action (`TendersTable`) offers only the record's currently-valid
  next statuses via a modal (status select + optional reason), hidden once a tender reaches a
  terminal status. A `TenderInfolist`/`ViewTender` page now exists (route `view`, reachable via
  a `ViewAction` on the table and on `EditTender`'s header) showing an overview section and a
  "Status history" section (`RepeatableEntry` over the `statusChanges` relation: changed_at,
  from/to, actor, reason) — this is the audit log's only UI surface right now.
- [x] Field-level rights enforcement wired into the Tender resource: `estimated_contract_volume`
  (the only price-bearing field on `Tender` today — margins/competitor-data/employee-stats
  fields don't exist until M5/M10/M11, so `see-margins`/`see-competitor-data`/
  `view-employee-statistics` have nothing to gate yet on this resource) is gated behind the
  `see-prices` right (`App\Enums\Right`) in three places: `TenderForm` (both the amount field
  and its `_unknown` companion toggle hidden via `->visible()`), `TenderInfolist` (a new
  gated `TextEntry` on the Overview section, formatted as either the money value or "unknown"),
  and — since a Livewire request can smuggle a value into a hidden field's state — server-side
  stripping in `CreateTender::mutateFormDataBeforeCreate()` /
  `EditTender::mutateFormDataBeforeSave()` that unsets both keys whenever the acting user lacks
  the right, per [[permissions]]'s "never trust UI hiding alone" rule. `execute-final-submission`
  has no action to gate yet (final submission doesn't exist until M5/M8). Tests in
  `TenderResourceTest` cover: field hidden/visible on the form by right, smuggled value stripped
  on both create and edit, and the view-page entry hidden/visible by right.
- [x] Category-scoped views: `App\Models\Scopes\ServiceCategoryScope`, a global scope on
  `Tender` (registered in `Tender::booted()`), restricts every query to
  `auth()->user()->service_category_id` when it's set; a `null` category (the seeded super
  admin's state) means management-level and spans all categories — see [[scopes-models]] for
  the full rationale and why this is keyed off the nullable FK rather than role. Write-side
  enforced too: `TenderForm`'s category Select defaults to and disables on the user's own
  category when scoped, and `CreateTender`/`EditTender` force `service_category_id` back to it
  regardless of submitted value. A scoped user hitting a foreign tender's view/edit route gets
  a `ModelNotFoundException` (404), not a hidden-but-reachable page. Tests in
  `TenderResourceTest`'s "category-scoped views" group cover: management sees all, a scoped
  user sees only their own, foreign view/edit blocked, and create is pinned to the user's own
  category despite a different submitted value.
- [ ] Archive/invalid field: idea.md M1 requires archiving/flagging-invalid to be its own field
  (e.g. `is_archived`/`invalidity_reason`), separate from `TenderStatus` — a won tender must
  stay archivable later, so this can't be folded into `changeStatusTo()`'s transition map. Not
  built yet — no such column exists on `tenders` today.
- [ ] Admin hard-delete of junk tenders: idea.md M1 requires a distinct, admin-gated hard-delete
  action (logged reason: who/when/why, captured before the row is removed) for true
  technical-junk entries. `TenderResource::canDelete()`/`canDeleteAny()` currently return
  `false` unconditionally (see [[resources]]) — that's the correct default for the regular
  resource, but the separate admin path doesn't exist yet.

These two are the remaining open M1 acceptance points.

Update the checklist above as work lands, and flip the milestone line when M1's acceptance
points (idea.md) are all met. Don't build ahead into a later milestone's scope without the
user asking for it explicitly — e.g. don't wire up the M5 calculation engine while M1 is still
in progress, even if it seems convenient.

M13 (import connectors, AI-assisted extraction) is explicitly deferred — don't build it, but
per idea.md's architectural note, don't make first-class structured data choices in earlier
milestones (documents, deadlines/terms, certificates) that would make it harder to bolt on
later.
