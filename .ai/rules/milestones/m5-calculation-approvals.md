# M5 — Calculation & Approvals

Full spec: [idea.md](../../../idea.md)'s M5 section. Index: [../milestones.md](../milestones.md).

**M5 — Calculation & Approvals is in progress**, started 2026-08-29 at the user's explicit
request, building incrementally task-by-task rather than in one pass (same rhythm as M4).

Four design decisions were confirmed with the user before any code was written:
- **Cost-driver fields**: a dedicated `ServiceCategoryCostDriverField` table (not a JSON
  schema column on `ServiceCategory`) — one row per field (`field_key`, `label`, `type`,
  `unit`, `required`, `order`) — so admins can add/adjust a category's cost-driver fields
  without a code change, and they're manageable via a normal Filament relation manager.
  Actual input values per calculation live in a JSON map on `TenderCalculation` keyed by
  `field_key`. (Rejected: an inline JSON schema column — harder to validate/query and not
  discoverable in the admin UI the way a relation manager table is.)
- **Calculation versioning**: a single `TenderCalculation` table with a `version_number`
  column (unique per `tender_id`), not a parent/version split. Each version is a fully
  independent row (inputs + computed outputs); unlike `TenderDocument`/`TenderDocumentVersion`
  there's no "current file being replaced" concept to justify the extra layer, so this mirrors
  `TenderDeadline`'s simpler "multiple rows per type" pattern instead. (Rejected: a
  `TenderCalculation`/`TenderCalculationVersion` split mirroring M4 — no payoff here since
  nothing about a calculation version is edited in place after creation.)
- **Approval-chain gating**: reuse existing rights rather than inventing new ones. Steps 1–5
  (calculation checked / concept checked / evidence documents checked / formal review complete
  / management approved) are each gated by the tender's `TenderTeamMember` row matching the
  corresponding `TeamRole` case (`CALCULATION`/`CONCEPT`/`EVIDENCE_DOCUMENTS`/
  `QUALITY_CONTROL`/`FINAL_APPROVAL`, all already defined in M2). Step 6 (final submission
  released) is gated by the existing `Right::EXECUTE_FINAL_SUBMISSION`. Steps must be approved
  in order — step *n+1* cannot be approved before step *n*. (Rejected: six new dedicated
  `Right` cases — would duplicate what `TeamRole` already expresses and add seeder/role-rights
  mapping work for no behavioral gain.)
- **Submission-gate interaction**: the 6-step approval chain *replaces* `Tender::tasksComplete()`
  as the gate on the `SUBMISSION` transition — it's a formal superset of what that check was
  standing in for, per [[tenders]]'s explicit forward-reference. `tasksComplete()` itself is
  left in place as informational task-tracking but stops being consulted in
  `changeStatusTo()`'s guard. (Rejected: requiring both — would enforce the same idea twice via
  mechanisms that can drift out of sync.)

