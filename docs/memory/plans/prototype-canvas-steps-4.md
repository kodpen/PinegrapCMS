---
name: prototype-canvas-steps-4
description: Plan prototype-canvas, steps PV11a and PV11b from the owner's answer of 2026-11-16: empty links and unreached pages as problems, then the Suggest targets dialog
---

## Which steps came from the owner's answer?
- PV11a (M) Further problems. After PV10. Done in #10204: `designer/prototype-problems.md`.
  - `PGProtoHotspots`: the mark `empty` for an address that is empty or `#` alone (F26); `#name`, a toggle and a bound link keep what they read as today. `CHIP_MARKS` gains nothing: an empty link has no chip and no arrow.
  - The view asks `G.check()` (PV1) with the Start page and marks the frames nothing reaches (F27); shared parts and chips are left out before the call.
  - The bar's amber "N to check", the three groups of the problems list, the head's amber count and the amber rectangle and list row (F28, F29).
  - Tests: the mark of `''`, `#`, `#top`, a toggle and a bound link; the unreached set of a small graph with a shared part and with the Start page unlinked; the counts of the bar.
  - Features: F26, F27, F28, F29. No setting, no schema.
- PV11b (M) Suggest targets. After PV11a.
  - `PGProtoHotspots.suggest(link, pages)` as F31 says, pure: answers `{ sure: page or null, candidates: [...] }`. The filler words are one constant; the nearest heading comes from the tab's tree, read by the view and passed in, so the function stays pure.
  - The dialog (F30, F32): built from the panel's own modal, list and form parts, no new stylesheet rules beyond its layout; keyboard: Tab through rows, Space ticks, Enter on a candidate picks it, Escape cancels.
  - The dashed arrows of ticked rows (F33) go through the core as a set of edges with a `dashed` flag the core draws; no second arrow drawer.
  - Apply (F34): one function writes a batch through `_sdProtoConnect()`'s parts, the warnings asked once, one undo entry per tab; a row that fails stays with its reason.
  - Tests, red first: `suggest()` on "Sepete git" with Sepet (sure); "Blog" with Blog and Blog arşivi (unsure, two); "Daha fazlası" under the heading "Blog" (unsure, Blog); a label that matches nothing; the link's own page and a page in the bin left out; Turkish letters folded both ways. The batch: three rows on two tabs make two undo entries; a locked tab's row stays unwritten.
  - Its sheet (the dialog open with sure, unsure and empty rows; the dashed arrows; the toast) goes to the owner: the dialog is new.
  - Features: F30, F31, F32, F33, F34; K1 to K4.
- Both steps fill the PR-body table of `plans/prototype-canvas-rules.md` for their F numbers.
