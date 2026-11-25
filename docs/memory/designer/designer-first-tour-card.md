---
name: designer-first-tour-card
description: The designer's first card "New here? A one-minute tour" and its tour (FT2): the part 'tour', when the card shows, the ring and the bubble, the keys, the phone's sheet, "Take the tour"
module: output
---

## Where is it?
- The part `tour` (`includes/designer_parts/tour.php`, `when` always): `assets/js/pg_designer_tour.js` (the model, `designer-first-tour-model.md`), then `assets/js/style_designer_tour.js`. Its look is the `.sd-tour-*` block at the end of `assets/css/style_designer.css`. Drawing: `designer-first-tour-drawing.md` (boards T1 to T3, T7). Built in #11646.
- The core keeps only `_sdTourDue()`, `_sdTourInit()` and `_sdTourStart()` (section `// ── The first tour` in `style_designer.src.js`).

## When is the card shown?
- `_sdTourInit()` runs at the end of `init()`. It reads the browser key `pg.designer.<user id>.tour` itself; when the card was offered, or this access's tour seen, or the browser keeps no storage, nothing loads. Else the part is called 1.2 s later (`offer`).
- The part shows the card when `PGDesignerTour.offer()` allows it (no dialog, no typed text, no held pointer or drag); else it looks again every 1.5 s of the visit. While it waits it listens to the pointer (to know a drag); nothing else is held.
- The state is written as offered the moment the card shows, whatever the answer. "Not now" and Escape close it; "Start" begins the tour.

## How does the tour run?
- A ring (`.sd-tour-ring`, fixed, no pointer events) round the stop's control and a bubble (`.sd-tour-bubble`, `role=dialog`, not modal) beside it on the side with most room; a control as large as the window (the canvas) gets the bubble inside it at its foot. Nothing is dimmed or locked.
- Every 0.4 s while it runs, and on resize, it looks whether the control moved (redrawn only when its box changed), went away (the next stop) or a dialog opened (ring and bubble hidden till it closes).
- Next, Back, Skip, Done; the right and left arrows inside the bubble; Escape anywhere but in a dialog. Skip, Done and Escape write the access's tour as seen. Focus goes to Next (or Done) at each stop and back to where it was at the end; a hidden live line reads "Tour, 3 of 5." and the sentence.

## What changes under 576 px?
- The card and the bubble are a sheet across the foot; the bubble stands under the page bar when the ringed control's middle is in the window's lower half. The palette and Options are not on screen, so the model skips "add" and "shape" falls back to the canvas: the count follows.

## How is it started again?
- "Take the tour" in the command palette (`act_tour`, shown when the part is handed). FT3 adds it as the Help window's first row.
