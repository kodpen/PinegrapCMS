---
name: designer-canvas-bands-drag-start
description: Canvas spacing bands fixes: handles take a drag (#11921: late PGSpacingBox, hit areas, auto start), back after Prototype, hidden once the selection empties (#11939, #12004)
---

## Why do the handles work with Side by side on at load?
- The part reads `window.PGSpacingBox` where a change writes (`_bdSpacing()`), never once in its factory. The mirrors' first-use line asks the part `tour` (`_sdPartCall('tour', 'model', [])`), which brings `PGDesignerTour`.
- Before #11921 the mirrors' `cfg.tour()` loaded the part `bands` before the part `spacing`; the factory kept `B = undefined` and every drag threw, writing nothing.

## Why can a margin handle be held on a side with no spacing?
- An inner handle sits at least one handle (6 px, `HANDLE`) inside the frame in `PGCanvasBands.layout()`, also for a padding thinner than that on screen; the outer one sits on the frame.
- Each handle's hit area (`.sd-cgb-h::before`, 6 px) reaches out only away from its pair: an outer handle outwards, an inner one inwards (`data-k` and `data-e` rules in `style_designer.css`).
- Before #11921 the reach went all round and the inner handle, later in the layer, covered the outer pill's middle: an outward drag on the margin handle wrote nothing, an inward one wrote padding.

## Where does a drag start?
- At the value the band shows (`C.startStep(px, scale, auto)`): a scale step when it is one (within 0.5 px); `{ step: 'auto' }` when the element's classes give that side `auto` at the canvas's size (`_bdAuto()`, `PGSpacingBox.read()`, e.g. `mx-auto`); `{ step: '' }` for any other off-scale value (a container's 12 px).
- A drag keeps that start until a scale step is nearer (`C.nearestStep(raw, scale, from)`); the arrows go to the next scale step that way, or stay (`C.keyStepFrom()`). The label shows "auto 186 px", or just the pixels for `''`.
- An `''` start never writes; `auto` with Shift or Alt writes `auto` to those sides.
- Before #11921 the start was the nearest step: `mx-auto`'s 186 px at 1200 px read as step 5; a drag wrote nothing outwards and `ms-xl-0` beside `mx-auto` inwards.

## Why are the bands back after the Prototype view?
- `_sdProtoButtonState()` (run on open and close) calls `_sdBandsSoon()` beside `_sdLinkMarksSoon()`. Before #11921 nothing asked for a draw on close and the layer stayed hidden until another element was selected.

## Why do the bands go when nothing is selected any more?
- `renderStatusBar()` calls `_sdBandsSoon()` when it runs with no selection; `_sdBandsDraw()` finds no node and calls the part's `hide()`. Every full `render()` and `_sdPanelsNow()` run it: a page tab switch (`_pgTabsSwitch()`), a removed page, a deleted selection, an undo or redo dropping it, a tree laid again.
- Before #11939 (PR #11942) only `_positionSizeBadge()` asked for a draw, and its callers skip it without a selection: the old bands stayed over the next page's canvas.
- A selection kept but not on the shown canvas still reaches `_sdBandsDraw()` through `_positionSizeBadge()`, which hides when `_sdCanvasInnerOf(node)` is missing.
- Once the parts `spacing` and `bands` are in, `_sdBandsDraw()` asks `_sdBandsNode()` again and draws only that same element. Before #12004 a selection emptied during the first load had no part to hide, and the old bands showed when it ended.

## How does a drag end when its release is not heard?
- A move without the main button held ends it as a release (written); `lostpointercapture` without a release puts the value back. Before PR #12003 the drag kept following the pointer and every handle refused the next drag. The Spacing box's sides end the same way, and only a heard release swallows the click after it.

## What tests it?
- `tests/designer_canvas_bands_test.php`: `read_the_spacing_box_late`, `inner_handle_clears_the_outer`, `start_step`, `nearest_step_from_auto`, `key_step_from`, `skip_a_selection_gone_while_loading`. Hit areas and the drag (#11921) and the empty-selection hide (PR #11942) were checked in Chromium on a sandbox.
