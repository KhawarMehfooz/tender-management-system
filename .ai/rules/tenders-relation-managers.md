---
paths:
  - 'app/Filament/Resources/Tasks/RelationManagers/*,app/Filament/Resources/Tenders/RelationManagers/TasksRelationManager.php'
---

# Tenders Relation Managers

## A RelationManager's generic ViewAction shows the disabled form, not the resource's infolist
`Filament\Actions\ViewAction` (used in a RelationManager's `recordActions()`) defaults to `disabledSchema()` over the RelationManager's own `form()` — it has no idea a resource-level infolist (e.g. `TaskInfolist`) exists unless told. This silently hid comments, checklist display, dependencies, and status history from `TasksRelationManager`'s View action on a tender's Tasks tab (all infolist-only sections), even for a super admin — reported as "can't see comments anywhere."

Fix pattern: extract the infolist's component array into a reusable static method (`TaskInfolist::components(): array`, with `configure(Schema $schema)` now just `$schema->components(static::components())`), then pass it to the RelationManager's `ViewAction::make()->schema(fn (): array => TaskInfolist::components())`. `Action::schema()` takes a component array, not a `Schema` object, so `TaskInfolist::configure()` itself can't be passed directly.

Also bumped Create/Edit/View modal widths to `Width::SixExtraLarge` on this relation manager (was default/unset — too narrow for the full task form/infolist in a modal). Regression test: `TaskResourceTest.php`'s "tasks relation manager on a tender" group, "shows a task's comments in the view action modal" — uses `mountTableAction('view', ...)` + `assertMountedActionModalSee()`, not `assertSee()` (which only sees the outer table markup, not modal content).
