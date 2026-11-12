---
name: decisions-made-product-48
description: Decisions list, product part 48 (2026-11-12 on): the Legacy to Bootstrap helper does nothing on a design that does not use Bootstrap 3 (#8967)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## What does the Legacy to Bootstrap helper do on a design that is not Bootstrap 3? (owner, 2026-11-12, #8967)
- Nothing: the card says that the design does not use Bootstrap 3 and offers no report and no trial design; such a site stays on its Legacy design. Rules `plans/legacy-bootstrap-helper-rules.md`.
- Asked with the pictures of the Materialize trial (`designer/legacy-materialize-trial.md`) and three options (nothing; a report only; a second helper with a Materialize map), the owner (2026-11-12): "Ta, Ma" (Ma: nothing).
- The architect's, not the owner's words: Bootstrap 3 is found by the classic framework sheet's asset row, never by class names.
