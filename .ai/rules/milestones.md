---
glob: "**/*"
title: Milestone scope tracker
---

# Milestone scope tracker

Full milestone specs live in [idea.md](../../idea.md) (M1–M13). This file is just the index —
per-milestone build history and current scope live one file per milestone under
`.ai/rules/milestones/`, whether that milestone is complete, in progress, or not yet started.
Read the linked file for whatever milestone you're touching (in particular the current
in-progress one) before planning or editing.

| Milestone | Status | File |
| --- | --- | --- |
| M1: Foundation | Complete | [milestones/m1-foundation.md](milestones/m1-foundation.md) |
| M2: Team & Tasks | Complete | [milestones/m2-team-tasks.md](milestones/m2-team-tasks.md) |
| M3: Deadlines & Escalation | Complete | [milestones/m3-deadlines-escalation.md](milestones/m3-deadlines-escalation.md) |
| M4: Documents & Versioning | Complete | [milestones/m4-documents-versioning.md](milestones/m4-documents-versioning.md) |
| M5: Calculation & Approvals | Complete | [milestones/m5-calculation-approvals.md](milestones/m5-calculation-approvals.md) |
| M6: Bid / No-Bid Decision | Not started | see idea.md |
| M7: References, Certificates, Concept Library | Not started | see idea.md |
| M8: Communication, Site Visits, Submission, Follow-up | Not started | see idea.md |
| M9: Result & Lessons Learned | Not started | see idea.md |
| M10: Competitors, Market Intelligence, Client History, Pipeline | Not started | see idea.md |
| M11: People, Teams, Cover | Not started | see idea.md |
| M12: Dashboards, Search, Statistics, Archive, Reporting | Not started | see idea.md |
| M13: Later Expansion | Explicitly deferred | see idea.md |

M1-M5 are complete. Don't start the next milestone (M6) without the user asking for it
explicitly — e.g. don't wire up M6's bid/no-bid score just because it seems convenient.

When a not-started milestone kicks off, create its file under `.ai/rules/milestones/` (see
[[general]]'s "Plan new milestones" rule for the exact workflow) and add its row above.

M13 (import connectors, AI-assisted extraction) is explicitly deferred — don't build it, but
per idea.md's architectural note, don't make first-class structured data choices in earlier
milestones (documents, deadlines/terms, certificates) that would make it harder to bolt on
later.
