---
glob: "app/Models/**,database/migrations/**,database/factories/**"
title: Data integrity rules (from idea.md M1)
---

## Never hard-delete tenders
- Tenders are never hard-deleted by normal application flow. They're archived or flagged
  invalid instead (soft-delete / status field), preserving historical value.
- Only an admin-gated action may hard-delete a true junk entry, and every hard delete must be
  logged with a reason (who, when, why) before the row is removed.
- When adding a `destroy`/delete path anywhere in the tender lifecycle, check this rule first
  — the default assumption is "no such path exists," not "add soft deletes later."

## Structured fields, not free text
- Tender **source** (TED, service.bund.de, oeffentlichevergabe.de, Vergabe.NRW, DTVP,
  subreport ELViS, direct enquiry, existing client, referral, etc.) is an admin-extensible
  enum/lookup table, never a free-text string column. It feeds win-rate/volume/quality-per-
  source reporting later (M10/M12) — a typo'd free-text value silently breaks that.
- **CPV code** and **NUTS code** are real structured fields (dedicated columns/lookup, with
  validation against the known code format), not free text — they're used for filtering and
  reporting.
- Before adding any new field that will later feed statistics or filters, ask whether it
  should be an enum/lookup rather than a plain string.

## Explicit "unknown," never blank
- Any field that feeds later statistics (contract volume, sector, source, etc.) must store an
  explicit "unknown" value when the real value isn't available yet — not `null` and not an
  empty string. A blank value is ambiguous (not entered vs. genuinely unknown); an explicit
  "unknown" is not.
- This means enum/lookup fields feeding stats should include an "unknown" option, and
  migrations for such columns should not allow silent nulls without a documented reason.
