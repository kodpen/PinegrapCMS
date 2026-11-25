---
name: designer-tips-off
description: The Help window's "Tips" switch off hides every first-use line in the designer, the older lines with their own browser keys included (#11711)
---

## What does "Tips" off hide?
- Every first-use line (drawing board T4/T5): the lines asked through `_sdTipAsk()` (History, Overview) and the older ones each part draws itself: select similar, overrides, the Sketch reading's first "Read the picture" step and the Sketch building's line, an element's states, variables, Preview as, the bands and side by side.
- What was seen stays seen; turning "Tips" on again shows only the lines not yet seen.

## How does an older line read the switch?
- The core's `_sdTipsOff()` reads `tipsOff` in `pg.designer.<user id>.tour` (false with no user, no key or garbage) and is handed to the parts as `K._sdTipsOff`.
- Each part's own "seen" check returns seen while it is true: `tipSeen()` (similar), `firstSeen()` (overrides), `seen()` (sketchbuild), `_srSeen()` (sketchread), `hintSeen()` (states), `tipShown()` (variables), `lineHtml()` (previewas). Their own keys are not written, so the line comes back if "Tips" is turned on before it was seen.
- Bands and side by side already go through `PGDesignerTour.tipDue()`, which reads the switch.
- A new part with a first-use line asks `_sdTipAsk()`; one that keeps its own key checks `K._sdTipsOff()` first. A Node test that builds a part with a fake kit gives it `_sdTipsOff`.

## Where is it tested?
- `tests/designer_tips_off_test.php`: the reading of the key in Node and each part's check.
