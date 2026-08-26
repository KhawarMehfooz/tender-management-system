---
paths:
  - 'config/permission.php,database/seeders/**'
---

# Seeders

## Spatie Role/Permission need custom UUID-aware models
The package's own `Spatie\Permission\Models\Role`/`Permission` classes do NOT use `HasUuids`, even though their table's `id` column was hand-edited to `uuid` (per [[models]]). Using them directly causes `null value in column "id"` on insert, since a uuid column has no DB-level default.

Fix already applied: `App\Models\Role extends Spatie\Permission\Models\Role` and `App\Models\Permission extends Spatie\Permission\Models\Permission`, each `use HasUuids`, registered in `config/permission.php`'s `models` array. The seeder (`RolesAndPermissionsSeeder`) and any future code must import `App\Models\Role`/`App\Models\Permission`, never the raw `Spatie\Permission\Models\*` classes, or the UUID generation is bypassed again.
