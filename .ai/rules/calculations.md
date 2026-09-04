---
glob: "app/Models/TenderCalculation.php,app/Models/TenderCalculationApproval.php,app/Models/ServiceCategoryCostDriverField.php,app/Enums/CalculationApprovalStep.php,app/Enums/CalculationModel.php,app/Enums/CostDriverFieldType.php,app/Calculations/**,app/Filament/Resources/Tenders/RelationManagers/CalculationsRelationManager.php,app/Filament/Resources/ServiceCategories/RelationManagers/CostDriverFieldsRelationManager.php,database/migrations/*cost_driver_fields*,database/migrations/*tender_calculations*,database/migrations/*tender_calculation_approvals*,database/seeders/ServiceCategorySeeder.php"
title: Calculation & approvals (M5) — cost-driver fields, versioning, engines, approval chain
---

# Calculations & approvals

Consolidates the design decisions built across M5 ([[milestones]]'s m5-calculation-approvals.md
has the full task-by-task build history — read that file for build order, traps hit, and test
inventory; this file is the settled reference for anyone touching this area afterward).

## Cost-driver fields are admin-configurable per category, not a fixed schema
`ServiceCategoryCostDriverField` (one row per field: `service_category_id`, `field_key` unique
per category, `label`, `type` — `App\Enums\CostDriverFieldType` (`NUMBER`/`DECIMAL`/`TEXT`),
`unit` nullable, `required`, `order`) lets admins add/adjust a category's cost-driver fields
without a code change, managed via `CostDriverFieldsRelationManager` on
`ServiceCategoryResource` (reorderable table, `->unique(...)` form validation scoped to the
owner category). Actual input values per calculation live in a JSON map on
`TenderCalculation.input_values` keyed by `field_key` — not a separate columns-per-field
design, so the field set stays fully dynamic.

Every category using either calculation model needs 3 standard fields configured alongside its
model-specific ones: `target_margin_pct`, `min_margin_pct`, `risk_surcharge_pct` — these are
per-calculation inputs, not `ServiceCategory` columns, and the engines read them directly out of
`input_values` same as any other field. `ServiceCategorySeeder` seeds these plus the
model-specific fields for its 3 demo categories; use it as the field-key reference when adding a
new category by hand.

