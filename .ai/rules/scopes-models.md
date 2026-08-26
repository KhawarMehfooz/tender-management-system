---
paths:
  - 'app/Models/Tender.php,app/Models/Scopes/**,app/Models/User.php'
---

# Scopes Models

## Category-scoped views key off User.service_category_id being null, not role
Tender::booted() registers a global scope (App\Models\Scopes\ServiceCategoryScope) that restricts every Tender query to auth()->user()->service_category_id when it's set, and no-ops when it's null or there's no authenticated user (console/seeders/jobs). A null service_category_id means "management-level, spans all categories" (matches the seeded super admin, who has none) — this is deliberately keyed off the nullable FK already on users, not off RoleName, since idea.md M1 doesn't pin a role→management mapping and the schema already encodes it.

Because this is a query-level scope, write paths must enforce it separately: TenderForm's service_category_id Select defaults to and disables on the user's own category when scoped, and CreateTender::mutateFormDataBeforeCreate()/EditTender::mutateFormDataBeforeSave() force service_category_id back to the user's own category regardless of submitted value (same belt-and-braces pattern as the see-prices right — never trust a disabled Filament field alone).

A scoped user hitting a foreign tender's view/edit route gets a hard ModelNotFoundException (404) via the global scope, not a hidden-but-reachable page — see TenderResourceTest's "category-scoped views" group for the reference tests.

## Child models with no service_category_id of their own still need their own scope
Task has no service_category_id column — it inherits category scoping from its parent Tender. That's automatic for relation-manager access (`$tender->tasks()`, since the parent Tender was already fetched through Tender's own ServiceCategoryScope) but NOT automatic for a standalone resource that queries the child model directly (TaskResource's ListTasks queries Task::query()). Task::booted() registers its own App\Models\Scopes\TaskTenderCategoryScope, which re-derives the same restriction via `whereRelation('tender', 'service_category_id', $user->service_category_id)`. Any future child-of-Tender model with its own top-level Filament resource (not just a relation manager) needs the same pattern — don't assume the parent's scope is enough.