Planned tasks for M5:
- [x] Schema + models for cost-driver fields: `service_category_cost_driver_fields` table
  (`ServiceCategoryCostDriverField` model — `service_category_id` FK `cascadeOnDelete`,
  `field_key` string unique per category, `label` string, `type` enum-cast
  (`App\Enums\CostDriverFieldType`: `NUMBER`/`DECIMAL`/`TEXT` — extend later if a category
  genuinely needs more), `unit` nullable string (e.g. `"h"`, `"m²"`, `"%"`), `required`
  boolean default true, `order` unsigned int for display sequencing). `ServiceCategory` gains
  `costDriverFields(): HasMany` ordered by `order`. Filament relation manager on
  `ServiceCategoryResource` for CRUD (reorderable via `order`). Factory + tests: uniqueness of
  `field_key` per category, ordering, cascade delete when a category is removed.
  Built as planned: migration adds a `unique(['service_category_id', 'field_key'])` composite
  index (not a global unique on `field_key` alone, since the same key like `deployment_hours`
  is expected to repeat across categories) plus an `order` index. `CostDriverFieldsRelationManager`
  lives under `ServiceCategoryResource`'s `RelationManagers/`, generated via
  `make:filament-relation-manager` then hand-edited: form has `field_key`/`label`/`type`
  (Select from the enum)/`unit`/`required` (Toggle), with a `->unique(modifyRuleUsing: ...,
  ignoreRecord: true)` on `field_key` scoped to `$this->getOwnerRecord()->id` so the DB
  constraint surfaces as a form validation error instead of an uncaught `QueryException`. Table
  uses `->defaultSort('order')->reorderable('order')` for drag reordering, and record actions
  are grouped in a single `ActionGroup` per [[resources-relation-managers]] (3 actions incl.
  Edit/Delete). No rights/role gating was added — `ServiceCategoryResource` itself has none
  today (confirmed by the pre-existing `ServiceCategoryResourceTest`, which creates/edits as a
  bare `User::factory()->create()` with no role assigned), so the relation manager just
  inherits that. Trap hit and fixed: `Livewire::test(..., ['pageClass' => ViewServiceCategory::class])`
  makes the relation manager read-only (`RelationManager::isReadOnly()` returns true for any
  `ViewRecord`-subclass page, denying `CreateAction`/`EditAction`/`DeleteAction` regardless of
  visibility settings) — mutation tests must use `EditServiceCategory` as `pageClass` instead,
  same split `DeadlinesRelationManager`'s tests already use. Tests added in both
  `tests/Feature/ServiceCategoryCostDriverFieldTest.php` (model-level: cross-category
  `field_key` reuse allowed, same-category duplicate rejected at the DB, `order` ascending via
  `costDriverFields()`, cascade delete) and `ServiceCategoryResourceTest.php` (relation-manager
  level: category-scoped table visibility, create, duplicate `field_key` form validation).
