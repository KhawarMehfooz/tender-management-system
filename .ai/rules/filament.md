---
glob: "app/Filament/**"
title: Filament resources — project-specific rules
---

Filament (v5, see CLAUDE.md's Boost-generated `filament/filament/core rules` section for
generic namespace/artisan conventions) is the admin/CRUD layer for tenders, tasks, documents,
etc. Filament's own conventions don't automatically satisfy this project's non-negotiables —
apply these on top.

## i18n (see [i18n.md](i18n.md))
- Every form field, table column, filter, action, and page has a human-readable label — never
  let Filament auto-generate a label from the property name for user-facing text. Set
  `->label(__('tenders.fields.title'))` explicitly, keyed against `lang/en/*.php`.
- Status/enum values shown as badges or select options must resolve through translation keys
  too (e.g. via an enum's `getLabel()` returning `__(...)`), not the raw enum case name.

## Server-side permissions (see [permissions.md](permissions.md))
- Filament's resource-level `canViewAny`/`canCreate`/`canEdit`/`canDelete` authorization
  hooks are necessary but not sufficient — they gate the resource as a whole, not our
  individually-assignable rights (see prices, see margins, see competitor data, execute final
  submission, view employee statistics).
- Any column/field carrying gated data (price, margin, competitor info, employee stats) must
  additionally be conditionally shown/hidden via `->visible(fn () => auth()->user()->can(...))`
  **and** excluded at the query/form-fill level for users without the right — a column merely
  hidden in the table UI can still leak through the edit form's underlying state or an export
  action unless the right is also checked there.
- Wire every custom Filament Action (bulk or row-level) through its own policy check; don't
  rely on the resource's blanket `canEdit`/`canDelete` for an action with narrower
  requirements (e.g. "execute final submission").

## Data integrity (see [data-integrity.md](data-integrity.md))
- Never add a `DeleteAction` / bulk delete action to a Tender resource that performs a real
  delete. Tenders are archived/flagged invalid, not hard-deleted, from the Filament UI same as
  anywhere else. A hard-delete path exists only behind an explicit admin-gated custom action
  that requires and logs a reason.
- Source, CPV code, and NUTS code fields use `Select`/lookup-backed components against the
  structured enum/lookup tables, never a plain `TextInput` free-text field.
- Fields feeding later statistics need an explicit "Unknown" option in their `Select`, not an
  unselected/nullable state left blank.