## Calculation versioning: one row per version, no parent/version split
`TenderCalculation` (`tender_id`, `version_number` unique per tender, `created_by`,
`input_values` jsonb, 7 output columns: `bid_price`/`unit_price`/`min_margin`/`target_margin`/
`actual_margin`/`break_even`/`risk_surcharge`, all `decimal:2` nullable until computed) is a
single table with a `version_number` column — each version is a fully independent row, unlike
`TenderDocument`/`TenderDocumentVersion`'s parent/version split, since nothing about a
calculation version is edited in place after creation. `Tender::calculations(): HasMany`
(`orderByDesc('version_number')`) and `Tender::currentCalculation(): HasOne` (same ordering,
`limit(1)`, deliberately not `ofMany()` per [[models]]'s UUID-PK trap) mirror
`TenderDocument::currentVersion()`'s exact pattern.

## Engine dispatch: `ServiceCategory.calculation_model` selects the formula, fields stay dynamic
`App\Enums\CalculationModel` (`DEPLOYMENT_HOURS`/`AREA_BASED`) has an `engine(): CalculationEngine`
method (`match` over `$this`) — the *field set* per category is configurable via
`ServiceCategoryCostDriverField`, but the *formula shape* is one of a small fixed number of
models, so dispatch lives on the enum rather than a separate resolver class.
`App\Calculations\CalculationEngine` is the interface (`calculate(array $inputValues):
CalculationResult`); `DeploymentHoursCalculationEngine`/`AreaBasedCalculationEngine` are the two
implementations, sharing `Concerns\ExtractsCostDriverInputs` for "required numeric input or
throw `InvalidArgumentException`". `TenderCalculation::computeOutputs()` is the integration
point — resolves `$this->tender->serviceCategory->calculation_model`, throws a
`RuntimeException` if unset, otherwise runs the engine and persists the 7 output columns.

The exact formulas (confirmed with the user, not derivable from idea.md alone, since idea.md
only lists input fields):
- **Deployment hours**: `cost_per_hour = wage_rate × (1 + supplements_pct) × (1 +
  social_costs_pct)`; `total_cost = cost_per_hour × hours`.
- **Area-based**: `labour_cost = labour_hours × wage_rate`; `total_cost = labour_cost +
  machines_consumables_cost` (`performance_rate` is informational-only, not read by the engine
  even when configured as a field).
- **Shared from `total_cost` onward**: `price_before_risk = total_cost × (1 + target_margin_pct)`;
  `bid_price = price_before_risk × (1 + risk_surcharge_pct)`; `risk_surcharge` output =
  `bid_price − price_before_risk`; `unit_price = bid_price ÷ hours` (or `÷ area`); `break_even =
  total_cost`; `actual_margin = ((bid_price − total_cost) ÷ total_cost) × 100` (folds in the risk
  surcharge on top of `target_margin`, so `actual_margin > target_margin` whenever
  `risk_surcharge_pct > 0`); `min_margin`/`target_margin` outputs are the input percentages ×100.

A static, human-readable copy of these formulas (not a numeric breakdown of any one
calculation's actual values — that would need the engines to expose intermediate values, not
built) is exposed via `CalculationModel::formulaSteps(): array`, sourced from
`lang/en/calculation_models.php`'s `formulas.*` key, and shown in the UI's "How this is
calculated" section (see below).

## Approval chain: 6 sequential steps, gated by existing roles/rights, no new `Right` cases
`App\Enums\CalculationApprovalStep` (6 cases in declared/chain order:
`CALCULATION_CHECKED`/`CONCEPT_CHECKED`/`EVIDENCE_DOCUMENTS_CHECKED`/`FORMAL_REVIEW_COMPLETE`/
`MANAGEMENT_APPROVED`/`FINAL_SUBMISSION_RELEASED`) + `TenderCalculationApproval` (one row per
step, created lazily via `updateOrCreate` only once that step is actually approved — not
pre-seeded pending rows). Steps 1-5 are each gated by a matching `TenderTeamMember` functional
role (`CalculationApprovalStep::teamRole()`, reusing M2's `TeamRole` enum — no new `Right` cases
were added, deliberately, to avoid duplicating what `TeamRole` already expresses). Step 6
(`FINAL_SUBMISSION_RELEASED`) is gated by `Right::EXECUTE_FINAL_SUBMISSION` instead
(`teamRole()` returns `null` for it).

`TenderCalculation::approve(CalculationApprovalStep $step, User $actor, ?string $comment)`
enforces the role/right check (`abort_unless(..., 403)`) and strict ordering — a step cannot be
approved while any earlier step in `CalculationApprovalStep::stepsBefore()` order is still
unapproved (`CalculationApprovalStepOutOfOrderException`), regardless of the acting user's
rights for later steps. This strict ordering alone is what makes a below-minimum-margin
calculation unable to skip `MANAGEMENT_APPROVED` — there's no separate margin-conditional branch
anywhere in `approve()`. `TenderCalculation::isFullyApproved()` checks all 6 steps have a
non-null `approved_at`.

## Submission gate: the approval chain replaces the old task-completion gate
`Tender::changeStatusTo()`'s `SUBMISSION` transition guard is
`! ($this->currentCalculation()->first()?->isFullyApproved() ?? false)` — both "no calculation
exists yet" and "chain incomplete" block the transition via the same nullsafe/`??` path, raising
`TenderCalculationNotApprovedException`. `Tender::tasksComplete()` (M2) is no longer consulted by
this guard at all — it's a formal superset now, not an addition; see [[tenders]]. Any seeder or
test that walks a tender to `SUBMISSION`-or-later must ensure a fully-approved calculation
exists first (`tests/Pest.php`'s `fullyApprovedCalculationFor()` helper for tests;
`DemoDataSeeder::createCalculation()`/`approveCalculationChain()` for seeded demo data — see
[[database-seeders]] for the ordering trap this caused when the gate first changed).

## UI: `CalculationsRelationManager`, tab visible to all, only the money parts see-prices-gated
Unlike `DocumentsRelationManager`'s per-category exclusion, `CalculationsRelationManager`'s tab
itself is visible to anyone with tender access — gating the whole tab behind `see-prices` would
block a `CONCEPT`/`EVIDENCE_DOCUMENTS`/`QUALITY_CONTROL`/`FINAL_APPROVAL` team member from ever
reaching their approval step unless they also held `see-prices`, but the approval chain's gating
is deliberately independent of that right (see above). Only the money columns, the create/
duplicate actions, and the View modal's "Cost inputs"/"Margin & risk"/"Results"/"How this is
calculated" sections are gated behind `Right::SEE_PRICES`; the approval-chain section and the
"approve next step" action are unconditionally visible, subject only to
`TenderCalculation::approve()`'s own team-role/`EXECUTE_FINAL_SUBMISSION` check.

Cost-driver inputs render dynamically as `TextInput::make("input_values.{field_key}")` — dot-path
components that Filament's `data_get()`-based state resolution nests into `input_values`
automatically, no manual flatten/unflatten needed. They're split into two `Section`s ("Cost
inputs" vs. "Margin & risk", the latter matched by a fixed 3-key list, not a DB column) with a
`prefixIcon()`/`icon()` per field chosen by a small heuristic over `unit`/`type`
(`iconForField()`) — reused identically by the create/duplicate form and the View infolist via
`inputValueSections()`. "New calculation" uses the RelationManager's default `CreateAction`
(Filament sets `tender_id` via the relationship); "duplicate" calls `TenderCalculation::create()`
directly (copying from an existing record, not filling a blank relationship form) with
`->fillForm(['input_values' => $record->input_values])` pre-populating the same components. Both
are hidden — not just before-guarded — when the tender's service category has no
`calculation_model` configured, so the UI never triggers `computeOutputs()`'s `RuntimeException`
path (this codebase has no global exception-to-notification handler; invalid-transition
exceptions are always prevented via `->visible()`, not caught).

Approval UI is one "approve next step" action per row, not one button per step: since the chain
is strictly sequential there's always exactly one approvable step at a time
(`nextStepFor()`/`canApproveStep()` — the latter duplicates `approve()`'s own check purely for
`->visible()` purposes, belt-and-braces per [[permissions]]). `TenderCalculation::
approvalTimeline()` (all 6 steps paired with their approval row or `null`) backs a read-only
`RepeatableEntry` in the View modal, fed via `->getStateUsing()` since `approvals()` only has
rows for steps already approved.

## Docs
Documented for end users in `docs/10-calculations-approvals.md`. If cost-driver field
configuration, versioning, the approval chain, or the submission gate changes, update that page
too — see [[docs]].
