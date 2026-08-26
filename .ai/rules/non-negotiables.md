---
glob: "**/*"
title: Contract-level non-negotiables
---

These three come directly from the client's original brief ("only apply if you can meet all
three") and override convenience or speed. Never trade them off silently — if a request seems
to require breaking one, stop and ask rather than proceeding.

## 1. Real, responsive web application — no desktop-only features
- Every core function must run in-browser, no local install, no "desktop version with a
  stripped-down web view."
- Must work on current Chrome, Edge, Safari, Firefox, responsive across desktop/laptop/
  tablet/phone.
- Complex calculation screens (M5) may be desktop-oriented, but tasks, approvals, and
  deadline monitoring (M2/M3) must genuinely work well on a phone — test at mobile widths,
  don't just rely on a framework's default responsiveness.

## 2. German-only UI (end state) — currently building in English via i18n
- The contractual end state: navigation, buttons, statuses, error messages, notifications,
  forms, filters, dashboards, emails, and exports are all in German.
- Right now the app is being built and populated in English while a human translator has not
  yet done the German pass. **Never hardcode UI strings** — every user-facing string goes
  through Laravel's translation helpers (`__()`, `trans()`, Blade `@lang`) against
  `lang/en/*.php`, with real English text as the value, using semantic keys (e.g.
  `tenders.status.in_review`), not English-sentence keys.
- AI must never write German text into translation files — that's reserved for a human
  translator later. See [i18n.md](i18n.md) for the staged workflow.
- Code identifiers (variables, methods, routes, DB columns) stay in English as normal; only
  user-facing text is (for now) English-via-translation-file, later swapped to German.

## 3. Permissions enforced server-side, never UI-only
- Roles control navigation/menus; individually assignable rights (see prices, see margins,
  see competitor data, execute final submission, view employee statistics) control data
  access independent of role.
- A hidden menu item or conditionally-rendered Blade/Livewire section is not a permission
  check. Every controller action, Livewire action, and query touching gated data must have
  its own policy/gate check.
- This matters most for: prices, margins, competitor data, employee statistics, final
  submission. Treat a missing server-side check on these as a bug, not a UI nicety.
- See [permissions.md](permissions.md) for implementation patterns.
