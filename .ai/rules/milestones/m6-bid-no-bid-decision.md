# M6 — Bid / No-Bid Decision

Full spec: [idea.md](../../../idea.md)'s M6 section. Index: [../milestones.md](../milestones.md).

**M6 — Bid / No-Bid Decision is in progress**, started 2026-08-30 at the user's explicit
request, building incrementally task-by-task (same rhythm as M3–M5).

Four design decisions were confirmed with the user before any code was written:
- **Missing score inputs are manual entry fields.** idea.md lists 10 participation-score
  factors; only contract value (`Tender.estimated_contract_volume`) and expected margin
  (`TenderCalculation.actual_margin` via [[calculations]]) are already captured elsewhere.
  Past win rate has no source until M9/M10 exist, so it's fixed at a neutral rating and
  flagged as unknown rather than guessed. The remaining 7 factors (distance, staffing
  requirement, wage/qualification requirements, reference position, competitive intensity,
  contractual penalties, strategic value) get dedicated manual rating fields on a new
  `TenderParticipationScore` model, rather than deferring them or leaving them out.
- **Score formula: 10 factors, each rated 1-5, equal weight, summed / 50 × 100 = 0-100 score.**
  No per-factor weighting — idea.md gives no weighting guidance, so equal weight is the
  simplest defensible default (not built as admin-configurable; that's speculative scope this
  milestone doesn't need). Derived factors are bucketed into the same 1-5 scale:
  - **Contract value** (from `Tender.estimated_contract_volume`, EUR): <50k=1, 50k–150k=2,
    150k–400k=3, 400k–1M=4, >1M=5.
  - **Expected margin** (from `currentCalculation()->actual_margin`, %): no calculation or
    margin ≤0%=1, 0–5%=2, 5–10%=3, 10–20%=4, >20%=5.
  - **Past win rate**: fixed at 3 (neutral), always rendered with an "unknown — no result
    history yet" note, not silently averaged in as if it were a real measurement.
  The score is **computed on demand, not persisted** — a stored column would go stale the
  moment `estimated_contract_volume` or the calculation's `actual_margin` changes after the
  ratings were entered. `TenderParticipationScore::score(): ?int` returns `null` ("incomplete")
  until all 7 manual ratings are set, otherwise runs the bucket/weight math live.
- **Bid/no-bid decision is an append-only log, not a mutable field.** A new
  `TenderBidDecision` model — one row per recorded decision (`BID`/`NO_BID` via a new
  `App\Enums\BidDecision`, mandatory `reason` when `NO_BID`, `decided_by`, `decided_at`, and a
  frozen `score` snapshot captured at decision time so the historical record survives later
  score changes). Rows are never updated or deleted; `Tender::currentBidDecision(): HasOne`
  (explicit `orderByDesc('decided_at')->limit(1)`, not `ofMany()` per [[models]]'s UUID-PK
  trap) surfaces the latest one. Editing a decision later means recording a new row, per
  idea.md's explicit "edits create a new logged entry, not a silent overwrite."
- **New `Right::MAKE_BID_DECISION`**, not a reuse of an existing right or a role check —
  consistent with M1's rights model (independent of role, individually assignable). Gates both
  editing the participation-score ratings and recording a decision; viewing the score/decision
  history stays open to anyone with tender access, mirroring
  [[calculations]]'s `CalculationsRelationManager` tab-visible-but-actions-gated pattern.
- **Informational only — no status-transition gating.** The score and decision are recorded
  and displayed but do not block `decision→processing` or any other `Tender::changeStatusTo()`
  transition. idea.md is explicit that "the system never decides automatically"; gating the
  transition would make the tool coercive in a way the spec doesn't ask for. (Rejected: gating
  `decision→processing` on a `BID` decision existing, mirroring M5's submission gate — would
  contradict the human-in-the-loop framing and wasn't requested.)

Planned tasks for M6:
- [x] `App\Enums\BidDecision` (`BID`/`NO_BID`, values `'bid'`/`'no-bid'`, `HasLabel` via a new
  `bid_decisions.*` lang file — mirrors `CalculationApprovalStep`'s shape) +
  `Right::MAKE_BID_DECISION` case added to the existing `Right` enum (`'make-bid-decision'`),
  with a `rights.*` lang key and seeded into `RolesAndPermissionsSeeder::DEFAULT_ROLE_RIGHTS`
  alongside the other default rights. Default grants: super-admin and department-head get it
  (mirroring their all-rights default), team-lead gets it too (decision-making authority
  consistent with team-lead already holding `EXECUTE_FINAL_SUBMISSION`), calculation and staff
  do not. This is a product default the user can adjust per-role afterward via the
  `RolesAndPermissions` admin page, same as every other right — not a hard rule. No `HasColor`
  yet; the won/lost-style badge colours belong to the relation-manager table task, not the enum.
  Tests: `BidDecisionTest`/`RightTest` (label resolution, generic loop over cases) and
  `RolesAndPermissionsSeederTest` (updated to assert calculation/staff don't get the new right,
  added a team-lead-specific assertion) — all passing. No dedicated "rejected without the
  right" action test yet since no gated action exists until the UI task.
- [ ] `TenderParticipationScore` model + migration: UUID PK, `tender_id` FK unique (one row
  per tender, `cascadeOnDelete`), 7 nullable tinyint rating columns (`distance_rating`,
  `staffing_requirement_rating`, `wage_qualification_rating`, `reference_position_rating`,
  `competitive_intensity_rating`, `contractual_penalties_rating`, `strategic_value_rating`,
  each 1-5, validated in the form not just the DB), timestamps. `Tender::participationScore():
  HasOne`. `TenderParticipationScore::score(): ?int` implementing the bucket/weight formula
  above, plus small private helpers for the contract-value and margin bucketing so they're unit
  testable in isolation. Factory + tests: score is null until all 7 ratings set; score math is
  correct for known input combinations including the value/margin/win-rate bucket edges;
  cascade-deletes with its tender.
- [ ] `TenderBidDecision` model + migration: UUID PK, `tender_id` FK (`cascadeOnDelete`),
  `decision` enum-cast `BidDecision`, `reason` nullable text, `score` nullable int (frozen
  snapshot), `decided_by` FK users, `decided_at` datetime, no `updated_at` (immutable, mirror
  `TenderStatusChange`'s `$timestamps = false` pattern). Model-level guard rejecting
  `reason === null` when `decision === BidDecision::NO_BID` (mirrors validation idea.md calls
  "mandatory reason" for declines) — enforce in both the model (defense in depth) and the
  Filament form. `Tender::bidDecisions(): HasMany` (`orderByDesc('decided_at')`) and
  `Tender::currentBidDecision(): HasOne` as described above. Factory + tests: creating a
  `NO_BID` decision without a reason is rejected; creating one with a reason succeeds; multiple
  decisions accumulate as separate rows rather than overwriting; `currentBidDecision()` returns
  the most recent one.
- [ ] Filament UI: a `BidDecisionRelationManager` tab on `TenderResource` (register alongside
  the existing four in `getRelations()`). Table is the append-only decision history (decision
  badge with won/lost-style status colours per idea.md's design-system convention — green for
  `BID`, red for `NO_BID` — score snapshot, reason, decided by, decided at), no edit/delete row
  actions since rows are immutable. Header actions: "Edit score inputs" (modal form,
  `updateOrCreate` on `TenderParticipationScore`, the 7 rating `Select`s 1-5) and "Record
  decision" (modal form: decision `Select`, `reason` `Textarea` required when `NO_BID` via
  `->required(fn (Get $get) => $get('decision') === BidDecision::NO_BID->value)`, captures the
  live `score()` value into the snapshot column at submit time) — both actions
  `->visible(fn () => auth()->user()->can(Right::MAKE_BID_DECISION->value))` per
  [[permissions]]'s server-side-plus-UI enforcement standard. A read-only summary (current
  computed score, or "incomplete — N of 7 ratings missing", plus each factor's current rating
  including the derived/fixed ones) rendered above the table, matching how
  `CalculationsRelationManager` surfaces computed output alongside its inputs. Feature tests:
  a user with the right can edit ratings and record a decision; a user without it is rejected
  server-side on both actions (per [[permissions]]'s explicit "test the right is denied, not
  just granted" rule); the tab renders correctly with zero ratings set (incomplete state) and
  fully set (numeric score shown).
- [ ] Docs: add a short section to whichever `docs/*.md` page covers tender-detail tabs (check
  [[docs]] for the right target, likely alongside where M5's calculations page lives) covering
  the participation score factors, the manual-vs-derived split, and how to record a bid/no-bid
  decision including the mandatory-reason-on-decline rule.

M6 is additive: it introduces one new right, two new tables, and one new tender-detail tab; it
does not touch `Tender::changeStatusTo()`, the M5 approval chain, or any existing transition
guard.
