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
- [ ] Tender model: ~25 master fields, internal ID scheme — not started.
- [ ] Lifecycle state machine: 8-phase status flow + status-change audit log — not started.
- [ ] Field-level rights enforcement wired into the Tender resource once it exists.

Update the checklist above as work lands, and flip the milestone line when M1's acceptance
points (idea.md) are all met. Don't build ahead into a later milestone's scope without the
user asking for it explicitly — e.g. don't wire up the M5 calculation engine while M1 is still
in progress, even if it seems convenient.

M13 (import connectors, AI-assisted extraction) is explicitly deferred — don't build it, but
per idea.md's architectural note, don't make first-class structured data choices in earlier
milestones (documents, deadlines/terms, certificates) that would make it harder to bolt on
later.
