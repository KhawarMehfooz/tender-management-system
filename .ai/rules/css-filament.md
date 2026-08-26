---
paths:
  - 'app/Filament/**,resources/views/filament/**,resources/css/filament/**'
---

# Css Filament

## Custom Filament theme required for any Tailwind class in custom views
The admin panel now has a custom theme at resources/css/filament/admin/theme.css, registered via ->viteTheme() in AdminPanelProvider and as an entry in vite.config.js's input array. Without this, Filament's default compiled CSS only contains styles for its own built-in components — any raw Tailwind utility class written in app/Filament/** or resources/views/filament/** (custom Blade partials, ViewEntry/ViewField views, etc.) silently does nothing, no error, just unstyled markup. This bit us once building the tender status-history timeline.

The theme.css has @source directives covering app/Filament/**/* and resources/views/filament/**/*, so any Tailwind class used in those paths will compile in — but only literal class strings. Tailwind's scanner cannot see runtime-interpolated class names like `"bg-{$variable}-500"`; map dynamic values (e.g. TenderStatus::color()) to a fixed literal string per branch (a match expression returning literal `'bg-success-500'` etc.) inside the Blade/PHP file that's actually scanned.

After adding/changing Tailwind classes in a custom Filament view, run `npm run build` (host machine has npm; the app Docker container does not) to recompile resources/css/filament/admin/theme.css before the change is visible.
