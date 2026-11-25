---
name: designer-side-by-side
description: "Side by side" in the designer's View menu (SS2): the switch, the row of the canvas and two mirrors, its room (928 px, 636 for an e-mail design), the heads and their size lists
---

## How is "Side by side" turned on?
- The View menu's last switch row, `#sd-vb-side` ("Side by side", `bi-layout-three-columns`) under a line; the badge counts it; the command palette has it as `act_side`. Kept per browser: `pg_sd_side`, the mirrors' widths `pg_sd_side_w` (default 375,768).
- On: the part runs (first use only), the canvas is fitted as Fit does, a screen reader hears "Side by side on: phone and tablet.". Off gives back the zoom (and Fit) from before.
- The core (`_sdSideSync()`) keeps the switch, the room and the zoom; the part's `side(cfg)` draws the row, `side(null)` takes it down. Prototype mode, too little room and a canvas column built again take the mirrors down; the switch stays on and they come back.

## How is the row laid out?
- `_sdSideLayout(colW, widths)`: a box of 200 px for a width up to 576, else 280; 12 px around and between; the canvas keeps at least 400 px. Two mirrors need a column of 928 px, an e-mail design's one mirror (375 beside the 600 canvas) 636.
- Too little room: the menu row is dimmed (`aria-disabled`) with "Not enough room here. Close a side panel or use a wider window." under it. The room is checked when the View menu opens, and while the switch is on by a ResizeObserver on `#sd-canvas`.
- The canvas area gets a right margin of the strip's width (`--sd-side-w` on `#sd-canvas.sd-side-on`), so Fit and the canvas's measures read the canvas alone; the strip `.sd-side` is placed absolutely from the area's offsets (`#sd-canvas` stays unpositioned).
- A head (28 px, then 6 px) holds the width button: the bar's icon and "375 px", opening a menu of the bar's five widths under the bar's names. The one shown is ticked; the canvas's and the other mirror's are dimmed with "on the canvas" / "in the other mirror" (`PGMirrorSchedule.sizes()`, `pick()`). An e-mail design's head has no list.
- The first-use line under the bar, once per person: the tour's tip `side-by-side` (`PGDesignerTour`, from the part `bands`, which `cfg.tour()` runs when it has not run yet).

## What comes next?
- SS3 adds "Edit at this size" to the head, the chosen element followed in each mirror and a click choosing it.

Related: docs/memory/designer/designer-canvas-mirror.md, docs/memory/designer/designer-screens-side-by-side-drawing.md