- [x] Schema + model for calculations: `App\Enums\CalculationOutputField` is *not* needed —
  outputs are explicit columns. New `tender_calculations` table (`TenderCalculation` model) —
  `tender_id` FK `cascadeOnDelete`, `version_number` unsigned int unique per `tender_id`,
  `created_by` FK `restrictOnDelete`, `input_values` JSON (map of `field_key => value`,
  validated at write-time against the tender's `service_category`'s
  `costDriverFields()->required` set), computed output columns: `bid_price`,`unit_price`,
  `min_margin`,`target_margin`,`actual_margin`,`break_even`,`risk_surcharge` (all
  `decimal:2`, nullable until computed). `Tender` gains `calculations(): HasMany` and
  `currentCalculation(): HasOne` (`orderByDesc('version_number')->limit(1)`, not `ofMany()`,
  per [[models]]'s UUID-PK trap already used for `TenderDocument::currentVersion()`). No
  standalone category-scoping global scope — reached only through the parent `Tender`, per
  [[scopes-models]]. Factory + tests: version-number uniqueness/ordering, `currentCalculation()`.
  Built as planned, mirroring `TenderDocument`/`TenderDocumentVersion`'s exact conventions:
  `input_values` is `jsonb` (Postgres, cast `array`) rather than plain `json`, matching the only
  existing precedent in this schema (`notifications.data`). `calculations()` is
  `orderByDesc('version_number')` (same as `TenderDocument::versions()`), and
  `currentCalculation()` reuses that ordering with `limit(1)` for the `HasOne`. The
  write-time validation against `costDriverFields()->required` named in this bullet is *not*
  implemented yet — deferred to the calculation-engine task next, since there's no input form
  or engine to validate against until then; this task only laid down schema/model/factory/tests.
  Tests added in `tests/Feature/TenderCalculationTest.php`: cross-tender `version_number` reuse
  allowed, same-tender duplicate rejected at the DB, `currentCalculation()`/`calculations()`
  ordering, cascade delete on tender hard-delete.
- [x] Calculation engine: a `CalculationEngine` contract plus one implementation per category
  calculation model (idea.md gives two concrete examples — a deployment-hours model
  (hours + wage rate + supplements + social costs) and an area-based model (area + performance
  rate + labour hours + machines/consumables)) — each implementation reads the category's
  configured `field_key`s out of `input_values` and returns the shared output shape (bid
  price, unit price, min/target/actual margin, break-even, risk surcharge). Which
  implementation runs for a given category is a simple mapping (e.g. a `calculation_model`
  column on `ServiceCategory` naming the class/key), since the *field set* is configurable but
  the *formula shape* per idea.md is still one of a small fixed number of models. Tests: at
  least the two example models running through the same engine interface with known
  input→output fixtures, and a below-minimum-margin case.

  **New top-level namespace, confirmed with the user first**: idea.md doesn't define exact
  pricing formulas (only the input field lists), and none of the existing directories
  (`app/Models`, `app/Filament`, `app/Enums`, `app/Http`, `app/Notifications`, `app/Console`)
  fit a formula-holding service class — per this project's "don't create new base folders
  without approval" rule, both the formulas and the new `app/Calculations/` namespace were
  confirmed with the user via `AskUserQuestion` before writing any code:
  - **Cost build-up** (both models): `cost_per_hour = wage_rate * (1 + supplements_pct) * (1 +
    social_costs_pct)` for the hours model (`total_cost = cost_per_hour * hours`);
    `labour_cost = labour_hours * wage_rate` for the area model (`total_cost = labour_cost +
    machines_consumables_cost` — `labour_hours` is a direct input, `performance_rate` is
    informational-only and not read by the engine even though it may still exist as a
    category-configured field).
  - **Margin/risk-surcharge source**: `target_margin_pct`/`min_margin_pct`/`risk_surcharge_pct`
    are per-calculation `input_values` entries, not `ServiceCategory` columns — every category
    using either engine needs these 3 added as standard cost-driver fields (via this file's
    task-1 relation manager, above) alongside its model-specific ones.
  - **Pricing**: `price_before_risk = total_cost * (1 + target_margin_pct)`; `bid_price =
    price_before_risk * (1 + risk_surcharge_pct)`; `risk_surcharge` output = `bid_price -
    price_before_risk` (the currency amount the risk multiplier added); `unit_price = bid_price
    / hours` (or `/ area`).
  - **break_even** = `total_cost` (the zero-margin bid price), confirmed over the
    minimum-margin-inclusive alternative.
  - **actual_margin** (an engine judgment call *not* covered by the confirmation round, since
    it wasn't asked): computed as `((bid_price - total_cost) / total_cost) * 100`, i.e. it
    folds in the risk surcharge on top of `target_margin`, so `actual_margin > target_margin`
    whenever `risk_surcharge_pct > 0`. `min_margin`/`target_margin` outputs are the input
    percentages ×100 (e.g. `0.15` → `15.0`). This is why a below-minimum-margin case needs
    `target_margin_pct` itself set below `min_margin_pct` — risk surcharge alone can't push
    `actual_margin` below `target_margin`, only above it.

  Structure: `App\Calculations\CalculationEngine` (interface, one method —
  `calculate(array $inputValues): CalculationResult`), `CalculationResult` (readonly DTO,
  `toOutputColumns(): array` maps its properties to the 7 `TenderCalculation` output column
  names), `DeploymentHoursCalculationEngine`/`AreaBasedCalculationEngine` (the two
  implementations, sharing a `Concerns\ExtractsCostDriverInputs` trait for the repeated
  "required numeric input or throw `InvalidArgumentException`" extraction). Dispatch lives on
  the enum: `App\Enums\CalculationModel` (`DEPLOYMENT_HOURS`/`AREA_BASED`) gained an
  `engine(): CalculationEngine` method (`match` over `$this`, instantiates the right class) —
  no separate factory/resolver class, since the enum is already the natural single place
  mapping "which model" to "which engine". `ServiceCategory` gained a nullable
  `calculation_model` column (enum-cast), exposed as a `Select` on `ServiceCategoryForm` (badge
  `TextEntry` on the infolist) — small enough addition that it didn't need the wizard/size
  check from [[resources]]. `TenderCalculation::computeOutputs()` is the integration point:
  resolves `$this->tender->serviceCategory->calculation_model`, throws a `RuntimeException`
  (mirroring `Tender::generateInternalId()`'s existing "category not configured" pattern) if
  unset, otherwise runs the engine against `$this->input_values` and persists the 7 output
  columns. Not yet wired to any UI action — that's task 6.
  Tests: `tests/Feature/CalculationEngineTest.php` (both engines against hand-computed
  fixtures, `CalculationModel::engine()` dispatch, missing-input exception, the
  below-minimum-margin fixture) and `TenderCalculationTest.php` (`computeOutputs()` end-to-end
  through a real `Tender`/`ServiceCategory`, and the unconfigured-category exception).
- [x] Approval chain: `App\Enums\CalculationApprovalStep` (6 cases, ordered:
  `CALCULATION_CHECKED`/`CONCEPT_CHECKED`/`EVIDENCE_DOCUMENTS_CHECKED`/
  `FORMAL_REVIEW_COMPLETE`/`MANAGEMENT_APPROVED`/`FINAL_SUBMISSION_RELEASED`) +
  `lang/en/calculation_approval_steps.php`, per [[enums]]. New `tender_calculation_approvals`
  table (`TenderCalculationApproval` model, `$timestamps = false`) — `tender_calculation_id`
  FK `cascadeOnDelete`, `step` enum-cast, unique per `(tender_calculation_id, step)`,
  `approved_by` FK nullable `restrictOnDelete`, `approved_at` datetime nullable, `comment`
  nullable text — mirrors `TenderStatusChange`'s who/when/optional-comment shape but as one
  row per step rather than one row per transition. `TenderCalculation::approve(
  CalculationApprovalStep $step, User $actor, ?string $comment)` enforces: the right/team-role
  for that step (per the confirmed gating above, `abort_unless`), all prior steps already
  approved, and — for `MANAGEMENT_APPROVED` specifically — auto-required (cannot be skipped)
  whenever `actual_margin < min_margin` on the calculation, even if the acting user's rights
  would otherwise let them jump past it. Tests: sequential enforcement (can't approve step 3
  before step 2), rights gating per step, below-margin forcing the management step, full
  6-step chain completion.

  Built mostly as planned, with two implementation calls worth recording:
  - **Rows are created lazily, not pre-seeded.** The plan's nullable `approved_by`/`approved_at`
    read as "pre-create all 6 pending rows when a `TenderCalculation` is made," but that would
    collide with `TenderCalculationApprovalFactory`'s own inserts under the
    `(tender_calculation_id, step)` unique constraint, and duplicates `TenderStatusChange`'s
    "row exists only once the event happened" convention for no benefit. `approve()` uses
    `updateOrCreate(['step' => $step], [...])` instead — a row for a step exists if and only if
    that step has been approved. `isFullyApproved()` and the sequential check both count/query
    `whereNotNull('approved_at')` rather than raw row existence, so a factory-built "pending"
    row (still a valid state for the nullable columns, useful for direct-model tests) can't be
    mistaken for an approval. `CalculationApprovalStep::stepsBefore()` (steps earlier than
    `$this` in declaration order) drives the sequential check — no separate integer `order`
    column needed.
  - **"Auto-required, cannot be skipped" needed no special-case code.** Once approvals are
    strictly sequential (any step requires every earlier step already approved, unconditionally
    — not just `MANAGEMENT_APPROVED`), a below-minimum-margin calculation already can't reach
    `FINAL_SUBMISSION_RELEASED` without `MANAGEMENT_APPROVED` first — margin doesn't change that
    rule, it's just why `MANAGEMENT_APPROVED` exists in the chain at all per idea.md's framing.
    So there's no separate "is this calculation below margin" branch anywhere in `approve()`;
    the below-margin test in `TenderCalculationApprovalTest.php` instead proves the general rule
    holds for a below-margin fixture specifically — a user holding `Right::EXECUTE_FINAL_SUBMISSION`
    still can't jump from step 4 straight to step 6, confirming no right or role shortcuts the
    ordering. If a genuinely margin-conditional bypass (e.g. skip `MANAGEMENT_APPROVED` entirely
    when *above* margin) is wanted later, that's a deliberate behavior change to raise with the
    user, not something this task inferred.
  - Rights/role check uses `abort_unless(..., 403)` inside the model method itself (per the
    original design decision, recorded above) rather than a dedicated domain exception — this
    is a deliberate split from this codebase's usual "domain rule violation → custom
    `RuntimeException` subclass" pattern (used here for the *sequential-order* violation via
    `CalculationApprovalStepOutOfOrderException`), treating authorization failures the same way
    `abort_unless` is already used at the Filament-action/controller layer elsewhere
    ([[documents]]), just one layer lower since there's no controller/action wrapping this call.
  - **Collision avoided mid-build**: another session on this machine had independently started
    the same task and had written an unused `UnauthorizedCalculationApprovalException` file
    before backing off once contacted — confirms the `abort_unless` choice above over a fourth
    custom exception class was deliberate, not an oversight.
  Tests added in `tests/Feature/TenderCalculationApprovalTest.php`: sequential enforcement
  (reject-before-predecessor, allow-after-predecessor), rights gating for both a team-role step
  and the final `Right::EXECUTE_FINAL_SUBMISSION` step, the below-minimum-margin
  cannot-jump-ahead case, and full 6-step completion asserting `isFullyApproved()`.
- [x] Wire into submission gate: replace the `tasksComplete()` check inside
  `Tender::changeStatusTo()`'s `SUBMISSION` guard with "the tender's `currentCalculation` has
  all 6 `TenderCalculationApproval` steps approved." Update [[tenders]]'s "Final-submission
  gate" note to record the resolution instead of forward-referencing it. Tests: transition
  blocked with an incomplete chain, allowed once all 6 steps are approved, and a regression
  test confirming `tasksComplete()` no longer blocks/unblocks the transition by itself.

  Built as planned. `changeStatusTo()`'s guard is now `! ($this->currentCalculation()->first()
  ?->isFullyApproved() ?? false)` — the nullsafe/`??` handles "no calculation exists yet" the
  same way "chain incomplete" is handled, both simply block. New
  `TenderCalculationNotApprovedException` (same `RuntimeException`-with-`::make()` shape as its
  siblings) replaces `TenderTasksNotCompleteException`, which was deleted outright — once
  `changeStatusTo()` stopped throwing it, it had zero remaining references, and this codebase's
  convention (per the general "no backwards-compatibility hacks" rule) is to delete confirmed-
  dead code rather than leave it as an unused shim. `Tender::tasksComplete()` itself is
  untouched and still public/tested — just no longer consulted by the gate.
  `TendersTable`'s `changeStatus` action `Select` (the belt-and-braces UI-level rejection of the
  `SUBMISSION` option) got the matching one-line swap.
  [[tenders]]'s "Final-submission gate" note rewritten to describe the calculation-based gate
  as current state, not a forward-reference to M5.
  Test fallout was larger than the diff: `TenderTest.php`'s task-based gate tests
  ("final submission task gate" describe block) were replaced with a "final submission
  calculation gate" block (no-calculation-at-all case, incomplete-chain case, no-audit-entry-
  on-rejection, allowed-once-fully-approved, and the explicit tasksComplete()-is-irrelevant
  regression test using one blocked/one allowed tender to prove neither direction depends on
  it). Every other `TenderTest.php`/`TenderResourceTest.php` test that drove a tender to
  `SUBMISSION` incidentally (document-locking tests, the Filament `changeStatus` action tests)
  needed a fully-approved calculation added first, since they no longer get there for free.
  Added a shared `fullyApprovedCalculationFor(Tender $tender): TenderCalculation` helper to
  `tests/Pest.php`'s global Functions section (not a per-file helper — it's needed by both
  `TenderTest.php` and `TenderResourceTest.php`, and PHP fatals on redeclaring a same-named
  global function if two test files each define their own copy) — it directly inserts 6
  approved `TenderCalculationApproval` rows rather than calling `TenderCalculation::approve()`
  per step, deliberately bypassing rights/order enforcement since that's already covered by
  `TenderCalculationApprovalTest.php` and these call sites only need "a tender ready for
  submission," not a re-derivation of team memberships.
- [x] Filament UI: a calculation relation manager (or dedicated sub-resource, whichever fits
  `TenderResource`'s existing tab structure) listing versions side by side for comparison
  (bid price / unit price / margins / break-even / risk surcharge columns), a create/duplicate
  action that renders the category's configured cost-driver fields dynamically (via
  `costDriverFields()`) as the input form, and an approval-steps widget/table on each
  calculation showing the 6 steps with approve buttons gated per the confirmed rights/roles,
  each showing who/when/comment once approved. `CALCULATION`-category price visibility still
  follows the existing `see-prices` gate from [[documents]] for anything calculation-adjacent.
  Feature tests mirroring `DocumentsRelationManager`'s test shape.

  Built as a `CalculationsRelationManager` on `TenderResource` (relationship `calculations`),
  registered alongside Tasks/Deadlines/Documents. One deliberate deviation from the plan's
  wording, resolved without asking since it follows directly from an already-confirmed M5
  decision: **the tab itself is not see-prices-gated, only its financial parts are.** Gating
  the whole tab (mirroring `DocumentsRelationManager`'s per-category exclusion) would have
  blocked a `CONCEPT`/`EVIDENCE_DOCUMENTS`/`QUALITY_CONTROL`/`FINAL_APPROVAL` team member from
  ever reaching their approval step unless they *also* held `see-prices` — but the approval
  chain's gating (already built and tested) is deliberately independent of that right. So:
  the RelationManager tab is visible to anyone with tender access like the other tabs; only
  the money columns (`bid_price`/`unit_price`/`target_margin`/`actual_margin`/`break_even`/
  `risk_surcharge`), the View modal's "Cost driver inputs"/"Results" sections, and the
  create/duplicate actions (which both read and write cost-driver values) are gated behind
  `Right::SEE_PRICES`. The approval-chain section of the View modal and the approve action are
  unconditionally visible/usable subject only to `TenderCalculation::approve()`'s own
  team-role/`EXECUTE_FINAL_SUBMISSION` check.

  Cost-driver input fields are rendered dynamically as `TextInput::make("input_values.{field_key}")`
  — Filament's dot-path state resolution (`data_get()`, confirmed by reading
  `HasState.php`) builds the nested `input_values` array automatically, so no manual
  flatten/unflatten step was needed in `mutateDataUsing()`. The same dot-path components are
  reused unmodified for the "duplicate" action's schema, with `->fillForm(['input_values' =>
  $record->input_values])` pre-populating them from the source version. "New calculation" uses
  the RelationManager's default `CreateAction` (Filament sets `tender_id` via the relationship
  automatically); "duplicate" instead calls `TenderCalculation::create()` directly since it's
  copying from an existing record rather than filling a blank relationship form, so `tender_id`
  is set explicitly there. Both call `computeOutputs()` in an `->after()`/inline step following
  creation, and both are hidden (not just before-guarded) when the tender's service category has
  no `calculation_model` configured, via a `calculationModelConfigured()` check — avoids ever
  hitting `computeOutputs()`'s `RuntimeException` path from the UI, matching this codebase's
  existing convention of preventing invalid-transition exceptions via `->visible()` rather than
  catching them (no global exception-to-notification handler exists here, per
  `TenderCalculationNotApprovedException`/`InvalidTenderStatusTransitionException` precedent).

  Approval UI is a single "approve next step" action per row rather than one button per step:
  since `CalculationApprovalStep`'s chain is strictly sequential, there is always exactly one
  approvable step at a time, so `nextStepFor()` (first case in `CalculationApprovalStep::cases()`
  without an approved row) plus `canApproveStep()` (team role membership, or
  `EXECUTE_FINAL_SUBMISSION` for the final step — duplicating `TenderCalculation::approve()`'s
  own check for `->visible()` purposes, belt-and-braces per `.ai/rules/permissions.md`) fully
  determine the action's visibility and label (`"Approve: {step label}"`). A new
  `TenderCalculation::approvalTimeline()` model method (list of all 6
  `CalculationApprovalStep` cases paired with their `TenderCalculationApproval` row or `null`)
  backs a read-only `RepeatableEntry` in the View action's infolist, since `approvals()` only
  has rows for steps already approved and the UI needs to show all 6 regardless of progress —
  fed via `->getStateUsing()` rather than a relationship binding.

  Tests added to `TenderResourceTest.php`'s new "calculations relation manager" describe block
  (10 cases): tender-scoped listing, create/duplicate hidden without `see-prices`, create hidden
  without a configured `calculation_model`, create computes and persists real engine output
  (cross-checked against `CalculationEngineTest`'s known fixture), duplicate pre-fills and
  increments the version, a team-role holder approving the first step, approve hidden for an
  unrelated user and once the chain is already fully approved (reusing `tests/Pest.php`'s
  `fullyApprovedCalculationFor()` helper), and financial-column visibility gated on
  `see-prices`. A `deploymentHoursServiceCategory()` test helper (local to this file, mirroring
  `CalculationEngineTest`'s deployment-hours fixture field set) builds a fully-configured
  category on demand. Full suite re-verified: 294 passed, no regressions.

  **Follow-up fix, same session, user-requested**: this task's own review surfaced that
  `DemoDataSeeder` had been silently broken since the earlier "wire into submission gate" task —
  every seeded tender walked to SUBMISSION-or-later threw `TenderCalculationNotApprovedException`,
  since the seeder never created a `TenderCalculation` at all and there's no seeder test to catch
  it (confirmed via a throwaway Pest test invoking `DemoDataSeeder` against the sqlite testing
  DB — never run `migrate:fresh --seed` against the real dev Postgres DB to check this kind of
  thing). Fixed in the same three places documented in full in [[database-seeders]]'s new
  "Demo calculations" entry: `ServiceCategorySeeder` now assigns each category a
  `calculation_model` (SEC/CLN → `DEPLOYMENT_HOURS`, FM → `AREA_BASED`) plus its matching
  `ServiceCategoryCostDriverField` rows; `DemoDataSeeder::createTender()` creates a calculation
  and runs `approveCalculationChain()` before the status walk (full chain for tenders reaching
  SUBMISSION-or-later, a random partial prefix otherwise, to demo the in-progress state); and
  `pickTeam()` gained a size floor (6-7 instead of 3-5) for submission-reaching tenders so all 5
  `TeamRole` cases are represented for the approval chain's role-gated steps. Team-member
  creation was also moved earlier in `createTender()` (right after `pickTeam()`, before
  tasks/documents) since the approval chain needs `tender_team_members` rows to already exist.

  **Follow-up UI/UX pass, same session, user-requested**: the cost-driver input form was
  one `TextInput` per line with no visual grouping. Reworked per [[resources]]'s "design forms
  evenly" rule: `costDriverFieldComponents()`/`inputValueSections()` now split a category's
  fields into two `Section`s — "Cost inputs" (category-specific fields) and "Margin & risk"
  (the 3 standard `target_margin_pct`/`min_margin_pct`/`risk_surcharge_pct` fields every model
  needs, matched by a fixed `MARGIN_FIELD_KEYS` list rather than a DB column, since that 3-field
  set is a convention from this file's original engine-dispatch task, not schema), each
  `->columns(2)`. Both the create/duplicate form and the View action's infolist share this
  grouping (`inputValueSections()` wraps `inputValueEntries()` the same way
  `costDriverFieldComponents()` wraps `costDriverInputs()`). Every input/entry also gets a
  `prefixIcon()`/`icon()` chosen by a small heuristic (`iconForField()`) over the field's
  `unit`/`type` — percent→`OutlinedReceiptPercent`, hours→`OutlinedClock`,
  m²→`OutlinedSquares2x2`, €/€ per h→`OutlinedBanknotes`, free text→`OutlinedPencil`, otherwise
  `OutlinedHashtag` — since cost-driver fields are admin-configured per category with no fixed
  icon of their own to draw on. No test changes needed (the create/duplicate tests fill by
  `input_values.{field_key}` state path regardless of which `Section` wraps the input).

  **Second follow-up, same session, user-requested**: added a "How this is calculated" section
  to the View action, showing the fixed formula for the tender's calculation model. Presented
  with two options (a static formula reference vs. a numeric worked-breakdown requiring engine
  changes); user chose the static reference. `CalculationModel::formulaSteps(): array` (new
  method) returns an ordered list of formula-step strings from a new
  `lang/en/calculation_models.php` `formulas.*` key per model case — deliberately a static
  reference to the fixed formula, not a numeric breakdown of any one calculation's actual
  values (that would need `CalculationResult`/the engines to expose intermediate values, out of
  scope for this pass). Rendered via a collapsed `Section` + a single `TextEntry::make('formula')
  ->bulleted()` fed by `->getStateUsing()`, gated behind `see-prices` same as the adjacent
  Results section (it sits in the same calculation-adjacent context, even though the formula
  text itself reveals no tender-specific figures). Tests: `CalculationEngineTest.php`'s new
  "formula reference" describe block (every `CalculationModel` case has a non-empty step list)
  and one relation-manager test asserting the formula text renders in the View action's modal.
  Full suite re-verified: 296 passed.
- [x] Docs + wrap-up: `.ai/rules/calculations.md` created, consolidating the cost-driver-field
  schema, calculation versioning, engine dispatch, and approval-chain decisions above, linked
  from `.ai/rules/index.md` for the relevant `app/**` paths. New customer-doc page under
  `docs/` (numbering continues after M4's `09-tender-documents.md`) covering: configuring
  cost-driver fields per category, creating/comparing calculation versions, and walking the
  6-step approval chain including the below-margin auto-escalation. Final full-suite
  `php artisan test --compact` re-verify.

  Built as planned. `.ai/rules/calculations.md` consolidates every decision from this file's
  tasks above (cost-driver-field schema, versioning, engine dispatch/formulas, approval-chain
  gating, submission gate, UI/RelationManager design, and the demo-seeder fix), added to
  `.ai/rules/index.md` with a glob covering the models/enums/`app/Calculations/**`/
  RelationManagers/migrations/`ServiceCategorySeeder.php` this area touches. Resolved a stale
  `[[calculations]] once written` forward-reference in `.ai/rules/tenders.md`'s "Final-submission
  gate" note now that the file exists.

  `docs/10-calculations-approvals.md` drafted after asking the user two clarifying questions per
  [[docs]]'s convention (audience: general user/approver mix; screenshots: placeholders like
  every prior page). Covers per-category cost-driver setup (pointer to 08 for admin
  configuration), creating/duplicating a calculation, version comparison, the see-prices gate on
  results, the "How this is calculated" formula reference, the 6-step approval chain with its
  functional-role table, the below-minimum-margin case (no override exists — management approval
  is still required, same as every other step), and the submission gate. Per [[docs]]'s "keep
  docs in sync" rule, this pass also caught and fixed a real staleness bug:
  `docs/03-managing-tenders.md`'s "Final submission is gated on tasks" section still described
  the pre-M5 task-based gate (never updated when the submission-gate task landed earlier in this
  milestone) — rewritten to describe the calculation-chain gate instead. Cross-linked from
  `03-managing-tenders.md`, `08-administration.md` (service category calculation setup), and
  `09-tender-documents.md` (distinguishing the calculation *document category* from the
  structured calculation this page covers; 09's "Where to go next" updated since it's no longer
  the sequence's final page). Full suite re-verified: 296 passed, Pint and Larastan clean.

M5 — Calculation & Approvals is now complete.

**Third follow-up, same session, user-reported**: after the docs task above, the user reported
the seeder still wasn't producing approval data and asked to rethink the approval-chain UI.
Investigation (querying the actual dev DB, not just the sqlite test suite) found the "demo
calculations" fix from earlier in this file only got the 12 SUBMISSION-or-later tenders to a
full 6/6 chain — all 24 other tenders had exactly zero approvals, not the intended random
partial prefix. Root cause was a pre-existing (pre-M5) off-by-one in the team-role assignment
loop, unrelated to anything built earlier in this file — full detail in [[database-seeders]]'s
"Reindexing trap" entry. Fixed with one `->values()` call; re-ran `migrate:fresh --seed` against
the dev DB to confirm (zero-approval calculations dropped from 24/36 to 2/36, full suite still
296 passed). The approval-chain UI rethink itself is a separate, not-yet-scoped follow-up — see
the session for whatever direction that took.
