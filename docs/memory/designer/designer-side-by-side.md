---
name: designer-side-by-side
description: "Side by side" in the designer's View menu (SS2, SS3): the switch, the row of canvas and two mirrors, its room, the heads, the chosen element followed, a click choosing, Edit at this size
---

## Why is it there, and what must it keep?
- Asked by the owner on 2026-11-24 (`workflow/decisions-made-product-108.md`): a change seen on a phone and a tablet while the desktop canvas is edited. Built by SS1 #11535, SS2 #11647, SS3 #11676; bound by `designer-light-and-simple.md`.
- Editing happens on the canvas only: a mirror takes no typing and no drag. Off, the frames leave the page and nothing of it runs. A mirror draws what the canvas draws at its width. No table or column.

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

## How does a mirror follow the chosen element (SS3, #11676)?
- Each mirror frames the canvas's `.sd-selected` elements: a div `[data-sd-mirror-mark="chosen"]` in the mirror's document over the picture, 2 px on screen at its scale, `#0d6efd`. An element that width hides has none. No frame while a redraw waits; the redraw frames again. Followed from the part's MutationObserver (`PGMirrorSchedule.selectionMoved()`), at the next animation frame.
- A mirror scrolls only when the choice moved and the element is out of its view, to a third of the way down (`follow()`).
- A click in a mirror chooses the element on the canvas (`cfg.select` = `_sdSideSelect(id, sid)`), a dashed frame (`hover`) shows what a click would choose.

## What does "Edit at this size" do?
- The head's icon button `.sd-side-swap` (`bi-arrow-left-right`) after the width: `PGMirrorSchedule.swap()` gives the canvas the mirror's width and the mirror the canvas's (Full width hands over 1200; another mirror on that width takes the first free one); the core (`_sdSideSwap()`) presses the bar's width button, stores the widths, says "Editing at {var:1} px.". Again swaps back. An e-mail design's head has no swap.

Related: docs/memory/designer/designer-canvas-mirror.md (numbers measured), docs/memory/designer/designer-screens-side-by-side-drawing.md
