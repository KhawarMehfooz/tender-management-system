# Tender Management System — Milestone Specifications

Derived from the original functional brief.

---

## M1 — Foundation

### Purpose
The spine every other milestone writes to: auth, permissions, service categories, the tender
record itself, and its lifecycle state machine.

### Service categories (configurable)
- Admin-managed list of service categories (replaces the fixed "security vs cleaning" split).
- Each category has: name, description, active flag, and its own dashboard/pipeline scoping.
- Every tender belongs to exactly one category. Category is the scoping dimension used
  throughout the system (dashboards, stats, calculation model selection).
- A management-level view can span all categories at once; category-level views stay scoped.

### Users, roles, permissions
- Role list (adapt from brief's 9 examples): super admin, department head, team lead,
  calculation, concept writer, documentation, quality control, staff, read-only/viewer.
- Roles control navigation/menus. Separately, individually assignable rights control data
  access regardless of role: see prices, see margins, see competitor data, execute final
  submission, view employee statistics.
- All rights enforced server-side (policy/gate checks on every query and action), never only
  hidden in the UI.

### Tender master data
Internal ID scheme (own format, not the client's `LIL-S-2026-0084` pattern) referenced
system-wide across tasks, documents, communication, calculation, timeline, result, archive.

Master fields (~25, matching the brief):
title, procurement number, contracting authority, procurement office, contact person, contact
details, location (city + region/state), service category, sector, procurement procedure,
estimated contract volume, contract term, contract start/end, extension options, submission
deadline (date + time), bidder-question deadline, site visit date, bid validity period,
publication date, source, portal link, notes, tender documents (link into M4).

- Source captured as structured data (enum/lookup), not free text — needed for later
  win-rate/volume/quality-per-source reporting. Seed with common sources (tender portals,
  direct enquiry, existing client, referral) but keep it admin-extensible.
- Structured CPV code and NUTS code fields (real fields, not free text) for filtering/reporting.
- Unknown values stored explicitly as "unknown," never left blank, wherever a field feeds
  statistics later.
- Tenders are never hard-deleted. They're archived or flagged invalid. Only admins can
  hard-delete true junk entries, and every hard delete is logged with a reason.
  - Archived/invalid is its own field (e.g. `is_archived` / `invalidity_reason`), separate
    from the lifecycle status enum below — a won tender can still be archived later for
    housekeeping, so this must not be folded into the status transitions.
  - Admin hard-delete is a distinct, admin-gated action from the "no delete" path on the
    regular tender resource: it requires a logged reason (who, when, why) captured before
    the row is removed, and is reserved for true technical-junk entries, not normal
    archival/invalidation.

### Workflow / lifecycle
8-phase status flow: intake → review → decision → processing → quality → submission →
follow-up → closure, ending in one of: won, lost, cancelled, not evaluated, excluded.
Every status change recorded with user, date, time (feeds the audit/activity log in M14).

### Acceptance points for M1
- Login with roles and category-scoped views working.
- Tenders can be created/edited with all master fields, structured source/CPV/NUTS.
- Full status workflow transitions enforced and logged.
- Field-level rights (prices, margins, competitor data, final submission, employee stats)
  verifiably blocked server-side for users without them, not just hidden in UI.
- Tenders can be archived/flagged invalid independently of lifecycle status, including from
  terminal statuses like won.
- Admin-gated hard-delete of junk tenders works, is separate from the regular resource's
  delete-blocked path, and logs a reason before the row is removed.

---

## M2 — Team & Tasks

### Purpose
Assign people to tenders and give them accountable, dependency-aware work items.

### Team assignment
- One named tender owner per tender, plus any number of team members in functional roles:
  calculation, concept, evidence documents, quality control, final approval.

### Tasks
Fields: title, description, owner, participants, priority, start date, due date, checklist,
attachments, comments, creator, reviewer, completion date.

Status chain: open → in progress → waiting on another task → in review → correction
required → done, plus an overdue state.

### Dependencies
- Tasks can depend on other tasks.
- Final submission is gated: it cannot unlock until calculation is approved, concepts and
  evidence documents are complete, formal review is finished, and management has signed off.
  This chain must be enforced by the system, not left to process discipline.

### Notifications (foundation for M3)
- In-app notification centre; email optional per user/notification type.

### User administration
- Added to M2 scope at the user's explicit request (not in the original brief) — M1 shipped
  roles/rights/category-scoping but no UI to create users or assign them, which blocks both
  real onboarding and the M2 seeder work below.
- Admin-facing panel to create/edit users: name, email, password, assigned role
  (`App\Enums\RoleName`), service category (nullable — unscoped/management), and individually
  assignable rights (`App\Enums\Right`), matching the model M1 already established.
- Same server-side enforcement standard as the rest of the app: only authorized roles
  (super admin at minimum — team lead/department head scope TBD) can reach this panel.

### Acceptance points for M2
- Tasks with owners, priorities, dependencies, review/correction loop working end to end.
- Final submission demonstrably blocked until the full dependency chain clears.
- Admins can create/edit users and assign role, category, and rights through the panel,
  server-side enforced.

---

## M3 — Deadlines & Escalation

### Purpose
Make every deadline visible and self-escalating, not dependent on someone remembering.

### Deadline types (14, per brief)
Bidder questions, site visit, internal calculation, concept, document check, approval,
quality check, upload, submission, document requests, presentation, negotiation, bid
validity, expected decision.

- Submission deadline always visible, shown as a live countdown.

### Escalation levels (4)
1. Notify the assignee when a task goes overdue.
2. Inform the team lead after 24 hours overdue.
3. Add the administrator when a critical task sits under 48 hours before submission.
4. Raise a management alert when under 24 hours before submission with critical items
   still open.

- Driven by a scheduler (queued/background jobs), not manual checks.

### Tender calendar
- Central calendar, filterable by department, employee, tender, contracting authority,
  and deadline type.

### Acceptance points for M3
- All 14 deadline types trackable per tender.
- Escalation levels fire correctly and land in the M2 notification centre.
- Calendar view with the required filters.

---

## M4 — Documents & Versioning

### Purpose
A versioned document store per tender with an immutable final state.

### Categories (11, per brief)
Tender documents, calculation, concepts, evidence documents, references, bidder questions,
communication, site visit, final bid documents, result, post-analysis.

### Rules
- Full version history on every document.
- Once a tender is submitted, its final submission version is locked/immutable — no further
  edits or replacements, only new tenders/versions going forward.
- Access to documents respects the same role/rights model as M1 (e.g. price-bearing
  calculation docs still gated by "see prices").

### Acceptance points for M4
- Upload, replace, and version documents across all 11 categories.
- Immutability enforced on the final submission version (verified: no edit/delete path
  exists once locked).

---

## M5 — Calculation & Approvals

### Purpose
A pluggable calculation engine (per service category) with versioning, margin logic, and a
formal multi-step approval chain.

### Calculation engine (generalized, since categories are now configurable)
Rather than hardcoding "hours-based" vs "area-based," each service category defines its own
calculation model:
- A set of cost-driver fields specific to that category (e.g. deployment hours + wage rate
  + supplements + social costs for one category; area + performance rate + labour hours +
  machines/consumables for another).
- A shared computation layer producing: bid price, unit price (hourly rate, per m², or
  whatever the category defines), minimum margin, target margin, actual margin, break-even,
  and risk surcharge.
- Category admins can define/adjust the cost-driver field set without a code change,
  matching the "configurable service categories" decision from M1.

### Versioning
- Multiple calculation versions can exist side by side for comparison.
- A calculation falling below the defined minimum margin automatically triggers the
  management approval step below.

### Approval chain (6 steps, per brief)
Calculation checked → concept checked → evidence documents checked → formal review
complete → management approved → final submission released.
Each step stores who approved, date, time, and an optional comment.

### Acceptance points for M5
- At least two distinct category calculation models running through the same engine.
- Multiple calculation versions comparable side by side.
- Below-margin calculations correctly force the management approval step.
- Full 6-step approval chain enforced and logged.

---

## M6 — Bid / No-Bid Decision

### Purpose
Turn accumulated tender data into a decision-support score, with a human making the actual call.

### Participation score
Computed from: contract value, expected margin (from M5), distance, staffing requirement,
wage/qualification requirements, reference position, competitive intensity, contractual
penalties, strategic value, and past win rate (once M9/M10 exist — early on this can default
to neutral/unknown).

### Decision
- A person with the appropriate right makes the bid/no-bid call — the system never decides
  automatically.
- Declines are recorded with a mandatory reason, so the organization retains a record of what
  was passed on and why.

### Acceptance points for M6
- Score computed and displayed per tender from available inputs.
- Decision + reason recorded and immutable once logged (edits create a new logged entry,
  not a silent overwrite).

---

## M7 — References, Certificates, Concept Library

### Purpose
Reusable proof-of-capability assets that feed into bids repeatedly.

### Reference database
Fields: client, service type, sector, location, period, contract volume, headcount, contact
person, description, reference letters, supporting documents.

### Certificate management
Track: insurance, ISO certificates, trade registration, relevant sector licences/permits, tax
clearance certificates, wage/labour compliance declarations, pre-qualifications.
- Each certificate has validity period, expiry date, and automatic expiry reminders.
- Certificates are a hard disqualification risk if expired — this module needs to be
  reliable, not an afterthought.

### Concept library
- Versioned, reusable text blocks: quality management, staffing concept, cover
  arrangements, escalation, complaints, sustainability, training, deployment organisation,
  and category-specific concept content.

### Acceptance points for M7
- References and certificates fully CRUD-able with expiry reminders firing correctly.
- Concept library blocks versioned and reusable across multiple tenders.

---

## M8 — Communication, Site Visits, Submission, Follow-up

### Purpose
Everything that happens around the actual bidding event, documented per tender.

### Communication
Bidder questions, answers, clarifications, amendments, emails, phone calls, internal
comments — all logged per tender, kept separate from official document versions (M4).

### Site visit record
Fields: date, attendees, contact person, photos, notes, access routes, parking, areas,
risks, technical particularities, staffing requirement, competitors spotted on site, open
questions.

### Submission record
Fields: date, time, responsible employee, portal, transmission route, final files, receipt
confirmation, notes.
- Note: the actual submission still happens in the external procurement portal — this system
  documents it, it doesn't replace the portal.

### Follow-up tracking
Receipt confirmation, document requests, presentation, negotiation, queries, bid validity,
expected result.
- Document requests are their own sub-workflow: description, owner, deadline, files, status,
  history — not just a checkbox.

### Acceptance points for M8
- Full communication thread, site visit record, and submission record captured per tender.
- Document-request sub-workflow functions as its own tracked mini-process.

---

## M9 — Result & Lessons Learned

### Purpose
Close the loop on every tender with a structured, comparable outcome.

### Result record
Fields: winner, our rank, winning price (where known), our price, price gap, award date,
known evaluation, reasoning, award decision, supporting documents.

### Win/loss analysis
Categorized by: price, quality, concept, references, experience, staffing, formal error,
exclusion, capacity, contract terms, competitor, strategic decision.

### Lessons learned
- 3 mandatory questions answered on every closed tender, retained permanently as part of
  the tender file (not editable away later).

### Acceptance points for M9
- Result capture complete with categorized win/loss reason.
- Lessons-learned answers permanently attached to the tender record.

---

## M10 — Competitors, Market Intelligence, Client History, Pipeline

### Purpose
Turn the accumulated result data (from M9 onward) into forward-looking intelligence.

### Competitor database
Fields: company name, region, service areas, known clients, won/lost procedures, known
prices, strengths, weaknesses, market segments, internal notes.
- Any price entry against a competitor requires a mandatory source field (compliance/legal
  reasoning from the brief — don't let this be optional).

### Derived analyses
Who we face most often, who beats us repeatedly, which regions/clients a competitor is
strong with, which service types are most contested.

### Market analysis
Breakdowns by region/state, city, sector, service category, client, contract size,
duration, competitor, price level, source, procurement procedure.

### Client history
Known procedures per client, our participation/win/loss record, past winners, typical
competitors, contacts, contract terms, price development.
- Auto-generated follow-up reminders at 12, 9, and 6 months before a known contract end
  date — explicitly including tenders we previously lost, not just ones we won.

### Pipeline & forecast
Open tenders, bid volume, weighted pipeline (value × win probability), internal win
probability, possible contract start, expected annual revenue and margin.
- Resource check: staffing requirement, qualifications, region, start date, available
  capacity, recruitment need, operational risk.

### Acceptance points for M10
- Competitor and client history visibly building up as real result data accumulates.
- Follow-up reminders correctly firing at 12/9/6 months, including on lost tenders.
- Pipeline/forecast view producing a weighted figure from real open tenders.

---

## M11 — People, Teams, Cover

### Purpose
Give managers visibility into workload and performance without turning it into surveillance.

### Employee profile
Tenders handled (in progress/completed/open/overdue tasks), on-time completions, correction
loops, average handling time, sector experience — derived from actual recorded contribution
across calculation, concept, references, documentation, quality control, communication,
final review, and coordination (not just "was assigned").

### Skills matrix & workload
- Skills matrix per employee.
- Workload view surfaced before every new assignment (so leads can see capacity before
  piling on).
- Team performance per department.
- Bottleneck analysis: average duration per process step.

### Performance score
- Weighted from: on-time delivery, quality, task completion, reliability, documentation
  quality, correction loops, collaboration.
- Win rate enters only as a secondary factor, explicitly not the primary driver.
- Full rankings visible to managers only — this is a management/bottleneck tool, not a
  public leaderboard.

### Absences & cover
- Holiday/sickness/other absences feed into deadline logic.
- System warns when a deadline falls inside someone's absence window.
- Supports assigning cover, with full history preserved (who covered what, when).

### Acceptance points for M11
- Employee profile numbers reconcile against actual task/approval history, not assignment
  counts alone.
- Performance score correctly weighted and restricted to manager visibility.
- Absence-aware deadline warnings and cover assignment working.

---

## M12 — Dashboards, Search, Statistics, Archive, Reporting

### Purpose
The read layer over everything built in M1–M11.

### Dashboards (3, per brief)
Employee, team lead, management — each showing a different cut of the same underlying data.
Plus a deadline radar (sorted by urgency) and an activity feed.

### Global search
Across: tender ID, procurement number, client, procurement office, city, employee,
competitor, winner, document, title, service type.

### Filters
Combinable across: period, department, status, employee, region, service category, sector,
procedure, source, outcome, volume, competitor.

### Statistics
Win rate, participation rate, bid volume, won/lost volume, average contract value and
margin, average handling time, formal exclusions, deadline reliability, how far ahead of
deadline bids are submitted, regional/sector/source analysis, loss reasons, price and
competitor development.
- Formal exclusions get special treatment: this is a headline KPI with a target value of
  zero — a bid thrown out on a technicality is pure wasted effort, and the whole system
  exists partly to prevent it.

### Archive & reporting
- Closed tenders remain fully searchable, never removed from the searchable set.
- Archived/invalid tenders (the M1 `is_archived`/`invalidity_reason` field, see Tender master
  data) surface here as a filterable archive view — housekeeping state, not a lifecycle
  status, so an archived tender keeps its own status (e.g. won) visible alongside the
  archive flag.
- Automatic monthly/quarterly/annual reports.
- All reports exportable as PDF and Excel: pipeline, win/loss, competitors, employee and
  department performance, deadlines, management reporting.

### Acceptance points for M12
- All three dashboards populated from real data with correct role-based scoping.
- Global search and combinable filters functioning across the listed fields.
- Formal-exclusion metric visibly tracked and reported.
- PDF/Excel export working for each listed report type.

---

## M13 — Later Expansion (explicitly deferred)

These are named in the brief as things the architecture should leave room for, without
building them in the initial milestones.

### Import connectors
- Intake inbox pulling from open tender-notice sources (procurement portals with open
  APIs/RSS feeds), filtered by CPV code and region.
- Deduplicated by procurement number.
- A single-click triage step for an employee to mark each incoming notice relevant/not
  relevant.

### AI-assisted extraction
- Extract deadlines, contract terms, eligibility criteria, required certificates, award
  criteria, and contractual penalties from uploaded documents.
- Every AI output is labelled as a suggestion and confirmed by a human. The system never
  automates the decision itself.

### Architectural note
Nothing in M1–M12 should make these harder to bolt on later — e.g. keep document
ingestion, deadline/term fields, and certificate records as first-class structured data
(already true per M1/M4/M7) so an extraction pipeline has somewhere real to write its
suggestions.

---

## Non-functional requirements (apply across all milestones)

- German-only UI: navigation, buttons, statuses, errors, notifications, forms, filters,
  dashboards, emails, exports — all through a clean i18n structure from the start, even
  while the team building it may not speak German themselves.
- Server-side permission enforcement everywhere data is retrieved, not just where it's
  displayed.
- GDPR-compliant handling of employee/client/company data; EU hosting.
- HTTPS everywhere, secure password storage, standard web-attack protections, secure
  session handling, password policy, optional 2FA.
- Regular database and file backups with a documented, tested restore procedure.
- Separate staging and production environments.
- Fully responsive: complex calculation screens are fine as desktop-oriented, but tasks,
  approvals, and deadline monitoring must work well on a phone.
- Design system: white/light-grey base with a single accent colour, consistent status
  colours (green = success/won, orange = warning, red = critical/overdue/lost), one
  consistent component set for buttons/badges/status chips/tables/dialogs/forms.
- Tender detail page: status/owner/team/progress/deadline/risk/value/participation score
  in the header, with tabs for overview, tasks, calculation, documents, team,
  communication, bidder questions, site visit, approvals, competitors, submission, result,
  post-analysis, activity log.
