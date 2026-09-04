---
paths:
  - app/Providers/Filament/AdminPanelProvider.php
---

# Providers Filament

## Relation-manager Create/Edit/Delete actions are live on View pages, panel-wide
AdminPanelProvider sets ->readOnlyRelationManagersOnResourceViewPagesByDefault(false), overriding Filament's own default (true) which auto-hides RelationManager Create/Edit/Delete/Attach/etc. actions whenever the page is a ViewRecord subclass. Changed 2026-09-02 because users had to switch to Edit mode just to add a related record (e.g. a tender's Result), which added friction with no real security benefit — before flipping this, every RelationManager in the app was audited (see .ai/rules/filament-resources-tenders.md) to confirm none relies solely on the old read-only-on-View default for protection.

Known pre-existing gap found during that audit, NOT fixed by this change: ServiceCategoryResource and 5 other lookup-table resources (Sources, Sectors, Procurement Procedures, CPV Codes, NUTS Codes) have no canCreate()/canEdit()/canViewAny() override at all, despite docs/08-administration.md documenting them as "admin-managed" — unlike UserResource, which correctly gates via a canManage() => hasRole(SUPER_ADMIN) pattern. Any authenticated user can currently edit these lookup tables through their normal Edit pages already (independent of this panel setting). Needs a dedicated follow-up task to add UserResource-style gating to all 6.

When adding a new RelationManager: don't assume a ViewRecord page provides any protection — gate CreateAction/EditAction/DeleteAction/AttachAction explicitly with ->visible()/->before(fn () => abort_unless(...)) if the action should be restricted, following the canManage()-style pattern already used throughout app/Filament/Resources/Tenders/RelationManagers/*.
