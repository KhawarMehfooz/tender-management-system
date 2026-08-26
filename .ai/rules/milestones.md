---
glob: "**/*"
title: Milestone scope tracker
---

Full milestone specs live in [idea.md](../../idea.md) (M1–M13). This file just tracks where
the build currently stands so an assistant knows what's in scope right now vs. not-yet-built
vs. explicitly deferred.

**Current milestone: M1 — Foundation** (auth, permissions, service categories, tender master
data, lifecycle state machine — not yet built beyond initial Laravel/Livewire skeleton +
Docker setup).

Update the line above as milestones complete. Don't build ahead into a later milestone's
scope without the user asking for it explicitly — e.g. don't wire up the M5 calculation
engine while M1 is still in progress, even if it seems convenient.

M13 (import connectors, AI-assisted extraction) is explicitly deferred — don't build it, but
per idea.md's architectural note, don't make first-class structured data choices in earlier
milestones (documents, deadlines/terms, certificates) that would make it harder to bolt on
later.
