---
paths:
  - 'app/Enums/**'
---

# Enums

## Enum case names are SCREAMING_SNAKE_CASE, not TitleCase
This project overrides the global Boost PHP convention ("TitleCase for Enum keys") deliberately: enum case identifiers use SCREAMING_SNAKE_CASE (e.g. `case SEE_PRICES`, `case SUPER_ADMIN`), not TitleCase (`case SeePrices`). Backed values stay lowercase-kebab-case (e.g. `'see-prices'`, `'super-admin'`) since those are the literal strings stored in the DB (Spatie role/permission names, etc.) and used as translation keys in `lang/en/*.php`. Apply this to every new enum in `app/Enums/`, not just RoleName/Right.
