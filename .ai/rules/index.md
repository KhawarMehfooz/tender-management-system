# Project rules index

Maps file globs to rule files under `.ai/rules/`. Read every rule file whose glob covers the
path(s) you're about to touch before entering plan mode or editing/creating a file.

| Glob | Rule file | Covers |
|---|---|---|
| `**/*` | [non-negotiables.md](non-negotiables.md) | The 3 contract-level constraints: web-only + responsive, German-only UI, server-side permission enforcement |
| `app/Models/**`, `database/migrations/**`, `database/factories/**` | [data-integrity.md](data-integrity.md) | Never hard-delete tenders, structured enum/lookup fields (source, CPV, NUTS), explicit "unknown" over null |
| `resources/**`, `lang/**` | [i18n.md](i18n.md) | German-only UI strings, translation-key conventions |
| `app/Policies/**`, `app/Http/Controllers/**`, `app/Livewire/**` | [permissions.md](permissions.md) | Server-side rights enforcement patterns (roles vs. individually assignable rights) |
| `app/Filament/**` | [filament.md](filament.md) | Filament resource conventions tied to i18n, permissions, and data-integrity rules |

See [milestones.md](milestones.md) for current build scope and what's explicitly deferred.
