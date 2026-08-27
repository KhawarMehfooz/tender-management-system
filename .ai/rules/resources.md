---
paths:
  - 'app/Filament/Resources/**'
---

# Resources

## Choose a semantically fitting navigationIcon, never the generator default
`make:filament-resource` always scaffolds `$navigationIcon` as `Heroicon::OutlinedRectangleStack` regardless of the resource's subject. Never leave this default — pick the `Filament\Support\Icons\Heroicon` case that actually matches the resource's real-world concept (e.g. `OutlinedTag` for a category/classification resource, `OutlinedUserGroup` for people, `OutlinedDocumentText` for documents, `OutlinedCalendarDays` for deadlines/scheduling).

Check available cases with `grep -n "case Outlined" vendor/filament/support/src/Icons/Heroicon.php` (filter by keyword) before picking one, rather than guessing a case name that may not exist.

## Resource canDelete()/canEdit()/canView() are NOT auto-wired to actions
Confirmed by reading vendor/filament/actions/src/DeleteAction.php and searching the whole Filament vendor tree: `Filament\Actions\DeleteAction`/`EditAction`/`ViewAction` placed manually (in a page's `getHeaderActions()`, or a table's `recordActions()`) never automatically call the Resource's `canDelete()`/`canEdit()`/`canView()` overrides. Those Resource methods only gate the resource's own page-mount access checks (`authorizeAccess()` on Create/Edit/View pages) — there is no equivalent auto-check for an inline action.

Consequence already hit once: `ServiceCategoryResource::canDelete()`/`canDeleteAny()` were overridden to return `false`, but `EditServiceCategory`'s header still had a bare `DeleteAction::make()` with zero wiring to that override — any authenticated user could actually delete the record through it.

Rule: for any resource where deletion (or edit/view) must be blocked, do NOT rely on the Resource-level `can*()` override alone. Either remove the corresponding `DeleteAction`/`EditAction`/`ViewAction` from every page/table that has it, or explicitly chain `->authorize(fn ($record) => Resource::canDelete($record))` (or `->visible(...)`) on that specific action instance. Check every page (`List*`, `Create*`, `Edit*`, `View*`) and the table's `recordActions()`/`toolbarActions()` — not just the one you're currently editing.

## Ask about a Wizard before building a large form; design forms evenly
Before building a Filament resource form, check its size: if it has roughly 7-8+ individual inputs, or spans 2+ logical groupings (e.g. basic info + calculation details + approval fields), stop and ask the user whether it should be a `Filament\Schemas\Components\Wizard` (multi-step) instead of one long form/Section stack. Don't decide this unilaterally.

Regardless of wizard vs. single form, design every form/infolist evenly rather than stacking everything in one column:
- Group related fields under a labeled `Section` (with a fitting icon per [[resources]]'s navigation-icon rule) instead of one flat field list.
- Use `Section::columns(2)` (or more) to pair short fields side-by-side (toggles, short text inputs, selects) rather than each taking its own full-width row.
- Reserve `columnSpanFull()` for genuinely long fields (textarea, rich text, repeaters).
- The `ServiceCategoryForm`/`ServiceCategoryInfolist` redesign (Section + icon + balanced 2-column layout, secondary collapsible "Record history" section for timestamps) is the reference example to follow for future resources.

## Filament file uploads: preserveFilenames + preventFilePathTampering + server-derived metadata
For any FileUpload field backing a persisted attachment/document row: use ->preserveFilenames() so the stored path's basename is the display filename (no separate client-supplied filename field to trust), and ->preventFilePathTampering() so a create form can't accept a smuggled arbitrary existing-disk path standing in for a fresh upload. Derive mime_type/size server-side via Storage::mimeType()/size() in the mutate hook rather than trusting client-reported values. Default to a private disk (e.g. 'local' → storage/app/private) for anything that may carry price/evidence-bearing documents — never the 'public' disk. Serve downloads through a dedicated authenticated controller/route that re-applies the parent model's scope (e.g. Task::query()->findOrFail()) before streaming via Storage::download(), so category/permission scoping produces a 404 rather than a hidden-but-reachable public URL. See TaskAttachment / AttachmentsRelationManager / TaskAttachmentDownloadController for the reference implementation.
