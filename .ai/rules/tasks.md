---
paths:
  - 'app/Models/Task.php,database/migrations/*task_dependencies*,app/Filament/Resources/Tasks/**'
---

# Tasks

## Task dependencies: self-referencing BelongsToMany, cycle prevention via modifyQueryUsing
idea.md M2: tasks can depend on other tasks; a task cannot be marked DONE until all its dependencies are DONE (Task::changeStatusTo() throws TaskDependenciesNotCompleteException, guarded belt-and-braces since TasksTable::changeStatusAction() also filters DONE out of the status options when !$record->dependenciesComplete()).

`task_dependencies` pivot (task_id/depends_on_task_id, both cascadeOnDelete) uses a composite primary key on both columns — no own uuid `id` column — to sidestep the known task_participants pivot-uuid bug ([[migrations]]) entirely rather than needing a custom Pivot/HasUuids class. Task::dependencies()/dependents() are self-referential belongsToMany over this pivot.

Cycle/self-dependency prevention is NOT a custom validation rule — it's baked into the `dependencies` Select field's `relationship(modifyQueryUsing: ...)` on TaskForm, which excludes $record's own id plus Task::transitiveDependentIds() (BFS over `dependents()`) from the query. Per Filament docs, `relationship()`'s modifyQueryUsing IS a real security boundary (submitted values are validated against it), unlike a table's presentational modifyQueryUsing/tableArguments. Dependencies are also scoped to the same tender only, via `$get('tender_id')` (standalone TaskResource form, which needs `->live()` on the tender_id Select) or an explicit `$tenderId` param threaded through `TaskForm::configure()` (TasksRelationManager, which has no tender_id field and passes `$this->getOwnerRecord()->getKey()`).

Test-writing trap: Filament's `in` validation rule for a `multiple()` Select is applied per-array-item at `{field}.*`, not at `{field}` — a rejected dependency shows up as a form error at `dependencies.0`, not `dependencies`. `assertHasFormErrors(['dependencies'])` will wrongly report no error; use `assertHasFormErrors(['dependencies.0'])`.
