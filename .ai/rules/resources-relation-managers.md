---
paths:
  - 'app/Filament/Resources/**/Tables/*.php,app/Filament/Resources/**/RelationManagers/*.php'
---

# Resources Relation Managers

## More than 2 table record actions go behind an ActionGroup menu
If a table's `recordActions()` (or a RelationManager's) would list more than 2 actions, wrap them all — including ViewAction/EditAction — in a single `Filament\Actions\ActionGroup::make([...])` rather than listing them as separate inline buttons. Keeps row width consistent and matches the dropdown-menu pattern. See `TendersTable::configure()` for the original pattern and `TasksTable::configure()`/`TasksRelationManager::table()` for the same treatment applied to Task tables.
