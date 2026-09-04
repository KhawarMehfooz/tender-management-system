---
paths:
  - 'app/Console/Commands/Import*.php,database/seeders/*CodeSeeder.php'
---

# Commands Seeders

## Bulk-code lookup tables import from CSV, never hardcode rows in seeders
CPV codes (~9k rows) and NUTS codes (~1.5k rows, hierarchical) are official EU reference lists — never hand-write them into a seeder as PHP array literals. Instead: an `import:cpv-codes {file}` / `import:nuts-codes {file}` Artisan command upserts from a CSV (code,label for CPV; code,label,level,parent_code for NUTS, imported lowest-level-first so parent_id resolves). Their seeders (`CpvCodeSeeder`/`NutsCodeSeeder`) check for a file at `database/data/{cpv,nuts}_codes.csv` and call the import command if present, otherwise fall back to a small dev subset. Source URLs to hand-download from: CPV via ted.europa.eu/en/simap/cpv (SIMAP), NUTS via ec.europa.eu/eurostat/web/nuts (Eurostat correspondence tables) — Eurostat doesn't ship a ready-made flat CSV, so the download needs light reshaping into the code,label,level,parent_code format before import.
