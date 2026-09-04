---
paths:
  - 'app/Filament/**'
---

# App Filament

## Navigation groups: Master Data / Administration stay separate; System Settings is new and currently holds only Notification Preferences
Correction of an earlier rule that briefly consolidated everything into one "System Settings" group — the user reverted that (2026-08-27) and asked to keep it minimal: `lang/en/navigation.php`'s `groups.master_data` (Service Categories, Sectors, Sources, Procurement Procedures, CPV Codes, NUTS Codes) and `groups.administration` (Users, Roles & Permissions) stay exactly as they were, unchanged, no explicit sort added to Users/RolesAndPermissions. Only `NotificationPreferences` moved out, into a new `groups.system_settings` group (no sort needed, it's the only item there). That group is still the intended home for future global/app-wide settings pages the user plans to add — but don't fold Master Data or Administration into it, and don't add navigation sort values speculatively.
