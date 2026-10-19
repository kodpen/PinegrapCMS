---
name: settings-modal-dirty-tracking
description: How the settings dialog decides "unsaved changes": two-question criterion, snapshot(), baseline in paint(), hand gesture triggering its own review.
---

## What counts as "unsaved changes" in the settings dialog?
- Not an event but TWO questions. Browser autofill and password managers write into fields after the pane is painted and fire the same trusted (`isTrusted`) `input`/`change` events as keystrokes, so events cannot tell who typed. Listening to events alone made a pane "unsaved" while it was still opening, and category switch / closing asked questions nobody caused.
- Criterion, both must hold:
  1. something able to change a value was touched inside the dialog: `pointerdown` on `:input` / `label` / the extras box, or `keydown`/`paste`/`drop` on a control; AND
  2. `snapshot()` differs from the value the pane arrived with.
- Scrolling and clicking a card header do not pass (1); typing then undoing does not pass (2).
- `snapshot()` `serialize()`s the form (a code field keeps its textarea current); the comparison is delayed 120 ms (code fields can be large).

## Where is the baseline taken?
- Inside `paint()`, not one frame later: a fill landing in between would become the baseline and the next save would store it although nobody chose it.

## Why does a hand gesture ask for its own review?
- A card script writing into a field fires no event. Without the gesture's own review a generated key did not enable Save (it never did) and the next review put it back as if nobody had asked for it.

Related: docs/memory/ui/settings-modal-autofill-restore.md
