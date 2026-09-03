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
| M6: Bid / No-Bid Decision | Complete | [milestones/m6-bid-no-bid-decision.md](milestones/m6-bid-no-bid-decision.md) |
| M7: References, Certificates, Concept Library | Complete | [milestones/m7-references-certificates-concept-library.md](milestones/m7-references-certificates-concept-library.md) |
| M8: Communication, Site Visits, Submission, Follow-up | Complete | [milestones/m8-communication-site-visits-submission-followup.md](milestones/m8-communication-site-visits-submission-followup.md) |
| M9: Result & Lessons Learned | Complete | [milestones/m9-result-lessons-learned.md](milestones/m9-result-lessons-learned.md) |
| M10: Competitors, Market Intelligence, Client History, Pipeline | Complete | [milestones/m10-competitors-market-intelligence-client-history-pipeline.md](milestones/m10-competitors-market-intelligence-client-history-pipeline.md) |
| M11: People, Teams, Cover | Complete | [milestones/m11-people-teams-cover.md](milestones/m11-people-teams-cover.md) |
| M12: Dashboards, Search, Statistics, Archive, Reporting | Not started | see idea.md |
| M13: Later Expansion | Explicitly deferred | see idea.md |

M1-M11 are complete. Don't start the next not-started milestone (M12) without
the user asking for it explicitly.

When a not-started milestone kicks off, create its file under `.ai/rules/milestones/` (see
[[general]]'s "Plan new milestones" rule for the exact workflow) and add its row above.

M13 (import connectors, AI-assisted extraction) is explicitly deferred — don't build it, but
per idea.md's architectural note, don't make first-class structured data choices in earlier
milestones (documents, deadlines/terms, certificates) that would make it harder to bolt on
later.
