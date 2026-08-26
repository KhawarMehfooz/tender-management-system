---
glob: "app/Policies/**,app/Http/Controllers/**,app/Livewire/**"
title: Server-side permission enforcement (roles vs. rights)
---

This project has two independent permission axes (per idea.md M1) — don't collapse them into
one.

## Roles (navigation/menu scope)
Super admin, department head, team lead, calculation, concept writer, documentation, quality
control, staff, read-only/viewer. Roles decide what shows up in navigation and which broad
areas a user lands in.

## Individually assignable rights (data access, independent of role)
See prices, see margins, see competitor data, execute final submission, view employee
statistics. A user's role does not imply they have these rights, and a user without the
matching role can still hold one of these rights if explicitly granted.

## Enforcement rules
- Every policy/gate check for a right (e.g. `see-margins`) must be evaluated in the
  controller/Livewire action and in the query/resource layer that assembles the response —
  never assume the Blade `@can` conditional guarding the UI element is sufficient.
- When adding a new field or endpoint that surfaces prices, margins, competitor data, or
  employee statistics, add the corresponding policy check before wiring up the UI, not after.
- Category-scoped views (M1: management can span all service categories; category-level
  users stay scoped to their category) are also a server-side filter, not just a UI toggle —
  scope queries by the user's accessible categories, don't trust a client-supplied category
  filter alone.
- When writing a Feature test for a gated action, include a test that a user *without* the
  right gets rejected server-side (403/redirect), not only a test that a user with the right
  succeeds.
